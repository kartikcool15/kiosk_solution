<?php

/**
 * Education Taxonomy Archive Template
 * Displays posts filtered by education taxonomy
 */

get_header(); ?>
<?php 
// Get current education term
$current_term = get_queried_object();
$term_slug = $current_term->slug;
$term_name = $current_term->name;

// Get current page number for pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$posts_per_page = 20; // Number of posts per page

// Fetch ALL posts with this education term (no pagination limit)
$all_posts_query = new WP_Query(array(
    'tax_query' => array(
        array(
            'taxonomy' => 'education',
            'field' => 'slug',
            'terms' => $term_slug,
        ),
    ),
    'posts_per_page' => -1, // Get all posts
    'post_status' => 'publish'
));

if ($all_posts_query->have_posts()) :
    // Collect all posts with their data for sorting
    $posts_data = array();
    while ($all_posts_query->have_posts()) : $all_posts_query->the_post();
        // Get the JSON data
        $post_id = get_the_ID();
        $json_data = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
        $data = json_decode($json_data, true);
        $post_title = !empty($data['post_title']) ? $data['post_title'] : get_the_title($post_id);

        $organization = !empty($data['organization']) ? $data['organization'] : 'N/A';

        // Handle dates - could be object from ChatGPT or array of objects or string from fallback
        $dates_obj = isset($data['dates']) ? $data['dates'] : array();
        $start_date = 'N/A';
        $last_date = 'N/A';
        $admit_card_date = 'N/A';
        $result_date = 'N/A';
        $next_date = 'N/A';

        if (is_array($dates_obj) && !empty($dates_obj)) {
            // Check if it's an array of date objects with 'event' and 'date' keys
            if (isset($dates_obj[0]) && is_array($dates_obj[0]) && isset($dates_obj[0]['event'])) {
                foreach ($dates_obj as $date_item) {
                    $event_lower = strtolower($date_item['event']);
                    if ((strpos($event_lower, 'start') !== false || strpos($event_lower, 'begin') !== false) && $start_date === 'N/A') {
                        $start_date = $date_item['date'];
                    } elseif ((strpos($event_lower, 'last') !== false || strpos($event_lower, 'end') !== false || strpos($event_lower, 'closing') !== false) && $last_date === 'N/A') {
                        $last_date = $date_item['date'];
                    } elseif ((strpos($event_lower, 'admit') !== false || strpos($event_lower, 'hall ticket') !== false) && $admit_card_date === 'N/A') {
                        $admit_card_date = $date_item['date'];
                    } elseif ((strpos($event_lower, 'result') !== false || strpos($event_lower, 'declaration') !== false) && $result_date === 'N/A') {
                        $result_date = $date_item['date'];
                    } elseif ((strpos($event_lower, 'counsel') !== false || strpos($event_lower, 'interview') !== false || strpos($event_lower, 'next') !== false) && $next_date === 'N/A') {
                        $next_date = $date_item['date'];
                    }
                }
            }
            // Or ChatGPT format - dates as associative array
            elseif (isset($dates_obj['start_date']) || isset($dates_obj['last_date']) || isset($dates_obj['admit_card_date']) || isset($dates_obj['result_date'])) {
                $start_date = !empty($dates_obj['start_date']) ? $dates_obj['start_date'] : 'N/A';
                $last_date = !empty($dates_obj['last_date']) ? $dates_obj['last_date'] : 'N/A';
                $admit_card_date = !empty($dates_obj['admit_card_date']) ? $dates_obj['admit_card_date'] : 'N/A';
                $result_date = !empty($dates_obj['result_date']) ? $dates_obj['result_date'] : 'N/A';

                // Check for counselling or similar next dates
                if (!empty($dates_obj['counselling_date'])) {
                    $next_date = $dates_obj['counselling_date'];
                } elseif (!empty($dates_obj['interview_date'])) {
                    $next_date = $dates_obj['interview_date'];
                } elseif (!empty($dates_obj['next_date'])) {
                    $next_date = $dates_obj['next_date'];
                }
            }
        } elseif (is_string($dates_obj) && !empty($dates_obj)) {
            // Fallback format - dates is a string
            $start_date = $dates_obj;
        }

        $category = get_the_category();
        $category_name = !empty($category) ? $category[0]->name : 'Uncategorized';
        $category_slug = !empty($category) ? $category[0]->slug : '';
        
        // Calculate Active Status for jobs
        $active_status = '';
        $status_class = '';
        $status_priority = 4; // Default priority (no status)
        
        if ($category_slug !== 'admit-card' && $category_slug !== 'result') {
            $current_time = current_time('timestamp');
            $start_timestamp = ($start_date !== 'N/A') ? strtotime($start_date) : false;
            $last_timestamp = ($last_date !== 'N/A') ? strtotime($last_date) : false;
            
            if ($start_timestamp && $start_timestamp > $current_time) {
                // Start date is in the future
                $active_status = 'Upcoming';
                $status_class = 'status-upcoming';
                $status_priority = 2;
            } elseif ($start_timestamp && ($current_time - $start_timestamp) <= 7 * 24 * 60 * 60) {
                // Start date is within the past week
                $active_status = 'New';
                $status_class = 'status-new';
                $status_priority = 3;
            } elseif ($last_timestamp && ($last_timestamp - $current_time) <= 7 * 24 * 60 * 60 && $last_timestamp >= $current_time) {
                // Last date is within 1 week from now
                $active_status = 'Ending Soon';
                $status_class = 'status-ending';
                $status_priority = 1; // Highest priority
            }
        }
        
        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'start_date' => $start_date,
            'last_date' => $last_date,
            'admit_card_date' => $admit_card_date,
            'result_date' => $result_date,
            'next_date' => $next_date,
            'category_name' => $category_name,
            'category_slug' => $category_slug,
            'active_status' => $active_status,
            'status_class' => $status_class,
            'status_priority' => $status_priority,
            'last_timestamp' => $last_timestamp,
            'start_timestamp' => $start_timestamp
        );
    endwhile;
    
    // Reset post data
    wp_reset_postdata();
    
    // Sort posts by status priority and date
    // Ending Soon: sorted by last_date (soonest first)
    // Upcoming: sorted by start_date (soonest first)
    // New: sorted by start_date (most recent first)
    usort($posts_data, function($a, $b) {
        // First, sort by status priority
        if ($a['status_priority'] != $b['status_priority']) {
            return $a['status_priority'] - $b['status_priority'];
        }
        
        // Within same priority, sort by date
        if ($a['status_priority'] == 1) {
            // Ending Soon - sort by last_date ascending (soonest first)
            $date_a = $a['last_timestamp'] ?: PHP_INT_MAX;
            $date_b = $b['last_timestamp'] ?: PHP_INT_MAX;
            return $date_a - $date_b;
        } elseif ($a['status_priority'] == 2) {
            // Upcoming - sort by start_date ascending (soonest first)
            $date_a = $a['start_timestamp'] ?: PHP_INT_MAX;
            $date_b = $b['start_timestamp'] ?: PHP_INT_MAX;
            return $date_a - $date_b;
        } elseif ($a['status_priority'] == 3) {
            // New - sort by start_date descending (most recent first)
            $date_a = $a['start_timestamp'] ?: 0;
            $date_b = $b['start_timestamp'] ?: 0;
            return $date_b - $date_a;
        }
        
        return 0;
    });
    
    // Calculate pagination
    $total_posts = count($posts_data);
    $total_pages = ceil($total_posts / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $current_page_posts = array_slice($posts_data, $offset, $posts_per_page);
    ?>
    
    <header class="page-header">
        <h1 class="page-title">Jobs for <?php echo esc_html($term_name); ?></h1>
        <p class="page-description">
            <?php echo $total_posts; ?> jobs found
        </p>
    </header>
    
    <div class="main-content-wrapper">
        <div class="posts-table-wrapper">
            <div class="posts-table">
                <!-- Table Header -->
                <div class="table-header">
                    <div class="th-cell th-title">Title</div>
                    <div class="th-cell th-organization">Organization</div>
                    <div class="th-cell th-category">Category</div>
                    <div class="th-cell th-start">Start Date</div>
                    <div class="th-cell th-last">Last Date</div>
                    <div class="th-cell th-status">Active Status</div>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php
                // Display sorted posts for current page
                foreach ($current_page_posts as $post_item) :
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $start_date = $post_item['start_date'];
                    $last_date = $post_item['last_date'];
                    $category_name = $post_item['category_name'];
                    $category_slug = $post_item['category_slug'];
                    $active_status = $post_item['active_status'];
                    $status_class = $post_item['status_class'];
                ?>
                    <div class="table-row">
                        <div class="td-cell td-title" data-label="Title">
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="post-title-link">
                                <?php echo esc_html($post_title); ?>
                            </a>
                        </div>

                        <div class="td-cell td-organization" data-label="Organization">
                            <?php echo esc_html($organization); ?>
                        </div>
                        
                        <div class="td-cell td-category" data-label="Category">
                            <span class="category-badge">
                                <?php echo esc_html($category_name); ?>
                            </span>
                        </div>

                        <div class="td-cell td-start" data-label="Start Date">
                            <?php echo esc_html($start_date); ?>
                        </div>

                        <div class="td-cell td-last" data-label="Last Date">
                            <span class="date-highlight"><?php echo esc_html($last_date); ?></span>
                        </div>
                        
                        <div class="td-cell td-status" data-label="Active Status">
                            <?php if (!empty($active_status)): ?>
                                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($active_status); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="td-cell td-action" data-label="Action">
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="btn-view">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    // Custom Pagination based on sorted posts
    if ($total_pages > 1) :
        $pagination = paginate_links(array(
            'base' => get_pagenum_link(1) . '%_%',
            'format' => 'page/%#%/',
            'current' => $paged,
            'total' => $total_pages,
            'prev_text' => '← Previous',
            'next_text' => 'Next →',
            'type' => 'array'
        ));
        
        if ($pagination) : ?>
            <div class="pagination-wrapper">
                <?php foreach ($pagination as $page) : ?>
                    <?php echo $page; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php else : ?>
    <header class="page-header">
        <h1 class="page-title">Jobs for <?php echo esc_html($term_name); ?></h1>
    </header>
    <div class="no-posts">
        <p>No jobs found for this education level</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
