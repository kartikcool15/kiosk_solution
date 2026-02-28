# Content Automation System - Refactored Structure

## Overview
The content automation system has been completely refactored into a modular structure with separate files for different responsibilities. The new system provides 4 distinct sync modes for better control over content synchronization.

## File Structure

### Content Automation Module (`/module/content-automation/`)
```
content-automation/
├── content-automation.php       # Main controller (loads all modules, handles AJAX)
├── api-fetcher.php              # API communication with external data sources
├── post-processor.php           # Post creation, updates, and data preparation
├── chatgpt-processor.php        # AI processing using OpenAI API
├── content-sync.php             # Sync controller with 4 sync modes
└── content-automation-old-backup.php  # Backup of old monolithic file
```

### Admin Module (`/module/admin/`)
```
admin/
├── admin-settings.php           # Admin panel settings page
├── admin-columns.php            # Custom columns, row actions, bulk actions
├── admin-scripts.js             # JavaScript for admin panel
└── admin-styles.css             # Styles for admin panel
```

## 4 Sync Modes Explained

### 1. 📥 Fetch Recently Created Posts
**Purpose:** Import only NEW posts created after the last sync timestamp

**When to use:**
- You want to import only brand new posts
- You don't want to update existing posts
- First-time setup or regular imports of new content

**How it works:**
- Fetches posts created after `kiosk_last_created_sync` timestamp
- Skips posts that already exist in your database
- Saves new timestamp for next run

**Timestamp tracked:** `kiosk_last_created_sync` option

---

### 2. 🔄 Fetch Recently Modified Posts
**Purpose:** Get posts that were modified OR created after last sync

**When to use:**
- You want to keep existing posts updated with changes from source
- You want to import new posts AND update existing ones
- Regular maintenance to catch all changes

**How it works:**
- Fetches posts modified after `kiosk_last_modified_sync` timestamp
- Updates existing posts if they already exist
- Creates new posts if they don't exist
- Saves new timestamp for next run

**Timestamp tracked:** `kiosk_last_modified_sync` option

---

### 3. 🔁 Resync Post Content
**Purpose:** Re-apply ChatGPT data to posts without making any API calls

**When to use:**
- You changed mapping logic (e.g., added organization taxonomy)
- You fixed bugs in slug generation
- You want to update titles/slugs from existing ChatGPT data
- You need to reapply dates or taxonomies

**How it works:**
- Loops through all posts with `kiosk_chatgpt_json`
- Re-applies titles, slugs, taxonomies, and dates from JSON
- NO API calls to source or ChatGPT
- Uses existing data only

**No API calls - Very fast!**

---

### 4. 🤖 Update All Posts
**Purpose:** Re-process all posts through ChatGPT using their raw JSON

**When to use:**
- You changed ChatGPT prompts
- You want fresh AI analysis of existing posts
- You need to update ChatGPT extracted data

**How it works:**
- Loops through all posts with `kiosk_raw_post_data`
- Marks them as `pending` for ChatGPT processing
- Clears old `kiosk_chatgpt_json`
- Background cron will process through OpenAI API

**WARNING:** This uses OpenAI API credits! Process runs in background.

---

## Module Responsibilities

### `api-fetcher.php`
- `fetch_posts()` - Fetch posts from external API with various filters
- `test_connection()` - Test API connectivity
- Handles timestamps, pagination, and category filtering

### `post-processor.php`
- `post_exists_by_source_id()` - Check if post already imported
- `create_post()` - Create new post from API data
- `update_post()` - Update existing post
- `prepare_post_json()` - Clean and prepare data for ChatGPT
- `map_categories()` - Map source categories to local
- `clean_and_parse_field()` - Clean HTML and extract links
- `download_and_attach_image()` - Handle featured images

### `chatgpt-processor.php`
- `process_queue()` - Background processing of pending posts
- `process_single_post()` - Process one post with ChatGPT
- `apply_chatgpt_data_to_post()` - Apply GPT data to post
- `process_with_api()` - Call OpenAI API
- `set_post_organization()` - Set organization taxonomy
- `set_post_education()` - Set education taxonomy
- `sync_dates_from_json()` - Sync dates to custom fields

### `content-sync.php`
- `fetch_recently_created()` - Sync Mode 1
- `fetch_recently_modified()` - Sync Mode 2
- `resync_post_content()` - Sync Mode 3
- `update_all_posts()` - Sync Mode 4
- `fetch_and_publish_content()` - Legacy method for cron

### `admin-columns.php`
- Custom "ChatGPT Status" column in posts list
- Row action: "Update from Source" for individual posts
- Bulk action: "Update from Source" for multiple posts
- Admin notices for bulk operations

## AJAX Endpoints

### New Sync Mode Endpoints
- `kiosk_sync_recent_created` - Sync Mode 1
- `kiosk_sync_recent_modified` - Sync Mode 2
- `kiosk_resync_content` - Sync Mode 3
- `kiosk_update_all_posts` - Sync Mode 4

### Legacy Endpoints (still supported)
- `kiosk_manual_sync` - Legacy sync (uses fetch_and_publish_content)
- `kiosk_force_full_sync` - Legacy full sync
- `kiosk_test_api_connection` - Test API
- `kiosk_fetch_single_post` - Fetch single post for testing
- `kiosk_process_chatgpt_now` - Manually trigger ChatGPT queue
- `kiosk_update_individual_post` - Update single post from row action
- `kiosk_manual_trigger_cron` - Admin bar cron trigger

## WordPress Options Used

### Timestamps
- `kiosk_last_created_sync` - Last "Fetch Recently Created" sync
- `kiosk_last_modified_sync` - Last "Fetch Recently Modified" sync
- `kiosk_last_sync` - Legacy sync timestamp
- `kiosk_last_chatgpt_processing` - Last ChatGPT processing stats

### Settings
- `kiosk_automation_settings` - All automation settings (API URL, API key, cron schedule, etc.)

## Custom Fields (Post Meta)

### Source Tracking
- `kiosk_source_post_id` - Source post ID from API
- `kiosk_raw_post_data` - Raw JSON for ChatGPT processing
- `kiosk_chatgpt_json` - Full ChatGPT processed JSON
- `kiosk_processing_status` - pending | processing | completed | failed

### Date Fields
- `kiosk_start_date` - Application start date
- `kiosk_last_date` - Application last date
- `kiosk_exam_date` - Exam date
- `kiosk_admit_card_date` - Admit card release date
- `kiosk_result_date` - Result date
- `kiosk_counselling_date` - Counselling date
- `kiosk_interview_date` - Interview date

## Cron Jobs

### Main Cron Hook
- `kiosk_fetch_content_cron` - Main sync cron (uses legacy fetch_and_publish_content)

### Background Processing
- `kiosk_process_chatgpt_queue` - Process pending ChatGPT posts (5 at a time)

## Admin Panel Updates

### New Sync Options Section
The admin panel now has a new "🔄 Content Sync Options" section with 4 large buttons:
1. **Fetch Recently Created** - Primary button (blue)
2. **Fetch Recently Modified** - Primary button (blue)
3. **Resync Post Content** - Secondary button
4. **Update All Posts** - Secondary button

### Utilities Section
- Test API Connection
- Legacy Manual Sync

### Helpful Guide
The admin panel includes a guide explaining when to use each sync mode.

## Best Practices

### For Regular Imports
Use **"Fetch Recently Created"** or **"Fetch Recently Modified"** depending on whether you want to update existing posts.

### After Changing Mapping Logic
Use **"Resync Post Content"** to re-apply your new logic without making API calls.

### After Changing Prompts
Use **"Update All Posts"** to get fresh ChatGPT analysis (uses API credits).

### For Testing
Use the **"Test API Connection"** button before running syncs.

## Migration Notes

### Backwards Compatibility
- All legacy functions are still supported
- Cron still works with the original hook
- Admin bar manual trigger still works
- All existing AJAX endpoints maintained

### What Changed
- Code split into modules for maintainability
- Added 4 distinct sync modes with separate timestamps
- Moved admin-specific code to admin folder
- All functionality preserved and enhanced

## Future Enhancements Ideas

1. **Selective category sync** - Sync only specific categories
2. **Post filtering** - Filter by date range, status, etc.
3. **Batch size control** - Control how many posts to process
4. **Error logging** - Better error tracking and reporting
5. **Progress indicators** - Real-time progress for large syncs
6. **Dry run mode** - Preview what would be synced without actually syncing

## Troubleshooting

### Posts not syncing?
1. Check API connection with "Test API Connection"
2. Verify automation is enabled in settings
3. Check cron status in admin panel
4. Look for timestamp conflicts

### ChatGPT not processing?
1. Verify OpenAI API key is set
2. Check "ChatGPT Processing" is enabled
3. Manually trigger with "Update All Posts" or admin panel button
4. Check post has `kiosk_processing_status` = 'pending'

### Timestamps not working?
Each sync mode has its own timestamp option. Clear the relevant option to reset:
- `kiosk_last_created_sync`
- `kiosk_last_modified_sync`

## Developer Notes

### Adding New Sync Mode
1. Add method to `content-sync.php`
2. Add AJAX handler to `content-automation.php`
3. Add button to admin panel
4. Add JavaScript handler to `admin-scripts.js`

### Extending Post Processor
All post creation/update logic is in `post-processor.php`. Extend or override methods there.

### Customizing ChatGPT Processing
All ChatGPT logic is in `chatgpt-processor.php`. Modify `apply_chatgpt_data_to_post()` to change how data is applied.

---

**Created:** February 28, 2026
**Last Updated:** February 28, 2026
**Version:** 2.0.0 (Modular Refactor)
