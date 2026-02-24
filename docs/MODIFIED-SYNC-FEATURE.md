# Modified Date Sync Feature

## Overview
The content automation system now uses WordPress REST API's `modified_after` parameter to efficiently sync only new or updated posts from the external source.

## How It Works

### 1. **First Sync**
- Fetches posts normally without any date filter
- Creates new posts locally
- Stores sync timestamp in ISO 8601 format

### 2. **Subsequent Syncs**
- Uses the last successful sync timestamp
- API endpoint: `wp-json/wp/v2/posts?modified_after=2026-02-24T00:00:00`
- Only fetches posts that were created or modified after the last sync

### 3. **Update Detection**
- When a post already exists (matched by `source_post_id`)
- **OLD behavior**: Skip the post
- **NEW behavior**: Update it with fresh data from the API
- This catches old posts that were updated with new links (admit_card, result, etc.)

## Benefits

1. **Efficiency**: Only fetches changed content, reducing API calls
2. **Updates**: Catches modifications to existing posts (new links, dates, etc.)
3. **Bandwidth**: Significantly less data transfer
4. **Speed**: Faster sync operations

## API Parameters

### Fetch Posts Function
```php
fetch_posts_from_api($page, $per_page, $categories, $modified_after)
```

**New Parameter:**
- `$modified_after` (string): ISO 8601 timestamp (e.g., "2026-02-24T14:30:00")

### Example API Request
```
https://sarkariresult.com.cm/wp-json/wp/v2/posts?
    page=1
    &per_page=10
    &_embed=1
    &acf_format=standard
    &modified_after=2026-02-24T00:00:00
```

## Stored Data

### Last Sync Option: `kiosk_last_sync`
```php
array(
    'time' => '2026-02-24 14:30:00',        // MySQL format for display
    'timestamp_iso' => '2026-02-24T14:30:00+00:00', // ISO 8601 for API
    'imported' => 5,                         // New posts created
    'updated' => 3,                          // Existing posts updated
    'skipped' => 0,                          // Posts unchanged (future use)
    'queued_for_chatgpt' => 8                // Total queued for processing
)
```

## Admin Display

The admin panel now shows:
- **Last Sync**: Timestamp of last successful sync
- **Posts Imported**: Count of new posts created
- **Posts Updated**: Count of existing posts refreshed with new data
- **Posts Skipped**: Count of unchanged posts

## Use Cases

### 1. New Links Added
A job post was published on Feb 20, but the admit card link was added on Feb 23:
- Feb 20: Post synced, no admit_card link
- Feb 23: Source post updated with admit_card link
- Next sync: Post detected as modified, updated locally with new link

### 2. Date Changes
Result date or exam date changed after initial publication:
- Original sync: exam_date = "2026-03-15"
- Source updated: exam_date = "2026-03-20"
- Next sync: Post updated with new date

### 3. Content Corrections
Source post had typos or incorrect information:
- Original sync: Post with errors
- Source corrected: Content fixed
- Next sync: Local post updated with corrections

## Technical Implementation

### 1. Update Existing Post Function
```php
update_existing_post($post_id, $post_data)
```
- Updates post title, content, excerpt
- Updates featured image if changed
- Refreshes all metadata
- Resets to draft for ChatGPT re-processing
- Clears old ChatGPT JSON to force fresh processing

### 2. Sync Flow
```
1. Get last sync timestamp (ISO format)
   ↓
2. Fetch posts with modified_after filter
   ↓
3. For each post:
   - Check if exists (by source_post_id)
   - If exists: UPDATE
   - If new: CREATE
   ↓
4. Queue all for ChatGPT processing
   ↓
5. Store new sync timestamp
```

## Configuration

No additional configuration needed. The feature works automatically with existing settings.

## Backwards Compatibility

- First run after upgrade will fetch all posts (no timestamp stored yet)
- Subsequent runs will use the modified_after filter
- Existing posts will be updated if they changed on source

## Cron Schedule

The automated sync runs based on your configured schedule:
- Every 15 minutes (default)
- Every 30 minutes
- Hourly
- Twice daily
- Daily

## Manual Sync

The "Run Manual Sync Now" button also uses the modified_after feature:
- Uses last successful sync timestamp
- Updates both new and modified posts
- Shows results in admin notice

## Troubleshooting

### No Updates Detected
- Check source site modified dates
- Verify API is returning modified_after correctly
- Test: `wp-json/wp/v2/posts?modified_after=YYYY-MM-DDTHH:MM:SS`

### Posts Not Updating
- Ensure source post has newer modified date
- Check `kiosk_source_post_id` meta field exists
- Verify API connection is working

### Reset Sync Timestamp
To force a full re-sync, delete the option:
```php
delete_option('kiosk_last_sync');
```

## External API Requirements

The external WordPress site must:
1. Have WordPress REST API enabled
2. Support `modified_after` parameter (WP 4.7+)
3. Have ACF REST API enabled (if using ACF fields)
4. Allow cross-origin requests (CORS)

## Performance

### Before (without modified_after)
- Fetches 10 posts every sync
- 9 skipped (already exist)
- 1 new imported
- API returns 10 full posts with embedded data

### After (with modified_after)
- Fetches only 1 modified post
- 0 skipped
- 1 imported/updated
- API returns 1 full post
- **90% reduction in data transfer**

## Future Enhancements

Possible improvements:
1. Pagination support for modified posts (if >per_page modified)
2. Track source post modified_date in meta
3. Compare local vs source modified date before updating
4. Selective field updates (only changed fields)
5. Webhook support for instant updates
