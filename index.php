<?php get_header(); ?>

<header class="page-header">
    <h1 class="page-title">Latest Updates</h1>
    <p class="page-description">Browse recent posts from all categories</p>
</header>

<div class="main-content-wrapper">
    <div class="homepage-categories">
        <?php
        // Define categories to display
        $categories = array(
            array('slug' => 'latest-job', 'name' => 'Latest Jobs'),
            array('slug' => 'admit-card', 'name' => 'Admit Cards'),
            array('slug' => 'result', 'name' => 'Results'),
            array('slug' => 'admission', 'name' => 'Admissions'),
            array('slug' => 'answer-key', 'name' => 'Answer Keys'),
            array('slug' => 'sarkari-job', 'name' => 'Govt. Jobs'),
            array('slug' => 'documents', 'name' => 'Documents'),
        );

        foreach ($categories as $category) :
            $cat = get_category_by_slug($category['slug']);
            if (!$cat) continue;

            // Query 5 posts from this category
            $args = array(
                'category_name' => $category['slug'],
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            $cat_posts = new WP_Query($args);

            if ($cat_posts->have_posts()) :
        ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title justify-content-between">
                            <?php echo esc_html($category['name']); ?>
                        </h2>

                        <a href="<?php echo esc_url(get_category_link($cat)); ?>" class="view-all-link">
                            View All →
                        </a>
                    </div>

                    <div class="card-content">
                        <div class="items">
                            <?php while ($cat_posts->have_posts()) : $cat_posts->the_post();
                                $post_id = get_the_ID();
                                $chatgpt_json = get_post_meta($post_id, 'kiosk_chatgpt_json', true);
                                $data = $chatgpt_json ? json_decode($chatgpt_json, true) : array();

                                $organization = !empty($data['organization']) ? $data['organization'] : 'N/A';
                                $start_date = !empty($data['start_date']) ? $data['start_date'] : '';
                                $last_date = !empty($data['last_date']) ? $data['last_date'] : '';
                                $post_title = !empty($data['post_title']) ? $data['post_title'] : get_the_title($post_id);
                            ?>
                                <div class="item">
                                    <div class="key">
                                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
                                            <?php echo esc_html($post_title); ?>
                                        </a>
                                    </div>

                                </div>
                            <?php endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        </div>
                    </div>

                </div>
        <?php
            endif;
        endforeach;
        ?>
    </div>
</div>
<?php get_footer(); ?>