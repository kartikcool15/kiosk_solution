# Post Update from Source API - Complete Analysis

## Overview of Update Flow

When you click "🔄 Update from Source" on a post, here's the complete flow:

### 1. **Initial Trigger** (admin-columns.php)
- AJAX handler: `update_individual_post_ajax()` at line 209
- Fetches the source post ID from post meta: `kiosk_source_post_id`
- Makes API call to source with `_embed=1` and `acf_format=standard`

### 2. **API Data Fetching**
```php
$url = $api_base_url . '/posts/' . $source_id . '?_embed=1&acf_format=standard'
```
- Fetches complete post data including:
  - Basic fields (title, content, excerpt, date, modified_date)
  - Categories
  - Featured image (via `_embedded['wp:featuredmedia']`)
  - **All ACF custom fields** in `post_data['acf']` array

### 3. **JSON Preparation** (post-processor.php - Line 266)
The `prepare_post_json()` method creates a cleaned JSON for ChatGPT:
```php
{
    "id": "123",
    "post_date": "2026-03-03...",
    "source_link": "https://...",
    "title": "Post Title",
    "acf_fields": {
        // ALL ACF fields from source are included here
        "post_title": "...",
        "education": "...",
        "age_eligibility": "...",
        "links": {...},
        "dates": {...},
        "fees": {...},
        // etc.
    }
}
```

### 4. **Post Update** (post-processor.php - Line 119)
The `update_post()` method:

#### ✅ WHAT IT UPDATES:
- Post title (uses ACF `post_title` if available, else default title)
- Post content
- Post excerpt  
- Categories (mapped from source to local)
- Featured image (downloads and attaches)
- Post status → set to `draft` (for re-processing)
- Meta: `kiosk_raw_post_data` → stores prepared JSON
- Meta: `kiosk_processing_status` → set to `pending`
- Meta: `kiosk_source_modified_gmt` → updated timestamp

#### ❌ WHAT IT DOES NOT UPDATE:
- **Custom taxonomies** (education, organization) - not synced here
- **ACF custom fields** - not directly synced to ACF fields
- **Custom meta fields** (dates, etc.) - not synced here
- Tags
- Author
- Post format

#### 🔄 Modification Check Logic:
```php
// Compares source modified_gmt with last synced timestamp
if ($source_time <= $synced_time) {
    return false; // Skip update - no changes
}
```

### 5. **ChatGPT Processing** (chatgpt-processor.php)
Scheduled via cron after update (`wp_schedule_single_event` + 60 seconds)

#### Process Flow:
1. **Fetches** posts with `kiosk_processing_status = 'pending'`
2. **Sends** the prepared JSON to ChatGPT API
3. **Receives** structured JSON response with:
   ```json
   {
       "post_title": "Cleaned Title",
       "post_content_summary": "Generated summary",
       "organization": "Organization Name",
       "education": ["10th", "12th", "Graduate"],
       "dates": {
           "start_date": "...",
           "last_date": "...",
           "exam_date": "...",
           // etc.
       },
       "fees": [...],
       "links": {...},
       "faqs": [...],
       // etc.
   }
   ```
4. **Applies data** via `apply_chatgpt_data_to_post()` (Line 149):
   - Updates post title & content (from ChatGPT)
   - Sets **organization taxonomy** (via `wp_set_object_terms`)
   - Sets **education taxonomy** (only for 'latest-job' category)
   - Syncs dates to custom meta fields (if empty)
   - Stores complete ChatGPT JSON in meta: `kiosk_chatgpt_json`
5. **Publishes** the post via `wp_publish_post()`

---

## ISSUES & MISSING SYNC

### 🔴 Issue #1: ACF Fields Not Directly Synced
**Problem:** The source post has ACF fields, but they're NOT synced to local ACF fields during update.

**Current Flow:**
```
Source ACF → Prepared JSON → ChatGPT → ChatGPT JSON → Custom Meta
```

**What's Missing:**
- ACF fields from source are sent to ChatGPT but NOT stored as ACF fields locally
- All data is processed through ChatGPT and stored in `kiosk_chatgpt_json` meta
- The site reads from ChatGPT JSON, not from ACF fields

**Impact:**
- If you edit ACF fields manually in WordPress, they'll be overwritten on next update
- ACF field data only exists in ChatGPT JSON format, not as proper ACF fields

### 🔴 Issue #2: Date Fields Only Update If Empty
**Code:** chatgpt-processor.php, Line 485-512
```php
// Only updates if field doesn't already exist
if (!get_post_meta($post_id, 'kiosk_start_date', true) && !empty($dates['start_date'])) {
    update_post_meta($post_id, 'kiosk_start_date', $dates['start_date']);
}
```

**Problem:**
- Date fields won't update if they already have a value
- This protects manual edits BUT prevents syncing updated dates from source

**Impact:**
- If source post's dates change, local post won't reflect the update

### 🔴 Issue #3: Taxonomies Only Set After ChatGPT Processing
**Problem:**
- Categories are synced during initial update
- But `education` and `organization` taxonomies are ONLY set during ChatGPT processing
- This means taxonomies depend on ChatGPT extracting them from the source data

**Code Flow:**
1. Update: Categories synced ✅
2. ChatGPT: `set_post_organization()` (Line 312) ✅
3. ChatGPT: `set_post_education()` (Line 348, only for latest-job) ✅

**Impact:**
- If ChatGPT processing fails, taxonomies won't be set
- Education taxonomy only works for 'latest-job' category

### 🔴 Issue #4: No Direct ACF to ACF Mapping
**Problem:**
- Source has ACF fields like:
  - `education`
  - `age_eligibility`
  - `links` (array)
  - `dates` (array)
  - `fees` (array)
  - `eligibility_post_wise` (array)
  - etc.
- These are NOT mapped to local ACF fields
- Everything goes through ChatGPT transformation

**Missing:**
```php
// This doesn't exist in update_post():
if (isset($post_data['acf']['education'])) {
    update_field('education', $post_data['acf']['education'], $post_id);
}
```

### 🔴 Issue #5: Update Doesn't Clear Old Data
**What's Cleared:**
- `kiosk_chatgpt_json` is deleted (Line 176) ✅

**What's NOT Cleared:**
- Old date custom fields
- Old taxonomy terms
- Old ACF fields (if they existed)

**Impact:**
- Stale data might persist across updates

---

## RECOMMENDATIONS

### Fix #1: Add ACF Field Mapping (If you want ACF fields)
```php
// In post-processor.php, update_post() method, after line 170:

// Sync ACF fields directly from source
if (isset($post_data['acf']) && is_array($post_data['acf'])) {
    foreach ($post_data['acf'] as $field_key => $field_value) {
        if (!empty($field_value)) {
            update_field($field_key, $field_value, $post_id);
        }
    }
}
```

### Fix #2: Force Update Date Fields
```php
// In chatgpt-processor.php, sync_dates_from_json(), change all checks:
// OLD:
if (!get_post_meta($post_id, 'kiosk_start_date', true) && !empty($dates['start_date']))

// NEW:
if (!empty($dates['start_date']))
```

### Fix #3: Clear All Related Data on Update
```php
// In post-processor.php, update_post(), add before line 170:

// Clear all date fields for fresh sync
$date_fields = ['kiosk_start_date', 'kiosk_last_date', 'kiosk_exam_date', 
                'kiosk_admit_card_date', 'kiosk_result_date', 
                'kiosk_counselling_date', 'kiosk_interview_date'];
foreach ($date_fields as $field) {
    delete_post_meta($post_id, $field);
}

// Clear taxonomies for fresh assignment
wp_set_object_terms($post_id, array(), 'organization', false);
wp_set_object_terms($post_id, array(), 'education', false);
```

### Fix #4: Add Logging for Debugging
```php
// Add after update completion in admin-columns.php:
error_log(sprintf(
    'Post %d updated from source %d. Categories: %s, Image: %s',
    $post_id,
    $source_id,
    json_encode($post_data['categories']),
    isset($post_data['_embedded']['wp:featuredmedia'][0]) ? 'Yes' : 'No'
));
```

---

## CURRENT SYNC STATUS SUMMARY

| Data Type | Initial Create | Manual Update | Auto Sync Mode |
|-----------|---------------|---------------|----------------|
| Title | ✅ From ACF or default | ✅ Via ChatGPT | ✅ Via ChatGPT |
| Content | ✅ From API | ✅ Via ChatGPT | ✅ Via ChatGPT |
| Excerpt | ✅ From API | ✅ From API | ✅ From API |
| Categories | ✅ Mapped | ✅ Mapped | ✅ Mapped |
| Featured Image | ✅ Downloaded | ✅ Downloaded | ✅ Downloaded |
| Organization Tax | ❌ | ✅ Via ChatGPT | ✅ Via ChatGPT |
| Education Tax | ❌ | ✅ Via ChatGPT (jobs only) | ✅ Via ChatGPT |
| Date Meta Fields | ❌ | ✅ Via ChatGPT (if empty) | ⚠️ Won't update if exists |
| ACF Fields | ❌ | ❌ Not synced | ❌ Not synced |
| ChatGPT JSON | ❌ | ✅ Generated | ✅ Regenerated |
| Raw Source Data | ✅ Stored | ✅ Updated | ✅ Updated |

---

## VERIFICATION STEPS

To check if sync is working:

1. **Check modification timestamps:**
   ```php
   // In WordPress debug log:
   get_post_meta($post_id, 'kiosk_source_modified_gmt', true);
   ```

2. **Check if ChatGPT processing happened:**
   ```php
   get_post_meta($post_id, 'kiosk_processing_status', true); // Should be 'completed'
   get_post_meta($post_id, 'kiosk_chatgpt_json', true); // Should have JSON data
   ```

3. **Check taxonomies:**
   ```php
   get_the_terms($post_id, 'organization');
   get_the_terms($post_id, 'education');
   ```

4. **Check raw source data:**
   ```php
   $raw = get_post_meta($post_id, 'kiosk_raw_post_data', true);
   $data = json_decode($raw, true);
   print_r($data['acf_fields']); // See what was prepared for ChatGPT
   ```

---

## CONCLUSION

**The update system works in 2 stages:**
1. **Direct Update** - Basic fields like title, content, categories, image
2. **ChatGPT Processing** - Advanced fields like taxonomies, dates, structured data

**Main Limitation:** 
- ACF fields from source are processed through ChatGPT but NOT stored as ACF fields
- Everything relies on ChatGPT processing
- The system stores processed data in `kiosk_chatgpt_json` meta, not in ACF fields

**If you need true ACF field syncing**, you'll need to implement direct ACF-to-ACF mapping in the `update_post()` method.
