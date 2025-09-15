<?php
/**
 * Post editor integration for AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function ahmaipsu_meta_box_callback($post) {
    $enabled = get_post_meta($post->ID, '_ahmaipsu_enabled', true);
    $summary = get_post_meta($post->ID, '_ahmaipsu_content', true);
    $global_enabled = get_option('ahmaipsu_settings')['ahmaipsu_global_enable'] ?? false;
    
    // For new posts/pages (no existing meta), default to global setting
    if ($enabled === '' && $global_enabled) {
        $enabled = '1';
    }
    
    // Get post type label for display
    $post_type_object = get_post_type_object($post->post_type);
    $post_type_label = $post_type_object ? $post_type_object->labels->singular_name : ucfirst($post->post_type);
    
    wp_nonce_field('ahmaipsu_meta', 'ahmaipsu_nonce');
    ?>
    <div class="gpt-summary-meta-box">
        <label>
            <input type="checkbox" name="ahmaipsu_enabled" value="1" <?php checked($enabled); ?> />
            <?php printf(esc_html__('Enable automatic summary generation for this %s', 'ahm-ai-post-summary'), esc_html(strtolower($post_type_label))); ?>
        </label>
        
        <?php if (!$global_enabled): ?>
            <p style="color: orange; font-style: italic; margin-top: 10px;">
                <strong>Note:</strong> Global summary is disabled. Enable it in Settings > AI Post Summary.
            </p>
        <?php endif; ?>
        
        <?php if ($summary): ?>
            <div id="gpt-summary-preview" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #0073aa;">Generated Summary:</h4>
                <p id="gpt-summary-text" style="margin: 0; line-height: 1.5; color: #333;"><?php echo esc_html($summary); ?></p>
                <div style="margin-top: 10px;">
                    <button type="button" id="ahmaipsu-regenerate-btn" class="button button-secondary ahmaipsu-regenerate-button">
                        <span class="dashicons dashicons-update"></span>
                        Regenerate Summary
                    </button>
                    <span id="ahmaipsu-regenerate-status" class="ahmaipsu-regenerate-status"></span>
                </div>
            </div>
        <?php else: ?>
            <div id="gpt-summary-preview" class="ahmaipsu-summary-preview hidden">
                <h4 class="ahmaipsu-summary-title">Generated Summary:</h4>
                <p id="gpt-summary-text" class="ahmaipsu-summary-text"></p>
                <div class="ahmaipsu-summary-actions">
                    <button type="button" id="ahmaipsu-regenerate-btn" class="button button-secondary ahmaipsu-regenerate-button">
                        <span class="dashicons dashicons-update"></span>
                        Regenerate Summary
                    </button>
                    <span id="ahmaipsu-regenerate-status" class="ahmaipsu-regenerate-status"></span>
                </div>
            </div>
            <p id="gpt-summary-placeholder" class="ahmaipsu-summary-placeholder">
                Summary will be generated automatically when you publish the post (if enabled above).
            </p>
            <div class="notice notice-info inline ahmaipsu-info-notice">
                <p><strong>💡 Tip:</strong> Summary generation may take a few moments. If it doesn't appear automatically after saving, please <strong>refresh this page</strong> to see the generated summary.</p>
            </div>
        <?php endif; ?>
        
        <div id="gpt-summary-status" class="ahmaipsu-summary-status">
            <span class="spinner ahmaipsu-status-spinner"></span>
            <span id="gpt-summary-status-text">Generating summary...</span>
        </div>
        
        <div class="ahmaipsu-summary-footer">
            💡 <strong>Tip:</strong> Summary is generated automatically when the post is published or updated.
        </div>
    </div>
    <?php
}

add_action('add_meta_boxes', 'ahmaipsu_add_meta_box');
add_action('save_post', 'ahmaipsu_save_post_meta', 10); // Save meta first
add_action('publish_post', 'ahmaipsu_auto_generate', 20); // Run after meta is saved
add_action('save_post', 'ahmaipsu_auto_generate', 25); // Run after save_post_meta with higher priority
add_action('transition_post_status', 'ahmaipsu_on_publish', 30, 3); // Handle status transitions last
add_action('wp_ajax_ahmaipsu_check_update', 'ahmaipsu_ajax_check_update');
add_action('admin_enqueue_scripts', 'ahmaipsu_enqueue_admin_scripts');

function ahmaipsu_enqueue_admin_scripts($hook) {
    // Get supported post types from settings
    $options = get_option('ahmaipsu_settings');
    $supported_post_types = isset($options['ahmaipsu_post_types']) ? $options['ahmaipsu_post_types'] : ['post'];
    
    // Check if current screen is for a supported post type
    $is_supported_screen = false;
    foreach ($supported_post_types as $post_type) {
        if ($hook === $post_type . '.php' || $hook === $post_type . '-new.php') {
            $is_supported_screen = true;
            break;
        }
    }
    
    if (!$is_supported_screen) {
        return;
    }
    
    // Enqueue admin CSS for post editor
    wp_enqueue_style(
        'ahmaipsu-admin',
        AHMAIPSU_PLUGIN_URL . 'dist/css/admin.min.css',
        array(),
        AHMAIPSU_VERSION
    );
    
    wp_enqueue_script('jquery');
    
    // Enqueue our post editor JavaScript
    wp_enqueue_script(
        'ahmaipsu-post-editor',
        AHMAIPSU_PLUGIN_URL . 'dist/js/post-editor.js',
        array('jquery'),
        AHMAIPSU_VERSION,
        true
    );
    
    // Localize script with data
    global $post;
    if ($post && $post->post_type === 'post') {
        wp_localize_script('ahmaipsu-post-editor', 'ahmaipsu_editor_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => $post->ID,
            'nonce' => wp_create_nonce('ahmaipsu_check_' . $post->ID),
            'regenerate_nonce' => wp_create_nonce('ahmaipsu_regenerate_' . $post->ID),
        ));
    }
}

function ahmaipsu_add_meta_box() {
    $options = get_option('ahmaipsu_settings');
    $supported_post_types = isset($options['ahmaipsu_post_types']) ? $options['ahmaipsu_post_types'] : ['post'];
    
    foreach ($supported_post_types as $post_type) {
        add_meta_box(
            'ahmaipsu_meta',
            __('AI Post Summary', 'ahm-ai-post-summary'),
            'ahmaipsu_meta_box_callback',
            $post_type,
            'side'
        );
    }
}
function ahmaipsu_save_post_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['ahmaipsu_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ahmaipsu_nonce'])), 'ahmaipsu_meta')) return;
    
    $enabled = isset($_POST['ahmaipsu_enabled']) ? 1 : 0;
    update_post_meta($post_id, '_ahmaipsu_enabled', $enabled);
    
    // Handle regeneration request - set a flag instead of deleting immediately
    if (isset($_POST['ahmaipsu_regenerate']) && sanitize_text_field(wp_unslash($_POST['ahmaipsu_regenerate'])) === '1') {
        // Set a flag to regenerate summary
        update_post_meta($post_id, '_ahmaipsu_regenerate_flag', '1');
    } else {
        // Remove the flag if not checking regenerate
        delete_post_meta($post_id, '_ahmaipsu_regenerate_flag');
    }
}

function ahmaipsu_auto_generate($post_id) {
    // Check if this is an autosave or revision
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    
    // Prevent running multiple times in the same request
    static $processed = array();
    if (isset($processed[$post_id])) return;
    $processed[$post_id] = true;
    
    // Check if this post type is supported
    $post = get_post($post_id);
    if (!$post) return;
    
    $options = get_option('ahmaipsu_settings');
    $supported_post_types = isset($options['ahmaipsu_post_types']) ? $options['ahmaipsu_post_types'] : ['post'];
    
    if (!in_array($post->post_type, $supported_post_types)) {
        return; // Post type not supported
    }
    
    // Check if summary is enabled for this specific post
    $post_enabled = get_post_meta($post_id, '_ahmaipsu_enabled', true);
    
    // Special handling for new posts - check if the form data indicates it should be enabled
    // Only check POST data if we have proper nonce verification
    if (!$post_enabled && isset($_POST['ahmaipsu_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ahmaipsu_nonce'])), 'ahmaipsu_meta')) {
        if (isset($_POST['ahmaipsu_enabled']) && sanitize_text_field(wp_unslash($_POST['ahmaipsu_enabled'])) == '1') {
            // The checkbox is checked in the form, so we should generate
            $post_enabled = true;
            // Also save the meta to ensure consistency (this might be why the timing is off)
            update_post_meta($post_id, '_ahmaipsu_enabled', '1');
        }
    }
    
    // If still not enabled, check if this is a new post and global setting allows it
    if (!$post_enabled) {
        $options = get_option('ahmaipsu_settings');
        $global_enabled = $options['ahmaipsu_global_enable'] ?? false;
        
        // For completely new posts (no existing _ahmaipsu_enabled meta at all), use global setting
        $existing_meta = get_post_meta($post_id, '_ahmaipsu_enabled');
        if (empty($existing_meta) && $global_enabled) {
            $post_enabled = true;
            // Save the meta for consistency
            update_post_meta($post_id, '_ahmaipsu_enabled', '1');
        }
    }
    
    // Only generate if the post-specific toggle is enabled
    if (!$post_enabled) return;
    
    // Check if we should regenerate or if no summary exists
    $existing_summary = get_post_meta($post_id, '_ahmaipsu_content', true);
    $should_regenerate = get_post_meta($post_id, '_ahmaipsu_regenerate_flag', true);
    
    // Only generate if no summary exists OR regeneration is requested
    if (!empty($existing_summary) && !$should_regenerate) return;
    
    // Get post content
    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) return;
    
    // Get options for character count setting
    $options = get_option('ahmaipsu_settings');
    $char_count = $options['ahmaipsu_char_count'] ?? 200;
    $summary = ahmaipsu_API_Handler::generate_summary($post->post_content, $char_count);
    
    // Save the summary if generation was successful
    if (!is_wp_error($summary)) {
        update_post_meta($post_id, '_ahmaipsu_content', $summary);
        // Clear the regeneration flag
        delete_post_meta($post_id, '_ahmaipsu_regenerate_flag');
        
        // Add admin notice for successful generation
        add_action('admin_notices', function() use ($should_regenerate) {
            $message = $should_regenerate ? 'Post summary regenerated successfully!' : 'Post summary generated successfully!';
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>AI Post Summary:</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        });
    } else {
        // Add admin notice for failed generation
        add_action('admin_notices', function() use ($summary) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>AI Post Summary Error:</strong> ' . esc_html($summary->get_error_message()) . '</p>';
            echo '</div>';
        });
    }
}

function ahmaipsu_on_publish($new_status, $old_status, $post) {
    // Only trigger on publish transition for posts
    if ($new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'post') {
        return;
    }
    
    // Call the auto-generate function
    ahmaipsu_auto_generate($post->ID);
}

function ahmaipsu_ajax_check_update() {
    if (!isset($_POST['post_id'])) {
        wp_die('Missing post ID');
    }
    
    $post_id = intval(sanitize_text_field(wp_unslash($_POST['post_id'])));
    $nonce_action = 'ahmaipsu_check_' . $post_id;
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $nonce_action) || !current_user_can('edit_post', $post_id)) {
        wp_die('Security check failed');
    }
    
    // Get current summary
    $summary = get_post_meta($post_id, '_ahmaipsu_content', true);
    $regenerate_flag = get_post_meta($post_id, '_ahmaipsu_regenerate_flag', true);
    
    // Check if summary generation is in progress
    $generating = !empty($regenerate_flag);
    
    wp_send_json_success([
        'summary' => $summary,
        'generating' => $generating,
        'regenerated' => !$generating && !empty($summary) // If we have a summary and no flag, it was just generated
    ]);
}

// AJAX handler for instant regeneration
add_action('wp_ajax_ahmaipsu_regenerate_instantly', 'ahmaipsu_ajax_regenerate_instantly');
add_action('wp_ajax_nopriv_ahmaipsu_regenerate_instantly', 'ahmaipsu_ajax_regenerate_instantly');
function ahmaipsu_ajax_regenerate_instantly() {
    // Basic validation first
    if (!isset($_POST['post_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required parameters');
    }
    
    // Add error handling for debugging
    try {
        // Verify nonce and permissions
        $post_id = intval($_POST['post_id']);
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ahmaipsu_regenerate_' . $post_id)) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        // Get character count from settings
        $options = get_option('ahmaipsu_settings', array());
        $char_count = isset($options['ahmaipsu_char_count']) ? intval($options['ahmaipsu_char_count']) : 200;
        if ($char_count < 50 || $char_count > 1000) {
            $char_count = 200;
        }
        
        // Generate summary using API handler
        $summary = ahmaipsu_API_Handler::generate_summary($post->post_content, $char_count);
        
        if (is_wp_error($summary)) {
            wp_send_json_error('Failed to generate summary: ' . $summary->get_error_message());
        }
        
        // Save the new summary
        update_post_meta($post_id, '_ahmaipsu_content', $summary);
        
        wp_send_json_success([
            'summary' => $summary,
            'message' => 'Summary regenerated successfully!'
        ]);
    } catch (Exception $e) {
        wp_send_json_error('PHP Error: ' . $e->getMessage());
    }
}
