<?php

/**
 * Category Archive Template for Result
 * Displays result posts sorted by result date
 */

get_header(); ?>

<?php
// Get pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

// Get ALL posts from this category (no pagination limit initially)
$all_posts_query = new WP_Query(array(
    'category_name' => 'result',
    'posts_per_page' => -1,
    'post_status' => 'publish',
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
        $result_date = $dates['result_date'];
        $counselling_date = $dates['counselling_date'];
        $interview_date = $dates['interview_date'];

        // Determine "next date" - counselling, interview, or N/A
        $next_date = 'N/A';
        $next_timestamp = false;
        if ($counselling_date !== 'N/A') {
            $next_date = $counselling_date;
            $next_timestamp = strtotime($counselling_date);
        } elseif ($interview_date !== 'N/A') {
            $next_date = $interview_date;
            $next_timestamp = strtotime($interview_date);
        }

        // Get timestamp for sorting
        $result_timestamp = ($result_date !== 'N/A') ? strtotime($result_date) : 0;
        $current_time = current_time('timestamp');

        // Calculate Active Status
        $active_status = '';
        $status_class = '';
        $status_priority = 5; // Default priority (no status)

        if ($result_timestamp && ($current_time - $result_timestamp) <= 7 * 24 * 60 * 60 && $result_timestamp <= $current_time) {
            // Result declared within last 7 days
            $active_status = 'Out Now';
            $status_class = 'status-new';
            $status_priority = 1; // Highest priority
        } elseif ($result_timestamp && ($result_timestamp - $current_time) <= 7 * 24 * 60 * 60 && $result_timestamp > $current_time) {
            // Result date is within 7 days in future
            $active_status = 'Releasing Soon';
            $status_class = 'status-upcoming';
            $status_priority = 2;
        } elseif ($next_timestamp && ($next_timestamp - $current_time) <= 14 * 24 * 60 * 60 && $next_timestamp > $current_time) {
            // Next date (counselling/interview) within 14 days
            $active_status = 'Counselling Soon';
            $status_class = 'status-upcoming';
            $status_priority = 3;
        } elseif ($result_timestamp && ($current_time - $result_timestamp) > 30 * 24 * 60 * 60) {
            // Result date more than 30 days ago
            $active_status = 'Result Old';
            $status_class = 'status-completed';
            $status_priority = 4;
        }

        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'result_date' => $result_date,
            'next_date' => $next_date,
            'active_status' => $active_status,
            'status_class' => $status_class,
            'status_priority' => $status_priority,
            'result_timestamp' => $result_timestamp,
            'next_timestamp' => $next_timestamp
        );
    endwhile;

    // Reset post data
    wp_reset_postdata();

    // Sort posts by status priority first, then by result date
    usort($posts_data, function ($a, $b) {
        // First, sort by status priority
        if ($a['status_priority'] != $b['status_priority']) {
            return $a['status_priority'] - $b['status_priority'];
        }
        // If same priority, sort by result date descending (newest first)
        return $b['result_timestamp'] - $a['result_timestamp'];
    });

    // Calculate pagination
    $total_posts = count($posts_data);
    $posts_per_page = 20;
    $total_pages = ceil($total_posts / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $current_page_posts = array_slice($posts_data, $offset, $posts_per_page);
?>
    <header class="page-header">
        <h1 class="page-title">Result</h1>
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
                    <div class="th-cell th-result">Result Date</div>
                    <div class="th-cell th-next">Next Date</div>
                    <div class="th-cell th-status">Active Status</div>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php foreach ($current_page_posts as $post_item):
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $result_date = $post_item['result_date'];
                    $next_date = $post_item['next_date'];
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

                        <div class="td-cell td-result" data-label="Result Date">
                            <span><?php echo esc_html(kiosk_format_date_display($result_date)); ?></span>
                        </div>

                        <div class="td-cell td-next" data-label="Next Date">
                            <?php echo esc_html(kiosk_format_date_display($next_date)); ?>
                        </div>

                        <div class="td-cell td-status" data-label="Active Status">
                            <?php if ($active_status): ?>
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
    // Custom pagination
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
    <?php endif;
    endif;
    ?>

<?php else : ?>
    <header class="page-header">
        <h1 class="page-title">Result</h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>