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
        $result_date = $dates['result_date'];

        // Get timestamp for sorting
        $admit_card_timestamp = ($admit_card_date !== 'N/A') ? strtotime($admit_card_date) : 0;
        
        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'admit_card_date' => $admit_card_date,
            'result_date' => $result_date,
            'admit_card_timestamp' => $admit_card_timestamp
        );
    endwhile;
    
    // Reset post data
    wp_reset_postdata();
    
    // Sort posts by admit card date (newest first)
    usort($posts_data, function($a, $b) {
        // Sort by admit card date descending (newest first)
        if ($a['admit_card_timestamp'] != $b['admit_card_timestamp']) {
            return $b['admit_card_timestamp'] - $a['admit_card_timestamp'];
        }
        return 0;
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
                    <div class="th-cell th-result">Result Date</div>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php foreach ($current_page_posts as $post_item): 
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $admit_card_date = $post_item['admit_card_date'];
                    $result_date = $post_item['result_date'];
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
                            <span class="date-highlight"><?php echo esc_html($admit_card_date); ?></span>
                        </div>

                        <div class="td-cell td-result" data-label="Result Date">
                            <?php echo esc_html($result_date); ?>
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
        <h1 class="page-title">Admit Card</h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
