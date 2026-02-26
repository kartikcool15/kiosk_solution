<?php

/**
 * Latest Job Category Archive Template
 * Displays latest job posts with sorting by Active Status
 */

get_header(); ?>
<?php 
// Get current category
$current_category = get_queried_object();
$category_slug = $current_category->slug;

// Get current page number for pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$posts_per_page = 20; // Number of posts per page

// Fetch ALL posts from this category (no pagination limit)
$all_posts_query = new WP_Query(array(
    'cat' => $current_category->term_id,
    'posts_per_page' => -1, // Get all posts
    'post_status' => 'publish'
));

if ($all_posts_query->have_posts()) :
    // Collect all posts with their data for sorting
    $posts_data = array();
    while ($all_posts_query->have_posts()) : $all_posts_query->the_post();
        // Get post data
        $post_id = get_the_ID();
        $json_data = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
        $data = json_decode($json_data, true);
        $post_title = !empty($data['post_title']) ? $data['post_title'] : get_the_title($post_id);
        $organization = !empty($data['organization']) ? $data['organization'] : 'N/A';

        // Get dates - prioritize custom fields over JSON
        $dates = kiosk_get_post_dates($post_id);
        $start_date = $dates['start_date'];
        $last_date = $dates['last_date'];

        // Calculate Active Status for jobs
        $active_status = '';
        $status_class = '';
        $status_priority = 6; // Default priority (no status)
        
        // Get today's date at midnight for proper date comparison
        $current_time = current_time('timestamp');
        $today_start = strtotime('today', $current_time);
        $tomorrow_start = strtotime('tomorrow', $current_time);
        
        $start_timestamp = ($start_date !== 'N/A') ? strtotime($start_date) : false;
        $last_timestamp = ($last_date !== 'N/A') ? strtotime($last_date) : false;
        
        if ($last_timestamp && $last_timestamp < $today_start) {
            // Last date was before today (application closed)
            $active_status = 'Application Closed';
            $status_class = 'status-completed';
            $status_priority = 5; // Lowest priority
        } elseif ($start_timestamp && $start_timestamp >= $today_start && $start_timestamp < $tomorrow_start) {
            // Start date is today (new)
            $active_status = 'New';
            $status_class = 'status-new';
            $status_priority = 1; // Highest priority
        } elseif ($last_timestamp && $last_timestamp >= $today_start && $last_timestamp < $tomorrow_start) {
            // Last date is today only (ending soon)
            $active_status = 'Ending Soon';
            $status_class = 'status-ending';
            $status_priority = 2;
        } elseif ($start_timestamp && $start_timestamp >= $tomorrow_start) {
            // Start date is tomorrow or later (upcoming)
            $active_status = 'Upcoming';
            $status_class = 'status-upcoming';
            $status_priority = 3;
        } elseif ($start_timestamp && $start_timestamp < $today_start && $last_timestamp && $last_timestamp >= $today_start) {
            // Start date has passed and last date is today or in future (ongoing)
            $active_status = 'Ongoing';
            $status_class = 'status-ongoing';
            $status_priority = 4;
        }
        
        // Get modified date for sorting
        $modified_timestamp = get_post_modified_time('U', false, $post_id);
        
        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'start_date' => $start_date,
            'last_date' => $last_date,
            'active_status' => $active_status,
            'status_class' => $status_class,
            'status_priority' => $status_priority,
            'last_timestamp' => $last_timestamp,
            'start_timestamp' => $start_timestamp,
            'modified_timestamp' => $modified_timestamp
        );
    endwhile;
    
    // Reset post data
    wp_reset_postdata();
    
    // Sort posts: active status at top (ending soon, upcoming, ongoing), then others, application closed last - all by modified date
    usort($posts_data, function($a, $b) {
        // Group posts: active status (1-4) > no status (6) > application closed (5)
        $a_group = ($a['status_priority'] <= 4) ? 1 : (($a['status_priority'] == 6) ? 2 : 3);
        $b_group = ($b['status_priority'] <= 4) ? 1 : (($b['status_priority'] == 6) ? 2 : 3);
        
        // Sort by group first
        if ($a_group != $b_group) {
            return $a_group - $b_group;
        }
        
        // Within active status group, sort by priority (Ending Soon > Upcoming > Ongoing)
        // Ending Soon = 2, Upcoming = 3, Ongoing = 4, New = 1
        if ($a_group == 1 && $a['status_priority'] != $b['status_priority']) {
            // Special ordering: Ending Soon (2), Upcoming (3), Ongoing (4), New (1)
            $priority_order = [1 => 4, 2 => 1, 3 => 2, 4 => 3];
            $a_order = isset($priority_order[$a['status_priority']]) ? $priority_order[$a['status_priority']] : 99;
            $b_order = isset($priority_order[$b['status_priority']]) ? $priority_order[$b['status_priority']] : 99;
            return $a_order - $b_order;
        }
        
        // Within same priority or other groups, sort by modified date (newest first)
        return $b['modified_timestamp'] - $a['modified_timestamp'];
    });
    
    // Calculate pagination
    $total_posts = count($posts_data);
    $total_pages = ceil($total_posts / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $current_page_posts = array_slice($posts_data, $offset, $posts_per_page);
    ?>
    
    <header class="page-header">
        <h1 class="page-title"><?php single_cat_title(); ?></h1>
        <p class="page-description">
            <?php echo $total_posts; ?> notifications found
        </p>
    </header>
    
    <div class="main-content-wrapper">
        <div class="posts-table-wrapper">
            <div class="posts-table">
                <!-- Table Header -->
                <div class="table-header">
                    <div class="th-cell th-title">Title</div>
                    <div class="th-cell th-organization">Organization</div>
                    <div class="th-cell th-start">Start Date</div>
                    <div class="th-cell th-last">Last Updated</div>
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
                    $active_status = $post_item['active_status'];
                    $status_class = $post_item['status_class'];
                    $modified_timestamp = $post_item['modified_timestamp'];
                    
                    // Calculate relative time
                    $time_diff = human_time_diff($modified_timestamp, current_time('timestamp'));
                    $last_updated = 'Updated ' . $time_diff . ' ago';
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

                        <div class="td-cell td-start" data-label="Start Date">
                            <?php echo esc_html(kiosk_format_date_display($start_date)); ?>
                        </div>

                        <div class="td-cell td-last" data-label="Last Updated">
                            <span><?php echo esc_html($last_updated); ?></span>
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
        <h1 class="page-title"><?php single_cat_title(); ?></h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
