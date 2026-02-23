<?php

/**
 * Category Archive Template for Admission
 * Displays admission posts sorted by start date
 */

get_header(); ?>

<?php
// Get pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

// Get ALL posts from this category (no pagination limit initially)
$all_posts_query = new WP_Query(array(
    'category_name' => 'admission',
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
        $start_date = $dates['start_date'];
        $last_date = $dates['last_date'];

        // Get timestamp for sorting
        $start_timestamp = ($start_date !== 'N/A') ? strtotime($start_date) : 0;
        $last_timestamp = ($last_date !== 'N/A') ? strtotime($last_date) : 0;
        
        // Store post data
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'start_date' => $start_date,
            'last_date' => $last_date,
            'start_timestamp' => $start_timestamp,
            'last_timestamp' => $last_timestamp
        );
    endwhile;
    
    // Reset post data
    wp_reset_postdata();
    
    // Sort posts by last date descending (most recent deadline first), then by start date
    usort($posts_data, function($a, $b) {
        // Sort by last date descending (upcoming deadlines first)
        if ($a['last_timestamp'] != $b['last_timestamp']) {
            return $b['last_timestamp'] - $a['last_timestamp'];
        }
        // If last dates are same, sort by start date descending
        return $b['start_timestamp'] - $a['start_timestamp'];
    });
    
    // Calculate pagination
    $total_posts = count($posts_data);
    $posts_per_page = 20;
    $total_pages = ceil($total_posts / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $current_page_posts = array_slice($posts_data, $offset, $posts_per_page);
?>
    <header class="page-header">
        <h1 class="page-title">Admission</h1>
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
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php foreach ($current_page_posts as $post_item): 
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $start_date = $post_item['start_date'];
                    $last_date = $post_item['last_date'];
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
                            <?php echo esc_html($start_date); ?>
                        </div>

                        <div class="td-cell td-last" data-label="Last Date">
                            <span class="date-highlight"><?php echo esc_html($last_date); ?></span>
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
        <h1 class="page-title">Admission</h1>
    </header>
    <div class="no-posts">
        <p>No posts found in this category</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
