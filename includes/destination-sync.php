<?php
/**
 * Sync generated summaries to native excerpt and SEO meta.
 *
 * @package AIPostSummary
 * @since   1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AHMAIPSU_EXCERPT_MAX', 300);
define('AHMAIPSU_META_MAX', 155);

/**
 * Read a checkbox-style setting, with a default for upgrades that lack the key.
 *
 * @param string $key     Option key inside ahmaipsu_settings.
 * @param bool   $default Default when the key is missing.
 * @return bool
 */
function ahmaipsu_setting_flag($key, $default = true) {
    $options = get_option('ahmaipsu_settings', array());
    if (!isset($options[$key])) {
        return (bool) $default;
    }
    return !empty($options[$key]);
}

/**
 * Whether Yoast SEO is active. No hard dependency.
 *
 * @return bool
 */
function ahmaipsu_is_yoast_active() {
    return defined('WPSEO_VERSION');
}

/**
 * Whether Rank Math is active. No hard dependency.
 *
 * @return bool
 */
function ahmaipsu_is_rankmath_active() {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath');
}

/**
 * Flatten generated HTML/markdown into one plaintext paragraph.
 *
 * @param string $summary Generated summary or key takeaways.
 * @return string
 */
function ahmaipsu_plain_summary($summary) {
    $text = wp_strip_all_tags((string) $summary, false);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/^[ \t]*[\-\x{2022}*]+[ \t]*/mu', '', $text);
    $lines = preg_split('/\R+/u', $text);
    $lines = array_values(array_filter(array_map('trim', (array) $lines)));
    if (count($lines) > 1) {
        $text = implode('. ', $lines);
        $text = preg_replace('/\.\s*\./u', '.', $text);
    } else {
        $text = isset($lines[0]) ? $lines[0] : '';
    }
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

/**
 * UTF-8 length.
 *
 * @param string $text Text.
 * @return int
 */
function ahmaipsu_text_len($text) {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

/**
 * UTF-8 substring.
 *
 * @param string $text Text.
 * @param int    $max  Max characters.
 * @return string
 */
function ahmaipsu_text_cut($text, $max) {
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

/**
 * Trim to last full sentence under $max, else last space, else hard cut.
 *
 * @param string $text Text.
 * @param int    $max  Max characters.
 * @return string
 */
function ahmaipsu_trim_to_sentence($text, $max) {
    $text = trim($text);
    if ($text === '' || ahmaipsu_text_len($text) <= $max) {
        return $text;
    }
    $slice = ahmaipsu_text_cut($text, $max);
    if (preg_match('/^(.*[.!?])(\s|$)/u', $slice, $matches) && ahmaipsu_text_len($matches[1]) >= 40) {
        return trim($matches[1]);
    }
    $space = function_exists('mb_strrpos') ? mb_strrpos($slice, ' ', 0, 'UTF-8') : strrpos($slice, ' ');
    if ($space) {
        $cut = ahmaipsu_text_cut($slice, $space);
        return rtrim($cut, '.,;:');
    }
    return $slice;
}

/**
 * Excerpt string derived from a generated summary.
 *
 * @param string $summary Generated summary.
 * @return string
 */
function ahmaipsu_excerpt_from_summary($summary) {
    return ahmaipsu_trim_to_sentence(ahmaipsu_plain_summary($summary), AHMAIPSU_EXCERPT_MAX);
}

/**
 * SEO meta description derived from a generated summary.
 *
 * @param string $summary Generated summary.
 * @return string
 */
function ahmaipsu_meta_from_summary($summary) {
    return ahmaipsu_trim_to_sentence(ahmaipsu_plain_summary($summary), AHMAIPSU_META_MAX);
}

/**
 * Whether the current field value was last written by this plugin.
 *
 * @param string $current    Current field value.
 * @param string $wrote_hash Stored md5 of the last value we wrote.
 * @return bool
 */
function ahmaipsu_value_is_ours($current, $wrote_hash) {
    $current = trim((string) $current);
    $wrote_hash = (string) $wrote_hash;
    if ($current === '' || $wrote_hash === '') {
        return false;
    }
    return hash_equals($wrote_hash, md5($current));
}

/**
 * Whether we may write a destination.
 *
 * Empty: always. Ours: if regenerate-updates is on. Human: only if overwrite is on.
 *
 * @param string $current         Current value.
 * @param string $wrote_hash      Hash of last value we wrote.
 * @param bool   $update_on_regen Update-on-regenerate setting.
 * @param bool   $overwrite       Overwrite-human setting.
 * @return bool
 */
function ahmaipsu_should_write_destination($current, $wrote_hash, $update_on_regen, $overwrite) {
    $current = trim((string) $current);
    if ($current === '') {
        return true;
    }
    if (ahmaipsu_value_is_ours($current, $wrote_hash)) {
        return (bool) $update_on_regen;
    }
    return (bool) $overwrite;
}

/**
 * After a successful generation, fill excerpt / Yoast / Rank Math per settings.
 *
 * @param int    $post_id        Post ID.
 * @param string $summary        Generated summary HTML/text.
 * @param bool   $is_regenerate  Whether this run is a regenerate.
 * @return void
 */
function ahmaipsu_sync_destinations($post_id, $summary, $is_regenerate = false) {
    $post_id = intval($post_id);
    if ($post_id <= 0 || $summary === '' || is_wp_error($summary)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post || wp_is_post_revision($post_id)) {
        return;
    }

    $update_on_regen = ahmaipsu_setting_flag('ahmaipsu_sync_on_regenerate', true);
    $overwrite = ahmaipsu_setting_flag('ahmaipsu_sync_overwrite', false);

    if (ahmaipsu_setting_flag('ahmaipsu_sync_excerpt', true)) {
        $excerpt = ahmaipsu_excerpt_from_summary($summary);
        if ($excerpt !== '') {
            $current = isset($post->post_excerpt) ? $post->post_excerpt : '';
            $hash = get_post_meta($post_id, '_ahmaipsu_wrote_excerpt', true);
            if (ahmaipsu_should_write_destination($current, $hash, $update_on_regen, $overwrite)) {
                ahmaipsu_write_post_excerpt($post_id, $excerpt);
                update_post_meta($post_id, '_ahmaipsu_wrote_excerpt', md5($excerpt));
            }
        }
    }

    $meta = ahmaipsu_meta_from_summary($summary);

    if ($meta !== '' && ahmaipsu_setting_flag('ahmaipsu_sync_yoast', true) && ahmaipsu_is_yoast_active()) {
        $current = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $hash = get_post_meta($post_id, '_ahmaipsu_wrote_yoast', true);
        if (ahmaipsu_should_write_destination($current, $hash, $update_on_regen, $overwrite)) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta);
            update_post_meta($post_id, '_ahmaipsu_wrote_yoast', md5($meta));
        }
    }

    if ($meta !== '' && ahmaipsu_setting_flag('ahmaipsu_sync_rankmath', true) && ahmaipsu_is_rankmath_active()) {
        $current = (string) get_post_meta($post_id, 'rank_math_description', true);
        $hash = get_post_meta($post_id, '_ahmaipsu_wrote_rankmath', true);
        if (ahmaipsu_should_write_destination($current, $hash, $update_on_regen, $overwrite)) {
            update_post_meta($post_id, 'rank_math_description', $meta);
            update_post_meta($post_id, '_ahmaipsu_wrote_rankmath', md5($meta));
        }
    }
}

/**
 * Write post_excerpt without re-entering generate hooks.
 *
 * @param int    $post_id Post ID.
 * @param string $excerpt Excerpt text.
 * @return void
 */
function ahmaipsu_write_post_excerpt($post_id, $excerpt) {
    remove_action('save_post', 'ahmaipsu_auto_generate', 25);
    remove_action('publish_post', 'ahmaipsu_auto_generate', 20);
    remove_action('transition_post_status', 'ahmaipsu_on_publish', 30);
    wp_update_post(
        array(
            'ID' => $post_id,
            'post_excerpt' => $excerpt,
        )
    );
    add_action('save_post', 'ahmaipsu_auto_generate', 25);
    add_action('publish_post', 'ahmaipsu_auto_generate', 20);
    add_action('transition_post_status', 'ahmaipsu_on_publish', 30, 3);
}
