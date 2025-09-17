<?php
/**
 * Admin settings for AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ahmaipsu_add_admin_menu');
add_action('admin_init', 'ahmaipsu_settings_init');
add_action('wp_ajax_ahmaipsu_test', 'ahmaipsu_ajax_test');
add_action('wp_ajax_ahmaipsu_validate_api_key', 'ahmaipsu_ajax_validate_api_key');
add_action('admin_enqueue_scripts', 'ahmaipsu_admin_scripts');

function ahmaipsu_admin_scripts($hook) {
    // Only load on our settings page - now it's a top-level menu
    if ($hook !== 'toplevel_page_ahmaipsu') {
        return;
    }
    
    // Enqueue admin CSS
    wp_enqueue_style(
        'ahmaipsu-admin',
        AHMAIPSU_PLUGIN_URL . 'dist/css/admin.min.css',
        array(),
        AHMAIPSU_VERSION
    );
    
    // Enqueue jQuery (WordPress core)
    wp_enqueue_script('jquery');
    
    // Enqueue our admin settings JavaScript
    wp_enqueue_script(
        'ahmaipsu-admin-settings',
        AHMAIPSU_PLUGIN_URL . 'dist/js/admin-settings.min.js',
        array('jquery'),
        AHMAIPSU_VERSION,
        true
    );
    
    // Localize script with data
    wp_localize_script('ahmaipsu-admin-settings', 'ahmaipsu_admin_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'test_nonce' => wp_create_nonce('ahmaipsu_test'),
        'validate_nonce' => wp_create_nonce('ahmaipsu_test'), // Use same nonce for simplicity
        'saving_text' => __('Saving...', 'ahm-ai-post-summary'),
    ));
    
    // Add inline script for admin functionality
    $admin_js = "
        jQuery(document).ready(function($) {
            // Ensure at least one post type checkbox is selected
            $('.ahmaipsu-post-type-checkbox').on('change', function() {
                var checkedBoxes = $('.ahmaipsu-post-type-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    $(this).prop('checked', true);
                }
            });
            
            // Test summary generation
            $('#generate_test_summary').on('click', function() {
                var content = $('#test_content').val();
                var nonce = $('#test_nonce').val();
                
                if (!content.trim()) {
                    $('#test_result').html('<div class=\"notice notice-error\"><p>Please enter some content to test.</p></div>');
                    return;
                }
                
                $(this).prop('disabled', true).text('Generating...');
                
                $.ajax({
                    url: ahmaipsu_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ahmaipsu_test',
                        content: content,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test_result').html('<div class=\"notice notice-success\"><p><strong>Test Summary:</strong></p><p>' + response.data + '</p></div>');
                        } else {
                            $('#test_result').html('<div class=\"notice notice-error\"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#test_result').html('<div class=\"notice notice-error\"><p>An error occurred while generating the summary.</p></div>');
                    },
                    complete: function() {
                        $('#generate_test_summary').prop('disabled', false).text('Generate Test Summary');
                    }
                });
            });
        });
    ";
    wp_add_inline_script('ahmaipsu-admin-settings', $admin_js);
}

function ahmaipsu_ajax_test() {
    // Verify nonce and user permissions
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ahmaipsu_test') || !current_user_can('manage_options')) {
        wp_die(esc_html__('Security check failed', 'ahm-ai-post-summary'));
    }
    
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    if (empty($content)) {
        wp_send_json_error(esc_html__('No content provided for testing.', 'ahm-ai-post-summary'));
    }
    
    $options = get_option('ahmaipsu_settings', array());
    $char_count = isset($options['ahmaipsu_char_count']) ? intval($options['ahmaipsu_char_count']) : 200;
    
    $summary = ahmaipsu_API_Handler::generate_summary($content, $char_count);
    
    if (is_wp_error($summary)) {
        wp_send_json_error($summary->get_error_message());
    } else {
        wp_send_json_success($summary);
    }
}

function ahmaipsu_ajax_validate_api_key() {
    // Verify nonce and user permissions
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ahmaipsu_test') || !current_user_can('manage_options')) {
        wp_die(esc_html__('Security check failed', 'ahm-ai-post-summary'));
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    $api_provider = isset($_POST['api_provider']) ? sanitize_text_field(wp_unslash($_POST['api_provider'])) : 'gemini';
    
    if (empty($api_key)) {
        wp_send_json_error(esc_html__('Please enter an API key to validate.', 'ahm-ai-post-summary'));
    }
    
    // Ensure valid provider
    if (!in_array($api_provider, array('gemini', 'chatgpt'))) {
        $api_provider = 'gemini';
    }
    
    // Validate the API key using our API handler
    $validation_result = ahmaipsu_API_Handler::validate_api_key($api_key, $api_provider);
    
    if (is_wp_error($validation_result)) {
        wp_send_json_error($validation_result->get_error_message());
    } else {
        $provider_name = ($api_provider === 'gemini') ? 'Gemini' : 'ChatGPT';
        /* translators: %s: API provider name (Gemini or ChatGPT) */
        wp_send_json_success(sprintf(esc_html__('✅ %s API key is valid and working correctly!', 'ahm-ai-post-summary'), $provider_name));
    }
}

function ahmaipsu_add_admin_menu() {
    add_menu_page(
        'AI Post Summary Settings',        // Page title
        'AI Summary',                      // Menu title (shorter for menu)
        'manage_options',                  // Capability
        'ahmaipsu',                       // Menu slug
        'ahmaipsu_options_page',          // Callback function
        'dashicons-lightbulb',            // Icon (lightbulb represents AI/ideas)
        58                                // Position (after Comments, before Appearance)
    );
}

// Sanitization callback for settings
function ahmaipsu_sanitize_settings($input) {
    $sanitized = array();
    
    // Sanitize API provider
    if (isset($input['ahmaipsu_api_provider'])) {
        $sanitized['ahmaipsu_api_provider'] = in_array($input['ahmaipsu_api_provider'], array('gemini', 'chatgpt')) ? $input['ahmaipsu_api_provider'] : 'gemini';
    }
    
    // Sanitize API key
    if (isset($input['ahmaipsu_api_key'])) {
        $sanitized['ahmaipsu_api_key'] = sanitize_text_field($input['ahmaipsu_api_key']);
    }
    
    // Sanitize character count
    if (isset($input['ahmaipsu_char_count'])) {
        $char_count = intval($input['ahmaipsu_char_count']);
        $sanitized['ahmaipsu_char_count'] = ($char_count >= 50 && $char_count <= 1000) ? $char_count : 200;
    }
    
    // Sanitize global enable checkbox - only allow if API key is present
    if (isset($input['ahmaipsu_global_enable'])) {
        $api_key = isset($input['ahmaipsu_api_key']) ? trim(sanitize_text_field($input['ahmaipsu_api_key'])) : '';
        
        // If no API key provided, check existing settings
        if (empty($api_key)) {
            $existing_options = get_option('ahmaipsu_settings', array());
            $api_key = isset($existing_options['ahmaipsu_api_key']) ? trim($existing_options['ahmaipsu_api_key']) : '';
        }
        
        if (!empty($api_key)) {
            $sanitized['ahmaipsu_global_enable'] = 1;
        } else {
            $sanitized['ahmaipsu_global_enable'] = 0;
            // Add admin notice about requiring API key
            add_settings_error(
                'ahmaipsu_settings',
                'api_key_required',
                __('Global summaries cannot be enabled without a valid API key. Please enter your API key first.', 'ahm-ai-post-summary'),
                'error'
            );
        }
    } else {
        $sanitized['ahmaipsu_global_enable'] = 0;
    }
    
    // Sanitize disclaimer text
    if (isset($input['ahmaipsu_disclaimer'])) {
        $sanitized['ahmaipsu_disclaimer'] = sanitize_textarea_field($input['ahmaipsu_disclaimer']);
    }
    
    // Sanitize default language
    if (isset($input['ahmaipsu_default_language'])) {
        $allowed_languages = ['auto', 'english', 'burmese', 'french', 'spanish', 'chinese', 'japanese', 'korean', 'thai', 'arabic', 'hindi'];
        $default_language = sanitize_text_field($input['ahmaipsu_default_language']);
        if (in_array($default_language, $allowed_languages)) {
            $sanitized['ahmaipsu_default_language'] = $default_language;
        } else {
            $sanitized['ahmaipsu_default_language'] = 'auto'; // fallback to auto-detect
        }
    }
    
    // Sanitize theme selection
    if (isset($input['ahmaipsu_theme'])) {
        $allowed_themes = ['classic', 'minimal', 'modern', 'elegant', 'card'];
        $theme = sanitize_text_field($input['ahmaipsu_theme']);
        if (in_array($theme, $allowed_themes)) {
            $sanitized['ahmaipsu_theme'] = $theme;
        } else {
            $sanitized['ahmaipsu_theme'] = 'classic'; // fallback to classic
        }
    }
    
    // Sanitize supported post types
    if (isset($input['ahmaipsu_post_types']) && is_array($input['ahmaipsu_post_types'])) {
        $allowed_post_types = ['post', 'page'];
        $sanitized_post_types = array();
        
        foreach ($input['ahmaipsu_post_types'] as $post_type) {
            if (in_array($post_type, $allowed_post_types)) {
                $sanitized_post_types[] = $post_type;
            }
        }
        
        // Ensure at least one post type is selected
        if (empty($sanitized_post_types)) {
            $sanitized_post_types = ['post']; // Default to post if none selected
        }
        
        $sanitized['ahmaipsu_post_types'] = $sanitized_post_types;
    } else {
        // Default to post if not set
        $sanitized['ahmaipsu_post_types'] = ['post'];
    }
    
    // Add success message if settings were saved successfully
    if (!empty($sanitized)) {
        add_settings_error(
            'ahmaipsu_settings',
            'settings_saved',
            __('✅ Settings saved successfully! Your AI Post Summary configuration has been updated.', 'ahm-ai-post-summary'),
            'success'
        );
    }
    
    return $sanitized;
}

function ahmaipsu_settings_init() {
    register_setting('ahmaipsu', 'ahmaipsu_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'ahmaipsu_sanitize_settings',
        'show_in_rest' => false
    ));

    // API Settings Section
    add_settings_section(
        'ahmaipsu_api_section',
        __('API Configuration', 'ahm-ai-post-summary'),
        null,
        'ahmaipsu'
    );

    add_settings_field(
        'ahmaipsu_api_provider',
        __('API Provider', 'ahm-ai-post-summary'),
        'ahmaipsu_api_provider_render',
        'ahmaipsu',
        'ahmaipsu_api_section'
    );

    add_settings_field(
        'ahmaipsu_api_key',
        __('API Key (Gemini/ChatGPT)', 'ahm-ai-post-summary'),
        'ahmaipsu_api_key_render',
        'ahmaipsu',
        'ahmaipsu_api_section'
    );

    // Summary Settings Section
    add_settings_section(
        'ahmaipsu_summary_section',
        __('Summary Configuration', 'ahm-ai-post-summary'),
        null,
        'ahmaipsu'
    );

    add_settings_field(
        'ahmaipsu_char_count',
        __('Summary Character Count', 'ahm-ai-post-summary'),
        'ahmaipsu_char_count_render',
        'ahmaipsu',
        'ahmaipsu_summary_section'
    );

    add_settings_field(
        'ahmaipsu_global_enable',
        __('Enable Globally', 'ahm-ai-post-summary'),
        'ahmaipsu_global_enable_render',
        'ahmaipsu',
        'ahmaipsu_summary_section'
    );

    add_settings_field(
        'ahmaipsu_post_types',
        __('Supported Post Types', 'ahm-ai-post-summary'),
        'ahmaipsu_post_types_render',
        'ahmaipsu',
        'ahmaipsu_summary_section'
    );

    add_settings_field(
        'ahmaipsu_default_language',
        __('Default Language', 'ahm-ai-post-summary'),
        'ahmaipsu_default_language_render',
        'ahmaipsu',
        'ahmaipsu_summary_section'
    );

    // Display Settings Section
    add_settings_section(
        'ahmaipsu_display_section',
        __('Display Configuration', 'ahm-ai-post-summary'),
        null,
        'ahmaipsu'
    );

    add_settings_field(
        'ahmaipsu_disclaimer',
        __('Disclaimer Text', 'ahm-ai-post-summary'),
        'ahmaipsu_disclaimer_render',
        'ahmaipsu',
        'ahmaipsu_display_section'
    );

    add_settings_field(
        'ahmaipsu_theme',
        __('Choose Theme', 'ahm-ai-post-summary'),
        'ahmaipsu_theme_render',
        'ahmaipsu',
        'ahmaipsu_display_section'
    );
}

function ahmaipsu_api_provider_render() {
    $options = get_option('ahmaipsu_settings', array());
    $provider = isset($options['ahmaipsu_api_provider']) ? $options['ahmaipsu_api_provider'] : 'gemini';
    ?>
    <select name="ahmaipsu_settings[ahmaipsu_api_provider]" id="ahmaipsu_api_provider">
        <option value="gemini" <?php selected($provider, 'gemini'); ?>>Gemini 2.0 Flash</option>
        <option value="chatgpt" <?php selected($provider, 'chatgpt'); ?>>ChatGPT</option>
    </select>
    <p class="description">Choose your preferred AI service. Gemini is recommended for better performance and lower costs.</p>
    <?php
}

function ahmaipsu_api_key_render() {
    $options = get_option('ahmaipsu_settings');
    $provider = $options['ahmaipsu_api_provider'] ?? 'gemini';
    $api_key = $options['ahmaipsu_api_key'] ?? '';
    
    echo '<div class="ahmaipsu-api-key-container">';
    echo '<input type="password" name="ahmaipsu_settings[ahmaipsu_api_key]" id="ahmaipsu_api_key" value="' . esc_attr($api_key) . '" class="ahmaipsu-api-key-input" />';
    
    // Always show the validation button, but initially hide it if no API key
    $button_class = empty(trim($api_key)) ? 'button button-secondary ahmaipsu-validate-button hidden' : 'button button-secondary ahmaipsu-validate-button';
    echo '<button type="button" id="validate-api-key" class="' . esc_attr($button_class) . '">';
    echo '<span class="dashicons dashicons-shield-alt ahmaipsu-validate-icon"></span>';
    echo 'Validate API Key';
    echo '</button>';
    echo '</div>';
    
    // API key validation result container
    echo '<div id="api-validation-result" class="ahmaipsu-validation-result"></div>';
    
    echo '<div id="gemini-instructions" class="ahmaipsu-instructions' . ($provider === 'chatgpt' ? ' hidden' : '') . '">';
    echo '<p class="description">';
    echo '🔐 <strong>Get your Gemini API key:</strong><br>';
    echo '1. Visit <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio →</a><br>';
    echo '2. Sign in with your Google account<br>';
    echo '3. Click "Create API Key" and select your project<br>';
    echo '4. Copy the generated API key and paste it above<br>';
    echo '<em>💡 Gemini offers generous free tier and faster responses.</em>';
    echo '</p>';
    echo '</div>';
    
    echo '<div id="chatgpt-instructions" class="ahmaipsu-instructions' . ($provider === 'gemini' ? ' hidden' : '') . '">';
    echo '<p class="description">';
    echo '🔐 <strong>Get your ChatGPT API key:</strong><br>';
    echo '1. Visit <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform →</a><br>';
    echo '2. Sign in to your OpenAI account (create one if needed)<br>';
    echo '3. Click "Create new secret key" and give it a name<br>';
    echo '4. Copy the generated API key and paste it above<br>';
    echo '<em>⚠️ Note: You may need to add billing information to use the API.</em>';
    echo '</p>';
    echo '</div>';
}

function ahmaipsu_char_count_render() {
    $options = get_option('ahmaipsu_settings');
    echo '<input type="number" name="ahmaipsu_settings[ahmaipsu_char_count]" value="' . esc_attr($options['ahmaipsu_char_count'] ?? '200') . '" min="50" max="500" />';
    echo '<p class="description">Set the target length for generated summaries (50-500 characters). Recommended: 200-300 for optimal readability.</p>';
}

function ahmaipsu_global_enable_render() {
    $options = get_option('ahmaipsu_settings');
    $api_key = $options['ahmaipsu_api_key'] ?? '';
    $is_enabled = !empty($options['ahmaipsu_global_enable']);
    $has_api_key = !empty(trim($api_key));
    
    // Auto-enable if API key is present and no explicit choice has been made
    if ($has_api_key && !isset($options['ahmaipsu_global_enable'])) {
        $is_enabled = true;
    }
    
    $checked = $is_enabled ? 'checked' : '';
    $disabled = !$has_api_key ? 'disabled' : '';
    
    echo '<input type="checkbox" name="ahmaipsu_settings[ahmaipsu_global_enable]" value="1" ' . esc_attr($checked) . ' ' . esc_attr($disabled) . ' id="ahmaipsu_global_enable" />';
    echo '<label for="ahmaipsu_global_enable"> Enable automatic summary generation for all new posts</label>';
    
    if (!$has_api_key) {
        echo '<div class="notice notice-warning inline ahmaipsu-warning-notice">';
        echo '<p><strong>⚠️ Warning:</strong> You must enter a valid API key <a href="#api-tab" onclick="jQuery(\'.nav-tab[data-tab=\\\'api\\\']\').click(); return false;">here</a> before enabling global summaries. ';
        echo '</div>';
        
    } else {
        echo '<p class="description">When enabled, AI summaries will be automatically generated for all new posts (individual posts can still opt out).</p>';
    }
}

function ahmaipsu_post_types_render() {
    $options = get_option('ahmaipsu_settings');
    $selected_post_types = isset($options['ahmaipsu_post_types']) ? $options['ahmaipsu_post_types'] : ['post'];
    
    $post_types = [
        'post' => __('Posts', 'ahm-ai-post-summary'),
        'page' => __('Pages', 'ahm-ai-post-summary')
    ];
    
    echo '<fieldset>';
    echo '<legend class="screen-reader-text">' . esc_html__('Supported Post Types', 'ahm-ai-post-summary') . '</legend>';
    
    foreach ($post_types as $post_type => $label) {
        $checked = in_array($post_type, $selected_post_types) ? 'checked' : '';
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="checkbox" name="ahmaipsu_settings[ahmaipsu_post_types][]" value="' . esc_attr($post_type) . '" ' . esc_attr($checked) . ' class="ahmaipsu-post-type-checkbox" />';
        echo ' ' . esc_html($label);
        echo '</label>';
    }
    
    echo '</fieldset>';
    echo '<p class="description">Select which post types should support AI summary generation. At least one post type must be selected.</p>';
}

function ahmaipsu_default_language_render() {
    $options = get_option('ahmaipsu_settings');
    $default_language = $options['ahmaipsu_default_language'] ?? 'auto';
    
    echo '<select name="ahmaipsu_settings[ahmaipsu_default_language]" id="ahmaipsu_default_language">';
    echo '<option value="auto" ' . selected($default_language, 'auto', false) . '>🔍 Detect Automatically</option>';
    echo '<option value="english" ' . selected($default_language, 'english', false) . '>English</option>';
    echo '<option value="burmese" ' . selected($default_language, 'burmese', false) . '>မြန်မာ (Burmese)</option>';
    echo '<option value="french" ' . selected($default_language, 'french', false) . '>Français (French)</option>';
    echo '<option value="spanish" ' . selected($default_language, 'spanish', false) . '>Español (Spanish)</option>';
    echo '<option value="chinese" ' . selected($default_language, 'chinese', false) . '>中文 (Chinese)</option>';
    echo '<option value="japanese" ' . selected($default_language, 'japanese', false) . '>日本語 (Japanese)</option>';
    echo '<option value="korean" ' . selected($default_language, 'korean', false) . '>한국어 (Korean)</option>';
    echo '<option value="thai" ' . selected($default_language, 'thai', false) . '>ไทย (Thai)</option>';
    echo '<option value="arabic" ' . selected($default_language, 'arabic', false) . '>العربية (Arabic)</option>';
    echo '<option value="hindi" ' . selected($default_language, 'hindi', false) . '>हिन्दी (Hindi)</option>';
    echo '</select>';
    echo '<p class="description">Choose "Detect Automatically" to let the AI analyze content and pick the best language, or select a specific language to force all summaries to use that language.</p>';
}

function ahmaipsu_disclaimer_render() {
    $options = get_option('ahmaipsu_settings');
    $disclaimer = $options['ahmaipsu_disclaimer'] ?? 'This summary was generated by AI and may contain inaccuracies or omissions. Please refer to the full article for complete information.';
    echo '<textarea name="ahmaipsu_settings[ahmaipsu_disclaimer]" rows="3" cols="50" class="ahmaipsu-disclaimer-textarea">' . esc_textarea($disclaimer) . '</textarea>';
    echo '<p class="description">This disclaimer will appear below all AI-generated summaries on your site.</p>';
}

function ahmaipsu_theme_render() {
    $options = get_option('ahmaipsu_settings');
    $selected_theme = $options['ahmaipsu_theme'] ?? 'classic';
    
    // Define available themes
    $themes = array(
        'classic' => array(
            'name' => '🎯 Classic',
            'description' => 'Clean and professional design with subtle borders and organized layout',
            'preview' => '<div class="ahmaipsu-preview-classic">
                <h4>📝 Summary</h4>
                <p>This is how your summary will appear with the Classic theme. Clean, professional, and easy to read with a structured layout.</p>
                <small>Generated by AI</small>
            </div>'
        ),
        'minimal' => array(
            'name' => '✨ Minimal',
            'description' => 'Simple and clean design with subtle background for distraction-free reading',
            'preview' => '<div class="ahmaipsu-preview-minimal">
                <h4>Summary</h4>
                <p>This is how your summary will appear with the Minimal theme. Simple, clean, and distraction-free with subtle styling.</p>
                <small>Generated by AI</small>
            </div>'
        ),
        'modern' => array(
            'name' => '🚀 Modern',
            'description' => 'Contemporary design with gradients and visual appeal for modern websites',
            'preview' => '<div class="ahmaipsu-preview-modern">
                <h4>🤖 AI Summary</h4>
                <p>This is how your summary will appear with the Modern theme. Eye-catching gradients and contemporary design elements.</p>
                <small>Generated by AI</small>
            </div>'
        ),
        'elegant' => array(
            'name' => '💎 Elegant',
            'description' => 'Sophisticated design with elegant typography and refined styling',
            'preview' => '<div class="ahmaipsu-preview-elegant">
                <h4>Summary</h4>
                <p>This is how your summary will appear with the Elegant theme. Sophisticated typography and refined styling for premium sites.</p>
                <small>Generated by AI</small>
            </div>'
        ),
        'card' => array(
            'name' => '📋 Card',
            'description' => 'Card-style design with shadows and borders that stands out prominently',
            'preview' => '<div class="ahmaipsu-preview-card">
                <h4>📄 Article Summary</h4>
                <p>This is how your summary will appear with the Card theme. Prominent card design with shadows that makes content stand out.</p>
                <small>Generated by AI</small>
            </div>'
        )
    );
    
    echo '<div class="ahmaipsu-theme-selector">';
    echo '<p class="description">🎨 Choose how you want your AI summaries to appear on your site. Click on any theme below to select it and see a live preview.</p>';
    
    // Hidden input to store selected theme
    echo '<input type="hidden" name="ahmaipsu_settings[ahmaipsu_theme]" value="' . esc_attr($selected_theme) . '" id="ahmaipsu_selected_theme" />';
    
    foreach ($themes as $theme_key => $theme_data) {
        echo '<div class="ahmaipsu-theme-option' . (($selected_theme === $theme_key) ? ' selected' : '') . '" data-theme="' . esc_attr($theme_key) . '">';
        
        echo '<div class="ahmaipsu-theme-info">';
        echo '<div class="ahmaipsu-theme-name">' . esc_html($theme_data['name']) . '</div>';
        echo '<p class="ahmaipsu-theme-description">' . esc_html($theme_data['description']) . '</p>';
        echo '</div>';
        
        echo '<div class="ahmaipsu-theme-preview">';
        echo wp_kses_post($theme_data['preview']);
        echo '</div>';
        
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<div class="ahmaipsu-theme-note">';
    echo '<p><strong>💡 Pro Tip:</strong> You can further customize the appearance by adding custom CSS to your theme\'s Additional CSS section under Appearance > Customize.</p>';
    echo '</div>';
}

function ahmaipsu_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
        // Display settings errors/messages
        settings_errors('ahmaipsu_settings');
        
        // Get current options for tab logic
        $options = get_option('ahmaipsu_settings', array());
        $has_api_key = !empty(trim($options['ahmaipsu_api_key'] ?? ''));
        $default_tab = $has_api_key ? 'summary' : 'api';
        ?>
        
        <form action="options.php" method="post" id="ahmaipsu-settings-form">
            <?php
            settings_fields('ahmaipsu');
            ?>
            
            <!-- Tab Navigation -->
            <div class="nav-tab-wrapper">
                <a href="#summary-tab" class="nav-tab <?php echo $default_tab === 'summary' ? 'nav-tab-active' : ''; ?>" data-tab="summary">📝 Summary</a>
                <a href="#display-tab" class="nav-tab <?php echo $default_tab === 'display' ? 'nav-tab-active' : ''; ?>" data-tab="display">🎨 Themes</a>
                <a href="#api-tab" class="nav-tab <?php echo $default_tab === 'api' ? 'nav-tab-active' : ''; ?>" data-tab="api">🔑 API Key</a>
            </div>
            
            <div id="summary-tab" class="tab-content <?php echo $default_tab === 'summary' ? 'active' : ''; ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Summary Character Count', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_char_count_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Globally', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_global_enable_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Supported Post Types', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_post_types_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Language', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_default_language_render(); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Display Tab Content -->
            <div id="display-tab" class="tab-content <?php echo $default_tab === 'display' ? 'active' : ''; ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Disclaimer Text', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_disclaimer_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Choose Theme', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_theme_render(); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- API Tab Content -->
            <div id="api-tab" class="tab-content <?php echo $default_tab === 'api' ? 'active' : ''; ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('API Provider', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_api_provider_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API Key (Gemini/ChatGPT)', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_api_key_render(); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button(__('Save Settings', 'ahm-ai-post-summary'), 'primary', 'submit', true, array('id' => 'ahmaipsu-save-button')); ?>
        </form>
        
        <div class="ahmaipsu-test-container">
            <h3>Generate Summary Test</h3>
            <textarea id="test_content" rows="4" cols="60" placeholder="Enter content to test summary generation..."></textarea><br><br>
            <?php wp_nonce_field('ahmaipsu_test', 'test_nonce'); ?>
            <button type="button" id="generate_test_summary" class="button button-secondary">Generate Test Summary</button>
            <div id="test_result" class="ahmaipsu-test-result"></div>
        </div>
    </div>
    <?php
}
