# ACF REST API Setup Guide

## Problem
When testing the API connection, you may see errors like:
- "No ACF fields found"
- "Failed to connect to API"
- Empty or missing custom field data

## Solution

### 1. Enable ACF REST API on Source Site

The source WordPress site needs to expose ACF fields through the REST API.

#### Option A: Using ACF Settings (ACF 5.9+)
1. Go to **ACF → Field Groups** on the source site
2. Edit each field group
3. Under "Settings" → find "Show in REST API"
4. Enable: **"Show in REST API"**
5. Save the field group

#### Option B: Add to functions.php (All ACF versions)
Add this to the source site's `functions.php`:

```php
// Enable ACF REST API for all field groups
add_filter('acf/rest_api/field_settings/show_in_response', '__return_true');

// Or enable for specific field groups
add_filter('acf/settings/rest_api_enabled', function() {
    return true;
});

// Add ACF to REST API endpoints
add_action('rest_api_init', function() {
    register_rest_field('post', 'acf', array(
        'get_callback' => function($post) {
            return get_fields($post['id']);
        },
        'schema' => null,
    ));
});
```

#### Option C: Install ACF to REST API Plugin
1. Install "ACF to REST API" plugin on source site
2. Activate it
3. All ACF fields will automatically appear in REST API

### 2. Verify ACF Data in REST API

Test if ACF fields are accessible by visiting:

```
https://your-source-site.com/wp-json/wp/v2/posts/[POST_ID]?acf_format=standard
```

You should see an `acf` object in the response with all custom fields.

**Example Response:**
```json
{
  "id": 12345,
  "title": {
    "rendered": "Post Title"
  },
  "acf": {
    "long_post_title": "Full Title Here",
    "short_details": "Short description",
    "important_dates": "<p>Date info</p>",
    "application_fee": "500",
    "total_post": "100",
    "age_limit_for": "as on 01/01/2025",
    "age_limit_details": "<p>Age details</p>",
    "vacancy_details": "<p>Vacancy info</p>",
    "important_links": "<p>Links</p>"
  }
}
```

### 3. Test Connection in WordPress Admin

1. Go to **Content Sync → Field Mapping**
2. Enter a test Post ID from your source site
3. Click **"Fetch Post"**
4. Check the output:
   - **ACF Fields Found** - Should show all ACF fields
   - **Prepared JSON** - Shows what will be sent to ChatGPT
   - Should see your custom fields mapped correctly

### 4. Common Issues & Solutions

#### Issue: "No ACF fields found"
**Solution:**
- ACF REST API is not enabled on source site
- Follow steps in Section 1
- Verify with Section 2

#### Issue: "Failed to connect to API"
**Solution:**
- Check API Base URL in **Content Sync → Settings**
- Should be: `https://your-source-site.com/wp-json/wp/v2`
- Test the URL in browser - should return JSON

#### Issue: "API returned error code: 403"
**Solution:**
- Source site is blocking REST API requests
- Add this to source site's `functions.php`:
```php
add_filter('rest_authentication_errors', function($result) {
    if (is_wp_error($result)) {
        return null;
    }
    return $result;
});
```

#### Issue: "API returned error code: 404"
**Solution:**
- Post ID doesn't exist
- Permalinks need to be refreshed on source site
- Go to **Settings → Permalinks** and click Save

#### Issue: ACF fields are empty or null
**Solution:**
- Fields have no data in that post
- Check the actual post on source site
- Try a different post ID with populated fields

### 5. Field Mapping Configuration

Once ACF REST API is working, map your fields:

| Your Custom Field | Source ACF Field | Description |
|------------------|-----------------|-------------|
| title | `long_post_title` | Post title |
| excerpt | `short_details` | Short description |
| dates | `important_dates` | Important dates |
| fees | `application_fee` | Fee information |
| total_posts | `total_post` | Number of posts |
| age_limit_as_on | `age_limit_for` | Age limit date |
| age_limit_details | `age_limit_details` | Age details |
| vacancy | `vacancy_details` | Vacancy info |
| links | `important_links` | Important links |

### 6. Testing Workflow

1. **Test Single Post First**
   - Use Field Mapping page
   - Fetch a post with known ACF data
   - Verify all fields appear correctly

2. **Check Prepared JSON**
   - Should show clean, structured data
   - Arrays for multi-line content
   - No HTML tags (just plain text)

3. **Test ChatGPT Processing** (if enabled)
   - Enable in settings
   - Add OpenAI API key
   - Update prompt files
   - Test with a post
   - Check both mapped fields and full JSON

4. **Run Manual Sync**
   - Go to Content Sync → Settings
   - Click "Run Manual Sync Now"
   - Check sync results

## Need More Help?

### Enable Debug Logging
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

### View JavaScript Console
1. Open browser DevTools (F12)
2. Go to Console tab
3. Test API connection
4. Look for errors or API responses

### Test API Manually
Use a tool like Postman or curl:
```bash
curl "https://your-source-site.com/wp-json/wp/v2/posts/12345?acf_format=standard"
```

## Quick Reference

### API Endpoints Used
- List posts: `/wp-json/wp/v2/posts?acf_format=standard&_embed=1`
- Single post: `/wp-json/wp/v2/posts/[ID]?acf_format=standard&_embed=1`
- Categories: `/wp-json/wp/v2/categories?per_page=100`

### Required Parameters
- `acf_format=standard` - Returns ACF fields
- `_embed=1` - Includes featured images and other embedded data

### Custom Fields Stored
All synced posts store:
- `kiosk_source_post_id` - Original post ID
- `kiosk_overview` - Overview text
- `kiosk_important_dates` - Date information
- `kiosk_eligibility` - Eligibility criteria
- `kiosk_required_documents` - Documents needed
- `kiosk_apply_link` - Application URL
- `kiosk_notification_pdf` - PDF URL
- `kiosk_form_instructions` - Instructions
- `kiosk_chatgpt_json` - Full ChatGPT response (if enabled)
