<?php
/**
 * Admin settings for AHM AI Post Summary plugin
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
add_action('wp_ajax_ahmaipsu_dismiss_donate_notice', 'ahmaipsu_dismiss_donate_notice');
add_action('admin_enqueue_scripts', 'ahmaipsu_admin_scripts');
add_action('admin_notices', 'ahmaipsu_donate_admin_notice');

function ahmaipsu_admin_scripts($hook) {
    // Load on all admin pages for donate notice dismissal functionality
    // Only load full admin settings scripts on our settings page
    if ($hook === 'toplevel_page_ahmaipsu') {
        // Enqueue admin CSS for settings page
        wp_enqueue_style(
            'ahmaipsu-frontend',
            AHMAIPSU_PLUGIN_URL . 'dist/css/frontend.min.css',
            array(),
            AHMAIPSU_VERSION
        );
        wp_enqueue_style(
            'ahmaipsu-admin',
            AHMAIPSU_PLUGIN_URL . 'dist/css/admin.min.css',
            array('ahmaipsu-frontend'),
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
    }
    
    // Always load jQuery for donate notice dismissal on all admin pages
    wp_enqueue_script('jquery');
    
    // Localize script with data for donate notice dismissal
    wp_localize_script('jquery', 'ahmaipsu_admin_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'test_nonce' => wp_create_nonce('ahmaipsu_test'),
        'validate_nonce' => wp_create_nonce('ahmaipsu_test'),
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
    $content_type = isset($options['ahmaipsu_summary_type']) ? sanitize_text_field($options['ahmaipsu_summary_type']) : 'summary';
    
    $summary = ahmaipsu_API_Handler::generate_summary($content, $char_count, $content_type);
    
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
    }

    $models = ahmaipsu_API_Handler::list_models($api_key, $api_provider);
    if (is_wp_error($models)) {
        $models = ahmaipsu_API_Handler::get_fallback_models($api_provider);
    }

    $provider_name = ($api_provider === 'gemini') ? 'Gemini' : 'ChatGPT';
    /* translators: %s: API provider name (Gemini or ChatGPT) */
    wp_send_json_success(array(
        'message' => sprintf(esc_html__('✅ %s API key is valid and working correctly!', 'ahm-ai-post-summary'), $provider_name),
        'models' => $models,
        'default_model' => ahmaipsu_API_Handler::get_default_model($api_provider),
    ));
}

function ahmaipsu_donate_admin_notice() {
    // Check if user has dismissed the notice for this month
    $current_user = wp_get_current_user();
    $dismissed_key = 'ahmaipsu_donate_notice_dismissed_' . gmdate('Y_m');
    $dismissed = get_user_meta($current_user->ID, $dismissed_key, true);

    if ($dismissed) {
        return;
    }

    // Show the notice
    ?>
    <div id="ahmaipsu-donate-notice" class="notice notice-info is-dismissible" style="border-left-color: #0070ba;">
        <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: 500;">
            ☕ <?php esc_html_e('Enjoying AHM AI Post Summary? Support ongoing development!', 'ahm-ai-post-summary'); ?>
        </p>
        <p style="margin: 0 0 15px 0;">
            <?php esc_html_e('Your support helps maintain and improve this plugin with new features and regular updates.', 'ahm-ai-post-summary'); ?>
        </p>
        <a href="https://paypal.com" target="_blank" rel="noopener noreferrer"
           style="display: inline-block; padding: 8px 16px; background: #0070ba; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; margin-right: 10px;">
            <?php esc_html_e('Buy Me a Coffee', 'ahm-ai-post-summary'); ?>
        </a>
        <button type="button" class="button-link" id="ahmaipsu-donate-remind-later" style="color: #666; text-decoration: none;">
            <?php esc_html_e('Remind me later', 'ahm-ai-post-summary'); ?>
        </button>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle dismiss button
        $('#ahmaipsu-donate-notice .notice-dismiss').on('click', function() {
            $.ajax({
                url: ahmaipsu_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'ahmaipsu_dismiss_donate_notice',
                    nonce: ahmaipsu_admin_vars.test_nonce
                }
            });
        });

        // Handle "Remind me later" button
        $('#ahmaipsu-donate-remind-later').on('click', function() {
            $('#ahmaipsu-donate-notice').fadeOut();
            $.ajax({
                url: ahmaipsu_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'ahmaipsu_dismiss_donate_notice',
                    nonce: ahmaipsu_admin_vars.test_nonce
                }
            });
        });
    });
    </script>
    <?php
}

function ahmaipsu_dismiss_donate_notice() {
    // Verify nonce and user permissions
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ahmaipsu_test') || !current_user_can('manage_options')) {
        wp_die(esc_html__('Security check failed', 'ahm-ai-post-summary'));
    }

    $current_user = wp_get_current_user();
    $dismissed_key = 'ahmaipsu_donate_notice_dismissed_' . gmdate('Y_m');

    // Store dismissal for current month
    update_user_meta($current_user->ID, $dismissed_key, '1');

    wp_die();
}

function ahmaipsu_add_admin_menu() {
    add_menu_page(
        'AHM AI Post Summary Settings',        // Page title
        'AHM AI Summary',                      // Menu title (shorter for menu)
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

    // Sanitize model ID
    if (isset($input['ahmaipsu_model'])) {
        $model = ahmaipsu_API_Handler::sanitize_model_id($input['ahmaipsu_model']);
        $provider = isset($sanitized['ahmaipsu_api_provider']) ? $sanitized['ahmaipsu_api_provider'] : 'gemini';
        $sanitized['ahmaipsu_model'] = ($model !== '') ? $model : ahmaipsu_API_Handler::get_default_model($provider);
    }
    
    // Sanitize character count
    if (isset($input['ahmaipsu_char_count'])) {
        $char_count = intval($input['ahmaipsu_char_count']);
        $sanitized['ahmaipsu_char_count'] = ($char_count >= 50 && $char_count <= 1500) ? $char_count : 200;
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
    
    // Sanitize Summary type (keep both option keys in sync)
    $existing_options = isset($existing_options) ? $existing_options : get_option('ahmaipsu_settings', array());
    $allowed_summary_types = ['summary', 'key_takeaways'];
    if (isset($input['ahmaipsu_summary_type'])) {
        $summary_type = sanitize_text_field($input['ahmaipsu_summary_type']);
        if (!in_array($summary_type, $allowed_summary_types, true)) {
            $summary_type = 'summary';
        }
    } else {
        $summary_type = $existing_options['ahmaipsu_summary_type'] ?? $existing_options['ahmaipsu_content_type'] ?? 'summary';
        if (!in_array($summary_type, $allowed_summary_types, true)) {
            $summary_type = 'summary';
        }
    }
    $sanitized['ahmaipsu_summary_type'] = $summary_type;
    $sanitized['ahmaipsu_content_type'] = $summary_type;
    
    // Sanitize custom summary title
    if (isset($input['ahmaipsu_custom_summary_title'])) {
        $sanitized['ahmaipsu_custom_summary_title'] = sanitize_text_field($input['ahmaipsu_custom_summary_title']);
    }
    
    // Sanitize custom key takeaways title
    if (isset($input['ahmaipsu_custom_key_takeaways_title'])) {
        $sanitized['ahmaipsu_custom_key_takeaways_title'] = sanitize_text_field($input['ahmaipsu_custom_key_takeaways_title']);
    }
    
    // Destination sync checkboxes (unchecked = 0)
    $sanitized['ahmaipsu_sync_excerpt'] = isset($input['ahmaipsu_sync_excerpt']) ? 1 : 0;
    $sanitized['ahmaipsu_sync_yoast'] = isset($input['ahmaipsu_sync_yoast']) ? 1 : 0;
    $sanitized['ahmaipsu_sync_rankmath'] = isset($input['ahmaipsu_sync_rankmath']) ? 1 : 0;
    $sanitized['ahmaipsu_sync_on_regenerate'] = isset($input['ahmaipsu_sync_on_regenerate']) ? 1 : 0;
    $sanitized['ahmaipsu_sync_overwrite'] = isset($input['ahmaipsu_sync_overwrite']) ? 1 : 0;

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

    add_settings_field(
        'ahmaipsu_model',
        __('Model', 'ahm-ai-post-summary'),
        'ahmaipsu_model_render',
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
        'ahmaipsu_sync_destinations',
        __('Also write to', 'ahm-ai-post-summary'),
        'ahmaipsu_sync_destinations_render',
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

    add_settings_field(
        'ahmaipsu_summary_type',
        __('Content Type', 'ahm-ai-post-summary'),
        'ahmaipsu_summary_type_render',
        'ahmaipsu',
        'ahmaipsu_display_section'
    );

    add_settings_field(
        'ahmaipsu_custom_summary_title',
        __('Custom Summary Title', 'ahm-ai-post-summary'),
        'ahmaipsu_custom_summary_title_render',
        'ahmaipsu',
        'ahmaipsu_display_section'
    );

    add_settings_field(
        'ahmaipsu_custom_key_takeaways_title',
        __('Custom Key Takeaways Title', 'ahm-ai-post-summary'),
        'ahmaipsu_custom_key_takeaways_title_render',
        'ahmaipsu',
        'ahmaipsu_display_section'
    );
}

function ahmaipsu_api_provider_render() {
    $options = get_option('ahmaipsu_settings', array());
    $provider = isset($options['ahmaipsu_api_provider']) ? $options['ahmaipsu_api_provider'] : 'gemini';
    ?>
    <select name="ahmaipsu_settings[ahmaipsu_api_provider]" id="ahmaipsu_api_provider">
        <option value="gemini" <?php selected($provider, 'gemini'); ?>>Google Gemini</option>
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


function ahmaipsu_model_render() {
    $options = get_option('ahmaipsu_settings', array());
    $provider = isset($options['ahmaipsu_api_provider']) ? $options['ahmaipsu_api_provider'] : 'gemini';
    $saved = isset($options['ahmaipsu_model']) ? $options['ahmaipsu_model'] : '';
    $selected = ahmaipsu_API_Handler::resolve_model($provider, $saved);
    $models = ahmaipsu_API_Handler::get_fallback_models($provider);
    $gemini_fallback = ahmaipsu_API_Handler::get_fallback_models('gemini');
    $openai_fallback = ahmaipsu_API_Handler::get_fallback_models('chatgpt');

    echo '<select name="ahmaipsu_settings[ahmaipsu_model]" id="ahmaipsu_model"';
    echo ' data-fallback-gemini="' . esc_attr(wp_json_encode($gemini_fallback)) . '"';
    echo ' data-fallback-chatgpt="' . esc_attr(wp_json_encode($openai_fallback)) . '"';
    echo ' data-default-gemini="' . esc_attr(ahmaipsu_API_Handler::get_default_model('gemini')) . '"';
    echo ' data-default-chatgpt="' . esc_attr(ahmaipsu_API_Handler::get_default_model('chatgpt')) . '">';

    $found = false;
    foreach ($models as $model) {
        $id = $model['id'];
        $label = $model['label'];
        if ($id === $selected) {
            $found = true;
        }
        echo '<option value="' . esc_attr($id) . '" ' . selected($selected, $id, false) . '>' . esc_html($label) . '</option>';
    }
    if (!$found && $selected !== '') {
        echo '<option value="' . esc_attr($selected) . '" selected>' . esc_html($selected) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Choose a model for the selected provider. Validate your API key to refresh the live list.', 'ahm-ai-post-summary') . '</p>';
}

function ahmaipsu_char_count_render() {
    $options = get_option('ahmaipsu_settings');
    echo '<input type="number" name="ahmaipsu_settings[ahmaipsu_char_count]" value="' . esc_attr($options['ahmaipsu_char_count'] ?? '500') . '" min="50" max="1500" />';
    echo '<p class="description">Set the target length for generated summaries (50-1500 characters). Recommended: 200-500 for optimal readability.</p>';
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


function ahmaipsu_sync_destinations_render() {
    $excerpt = ahmaipsu_setting_flag('ahmaipsu_sync_excerpt', true);
    $yoast = ahmaipsu_setting_flag('ahmaipsu_sync_yoast', true);
    $rankmath = ahmaipsu_setting_flag('ahmaipsu_sync_rankmath', true);
    $on_regen = ahmaipsu_setting_flag('ahmaipsu_sync_on_regenerate', true);
    $overwrite = ahmaipsu_setting_flag('ahmaipsu_sync_overwrite', false);
    $yoast_active = ahmaipsu_is_yoast_active();
    $rankmath_active = ahmaipsu_is_rankmath_active();

    echo '<fieldset>';
    echo '<legend class="screen-reader-text">' . esc_html__('Also write to', 'ahm-ai-post-summary') . '</legend>';

    echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ahmaipsu_settings[ahmaipsu_sync_excerpt]" value="1" ' . checked($excerpt, true, false) . ' /> ';
    echo esc_html__('WordPress excerpt (archives, RSS, search)', 'ahm-ai-post-summary') . '</label>';

    echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ahmaipsu_settings[ahmaipsu_sync_yoast]" value="1" ' . checked($yoast, true, false) . ' /> ';
    echo esc_html__('Yoast SEO meta description', 'ahm-ai-post-summary');
    if (!$yoast_active) {
        echo ' <span class="description">' . esc_html__('(Yoast not installed — writes are skipped)', 'ahm-ai-post-summary') . '</span>';
    }
    echo '</label>';

    echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ahmaipsu_settings[ahmaipsu_sync_rankmath]" value="1" ' . checked($rankmath, true, false) . ' /> ';
    echo esc_html__('Rank Math meta description', 'ahm-ai-post-summary');
    if (!$rankmath_active) {
        echo ' <span class="description">' . esc_html__('(Rank Math not installed — writes are skipped)', 'ahm-ai-post-summary') . '</span>';
    }
    echo '</label>';

    echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ahmaipsu_settings[ahmaipsu_sync_on_regenerate]" value="1" ' . checked($on_regen, true, false) . ' /> ';
    echo esc_html__('Update these fields when regenerating a summary we previously wrote', 'ahm-ai-post-summary') . '</label>';

    echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ahmaipsu_settings[ahmaipsu_sync_overwrite]" value="1" ' . checked($overwrite, true, false) . ' /> ';
    echo esc_html__('Overwrite existing hand-written excerpt and SEO meta', 'ahm-ai-post-summary') . '</label>';

    echo '<p class="description">' . esc_html__('Empty fields are filled from the same generation as the on-post summary (excerpt up to 300 characters, SEO meta up to 155). Hand-written text is never changed unless you enable overwrite. Admin test generation does not write to a post.', 'ahm-ai-post-summary') . '</p>';
    echo '</fieldset>';
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
    echo '<textarea name="ahmaipsu_settings[ahmaipsu_disclaimer]" rows="3" cols="40" class="ahmaipsu-disclaimer-textarea">' . esc_textarea($disclaimer) . '</textarea>';
    echo '<p class="description">This disclaimer will appear below all AI-generated summaries on your site.</p>';
}

function ahmaipsu_summary_type_render() {
    $options = get_option('ahmaipsu_settings');
    $summary_type = $options['ahmaipsu_summary_type'] ?? $options['ahmaipsu_content_type'] ?? 'summary';
    ?>
    <fieldset>
        <legend class="screen-reader-text"><?php esc_html_e('Summary Type', 'ahm-ai-post-summary'); ?></legend>
        <label style="display: block; margin-bottom: 8px;">
            <input type="radio" name="ahmaipsu_settings[ahmaipsu_summary_type]" value="summary" <?php checked($summary_type, 'summary'); ?> />
            <?php esc_html_e('Summary', 'ahm-ai-post-summary'); ?> - <?php esc_html_e('Generate a concise overview of the post content.', 'ahm-ai-post-summary'); ?>
        </label>
        <label style="display: block; margin-bottom: 8px;">
            <input type="radio" name="ahmaipsu_settings[ahmaipsu_summary_type]" value="key_takeaways" <?php checked($summary_type, 'key_takeaways'); ?> />
            <?php esc_html_e('Key Takeaways', 'ahm-ai-post-summary'); ?> - <?php esc_html_e('Extract and list the main insights and actionable points as bullet points.', 'ahm-ai-post-summary'); ?>
        </label>
    </fieldset>
    <p class="description"><?php esc_html_e('Choose the type of summary to generate for your posts.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_custom_summary_title_render() {
    $options = get_option('ahmaipsu_settings');
    $custom_title = $options['ahmaipsu_custom_summary_title'] ?? '';
    ?>
    <input type="text" name="ahmaipsu_settings[ahmaipsu_custom_summary_title]" value="<?php echo esc_attr($custom_title); ?>" placeholder="📝 Summary" class="regular-text" />
    <p class="description"><?php esc_html_e('Custom title for summary content. Leave empty to use theme default. Supports emojis.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_custom_key_takeaways_title_render() {
    $options = get_option('ahmaipsu_settings');
    $custom_title = $options['ahmaipsu_custom_key_takeaways_title'] ?? '';
    ?>
    <input type="text" name="ahmaipsu_settings[ahmaipsu_custom_key_takeaways_title]" value="<?php echo esc_attr($custom_title); ?>" placeholder="🔑 Key Takeaways" class="regular-text" />
    <p class="description"><?php esc_html_e('Custom title for key takeaways content. Leave empty to use theme default. Supports emojis.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_donate_section_callback() {
    echo '<p>' . esc_html__('If you find this plugin helpful, consider supporting its development. Your donation helps maintain and improve the plugin!', 'ahm-ai-post-summary') . '</p>';
}

function ahmaipsu_show_donate_render() {
    $options = get_option('ahmaipsu_settings');
    $show_donate = isset($options['ahmaipsu_show_donate']) ? $options['ahmaipsu_show_donate'] : false;
    ?>
    <input type="checkbox" name="ahmaipsu_settings[ahmaipsu_show_donate]" value="1" <?php checked($show_donate); ?> id="ahmaipsu_show_donate" />
    <label for="ahmaipsu_show_donate"><?php esc_html_e('Show donate button in admin settings', 'ahm-ai-post-summary'); ?></label>
    <p class="description"><?php esc_html_e('Display a donate button in the plugin settings to support development.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_donate_text_render() {
    $options = get_option('ahmaipsu_settings');
    $donate_text = $options['ahmaipsu_donate_text'] ?? '☕ Buy Me a Coffee';
    ?>
    <input type="text" name="ahmaipsu_settings[ahmaipsu_donate_text]" value="<?php echo esc_attr($donate_text); ?>" placeholder="☕ Buy Me a Coffee" class="regular-text" />
    <p class="description"><?php esc_html_e('Text to display on the donate button. Supports emojis.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_donate_url_render() {
    $options = get_option('ahmaipsu_settings');
    $donate_url = $options['ahmaipsu_donate_url'] ?? '';
    ?>
    <input type="url" name="ahmaipsu_settings[ahmaipsu_donate_url]" value="<?php echo esc_attr($donate_url); ?>" placeholder="https://www.paypal.com/donate/..." class="regular-text" />
    <p class="description"><?php esc_html_e('URL for donations (PayPal, Buy Me a Coffee, Ko-fi, etc.). Leave empty to hide the donate button.', 'ahm-ai-post-summary'); ?></p>
    <?php
}

function ahmaipsu_theme_render() {
    $options = get_option('ahmaipsu_settings');
    $selected_theme = $options['ahmaipsu_theme'] ?? 'classic';
    $disclaimer = isset($options['ahmaipsu_disclaimer']) && $options['ahmaipsu_disclaimer'] !== ''
        ? $options['ahmaipsu_disclaimer']
        : 'This summary was generated by AI and may contain inaccuracies or omissions. Please refer to the full article for complete information.';

    $themes = array(
        'classic' => array(
            'name' => '🎯 Classic',
            'description' => 'Hairline box with a 2px accent underline.',
            'title' => '📝 Summary',
            'body' => 'This is how your summary will appear with the Classic theme.',
        ),
        'minimal' => array(
            'name' => '✨ Minimal',
            'description' => 'No box, no fill. Small tracked label.',
            'title' => 'Summary',
            'body' => 'This is how your summary will appear with the Minimal theme.',
        ),
        'modern' => array(
            'name' => '🚀 Modern',
            'description' => 'Uppercase kicker and a 2px accent bar on a surface panel.',
            'title' => 'Summary',
            'body' => 'This is how your summary will appear with the Modern theme.',
        ),
        'elegant' => array(
            'name' => '💎 Elegant',
            'description' => 'Italic title and a 3px left accent bar.',
            'title' => 'Summary',
            'body' => 'This is how your summary will appear with the Elegant theme.',
        ),
        'card' => array(
            'name' => '📋 Card',
            'description' => 'Raised object with radius, soft shadow, and a hairline.',
            'title' => '📄 Summary',
            'body' => 'This is how your summary will appear with the Card theme.',
        ),
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
        echo '<div class="ahmaipsu-summary-box ahmaipsu-theme-' . esc_attr($theme_key) . '">';
        echo '<h4 class="ahmaipsu-summary-title">' . esc_html($theme_data['title']) . '</h4>';
        echo '<div class="ahmaipsu-summary-content"><p>' . esc_html($theme_data['body']) . '</p></div>';
        echo '<div class="ahmaipsu-summary-disclaimer"><small>ℹ️ ' . esc_html($disclaimer) . '</small></div>';
        echo '</div>';
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
                        <th scope="row"><?php esc_html_e('Summary Type', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_summary_type_render(); ?></td>
                    </tr>
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
                        <th scope="row"><?php esc_html_e('Also write to', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_sync_destinations_render(); ?></td>
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
                        <th scope="row"><?php esc_html_e('Custom Summary Title', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_custom_summary_title_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Key Takeaways Title', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_custom_key_takeaways_title_render(); ?></td>
                    </tr>
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
                    <tr>
                        <th scope="row"><?php esc_html_e('Model', 'ahm-ai-post-summary'); ?></th>
                        <td><?php ahmaipsu_model_render(); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button(__('Save Settings', 'ahm-ai-post-summary'), 'primary', 'submit', true, array('id' => 'ahmaipsu-save-button')); ?>
        </form>
        
        <!-- Buy Me a Coffee Link -->
        <div class="ahmaipsu-donate-notice" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; text-align: center;">
            <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: 500;">
                ☕ <?php esc_html_e('Enjoying this plugin? Support the developer!', 'ahm-ai-post-summary'); ?>
            </p>
            <a href="https://paypal.com" target="_blank" rel="noopener noreferrer" 
               style="display: inline-block; padding: 10px 20px; background: #0070ba; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; transition: background-color 0.3s ease;">
                <?php esc_html_e('Buy Me a Coffee', 'ahm-ai-post-summary'); ?>
            </a>
        </div>
        
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
