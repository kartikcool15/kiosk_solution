<?php

/**
 * Category Archive Template for Admit Card
 * Displays admit card posts sorted by admit card date
 */

get_header(); ?>

<?php
// Get pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

// Get ALL posts from this category (no pagination limit initially)
$all_posts_query = new WP_Query(array(
    'category_name' => 'admit-card',
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
        $admit_card_date = $dates['admit_card_date'];
        $exam_date = $dates['exam_date'];

        // Get admit card link from JSON
        $admit_card_link = !empty($data['links']['admit_card']) ? $data['links']['admit_card'] : '';

        // Get timestamp for sorting
        $admit_card_timestamp = ($admit_card_date !== 'N/A') ? strtotime($admit_card_date) : 0;
        $exam_timestamp = ($exam_date !== 'N/A') ? strtotime($exam_date) : false;

        // Get today's date at midnight for proper date comparison
        $current_time = current_time('timestamp');
        $today_start = strtotime('today', $current_time);
        $tomorrow_start = strtotime('tomorrow', $current_time);

        // Calculate Active Status
        $active_status = '';
        $status_class = '';
        $status_priority = 5; // Default priority (no status)

        if ($exam_timestamp && $exam_timestamp < $today_start) {
            // Exam date was before today (exam completed)
            $active_status = 'Exam Completed';
            $status_class = 'status-completed';
            $status_priority = 6; // Lowest priority (shown last)
        } elseif ($exam_timestamp && $exam_timestamp >= $today_start && $exam_timestamp < ($today_start + 7 * 24 * 60 * 60)) {
            // Exam date is today or within the next 7 days (exam soon)
            $active_status = 'Exam Soon';
            $status_class = 'status-ending';
            $status_priority = 1; // Highest priority
        } elseif ($admit_card_timestamp && $admit_card_timestamp < $tomorrow_start) {
            // Admit card date is today or has passed (card is released/available)
            $active_status = 'Available';
            $status_class = 'status-new';
            $status_priority = 2;
        } elseif ($admit_card_timestamp && $admit_card_timestamp >= $tomorrow_start && $admit_card_timestamp < ($today_start + 10 * 24 * 60 * 60)) {
            // Admit card date is within the next 10 days (releasing soon)
            $active_status = 'Releasing Soon';
            $status_class = 'status-upcoming';
            $status_priority = 3;
        }

        // Get modified date for sorting
        $modified_timestamp = get_post_modified_time('U', false, $post_id);

        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'admit_card_date' => $admit_card_date,
            'exam_date' => $exam_date,
            'admit_card_link' => $admit_card_link,
            'active_status' => $active_status,
            'status_class' => $status_class,
            'status_priority' => $status_priority,
            'admit_card_timestamp' => $admit_card_timestamp,
            'exam_timestamp' => $exam_timestamp,
            'modified_timestamp' => $modified_timestamp
        );
    endwhile;

    // Reset post data
    wp_reset_postdata();

    // Sort posts by status priority first, then by exam date for "Exam Soon"
    usort($posts_data, function ($a, $b) {
        // First, sort by status priority
        if ($a['status_priority'] != $b['status_priority']) {
            return $a['status_priority'] - $b['status_priority'];
        }
        // If same priority, sort by exam date (closest first) for Exam Soon
        if ($a['status_priority'] == 1 && $a['exam_timestamp'] != $b['exam_timestamp']) {
            return $a['exam_timestamp'] - $b['exam_timestamp'];
        }
        // For posts with no priority (priority 5), sort by modified date (newest first)
        if ($a['status_priority'] == 5) {
            return $b['modified_timestamp'] - $a['modified_timestamp'];
        }
        // Otherwise sort by admit card date descending (newest first)
        return $b['admit_card_timestamp'] - $a['admit_card_timestamp'];
    });

    // Calculate pagination
    $total_posts = count($posts_data);
    $posts_per_page = 20;
    $total_pages = ceil($total_posts / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $current_page_posts = array_slice($posts_data, $offset, $posts_per_page);
?>
    <header class="page-header">
        <h1 class="page-title">Admit Card</h1>
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
                    <div class="th-cell th-date">Admit Card Date</div>
                    <div class="th-cell th-exam">Last Updated</div>
                    <div class="th-cell th-status">Active Status</div>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php foreach ($current_page_posts as $post_item):
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $admit_card_date = $post_item['admit_card_date'];
                    $exam_date = $post_item['exam_date'];
                    $admit_card_link = $post_item['admit_card_link'];
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

                        <div class="td-cell td-date" data-label="Admit Card Date">
                            <?php echo esc_html(kiosk_format_date_display($admit_card_date)); ?>
                        </div>

                        <div class="td-cell td-exam" data-label="Last Updated">
                            <?php echo esc_html($last_updated); ?>
                        </div>

                        <div class="td-cell td-status" data-label="Active Status">
                            <?php if ($active_status): ?>
                                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($active_status); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="td-cell td-action" data-label="Action">
                            <?php if ($active_status === 'Exam Completed'): ?>
                                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="btn-view">
                                    View Details
                                </a>
                            <?php elseif ($admit_card_link): ?>
                                <a href="<?php echo esc_url($admit_card_link); ?>" target="_blank" rel="nofollow" class="btn-view">
                                    Download Admit Card
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="btn-view">
                                    View Details
                                </a>
                            <?php endif; ?>
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
        <h1 class="page-title">Admit Card</h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>