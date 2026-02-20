# Custom Field Mapping Configuration

## Overview
The system now uses custom field mappings to extract data from WordPress API ACF (Advanced Custom Fields) and prepare it for ChatGPT processing.

## Field Mappings

The following ACF fields from the source WordPress site are mapped and sent to ChatGPT:

| Local Key | Source ACF Field | Description |
|-----------|-----------------|-------------|
| `title` | `acf.long_post_title` | Full post title |
| `excerpt` | `acf.short_details` | Short summary/description |
| `dates` | `acf.important_dates` | Important dates information |
| `fees` | `acf.application_fee` | Application fee details |
| `total_posts` | `acf.total_post` | Total number of posts |
| `age_limit_as_on` | `acf.age_limit_for` | "As on" date extracted from content |
| `age_limit_details` | `acf.age_limit_details` | Age limit information |
| `vacancy` | `acf.vacancy_details` | Vacancy details |
| `links` | `acf.important_links` | Important links |

## Data Processing

### HTML Cleaning
- All HTML tags are stripped from field values
- HTML entities are decoded
- Plain text is returned

### Array Conversion
If a field contains multiple lines (separated by `\n`), the system automatically converts it to an array:

**Example:**
```
Input (HTML with line breaks):
<p>Post 1 - 10 vacancies</p>
<p>Post 2 - 20 vacancies</p>

Output (Array):
[
  "Post 1 - 10 vacancies",
  "Post 2 - 20 vacancies"
]
```

### "As On" Date Extraction
The system automatically extracts "as on" dates from the `age_limit_for` field or content using patterns like:
- "as on 01/01/2025"
- "as on 01-01-2025"

## JSON Structure Sent to ChatGPT

```json
{
  "id": 12345,
  "post_date": "2025-02-16T10:30:00",
  "source_link": "https://example.com/post/12345",
  "title": "Post Title",
  "excerpt": "Short description",
  "dates": [
    "Application Start: 01/02/2025",
    "Last Date: 28/02/2025"
  ],
  "fees": "500 for General, 250 for SC/ST",
  "total_posts": "100",
  "age_limit_as_on": "01/01/2025",
  "age_limit_details": [
    "Minimum: 18 years",
    "Maximum: 35 years"
  ],
  "vacancy": [
    "Post 1 - 50 vacancies",
    "Post 2 - 50 vacancies"
  ],
  "links": [
    "Apply Online: https://example.com/apply",
    "Notification PDF: https://example.com/notification.pdf"
  ]
}
```

## ChatGPT Response Storage

### Full JSON Storage
The complete ChatGPT JSON response is stored in the custom field: `kiosk_chatgpt_json`

This allows you to:
- Access the full AI-processed data later
- Re-process if needed
- Use for custom templates or exports

### Custom Fields Storage
The system also extracts and stores specific fields in individual meta keys:
- `kiosk_overview`
- `kiosk_important_dates`
- `kiosk_eligibility`
- `kiosk_required_documents`
- `kiosk_apply_link`
- `kiosk_notification_pdf`
- `kiosk_form_instructions`
- `kiosk_chatgpt_json` (NEW - Full ChatGPT response)

## Updating Your Prompts

### System Prompt (prompt/system-prompt.txt)
Update your system prompt to instruct ChatGPT on how to process the new JSON structure. Example:

```
You are a data extraction assistant. You will receive job/recruitment post data in JSON format.

Input JSON will contain:
- title: Post title
- excerpt: Short description
- dates: Important dates (may be string or array)
- fees: Application fee details
- total_posts: Total positions
- age_limit_as_on: The "as on" date for age calculation
- age_limit_details: Age limit information (may be array)
- vacancy: Vacancy details (may be array)
- links: Important links (may be array)

Your task is to analyze and structure this data into a comprehensive JSON following the required schema.
```

### User Prompt (prompt/user-prompt.txt)
Replace `[PASTE CLEANED JSON HERE]` with the actual data. Example:

```
Process the following recruitment post data and extract all relevant information:

[PASTE CLEANED JSON HERE]

Return a structured JSON with:
1. Complete post details
2. Important dates organized
3. Eligibility criteria
4. Vacancy breakdown
5. Fee structure
6. Application links
7. Any other relevant information

Ensure all data is properly structured and cleaned.
```

## Accessing Stored Data in Templates

### Get Full ChatGPT JSON
```php
$chatgpt_json = get_post_meta(get_the_ID(), 'kiosk_chatgpt_json', true);
$data = json_decode($chatgpt_json, true);
```

### Get Individual Fields
```php
$overview = get_post_meta(get_the_ID(), 'kiosk_overview', true);
$dates = get_post_meta(get_the_ID(), 'kiosk_important_dates', true);
$eligibility = get_post_meta(get_the_ID(), 'kiosk_eligibility', true);
```

## Testing

Use the **Field Mapping** page in WordPress admin to:
1. Enter a test post ID from the source site
2. Fetch the post to see the transformed JSON
3. View the ChatGPT processed results
4. Check both the mapped fields and full JSON response

## Notes

- If ACF fields are not available, the system falls back to standard WordPress fields (title, excerpt, content)
- Arrays are automatically created when multiple lines are detected
- Empty fields are returned as empty strings
- HTML is always stripped to ensure clean data for ChatGPT processing
