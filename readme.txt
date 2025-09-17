=== AI Post Summary ===
Contributors: aheinmynn
Tags: ai, summary, seo, automation, content
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate AI-powered summaries for your blog posts using Google Gemini or OpenAI ChatGPT to improve reader engagement and SEO.

== Description ==

**AI Post Summary** is a powerful WordPress plugin that automatically generates concise, engaging summaries for your blog posts using cutting-edge AI technology from Google Gemini or OpenAI ChatGPT.

### 🚀 Key Features

* **Automatic Summary Generation**: Summaries are created automatically when posts are published or updated
* **Multiple AI Providers**: Choose between Google Gemini (recommended) or OpenAI ChatGPT
* **API Key Validation**: Real-time validation ensures your API keys work before you save settings
* **Instant Summary Regeneration**: Regenerate summaries instantly from the post editor
* **Smart Language Detection**: Automatically detects content language and generates summaries in the same language
* **Per-Post Control**: Enable or disable summaries for individual posts
* **Customizable Length**: Set your preferred summary character count (50-1000 characters)
* **Frontend Display**: Summaries appear automatically at the top of posts
* **SEO-Friendly**: Improve your content's search engine visibility
* **Easy Setup**: Simple configuration with step-by-step API key instructions
* **Secure**: API keys are stored securely and never exposed to visitors
* **Real-time Updates**: Backend interface updates automatically when summaries are generated
* **Top-Level Admin Menu**: Easy access from WordPress admin sidebar

### 💡 Why Use AI Post Summary?

* **Improve Reader Engagement**: Give readers a quick overview to decide if they want to read the full article
* **Better SEO**: Search engines love well-structured content with clear summaries
* **Save Time**: No need to manually write summaries for every post
* **Professional Appearance**: Clean, responsive design that works with any theme
* **Accessibility**: Helps users quickly understand your content

### 🔧 Easy Setup

1. Install and activate the plugin
2. Go to **AI Summary** in your WordPress admin menu
3. Choose your AI provider (Gemini recommended for free tier)
4. Enter your API key and click "Validate API Key" to test it
5. Configure your preferences (summary length, default language, etc.)
6. Enable global summaries or control them per-post
7. Start publishing - summaries are generated automatically!

### ✨ Latest Improvements

* **Real-time API Validation**: Test your API keys instantly before saving
* **Enhanced Error Handling**: Clear error messages help troubleshoot issues
* **Improved Gemini Support**: Multiple endpoint fallbacks ensure reliability
* **Better OpenAI Integration**: Flexible API key validation and rate limit handling
* **Instant Regeneration**: "Regenerate Summary" button for quick updates
* **Smart Language Detection**: Maintains content language consistency

### 🎯 Perfect For

* Bloggers who want to improve content engagement
* News sites that need quick article summaries
* E-commerce sites with product descriptions
* Corporate blogs with lengthy articles
* Any website that wants to improve user experience

### 🔒 Privacy & Security

* API keys are stored securely in your WordPress database
* Post content is sent to AI services (Google Gemini or OpenAI) only when generating summaries
* No personal user data, visitor information, or sensitive site data is transmitted to external services
* All communication with AI services is encrypted via HTTPS
* Full control over when and how summaries are generated
* You can disable summary generation entirely or per individual post

### 📚 Documentation

For detailed setup instructions, API key generation guides, and troubleshooting, visit our [documentation](https://github.com/aungheinmynn/ai-post-summary).

== External Services ==

This plugin relies on third-party AI services to generate summaries. Please be aware that your content data will be sent to these external services for processing.

### Google Gemini API

**What it's used for**: Generating AI-powered summaries of your blog post content when you select "Gemini" as your AI provider.

**Data sent**: Your blog post content (title and body text) is sent to Google's Generative Language API when generating summaries. No user personal data, visitor information, or sensitive site data is transmitted.

**When data is sent**: Data is only sent when:
- You manually generate a summary for a post
- You publish or update a post with summary generation enabled
- You test the summary generation feature in the plugin settings

**Service endpoint**: https://generativelanguage.googleapis.com/v1beta/models/

**Terms of Service**: https://developers.generativeai.google/terms
**Privacy Policy**: https://policies.google.com/privacy

### OpenAI ChatGPT API

**What it's used for**: Generating AI-powered summaries of your blog post content when you select "ChatGPT" as your AI provider.

**Data sent**: Your blog post content (title and body text) is sent to OpenAI's API when generating summaries. No user personal data, visitor information, or sensitive site data is transmitted.

**When data is sent**: Data is only sent when:
- You manually generate a summary for a post
- You publish or update a post with summary generation enabled  
- You test the summary generation feature in the plugin settings

**Service endpoint**: https://api.openai.com/v1/chat/completions

**Terms of Service**: https://openai.com/terms/
**Privacy Policy**: https://openai.com/privacy/

### Important Notes

- **User Control**: You have full control over when summaries are generated and can disable the feature entirely
- **No Automatic Transmission**: Data is only sent when you explicitly choose to generate summaries
- **Content Only**: Only the post content you choose to summarize is sent - no personal data, user information, or site analytics
- **API Key Required**: You must provide your own API key, giving you direct control over the external service usage
- **Optional Feature**: Summary generation is entirely optional and can be disabled per post or globally

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin dashboard
2. Go to Plugins > Add New
3. Search for "AI Post Summary"
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin

### After Installation

1. Go to Settings > AI Post Summary
2. Choose your preferred AI provider
3. Add your API key (instructions provided)
4. Configure your summary preferences
5. Enable summaries globally
6. Edit any post to enable/disable summaries per post

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you'll need either a Google Gemini API key or an OpenAI API key. The plugin provides detailed instructions on how to get these for free.

= Which AI provider should I choose? =

We recommend Google Gemini as it offers a generous free tier and faster response times. However, both providers work excellently.

= Are API keys secure? =

Yes, API keys are stored securely in your WordPress database and are never exposed to website visitors or included in any frontend code.

= How do I know if my API key is working? =

The plugin now includes a "Validate API Key" button that appears when you enter an API key. Click it to test your key in real-time and get immediate feedback on whether it's working correctly.

= Can I regenerate summaries after they're created? =

Yes! Each post now has a "Regenerate Summary" button in the post editor. Click it to instantly generate a new summary with your current settings.

= Why am I getting API errors? =

The plugin provides detailed error messages to help troubleshoot:
- **Invalid API key**: Check your key format and permissions
- **Rate limit exceeded**: Wait a moment or check your usage limits
- **404 errors**: The plugin automatically tries multiple endpoints to find working ones
- Use the "Validate API Key" button to test your configuration

= Can I customize the summary appearance? =

Yes, the summaries use CSS classes that you can style in your theme. The default styling is clean and responsive.

= Will this slow down my website? =

No, summaries are generated in the background when posts are saved. Visitors see cached summaries, so there's no impact on loading speed.

= Can I disable summaries for specific posts? =

Yes, each post has a checkbox in the editor to enable/disable summaries individually.

= Does it work with all themes? =

Yes, the plugin is designed to work with any WordPress theme. Summaries are added to the content automatically.

= Can I place summaries manually? =

Yes, you can use the [ai_post_summary] shortcode to place summaries anywhere in your content.

= What happens if I deactivate the plugin? =

Your summaries are preserved in the database. If you reactivate the plugin, everything will work as before.

= Is there a word/character limit? =

You can set your preferred character count in the settings. The default is 200 characters, but you can adjust this as needed.

== Screenshots ==

1. Plugin settings page with API provider selection and key configuration
2. Post editor meta box showing summary controls
3. Frontend display of AI-generated summary
4. Summary generation in action with real-time updates

== Changelog ==

= 1.1.5 =
* Added: Real-time API key validation with "Validate API Key" button
* Added: Instant summary regeneration button in post editor
* Enhanced: Improved Gemini API endpoints with multiple fallback options
* Enhanced: Better OpenAI API rate limit handling and error messages
* Enhanced: Smart API endpoint detection (v1 and v1beta support)
* Enhanced: More flexible OpenAI API key format validation
* Fixed: Admin menu moved to top-level for easier access
* Fixed: Improved error handling for 404 and rate limit responses
* Fixed: Better language detection and AI prompting
* Improved: WordPress i18n compliance with proper translator comments
* Improved: Enhanced user experience with validation feedback

= 1.1.4 =
* Security: Fixed nonce verification for POST data processing
* Security: Added proper input sanitization for form data
* Security: Enhanced WordPress security compliance for WordPress.org validation
* Fixed: Resolved security warnings about unverified form data access

= 1.1.3 =
* Fixed: Burmese language detection in mixed content
* Improved: Language detection now properly identifies Burmese even when mixed with English
* Enhanced: Non-English languages now take priority over English in mixed content
* Added: More robust Unicode character counting for better language detection

= 1.1.2 =
* Enhanced: Improved language detection prioritization for mixed content
* Enhanced: Burmese language now takes highest priority in mixed content
* Enhanced: Non-English languages prioritized over English in mixed content
* Enhanced: Stronger AI instructions for consistent language output

= 1.1.1 =
* Fixed: Auto-generation now properly respects per-post toggle settings
* Improved: New posts default to enabled when global setting is on
* Enhanced: Clearer separation between global and per-post settings
* Updated: Testing documentation with correct behavior

= 1.1.0 =
* Added multilingual support - AI summaries now generated in the same language as content
* Enhanced language detection for Burmese (Myanmar), Thai, Chinese, Japanese, Korean, Arabic, Hindi, and other languages
* Fixed global enable validation - now requires API key to be entered before enabling
* Added warning messages when API key is missing
* Improved user experience with real-time API key validation
* Enhanced AI prompts for better language consistency

= 1.0.0 =
* Initial release
* Google Gemini and OpenAI ChatGPT integration
* Automatic summary generation
* Per-post summary controls
* Frontend display with customizable styling
* Real-time backend updates
* Comprehensive admin interface
* Security and performance optimizations

= 1.0.0 =
Initial release of AI Post Summary. Install now to start generating AI-powered summaries for your content!

== Support ==

Need help? Visit our [support forum](https://wordpress.org/support/plugin/ahm-ai-post-summary/)
