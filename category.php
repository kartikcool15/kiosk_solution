<?php

/**
 * Category Archive Template
 * Displays posts in a responsive table layout
 */

get_header(); ?>
<?php if (have_posts()) :
    // Get current category
    $current_category = get_queried_object();
    $category_slug = $current_category->slug;
?>
    <header class="page-header">
        <h1 class="page-title"><?php single_cat_title(); ?></h1>
        <p class="page-description">
            <?php echo $wp_query->found_posts; ?> notifications found
        </p>
    </header>
    
    <div class="main-content-wrapper">
        <div class="posts-table-wrapper">
            <div class="posts-table">
                <!-- Table Header -->
                <div class="table-header">
                    <div class="th-cell th-title">Title</div>
                    <div class="th-cell th-organization">Organization</div>
                    <?php if ($category_slug === 'admit-card'): ?>
                        <div class="th-cell th-date">Admit Card Date</div>
                        <div class="th-cell th-result">Result Date</div>
                    <?php elseif ($category_slug === 'result'): ?>
                        <div class="th-cell th-result">Result Date</div>
                        <div class="th-cell th-next">Next Date</div>
                    <?php else: ?>
                        <div class="th-cell th-start">Start Date</div>
                        <div class="th-cell th-last">Last Date</div>
                    <?php endif; ?>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php while (have_posts()) : the_post();
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
                        <?php if ($category_slug === 'admit-card'): ?>
                            <div class="td-cell td-date" data-label="Admit Card Date">
                                <?php echo esc_html($admit_card_date); ?>
                            </div>

                            <div class="td-cell td-result" data-label="Result Date">
                                <span class="date-highlight"><?php echo esc_html($result_date); ?></span>
                            </div>
                        <?php elseif ($category_slug === 'result'): ?>
                            <div class="td-cell td-result" data-label="Result Date">
                                <?php echo esc_html($result_date); ?>
                            </div>

                            <div class="td-cell td-next" data-label="Next Date">
                                <span class="date-highlight"><?php echo esc_html($next_date); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="td-cell td-start" data-label="Start Date">
                                <?php echo esc_html($start_date); ?>
                            </div>

                            <div class="td-cell td-last" data-label="Last Date">
                                <span class="date-highlight"><?php echo esc_html($last_date); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="td-cell td-action" data-label="Action">
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="btn-view">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php
    // Pagination
    $pagination = paginate_links(array(
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

<?php else : ?>
    <header class="page-header">
        <h1 class="page-title"><?php single_cat_title(); ?></h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>