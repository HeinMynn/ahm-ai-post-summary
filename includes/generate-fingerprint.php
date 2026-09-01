<?php
/**
 * Source fingerprint for regen-on-change.
 *
 * @package AIPostSummary
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hash of the post body sent to the last successful generate.
 *
 * @param string $content Raw post_content.
 * @return string
 */
function ahmaipsu_fingerprint_body($content) {
    return md5(wp_strip_all_tags((string) $content));
}

/**
 * Settings + body snapshot used for skip-vs-generate.
 *
 * @param WP_Post $post         Post being generated.
 * @param string  $content_type summary|key_takeaways.
 * @return array
 */
function ahmaipsu_generate_context($post, $content_type = '') {
    $options = get_option('ahmaipsu_settings', array());
    $provider = isset($options['ahmaipsu_api_provider']) ? sanitize_text_field($options['ahmaipsu_api_provider']) : 'gemini';
    $saved_model = isset($options['ahmaipsu_model']) ? $options['ahmaipsu_model'] : '';
    $model = ahmaipsu_API_Handler::resolve_model($provider, $saved_model);
    $char_count = isset($options['ahmaipsu_char_count']) ? intval($options['ahmaipsu_char_count']) : 200;
    if ($char_count < 50 || $char_count > 1500) {
        $char_count = 200;
    }
    if ($content_type === '') {
        $content_type = get_post_meta($post->ID, '_ahmaipsu_content_type', true) ?: 'summary';
    }
    $language = isset($options['ahmaipsu_default_language']) ? sanitize_text_field($options['ahmaipsu_default_language']) : 'auto';

    return array(
        'body' => ahmaipsu_fingerprint_body($post->post_content),
        'provider' => $provider,
        'model' => $model,
        'char_count' => $char_count,
        'content_type' => sanitize_text_field($content_type),
        'language' => $language,
    );
}

/**
 * @param int   $post_id Post ID.
 * @param array $context Snapshot from ahmaipsu_generate_context().
 */
function ahmaipsu_store_generate_fingerprint($post_id, $context) {
    update_post_meta((int) $post_id, '_ahmaipsu_generate_fingerprint', wp_json_encode($context));
}

/**
 * @param int $post_id Post ID.
 * @return array
 */
function ahmaipsu_get_generate_fingerprint($post_id) {
    $raw = get_post_meta((int) $post_id, '_ahmaipsu_generate_fingerprint', true);
    if (empty($raw)) {
        return array();
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
}

/**
 * @param array $stored  Last successful snapshot.
 * @param array $current Current snapshot.
 * @return bool
 */
function ahmaipsu_generate_context_matches($stored, $current) {
    if (empty($stored) || empty($current)) {
        return false;
    }
    $keys = array('body', 'provider', 'model', 'char_count', 'content_type', 'language');
    foreach ($keys as $key) {
        if (!isset($stored[$key], $current[$key])) {
            return false;
        }
        if ((string) $stored[$key] !== (string) $current[$key]) {
            return false;
        }
    }
    return true;
}
