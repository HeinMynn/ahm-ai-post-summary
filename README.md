# AHM AI Post Summary

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/plugins/ahm-ai-post-summary/)
[![Requires WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![Requires PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/ahm-ai-post-summary.svg)](https://wordpress.org/plugins/ahm-ai-post-summary/)
[![WordPress.org](https://img.shields.io/wordpress/plugin/dt/ahm-ai-post-summary.svg)](https://wordpress.org/plugins/ahm-ai-post-summary/)

> Automatically generate AI-powered summaries for your WordPress blog posts using Google Gemini or OpenAI ChatGPT to improve reader engagement and SEO.

## ✨ Features

### 🔧 Admin Panel

- **Secure API Key Storage**: Safely store Gemini or ChatGPT API keys using WordPress options API
- **Character Count Control**: Set the desired length of generated summaries (50-1500 characters)
- **Global Toggle**: Enable or disable summary generation site-wide
- **API Provider Selection**: Choose between Gemini (preferred) and ChatGPT
- **Excerpt and SEO meta**: Optionally fill the native excerpt, Yoast, and Rank Math from the same generation
- **Model Picker**: After validating the API key, choose a model from that provider
- **Test Summary Generation**: Built-in testing tool in admin settings

### 📝 Post Editor

- **Per-Post Control**: Enable or disable summary generation for individual posts
- **One-Click Generation**: Generate summaries directly in the post editor
- **Real-time Preview**: See generated summaries before publishing
- **Auto-save**: Generated summaries are automatically saved with the post

### 🌐 Frontend Display

- **Automatic Display**: Summaries appear automatically at the top of posts (when enabled)
- **Gutenberg block**: Insert **AI Summary** (Text category) to place the box anywhere; Default inherits the site theme, plus Minimal and Card styles
- **Shortcode Support**: Use `[ahm_ai_post_summary]` for manual placement (`[ahmaipsu]` still works)
- **Responsive Design**: Clean, mobile-friendly summary display box

## 🚀 Quick Start

### Installation

#### From WordPress.org (Recommended)

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "AHM AI Post Summary"
3. Click **Install Now** and then **Activate**

#### From GitHub (Development)

```bash
# Clone the repository
git clone https://github.com/HeinMynn/ahm-ai-post-summary.git

# Upload to your WordPress plugins directory
cp -r ai-post-summary /wp-content/plugins/

# Activate the plugin
wp plugin activate ahm-ai-post-summary
```

### Configuration

1. Navigate to **AHM AI Post Summary** in your WordPress admin
2. Choose your AI provider (Gemini recommended)
3. Get your API key:
   - **Gemini**: [Google AI Studio](https://aistudio.google.com/app/apikey)
   - **ChatGPT**: [OpenAI Platform](https://platform.openai.com/api-keys)
4. Enter your API key and validate it
5. Choose a model from the list (or keep the default)
6. Configure preferences and enable summaries

## 📖 Usage

### Automatic Generation

1. Activate the plugin and add an API key
2. Create/edit a post — summaries are enabled for new posts by default
3. Publish — the summary generates automatically
4. Uncheck the per-post toggle to opt out

### REST API / XML-RPC (including AI agents)

With global auto-generation on, posts created through REST or XML-RPC get a summary unless the agent sends an explicit off.

To skip a post, set meta `_ahmaipsu_enabled` to `0`:

```json
{
  "title": "My post",
  "content": "…",
  "status": "publish",
  "meta": {
    "_ahmaipsu_enabled": "0"
  }
}
```

XML-RPC: custom field `_ahmaipsu_enabled` = `0`. Omit the field (or send `1`) to generate. An empty string is not an off.

### Manual Control

- Use `[ahm_ai_post_summary]` for custom placement (`[ahmaipsu]` still works)
- Regenerate summaries anytime from post editor
- Disable per post or globally

## 🔒 Security & Privacy

- API keys stored securely in WordPress database
- Post content sent to AI services only during generation
- No personal user data transmitted
- HTTPS encryption for all API calls
- Full user control over data transmission

## 🌐 External Services

This plugin connects to third-party AI services. See [readme.txt](readme.txt) for detailed disclosure.

### Google Gemini API

- **Purpose**: AI-powered summary generation
- **Data**: Post content only
- **Terms**: [Google AI Terms](https://developers.generativeai.google/terms)
- **Privacy**: [Google Privacy](https://policies.google.com/privacy)

### OpenAI ChatGPT API

- **Purpose**: AI-powered summary generation
- **Data**: Post content only
- **Terms**: [OpenAI Terms](https://openai.com/terms/)
- **Privacy**: [OpenAI Privacy](https://openai.com/privacy/)

## 📁 Project Structure

```
ahm-ai-post-summary/
├── ahm-ai-post-summary.php      # Main plugin file
├── readme.txt                   # WordPress.org readme
├── package.json                 # Build configuration
├── changelog.md                 # Version history
├── assets/                      # Plugin assets (screenshots, icons)
├── includes/                    # Core functionality
│   ├── admin-settings.php       # Admin interface
│   ├── post-editor.php          # Post editor integration
│   ├── api-handler.php          # AI API communication
│   └── frontend-display.php     # Frontend display
└── languages/                   # Translation files
    └── ahm-ai-post-summary.pot  # Translation template
```

## 🛠️ Development

### Requirements

- WordPress 5.0+
- PHP 7.4+
- Valid API key (Gemini or ChatGPT)

### Setup for Development

```bash
# Install dependencies
npm install

# Build assets
npm run build

# Watch for changes
npm run watch
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

Please read our [contributing guidelines](CONTRIBUTING.md) for details.

## 📋 WordPress.org Notes

**Plugin Details:**

- **Slug**: `ahm-ai-post-summary`
- **Main File**: `ahm-ai-post-summary.php`
- **Plugin URI**: `https://wordpress.org/plugins/ahm-ai-post-summary/`

## 📜 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Author**: Aung Hein Mynn
- **Contributors**: Community contributors
- **AI Services**: Google Gemini, OpenAI ChatGPT

## 📞 Support

- **WordPress.org**: [Support Forum](https://wordpress.org/support/plugin/ahm-ai-post-summary/)
- **GitHub Issues**: [Report Bugs](https://github.com/HeinMynn/ahm-ai-post-summary/issues)
- **Documentation**: [Plugin Docs](https://github.com/HeinMynn/ahm-ai-post-summary/wiki)

---

⭐ **Star this repo** if you find it useful!
