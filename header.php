<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9103495719984418"
        crossorigin="anonymous"></script>
    <?php wp_head(); ?>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-1RSRP789WR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-1RSRP789WR');
    </script>
</head>

<body <?php body_class(); ?>>

    <div class="content-wrapper">
        <?php get_sidebar(); ?>

        <main class="main-content">
            <div class="topbar">
                <div class="topbar-filters">
                    <select class="sidebar-dropdown-select" id="organization-dropdown">
                        <option value="">-- Choose Organization --</option>
                        <?php
                        $organizations = get_terms(array(
                            'taxonomy' => 'organization',
                            'hide_empty' => true,
                            'orderby' => 'name',
                            'order' => 'ASC'
                        ));

                        if (!empty($organizations) && !is_wp_error($organizations)) :
                            foreach ($organizations as $org) :
                                $term_link = get_term_link($org);
                                $selected = (is_tax('organization', $org->slug)) ? 'selected' : '';
                        ?>
                                <option value="<?php echo esc_url($term_link); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($org->name); ?> (<?php echo $org->count; ?>)
                                </option>
                        <?php endforeach;
                        endif;
                        ?>
                    </select>

                    <?php if (is_category('latest-job') || is_home() || is_front_page() || is_tax('education')) :
                        // Get all education terms
                        $education_terms = get_terms(array(
                            'taxonomy' => 'education',
                            'hide_empty' => true,
                            'orderby' => 'name',
                            'order' => 'ASC'
                        ));

                        // Get current education term if on taxonomy archive
                        $current_education = '';
                        if (is_tax('education')) {
                            $current_term = get_queried_object();
                            $current_education = $current_term->slug;
                        }
                    ?>
                        <select class="sidebar-dropdown-select" id="education-dropdown">
                            <option value="<?php echo esc_url(get_category_link(get_category_by_slug('latest-job'))); ?>">-- Filter by Education --</option>
                            <?php if (!empty($education_terms) && !is_wp_error($education_terms)) : ?>
                                <?php foreach ($education_terms as $term) :
                                    $selected = ($current_education === $term->slug) ? 'selected' : '';
                                    // Use taxonomy term archive URL
                                    $term_link = get_term_link($term, 'education');
                                ?>
                                    <option value="<?php echo esc_url($term_link); ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($term->name); ?> (<?php echo $term->count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <ul class="top-menu">
                    <li><a href="<?php echo home_url(); ?>">Home</a></li>
                    <!-- <li>Login</li> -->
                </ul>
            </div>