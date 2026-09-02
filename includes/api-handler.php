<?php
/**
 * API handler for AHM AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Handler Class for AHM AI Post Summary plugin
 *
 * Handles communication with AI services (Gemini and ChatGPT)
 *
 * @package AIPostSummary
 * @since   1.0.0
 */
class ahmaipsu_API_Handler {

    /**
     * Validate API key by making a test request
     *
     * @param string $api_key       The API key to validate
     * @param string $api_provider  The API provider ('gemini' or 'chatgpt')
     * @return bool|WP_Error        True if valid, WP_Error if invalid
     */
    public static function validate_api_key($api_key, $api_provider = 'gemini') {
        if (empty($api_key)) {
            return new WP_Error('empty_api_key', esc_html__('API key cannot be empty.', 'ahm-ai-post-summary'));
        }

        // Basic format validation
        if ($api_provider === 'gemini') {
            // Gemini keys: legacy AIza... (no dots) or 2026 auth keys AQ....
            if (!preg_match('/^[A-Za-z0-9._-]{20,}$/', $api_key)) {
                return new WP_Error('invalid_format', esc_html__('Invalid Gemini API key format. Please check your key.', 'ahm-ai-post-summary'));
            }
        } elseif ($api_provider === 'chatgpt') {
            // OpenAI API keys start with 'sk-' - minimal validation to avoid format changes
            if (substr($api_key, 0, 3) !== 'sk-' || strlen($api_key) < 20) {
                return new WP_Error('invalid_format', esc_html__('Invalid OpenAI API key format. Keys should start with "sk-".', 'ahm-ai-post-summary'));
            }
        }

        // Make a test API call
        $test_result = self::make_test_api_call($api_key, $api_provider);

        if (is_wp_error($test_result)) {
            return $test_result;
        }

        return true;
    }

    /**
     * Default model ID for a provider (backward compatible with 1.1.x).
     *
     * @param string $api_provider Provider slug.
     * @return string
     */
    public static function get_default_model($api_provider) {
        return ($api_provider === 'chatgpt') ? 'gpt-3.5-turbo' : 'gemini-3.6-flash';
    }

    /**
     * Curated fallback models if live listing fails.
     *
     * @param string $api_provider Provider slug.
     * @return array[] Array of array(id, label).
     */
    public static function get_fallback_models($api_provider) {
        if ($api_provider === 'chatgpt') {
            return array(
                array('id' => 'gpt-3.5-turbo', 'label' => 'gpt-3.5-turbo'),
                array('id' => 'gpt-4o-mini', 'label' => 'gpt-4o-mini'),
                array('id' => 'gpt-4o', 'label' => 'gpt-4o'),
                array('id' => 'gpt-4-turbo', 'label' => 'gpt-4-turbo'),
            );
        }

        return array(
            array('id' => 'gemini-3.6-flash', 'label' => 'gemini-3.6-flash'),
            array('id' => 'gemini-flash-lite-latest', 'label' => 'gemini-flash-lite-latest'),
            array('id' => 'gemini-3.5-flash-lite', 'label' => 'gemini-3.5-flash-lite'),
            array('id' => 'gemini-flash-latest', 'label' => 'gemini-flash-latest'),
            array('id' => 'gemini-2.0-flash', 'label' => 'gemini-2.0-flash'),
        );
    }

    /**
     * Sanitize a model ID for use in API URLs and settings.
     *
     * @param string $model Raw model ID.
     * @return string
     */
    public static function sanitize_model_id($model) {
        $model = sanitize_text_field($model);
        $model = preg_replace('#^models/#', '', $model);
        if (!preg_match('/^[A-Za-z0-9._\-\/]+$/', $model)) {
            return '';
        }
        return $model;
    }

    /**
     * Resolve the model to call: saved setting, else provider default.
     *
     * @param string $api_provider Provider slug.
     * @param string $saved        Saved model ID.
     * @return string
     */
    public static function resolve_model($api_provider, $saved = '') {
        $model = self::sanitize_model_id($saved);
        if ($model === '') {
            $model = self::get_default_model($api_provider);
        }
        return $model;
    }

    /**
     * List usable models for a provider using a live API key.
     *
     * @param string $api_key      API key.
     * @param string $api_provider Provider slug.
     * @return array[]|WP_Error
     */
    public static function list_models($api_key, $api_provider = 'gemini') {
        if ($api_provider === 'chatgpt') {
            return self::list_openai_models($api_key);
        }
        return self::list_gemini_models($api_key);
    }

    /**
     * List Gemini models that support generateContent.
     *
     * @param string $api_key API key.
     * @return array[]|WP_Error
     */
    private static function list_gemini_models($api_key) {
        $list_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        $list_response = wp_remote_get($list_url, array('timeout' => 15));

        if (is_wp_error($list_response)) {
            return $list_response;
        }

        if (wp_remote_retrieve_response_code($list_response) !== 200) {
            return new WP_Error('list_failed', esc_html__('Unable to list Gemini models.', 'ahm-ai-post-summary'));
        }

        $data = json_decode(wp_remote_retrieve_body($list_response), true);
        if (!is_array($data) || empty($data['models']) || !is_array($data['models'])) {
            return self::get_fallback_models('gemini');
        }

        $models = array();
        foreach ($data['models'] as $item) {
            $methods = isset($item['supportedGenerationMethods']) && is_array($item['supportedGenerationMethods'])
                ? $item['supportedGenerationMethods']
                : array();
            if (!in_array('generateContent', $methods, true)) {
                continue;
            }
            $id = self::sanitize_model_id(isset($item['name']) ? $item['name'] : '');
            if ($id === '') {
                continue;
            }
            // Skip non-text models that still advertise generateContent.
            if (preg_match('/(tts|image|audio|video|robotics)/i', $id)) {
                continue;
            }
            $label = isset($item['displayName']) ? sanitize_text_field($item['displayName']) : $id;
            $models[] = array('id' => $id, 'label' => $label . ' (' . $id . ')');
        }

        return !empty($models) ? $models : self::get_fallback_models('gemini');
    }

    /**
     * List OpenAI chat-capable models.
     *
     * @param string $api_key API key.
     * @return array[]|WP_Error
     */
    private static function list_openai_models($api_key) {
        $models_response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($models_response)) {
            return $models_response;
        }

        if (wp_remote_retrieve_response_code($models_response) !== 200) {
            return new WP_Error('list_failed', esc_html__('Unable to list OpenAI models.', 'ahm-ai-post-summary'));
        }

        $data = json_decode(wp_remote_retrieve_body($models_response), true);
        if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) {
            return self::get_fallback_models('chatgpt');
        }

        $models = array();
        foreach ($data['data'] as $item) {
            $id = self::sanitize_model_id(isset($item['id']) ? $item['id'] : '');
            if ($id === '') {
                continue;
            }
            if (!preg_match('/^(gpt-|o[1-9]|chatgpt-)/', $id)) {
                continue;
            }
            if (preg_match('/(instruct|audio|realtime|image|tts|whisper|transcribe|search|moderation|davinci|babbage|ada|curie)/', $id)) {
                continue;
            }
            $models[] = array('id' => $id, 'label' => $id);
        }

        usort($models, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        return !empty($models) ? $models : self::get_fallback_models('chatgpt');
    }

    /**
     * Make a test API call to validate the key
     *
     * @param string $api_key       The API key to test
     * @param string $api_provider  The API provider
     * @return bool|WP_Error        True if successful, WP_Error if failed
     */
    private static function make_test_api_call($api_key, $api_provider) {
        if ($api_provider === 'gemini') {
            return self::test_gemini_api($api_key);
        } elseif ($api_provider === 'chatgpt') {
            return self::test_chatgpt_api($api_key);
        }

        return new WP_Error('invalid_provider', esc_html__('Invalid API provider specified.', 'ahm-ai-post-summary'));
    }

    /**
     * Test Gemini API key
     *
     * @param string $api_key The API key to test
     * @return bool|WP_Error
     */
    private static function test_gemini_api($api_key) {
        // First try to list models - this is a simpler endpoint that works reliably
        $list_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        
        $list_response = wp_remote_get($list_url, array(
            'timeout' => 15,
        ));

        if (is_wp_error($list_response)) {
            return new WP_Error('connection_error', esc_html__('Unable to connect to Gemini API. Please check your internet connection.', 'ahm-ai-post-summary'));
        }

        $response_code = wp_remote_retrieve_response_code($list_response);

        if ($response_code === 400) {
            return new WP_Error('invalid_key', esc_html__('Invalid Gemini API key. Please check your key and try again.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 403) {
            return new WP_Error('forbidden', esc_html__('API key does not have permission to access Gemini API. Please check your API key permissions.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 200) {
            // API key is valid
            return true;
        }

        // If listing models fails, try generation endpoints as fallback
        $endpoints = array(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $api_key,
        );

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => 'Test')
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.1,
                'maxOutputTokens' => 5,
            )
        );

        foreach ($endpoints as $url) {
            $response = wp_remote_post($url, array(
                'body' => wp_json_encode($body),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                continue; // Try next endpoint
            }

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 400) {
                return new WP_Error('invalid_key', esc_html__('Invalid Gemini API key. Please check your key and try again.', 'ahm-ai-post-summary'));
            }

            if ($response_code === 403) {
                return new WP_Error('forbidden', esc_html__('API key does not have permission to access Gemini API. Please check your API key permissions.', 'ahm-ai-post-summary'));
            }

            if ($response_code === 404) {
                continue; // Try next endpoint
            }

            if ($response_code === 200) {
                // API key is valid
                return true;
            }
        }

        return new WP_Error('connection_error', esc_html__('Unable to connect to Gemini API. Please check your API key and internet connection.', 'ahm-ai-post-summary'));
    }

    /**
     * Test ChatGPT API key
     *
     * @param string $api_key The API key to test
     * @return bool|WP_Error
     */
    private static function test_chatgpt_api($api_key) {
        // First try to list models - this is a lighter request that's less likely to hit rate limits
        $models_url = 'https://api.openai.com/v1/models';

        $models_response = wp_remote_get($models_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($models_response)) {
            return new WP_Error('connection_error', esc_html__('Unable to connect to OpenAI API. Please check your internet connection.', 'ahm-ai-post-summary'));
        }

        $response_code = wp_remote_retrieve_response_code($models_response);

        if ($response_code === 401) {
            return new WP_Error('invalid_key', esc_html__('Invalid OpenAI API key. Please check your key and try again.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 403) {
            return new WP_Error('forbidden', esc_html__('API key does not have permission to access OpenAI API. Please check your billing status.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 429) {
            return new WP_Error('rate_limit', esc_html__('API rate limit exceeded. Please try again later or check your usage limits.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 200) {
            // API key is valid
            return true;
        }

        // If models endpoint fails, try a minimal completion request as fallback
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hi'
                )
            ),
            'max_tokens' => 1,
            'temperature' => 0,
        );

        $response = wp_remote_post($url, array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('connection_error', esc_html__('Unable to connect to OpenAI API. Please check your internet connection.', 'ahm-ai-post-summary'));
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 401) {
            return new WP_Error('invalid_key', esc_html__('Invalid OpenAI API key. Please check your key and try again.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 403) {
            return new WP_Error('forbidden', esc_html__('API key does not have permission to access OpenAI API. Please check your billing status.', 'ahm-ai-post-summary'));
        }

        if ($response_code === 429) {
            return new WP_Error('rate_limit', esc_html__('API rate limit exceeded. Please try again later or check your usage limits.', 'ahm-ai-post-summary'));
        }

        if ($response_code !== 200) {
            /* translators: %s: HTTP response code from OpenAI API */
            return new WP_Error('api_error', sprintf(esc_html__('OpenAI API error: %s', 'ahm-ai-post-summary'), $response_code));
        }

        return true;
    }

    /**
     * Detect content language for better AI prompting with improved multi-language detection
     *
     * @param string $content The content to analyze
     * @return string Language instruction for AI
     */
    private static function detect_language_instruction($content) {
        // Clean content for analysis - remove HTML tags and extra whitespace
        $clean_content = wp_strip_all_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', $clean_content); // Normalize whitespace
        $sample = trim($clean_content);

        // Get default language from settings
        $options = get_option('ahmaipsu_settings', array());
        $default_language = isset($options['ahmaipsu_default_language']) ? sanitize_text_field($options['ahmaipsu_default_language']) : 'auto';

        // If user chose a specific language (not auto), force that language
        if ($default_language !== 'auto') {
            switch ($default_language) {
                case 'burmese':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Burmese (Myanmar language). You MUST write the entire summary in Burmese using Myanmar script ONLY. Do not use English, French, or any other language. Write everything in Myanmar script: ကျေးဇူးပြု၍ မြန်မာလိုသာ အနှစ်ချုပ် ရေးသားပါ။";
                case 'thai':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Thai language. You MUST write the entire summary in Thai language using Thai script only.";
                case 'chinese':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Chinese. You MUST write the entire summary in Chinese using Chinese characters only.";
                case 'japanese':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Japanese. You MUST write the entire summary in Japanese using appropriate Japanese script only.";
                case 'korean':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Korean. You MUST write the entire summary in Korean using Hangul script only.";
                case 'arabic':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Arabic. You MUST write the entire summary in Arabic using Arabic script only.";
                case 'hindi':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Hindi. You MUST write the entire summary in Hindi using Devanagari script only.";
                case 'french':
                    return "CRITICAL INSTRUCTION: This content should be summarized in French. You MUST write the entire summary in French only.";
                case 'spanish':
                    return "CRITICAL INSTRUCTION: This content should be summarized in Spanish. You MUST write the entire summary in Spanish only.";
                case 'english':
                default:
                    return "CRITICAL INSTRUCTION: The content should be summarized in English. You MUST write the entire summary in English. Never use French, Spanish, or any other language.";
            }
        }

        // AUTO-DETECTION MODE: Analyze content to determine language
        // Language detection results array
        $language_scores = array();

        // PRIORITY 1: Check for non-Latin scripts (Unicode ranges)
        $burmese_matches = array();
        preg_match_all('/[\x{1000}-\x{109F}]/u', $sample, $burmese_matches);
        $burmese_char_count = count($burmese_matches[0]);
        if ($burmese_char_count > 0) {
            $language_scores['burmese'] = $burmese_char_count;
        }

        $thai_matches = array();
        preg_match_all('/[\x{0E00}-\x{0E7F}]/u', $sample, $thai_matches);
        $thai_char_count = count($thai_matches[0]);
        if ($thai_char_count > 0) {
            $language_scores['thai'] = $thai_char_count;
        }

        $chinese_matches = array();
        preg_match_all('/[\x{4E00}-\x{9FFF}]/u', $sample, $chinese_matches);
        $chinese_char_count = count($chinese_matches[0]);
        if ($chinese_char_count > 0) {
            $language_scores['chinese'] = $chinese_char_count;
        }

        $japanese_matches = array();
        preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $sample, $japanese_matches);
        $japanese_char_count = count($japanese_matches[0]);
        if ($japanese_char_count > 0) {
            $language_scores['japanese'] = $japanese_char_count;
        }

        $korean_matches = array();
        preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $sample, $korean_matches);
        $korean_char_count = count($korean_matches[0]);
        if ($korean_char_count > 0) {
            $language_scores['korean'] = $korean_char_count;
        }

        $arabic_matches = array();
        preg_match_all('/[\x{0600}-\x{06FF}]/u', $sample, $arabic_matches);
        $arabic_char_count = count($arabic_matches[0]);
        if ($arabic_char_count > 0) {
            $language_scores['arabic'] = $arabic_char_count;
        }

        $hindi_matches = array();
        preg_match_all('/[\x{0900}-\x{097F}]/u', $sample, $hindi_matches);
        $hindi_char_count = count($hindi_matches[0]);
        if ($hindi_char_count > 0) {
            $language_scores['hindi'] = $hindi_char_count;
        }

        // PRIORITY 2: Enhanced detection for Latin-based languages with improved accuracy
        $sample_lower = strtolower($sample);

        // More specific French indicators (avoid words that appear in English)
        $french_specific = ['être ', 'avoir ', 'avec ', 'pour ', 'dans ', 'sur ', 'vous ', 'nous ', 'mais ', 'tout ', 'plus ', 'bien ', 'où ', 'comment ', 'pourquoi ', 'ça ', 'cette ', 'ces '];
        $french_common = ['le ', 'la ', 'les ', 'du ', 'des ', 'et ', 'une ', 'un '];

        $french_specific_count = 0;
        $french_common_count = 0;

        foreach ($french_specific as $indicator) {
            $french_specific_count += substr_count($sample_lower, $indicator);
        }
        foreach ($french_common as $indicator) {
            $french_common_count += substr_count($sample_lower, $indicator);
        }

        // Weight specific French words more heavily
        $french_total_score = ($french_specific_count * 3) + $french_common_count;

        if ($french_total_score > 0) {
            $language_scores['french'] = $french_total_score;
        }

        // More specific Spanish indicators
        $spanish_specific = ['ser ', 'estar ', 'pero ', 'más ', 'muy ', 'como ', 'también ', 'solo ', 'porque ', 'cuando ', 'donde ', 'este ', 'esta ', 'estos ', 'estas '];
        $spanish_common = ['el ', 'la ', 'los ', 'las ', 'del ', 'y ', 'con ', 'por ', 'para ', 'en ', 'un ', 'una '];

        $spanish_specific_count = 0;
        $spanish_common_count = 0;

        foreach ($spanish_specific as $indicator) {
            $spanish_specific_count += substr_count($sample_lower, $indicator);
        }
        foreach ($spanish_common as $indicator) {
            $spanish_common_count += substr_count($sample_lower, $indicator);
        }

        // Weight specific Spanish words more heavily
        $spanish_total_score = ($spanish_specific_count * 3) + $spanish_common_count;

        if ($spanish_total_score > 0) {
            $language_scores['spanish'] = $spanish_total_score;
        }

        // English indicators - highly specific English words that rarely appear in other languages
        $english_specific = ['the ', 'and ', 'are ', 'was ', 'were ', 'been ', 'being ', 'have ', 'has ', 'had ', 'will ', 'would ', 'could ', 'should ', 'might ', 'must ', 'shall ', 'this ', 'that ', 'these ', 'those ', 'with ', 'from ', 'about ', 'which ', 'their ', 'there ', 'they ', 'them ', 'what ', 'when ', 'where ', 'why ', 'how '];
        $english_common = ['to ', 'of ', 'in ', 'for ', 'on ', 'at ', 'by ', 'as ', 'but ', 'or ', 'if ', 'a ', 'an '];

        $english_specific_count = 0;
        $english_common_count = 0;

        foreach ($english_specific as $indicator) {
            $english_specific_count += substr_count($sample_lower, $indicator);
        }
        foreach ($english_common as $indicator) {
            $english_common_count += substr_count($sample_lower, $indicator);
        }

        // Weight specific English words more heavily and require a minimum threshold
        $english_total_score = ($english_specific_count * 2) + $english_common_count;

        if ($english_total_score >= 5) { // Require minimum threshold for English
            $language_scores['english'] = $english_total_score;
        }

        // IMPROVED LOGIC: Find the language with the highest score
        if (!empty($language_scores)) {
            // Get maximum score
            $max_score = max($language_scores);

            // Get all languages with the maximum score
            $top_languages = array_keys($language_scores, $max_score);

            // If multiple languages have the same score, prefer English as fallback
            if (count($top_languages) > 1) {
                if (in_array('english', $top_languages)) {
                    $detected_language = 'english';
                } else {
                    // If English isn't tied, use the first language alphabetically for consistency
                    sort($top_languages);
                    $detected_language = $top_languages[0];
                }
            } else {
                $detected_language = $top_languages[0];
            }
        } else {
            // No languages detected in auto mode, default to English
            $detected_language = 'english';
        }

        // Return appropriate instruction based on detected language
        switch ($detected_language) {
            case 'burmese':
                return "CRITICAL INSTRUCTION: This content is in Burmese (Myanmar language). You MUST write the entire summary in Burmese using Myanmar script ONLY. Do not use English, French, or any other language. Write everything in Myanmar script: ကျေးဇူးပြု၍ မြန်မာလိုသာ အနှစ်ချုပ် ရေးသားပါ။";

            case 'thai':
                return "CRITICAL INSTRUCTION: This content contains Thai text. You MUST write the entire summary in Thai language using Thai script only. Even if there is English mixed in, write everything in Thai.";

            case 'chinese':
                return "CRITICAL INSTRUCTION: This content contains Chinese text. You MUST write the entire summary in Chinese using Chinese characters only. Even if there is English mixed in, write everything in Chinese.";

            case 'japanese':
                return "CRITICAL INSTRUCTION: This content contains Japanese text. You MUST write the entire summary in Japanese using appropriate Japanese script only. Even if there is English mixed in, write everything in Japanese.";

            case 'korean':
                return "CRITICAL INSTRUCTION: This content contains Korean text. You MUST write the entire summary in Korean using Hangul script only. Even if there is English mixed in, write everything in Korean.";

            case 'arabic':
                return "CRITICAL INSTRUCTION: This content contains Arabic text. You MUST write the entire summary in Arabic using Arabic script only. Even if there is English mixed in, write everything in Arabic.";

            case 'hindi':
                return "CRITICAL INSTRUCTION: This content contains Hindi text. You MUST write the entire summary in Hindi using Devanagari script only. Even if there is English mixed in, write everything in Hindi.";

            case 'french':
                return "CRITICAL INSTRUCTION: This content contains French text. You MUST write the entire summary in French only. Even if there is English mixed in, write everything in French.";

            case 'spanish':
                return "CRITICAL INSTRUCTION: This content contains Spanish text. You MUST write the entire summary in Spanish only. Even if there is English mixed in, write everything in Spanish.";

            case 'english':
            default:
                return "CRITICAL INSTRUCTION: The content is in English. You MUST write the entire summary in English. Never use French, Spanish, or any other language.";
        }
    }


    /**
     * Prompt for Key Takeaways: 3–5 distinct bullets, character count is a target.
     *
     * @param int    $char_count Target character count for the whole list.
     * @param string $content    Post content.
     * @return string
     */
    private static function takeaways_user_prompt($char_count, $content) {
        return "Extract 3 to 5 key takeaways from the following content.\n" .
            "Return a markdown list only: one short distinct point per line, each starting with \"- \".\n" .
            "Do not write a paragraph. Do not put more than one point on the same line. Do not add introductory or closing text.\n" .
            "Aim for about {$char_count} characters across the whole list. Prefer 3–5 complete short bullets over hitting that count exactly.\n" .
            "Start directly with the first \"- \".\n\n" .
            "Content to analyze:\n" . $content;
    }

    /**
     * Split takeaways text into distinct items (newlines, markdown, or inline bullets).
     *
     * @param string $text Raw model output.
     * @return string[]
     */
    public static function parse_takeaways($text) {
        $text = is_string($text) ? $text : '';
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        if (function_exists('wp_strip_all_tags')) {
            $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8');
        }
        $lines = preg_split("/\n+/", $text);
        $items = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+(?=[-•*]\s+)/u', $line);
            if (count($parts) === 1) {
                $numbered = preg_split('/\s+(?=\d+[\.\)]\s+)/u', $line);
                if (count($numbered) > 1) {
                    $parts = $numbered;
                }
            }
            foreach ($parts as $part) {
                $part = trim($part);
                $part = preg_replace('/^[-•*]+\s+/u', '', $part);
                $part = preg_replace('/^\d+[\.\)]\s+/', '', $part);
                $part = trim($part);
                if ($part !== '') {
                    $items[] = $part;
                }
            }
        }
        return $items;
    }

    /**
     * Keep complete takeaway items under the character target. Never slice mid-item.
     *
     * @param string $text       Raw takeaways.
     * @param int    $char_count Target length.
     * @return string
     */
    private static function trim_takeaways_list($text, $char_count) {
        $items = self::parse_takeaways($text);
        if (empty($items)) {
            return $text;
        }
        $kept = array();
        $len = 0;
        foreach ($items as $item) {
            $line = '- ' . $item;
            $add = ($len === 0) ? mb_strlen($line, 'UTF-8') : $len + 1 + mb_strlen($line, 'UTF-8');
            if ($add > $char_count) {
                if (empty($kept)) {
                    $kept[] = $line;
                }
                break;
            }
            $kept[] = $line;
            $len = $add;
        }
        return implode("\n", $kept);
    }

    /**
     * Apply the character target. Summaries cut at a word boundary; takeaways drop whole trailing items.
     *
     * @param string $text
     * @param int    $char_count
     * @param string $content_type
     * @return string
     */
    private static function apply_char_limit($text, $char_count, $content_type) {
        if (mb_strlen($text, 'UTF-8') <= $char_count) {
            if ($content_type === 'key_takeaways') {
                $items = self::parse_takeaways($text);
                if (!empty($items)) {
                    $prefixed = array();
                    foreach ($items as $item) {
                        $prefixed[] = '- ' . $item;
                    }
                    return implode("\n", $prefixed);
                }
            }
            return $text;
        }
        if ($content_type === 'key_takeaways') {
            return self::trim_takeaways_list($text, $char_count);
        }
        $text = mb_substr($text, 0, $char_count, 'UTF-8');
        $last_space = mb_strrpos($text, ' ', 0, 'UTF-8');
        if ($last_space !== false) {
            $text = mb_substr($text, 0, $last_space, 'UTF-8');
        }
        return $text;
    }

    /**
     * Generate summary using selected AI provider
     *
     * @param string $content    The content to summarize
     * @param int    $char_count Target character count
     * @return string|WP_Error   Generated summary or error
     */
    public static function generate_summary($content, $char_count = 200, $content_type = 'summary') {
        $options = get_option('ahmaipsu_settings', array());
        $api_key = isset($options['ahmaipsu_api_key']) ? sanitize_text_field($options['ahmaipsu_api_key']) : '';
        $api_provider = isset($options['ahmaipsu_api_provider']) ? sanitize_text_field($options['ahmaipsu_api_provider']) : 'gemini';

        if (empty($api_key)) {
            return new WP_Error('no_api_key', esc_html__('API key not configured. Please set your API key in plugin settings.', 'ahm-ai-post-summary'));
        }

        // Sanitize and validate inputs
        $content = wp_strip_all_tags($content);
        $char_count = intval($char_count);
        $content_type = sanitize_text_field($content_type);

        if ($char_count < 50 || $char_count > 1500) {
            $char_count = 200; // Default fallback
        }

        $saved_model = isset($options['ahmaipsu_model']) ? $options['ahmaipsu_model'] : '';
        $model = self::resolve_model($api_provider, $saved_model);

        // Use the selected API provider
        if ($api_provider === 'gemini') {
            return self::call_gemini_api($content, $char_count, $api_key, $content_type, $model);
        } else {
            return self::call_chatgpt_api($content, $char_count, $api_key, $content_type, $model);
        }
    }

    private static function call_gemini_api($content, $char_count, $api_key, $content_type = 'summary', $model = '') {
        $model = self::resolve_model('gemini', $model);
        $endpoints = array(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key,
        );
        
        // Get language instruction for better AI prompting
        $language_instruction = self::detect_language_instruction($content);

        if ($content_type === 'key_takeaways') {
            $prompt = $language_instruction . "\n\n" . self::takeaways_user_prompt($char_count, $content);
        } else {
            $prompt = $language_instruction . "\n\n" .
                     "Please provide a concise summary of the following content in " . $char_count . " characters or less. " .
                     "Focus on the main points and key information. Make it engaging and readable.\n\n" .
                     "Make sure to be a full sentence and not cut off in the middle.\n\n" .
                     "Content to summarize:\n" . $content;
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.3,
                'maxOutputTokens' => 1024,
                'topP' => 0.8,
                'topK' => 10,
            )
        );

        $last_error = null;

        foreach ($endpoints as $url) {
            $response = wp_remote_post($url, array(
                'body' => wp_json_encode($body),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                $last_error = new WP_Error('gemini_connection_error', 'Failed to connect to Gemini API: ' . $response->get_error_message());
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 404) {
                // Try next endpoint
                $last_error = new WP_Error('gemini_api_error', 'Gemini API endpoint not found (' . $response_code . '): ' . $response_body);
                continue;
            }

            if ($response_code !== 200) {
                $last_error = new WP_Error('gemini_api_error', 'Gemini API error (' . $response_code . '): ' . $response_body);
                continue;
            }

            $data = json_decode($response_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $last_error = new WP_Error('gemini_json_error', 'Invalid JSON response from Gemini API');
                continue;
            }

            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $last_error = new WP_Error('gemini_response_error', 'Unexpected response format from Gemini API');
                continue;
            }

            $summary = trim($data['candidates'][0]['content']['parts'][0]['text']);
            return self::apply_char_limit($summary, $char_count, $content_type);
        }

        // If all endpoints failed, return the last error
        return $last_error;
    }

    private static function call_chatgpt_api($content, $char_count, $api_key, $content_type = 'summary', $model = '') {
        $url = 'https://api.openai.com/v1/chat/completions';
        $model = self::resolve_model('chatgpt', $model);

        // Get language instruction for better AI prompting
        $language_instruction = self::detect_language_instruction($content);

        if ($content_type === 'key_takeaways') {
            $user_content = self::takeaways_user_prompt($char_count, $content);
        } else {
            $user_content = "Please provide a concise summary of the following content in exactly {$char_count} characters or less. Focus on the main points and key information. Make it engaging and readable.\n\nContent to summarize:\n" . $content;
        }

        $messages = array(
            array(
                'role' => 'system',
                'content' => $language_instruction
            ),
            array(
                'role' => 'user',
                'content' => $user_content
            )
        );

        $body = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.3,
            'top_p' => 0.9,
        );

        $response = wp_remote_post($url, array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('chatgpt_connection_error', 'Failed to connect to ChatGPT API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('chatgpt_api_error', 'ChatGPT API error (' . $response_code . '): ' . $response_body);
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('chatgpt_json_error', 'Invalid JSON response from ChatGPT API');
        }

        if (isset($data['choices'][0]['message']['content'])) {
            $summary = trim($data['choices'][0]['message']['content']);
            return self::apply_char_limit($summary, $char_count, $content_type);
        }

        return new WP_Error('chatgpt_response_error', 'Unexpected response format from ChatGPT API: ' . $response_body);
    }
}
