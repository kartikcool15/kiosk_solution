<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
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
                <button class="mobile-menu-toggle sidebar-toggle" aria-label="Toggle Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <!-- <a href="<?php echo home_url(); ?>">
                    <h2 class="sidebar-brand">Govt Jobs Exams</h2>
                </a> -->
                <button class="filter-toggle" aria-label="Toggle Filters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                </button>
                <div class="topbar-filters">
                    <div class="search-container">
                        <input type="search" id="post-search" class="post-search-input" placeholder="Search Anything...">
                        <div id="search-results" class="search-results-dropdown"></div>
                    </div>

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
            </div>

            <!-- Filter Sidebar (Mobile Only) -->
            <aside id="filter-sidebar" class="filter-sidebar">
                <div class="filter-sidebar-header">
                    <h3 class="filter-sidebar-title">Filters</h3>
                    <button class="filter-sidebar-close filter-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="filter-sidebar-content">
                    <div class="search-container">
                        <input type="search" class="post-search-input" placeholder="Search Anything...">
                        <div class="search-results-dropdown"></div>
                    </div>

                    <select class="sidebar-dropdown-select mobile-organization-dropdown">
                        <option value="">-- Choose Organization --</option>
                        <?php
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

                    <?php if (is_category('latest-job') || is_home() || is_front_page() || is_tax('education')) : ?>
                        <select class="sidebar-dropdown-select mobile-education-dropdown">
                            <option value="<?php echo esc_url(get_category_link(get_category_by_slug('latest-job'))); ?>">-- Filter by Education --</option>
                            <?php if (!empty($education_terms) && !is_wp_error($education_terms)) : ?>
                                <?php foreach ($education_terms as $term) :
                                    $selected = ($current_education === $term->slug) ? 'selected' : '';
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
            </aside>