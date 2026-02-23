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
        $current_time = current_time('timestamp');
        $start_timestamp = ($start_date !== 'N/A') ? strtotime($start_date) : false;
        $last_timestamp = ($last_date !== 'N/A') ? strtotime($last_date) : false;
        
        if ($last_timestamp && $last_timestamp < $current_time) {
            // Last date has passed
            $active_status = 'Application Closed';
            $status_class = 'status-completed';
            $status_priority = 5; // Lowest priority
        } elseif ($last_timestamp && ($last_timestamp - $current_time) <= 7 * 24 * 60 * 60 && $last_timestamp >= $current_time) {
            // Last date is within 1 week from now
            $active_status = 'Ending Soon';
            $status_class = 'status-ending';
            $status_priority = 1; // Highest priority
        } elseif ($start_timestamp && $start_timestamp > $current_time) {
            // Start date is in the future
            $active_status = 'Upcoming';
            $status_class = 'status-upcoming';
            $status_priority = 2;
        } elseif ($start_timestamp && ($current_time - $start_timestamp) <= 7 * 24 * 60 * 60) {
            // Start date is within the past week
            $active_status = 'New';
            $status_class = 'status-new';
            $status_priority = 3;
        } elseif ($start_timestamp && $start_timestamp <= $current_time && $last_timestamp && $last_timestamp > $current_time) {
            // Start date has passed and last date is still in future (Ongoing)
            $active_status = 'Ongoing';
            $status_class = 'status-ongoing';
            $status_priority = 4;
        }
        
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

                        <div class="td-cell td-start" data-label="Start Date">
                            <?php echo esc_html(kiosk_format_date_display($start_date)); ?>
                        </div>

                        <div class="td-cell td-last" data-label="Last Date">
                            <span><?php echo esc_html(kiosk_format_date_display($last_date)); ?></span>
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
