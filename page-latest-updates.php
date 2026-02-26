<?php
/**
 * Template Name: Latest Updates
 * Description: Displays 20 most recent posts from all categories sorted by modified date
 */

get_header(); ?>

<?php
// Get pagination
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$posts_per_page = 20;

// Get posts from all categories sorted by modified date
$query = new WP_Query(array(
    'post_type' => 'post',
    'posts_per_page' => $posts_per_page,
    'paged' => $paged,
    'post_status' => 'publish',
    'orderby' => 'modified',
    'order' => 'DESC'
));

if ($query->have_posts()) :
    // Collect all posts with their data
    $posts_data = array();
    while ($query->have_posts()) : $query->the_post();
        $post_id = get_the_ID();
        $json_data = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
        $data = json_decode($json_data, true);
        $post_title = !empty($data['post_title']) ? $data['post_title'] : get_the_title($post_id);
        $organization = !empty($data['organization']) ? $data['organization'] : 'N/A';
        
        // Get post category
        $categories = get_the_category($post_id);
        $category_name = !empty($categories) ? $categories[0]->name : 'Uncategorized';
        
        // Get modified timestamp
        $modified_timestamp = get_post_modified_time('U', false, $post_id);
        
        // Calculate relative time
        $time_diff = human_time_diff($modified_timestamp, current_time('timestamp'));
        $last_updated = 'Updated ' . $time_diff . ' ago';
        
        $posts_data[] = array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'organization' => $organization,
            'category_name' => $category_name,
            'last_updated' => $last_updated,
            'modified_timestamp' => $modified_timestamp
        );
    endwhile;
    
    wp_reset_postdata();
    
    $total_posts = $query->found_posts;
    $total_pages = $query->max_num_pages;
?>

    <header class="page-header">
        <h1 class="page-title">Latest Updates</h1>
        <p class="page-description">
            <?php echo $total_posts; ?> total posts • Showing most recently updated
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
                    <div class="th-cell th-updated">Last Updated</div>
                    <div class="th-cell th-action">Action</div>
                </div>

                <!-- Table Body -->
                <?php foreach ($posts_data as $post_item):
                    $post_id = $post_item['post_id'];
                    $post_title = $post_item['post_title'];
                    $organization = $post_item['organization'];
                    $category_name = $post_item['category_name'];
                    $last_updated = $post_item['last_updated'];
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
                            <?php echo esc_html($category_name); ?>
                        </div>

                        <div class="td-cell td-updated" data-label="Last Updated">
                            <?php echo esc_html($last_updated); ?>
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
    // Pagination
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
        <h1 class="page-title">Latest Updates</h1>
    </header>
    <div class="no-posts">
        <p>No posts found</p>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
