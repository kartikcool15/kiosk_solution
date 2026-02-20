# ChatGPT Integration Guide

## Overview
Your WordPress automation system now includes ChatGPT API integration to intelligently extract and structure content from fetched posts.

## How It Works

1. **Post Fetching** → API retrieves post with ACF fields
2. **JSON Preparation** → Post data cleaned and formatted as JSON
3. **ChatGPT Processing** → Sent to OpenAI with your custom prompts
4. **Structured Output** → ChatGPT returns normalized data
5. **Field Mapping** → Data saved to your custom fields

## Setup Instructions

### 1. Get OpenAI API Key
- Visit https://platform.openai.com/api-keys
- Create a new API key
- Copy the key (starts with `sk-...`)

### 2. Configure in WordPress Admin
Go to **Content Sync → Settings** and scroll to "🤖 ChatGPT Processing":

- ✅ **Enable ChatGPT Processing**: Turn on the toggle
- 🔑 **OpenAI API Key**: Paste your API key
- 🤖 **OpenAI Model**: Choose model (GPT-4o recommended)
- 📄 **Prompt Files**: Verify both files are found

Click **Save Settings**

### 3. Test the Integration
Go to **Content Sync → Field Mapping**:

1. Enter a post ID (e.g., `23270`)
2. Click **Fetch Post**
3. You'll see:
   - Standard post details
   - ACF fields found
   - **🤖 ChatGPT Extracted Fields** (if processing succeeds)

## Prompt Files

Located in `themes/kiosk/prompt/`:

### system-prompt.txt
Defines ChatGPT's role and rules:
- Acts as a structured data extraction engine
- Returns only valid JSON
- Normalizes dates, numbers, and text
- Removes HTML formatting

### user-prompt.txt
Defines the output structure:
- short_title, long_title
- post_content_summary
- dates (start_date, last_date, exam_date, etc.)
- age_eligibility, total_vacancy
- post_vacancy, eligibility_post_wise
- important_links

**Customize these files** to match your specific content structure.

## Field Mapping

ChatGPT output is automatically mapped to your custom fields:

| ChatGPT Field | → | Custom Field |
|---------------|---|--------------|
| post_content_summary | → | Overview |
| dates object | → | Important Dates |
| age_eligibility + eligibility_post_wise | → | Eligibility |
| total_vacancy + post_vacancy | → | Required Documents |
| important_links (apply) | → | Apply Link |
| important_links (pdf) | → | Notification PDF |
| important_links (all) | → | Form Instructions |

**Modify mapping** in `map_chatgpt_response()` function in `inc/content-automation.php`

## How Posts are Processed

### During Automatic Sync (Cron)
When automation is enabled:
1. System fetches posts from API
2. If ChatGPT is enabled → processes through ChatGPT
3. If ChatGPT fails or is disabled → uses ACF field extraction
4. Posts published with structured data

### During Test Fetch
In Field Mapping page:
1. Fetch any post by ID
2. See ACF fields raw data
3. See ChatGPT extracted and structured data
4. Compare results before going live

## API Costs

OpenAI charges per token used:

| Model | Input (per 1M tokens) | Output (per 1M tokens) |
|-------|-----------------------|------------------------|
| GPT-4o | $2.50 | $10.00 |
| GPT-4o Mini | $0.15 | $0.60 |
| GPT-4 Turbo | $10.00 | $30.00 |
| GPT-3.5 Turbo | $0.50 | $1.50 |

**Typical usage**: ~2,000-3,000 tokens per post (input+output)

**GPT-4o Mini recommended** for production (balance of cost and quality)

## Troubleshooting

### ⚠️ ChatGPT processing failed
**Possible causes:**
1. Invalid API key → Check key in settings
2. Insufficient credits → Add credits to OpenAI account
3. Prompt files missing → Verify files exist in `/prompt/` folder
4. API timeout → Try GPT-4o Mini (faster)
5. Invalid JSON format → Check prompt expects JSON output

**Check WordPress debug log** for detailed errors:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look in `wp-content/debug.log` for "kiosk Automation" entries

### Post processed but fields empty
- ChatGPT returned data that doesn't match expected structure
- Modify `user-prompt.txt` to better describe your data
- Adjust `map_chatgpt_response()` mapping logic

### API key not working
- Ensure key has no extra spaces
- Verify billing is set up in OpenAI account
- Check key hasn't been revoked
- Create new key if needed

## Customization Examples

### Example 1: Add Custom Field
Edit `user-prompt.txt`:
```json
{
  ...
  "application_fee": "",
  "exam_pattern": ""
}
```

Edit `map_chatgpt_response()`:
```php
if (isset($data['application_fee'])) {
    $fields['custom_field_name'] = sanitize_text_field($data['application_fee']);
}
```

### Example 2: Change Model Based on Post Type
Edit settings or add logic:
```php
$model = (strlen($content) > 5000) ? 'gpt-4o-mini' : 'gpt-4o';
```

### Example 3: Add Retry Logic
Edit `call_openai_api()`:
```php
$max_retries = 3;
for ($i = 0; $i < $max_retries; $i++) {
    $response = wp_remote_post(...);
    if (!is_wp_error($response)) break;
    sleep(2);
}
```

## Best Practices

1. **Test first**: Use Field Mapping page before enabling automation
2. **Start with GPT-4o Mini**: Cheaper, test prompt quality
3. **Monitor costs**: Check OpenAI dashboard regularly
4. **Optimize prompts**: Be specific about what data you need
5. **Handle failures**: System falls back to ACF extraction automatically
6. **Version control prompts**: Track changes to prompt files
7. **Use JSON mode**: Already enabled (`response_format: json_object`)

## System Requirements

- WordPress 5.0+
- PHP 7.4+
- wp_remote_post() enabled (cURL/fsockopen)
- OpenAI API access
- Internet connection for API calls

## Support

For issues or customization:
1. Check debug.log for error messages
2. Test API connection in Settings page
3. Verify prompt files exist and are readable
4. Test single post in Field Mapping before automation

---

**Status**: ✅ Fully integrated and ready to use
**Last Updated**: February 15, 2026

