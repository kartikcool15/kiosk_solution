# Content Automation System - Documentation

## Overview
This WordPress automation system fetches content from an external source via REST API and automatically publishes it on your site with custom fields and category mapping.

## Features

### ✅ Custom Fields
The system automatically extracts and stores the following fields for each post:

1. **Overview** - `kiosk_overview`
2. **Important Dates** - `kiosk_important_dates`
3. **Eligibility** - `kiosk_eligibility`
4. **Required Documents** - `kiosk_required_documents`
5. **Direct Apply Link** - `kiosk_apply_link`
6. **Official Notification PDF** - `kiosk_notification_pdf`
7. **Form Filling Instructions** 🏆 - `kiosk_form_instructions`

### ✅ Category Mapping
Map source categories to your local WordPress categories to maintain proper organization.

### ✅ Automated Sync
Schedule automatic content fetching at intervals:
- Every 30 minutes
- Hourly
- Every 6 hours
- Every 12 hours
- Daily

### ✅ Duplicate Prevention
The system tracks already imported posts and skips duplicates automatically.

### ✅ Featured Images
Automatically downloads and sets featured images from source posts.

## Setup Instructions

### 1. Activate the Theme
Make sure the kiosk theme is activated.

### 2. Access Admin Panel
Go to **WordPress Admin → Content Sync**

### 3. Configure Settings

#### Enable Automation
1. Toggle "Enable Automation" to ON
2. Select your preferred sync schedule
3. Set the number of posts to fetch per sync (1-100)
4. Click "Save Settings"

#### Map Categories
1. Go to **Content Sync → Category Mapping**
2. Map each source category to your local categories
3. Click "Save Category Mapping"

### 4. Test Connection
Click "Test API Connection" to verify the API is accessible.

### 5. Run Manual Sync
Click "Run Manual Sync Now" to import the first batch of posts immediately.

## Using Custom Fields in Templates

### Get Individual Field
```php
$overview = get_post_meta(get_the_ID(), 'kiosk_overview', true);
if (!empty($overview)) {
    echo '<div>' . esc_html($overview) . '</div>';
}
```

### Get Apply Link
```php
$apply_link = get_post_meta(get_the_ID(), 'kiosk_apply_link', true);
if (!empty($apply_link)) {
    echo '<a href="' . esc_url($apply_link) . '">Apply Now</a>';
}
```

### Get Form Instructions (Gold Content)
```php
$instructions = get_post_meta(get_the_ID(), 'kiosk_form_instructions', true);
if (!empty($instructions)) {
    echo '<div class="instructions">' . wp_kses_post($instructions) . '</div>';
}
```

## Available Meta Keys

| Field Name | Meta Key | Type |
|------------|----------|------|
| Overview | `kiosk_overview` | String |
| Important Dates | `kiosk_important_dates` | HTML |
| Eligibility | `kiosk_eligibility` | HTML |
| Required Documents | `kiosk_required_documents` | HTML |
| Apply Link | `kiosk_apply_link` | URL |
| Notification PDF | `kiosk_notification_pdf` | URL |
| Form Instructions | `kiosk_form_instructions` | HTML |
| Source Post ID | `kiosk_source_post_id` | Integer |

## Admin Pages

### Content Sync (Main)
- Enable/disable automation
- Configure sync schedule
- Set posts per sync
- View sync status
- Test API connection
- Run manual sync

### Category Mapping
- Map source categories to local categories
- Ensure proper categorization of imported posts

## Cron Jobs

The system uses WordPress cron to schedule automatic syncs. The cron event name is:
```
kiosk_fetch_content_cron
```

To view active cron jobs, use a plugin like "WP Crontrol" or add this code:
```php
$crons = _get_cron_array();
print_r($crons);
```

## Troubleshooting

### Posts Not Syncing
1. Check if automation is enabled
2. Verify API connection with "Test Connection" button
3. Check WordPress cron is working (use WP-CLI: `wp cron event list`)
4. Review error logs in: `wp-content/debug.log`

### Duplicate Posts
The system automatically prevents duplicates by tracking source post IDs. If duplicates appear, they may be from before the system was installed.

### Missing Custom Fields
If custom fields aren't extracting properly:
1. Check the source post content structure
2. The regex patterns may need adjustment in `content-automation.php`
3. Contact developer for custom extraction rules

### Category Mapping Not Working
1. Ensure you've saved the category mapping
2. Verify source categories are being fetched (check Category Mapping page)
3. Clear transients: `delete_transient('kiosk_source_categories')`

## Files Structure

```
themes/kiosk/
├── functions.php                    # Main theme file
├── single.php                       # Single post template
├── sidebar.php                      # Sidebar template
├── style.scss                       # Theme styles (compile to CSS)
└── inc/
    ├── content-automation.php       # Core automation logic
    ├── admin-settings.php           # Admin panel interface
    ├── admin-styles.css            # Admin panel styles
    └── admin-scripts.js            # Admin panel JavaScript
```

## Important Notes

⚠️ **Performance**: Fetching content from external sources can be slow. Adjust "Posts Per Sync" based on your server capacity.

⚠️ **Legal**: Ensure you have proper permissions to republish content from external sources.

⚠️ **Attribution**: Consider adding attribution if required by the content source.

⚠️ **Backups**: Always backup your database before running bulk imports.

## Advanced Usage

### Modify Content Before Publishing
Edit the `fetch_and_publish_content()` method in `content-automation.php` to filter or modify content.

### Custom Field Extraction
The extraction patterns are in the `extract_custom_fields()` method. Adjust regex patterns to match your source content structure.

### Add More Custom Fields
1. Register additional fields in `register_custom_fields()`
2. Extract them in `extract_custom_fields()`
3. Save them in `fetch_and_publish_content()`
4. Display them in `single.php`

## Support

For issues or customizations, check:
1. WordPress error logs
2. Browser console (for admin panel issues)
3. API response format changes

## Version
Current Version: 1.0.0

