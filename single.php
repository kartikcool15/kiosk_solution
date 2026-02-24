<?php get_header(); ?>

<?php if (have_posts()) : while (have_posts()) : the_post();
        // Get ChatGPT JSON data
        $json_data = get_post_meta(get_the_ID(), 'kiosk_chatgpt_json', true);
        $data = $json_data ? json_decode($json_data, true) : array();

        // Extract basic fields
        $organization = !empty($data['organization']) ? $data['organization'] : '';
        $total_vacancy = !empty($data['total_vacancy']) ? $data['total_vacancy'] : '';

        $category = get_the_category();
        $category_name = !empty($category) ? $category[0]->name : '';
?>
        <div class="post-hero">
            <div class="post-hero-content">
                <nav class="breadcrumb">
                    <?php if (function_exists("rank_math_the_breadcrumbs")) rank_math_the_breadcrumbs(); ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </a>
                    <?php if ($category_name): ?>
                        <span class="separator">›</span>
                        <a href="<?php echo esc_url(get_category_link($category[0]->term_id)); ?>"><?php echo esc_html($category_name); ?></a>
                    <?php endif; ?>
                </nav>

                <h1 class="post-hero-title"><?php the_title(); ?></h1>

                <div class="post-meta">
                    <time datetime="<?php echo get_the_date('c'); ?>">Posted on <?php echo get_the_date('F j, Y'); ?></time>
                </div>
            </div>
        </div>

        <?php
        // Extract links from ChatGPT JSON
        $apply_link = !empty($data['links']['apply_online']) ? $data['links']['apply_online'] : '';
        $pdf_link = !empty($data['links']['notification_pdf']) ? $data['links']['notification_pdf'] : '';
        $admit_card_link = !empty($data['links']['admit_card']) ? $data['links']['admit_card'] : '';
        $result_link = !empty($data['links']['result']) ? $data['links']['result'] : '';
        $website_link = !empty($data['links']['official_website']) ? $data['links']['official_website'] : '';
        $additional_links = !empty($data['links']['additional_links']) ? $data['links']['additional_links'] : array();
        ?>

        <div class="single-content-sidebar">
            <!-- Main Content Area -->
            <div class="post-main-content">
                <!-- Overview Section -->
                <?php if (!empty($data['post_content_summary'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                Overview
                            </h2>
                        </div>

                        <div class="card-content">
                            <?php echo wpautop(wp_kses_post($data['post_content_summary'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Important Dates Section -->
                <?php
                $dates_display = array();

                if (!empty($data['dates']) && is_array($data['dates'])) {
                    // Build dates array from ChatGPT structure
                    if (!empty($data['dates']['start_date'])) {
                        $dates_display[] = array('event' => 'Start Date', 'date' => $data['dates']['start_date']);
                    }
                    if (!empty($data['dates']['last_date'])) {
                        $dates_display[] = array('event' => 'Last Date', 'date' => $data['dates']['last_date']);
                    }
                    if (!empty($data['dates']['exam_date'])) {
                        $dates_display[] = array('event' => 'Exam Date', 'date' => $data['dates']['exam_date']);
                    }
                    if (!empty($data['dates']['admit_card_date'])) {
                        $dates_display[] = array('event' => 'Admit Card', 'date' => $data['dates']['admit_card_date']);
                    }
                    if (!empty($data['dates']['result_date'])) {
                        $dates_display[] = array('event' => 'Result Date', 'date' => $data['dates']['result_date']);
                    }
                    if (!empty($data['dates']['counselling_date'])) {
                        $dates_display[] = array('event' => 'Counselling Date', 'date' => $data['dates']['counselling_date']);
                    }
                    if (!empty($data['dates']['interview_date'])) {
                        $dates_display[] = array('event' => 'Interview Date', 'date' => $data['dates']['interview_date']);
                    }
                    // Add other important dates
                    if (!empty($data['dates']['other_important_dates']) && is_array($data['dates']['other_important_dates'])) {
                        foreach ($data['dates']['other_important_dates'] as $other_date) {
                            if (!empty($other_date['event']) && !empty($other_date['date'])) {
                                $dates_display[] = $other_date;
                            }
                        }
                    }
                }

                if (!empty($dates_display)):
                ?>
                    <div class="card section-dates">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Important Dates
                            </h2>
                        </div>

                        <div class="card-content">
                            <div class="items">
                                <?php foreach ($dates_display as $date_item): ?>
                                    <div class="item">
                                        <div class="key"><?php echo esc_html($date_item['event']); ?></div>
                                        <div class="value"><?php echo esc_html($date_item['date']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>



                <!-- Age Limit Details -->
                <?php if (!empty($data['age_eligibility']) || !empty($data['age_limit_as_on'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="7"></circle>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                </svg>
                                Age Limit Details
                            </h2>
                        </div>

                        <div class="card-content">
                            <div class="items">
                                <?php if (!empty($data['age_eligibility'])): ?>
                                    <div class="item">
                                        <div class="key">Age Limit</div>
                                        <div class="value"><?php echo esc_html($data['age_eligibility']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($data['age_limit_as_on'])): ?>
                                    <div class="item">
                                        <div class="key">Age as on</div>
                                        <div class="value"><?php echo esc_html($data['age_limit_as_on']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Application Fee Details -->
                <?php if (!empty($data['fees']) && is_array($data['fees'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                                Application Fee Details
                            </h2>
                        </div>

                        <div class="card-content">
                            <div class="items">
                                <?php foreach ($data['fees'] as $fee_item): ?>
                                    <?php if (!empty($fee_item['title'])): ?>
                                        <div class="item">
                                            <div class="key"><?php echo esc_html($fee_item['title']); ?></div>
                                            <div class="value">
                                                <?php
                                                $value = !empty($fee_item['value']) ? $fee_item['value'] : '';
                                                // Add ₹ for numeric values
                                                if (!empty($value) && is_numeric($value)) {
                                                    echo '₹' . esc_html($value);
                                                } else {
                                                    echo esc_html($value);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>



                <!-- Vacancy Details -->
                <?php if (!empty($data['post_vacancy']) && is_array($data['post_vacancy'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                Vacancy Details
                            </h2>
                        </div>

                        <div class="card-content">
                            <div class="items">
                                <?php if (!empty($total_vacancy)): ?>
                                    <div class="item">
                                        <div class="key"><strong>Total Vacancy</strong></div>
                                        <div class="value"><strong><?php echo esc_html($total_vacancy); ?></strong></div>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($data['post_vacancy'] as $vacancy_item): ?>
                                    <?php if (!empty($vacancy_item['post_name']) && !empty($vacancy_item['vacancy'])): ?>
                                        <div class="item">
                                            <div class="key"><?php echo esc_html($vacancy_item['post_name']); ?></div>
                                            <div class="value"><?php echo esc_html($vacancy_item['vacancy']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Eligibility Criteria Section -->
                <?php if (!empty($data['eligibility_post_wise']) && is_array($data['eligibility_post_wise'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Eligibility Criteria
                            </h2>
                        </div>

                        <div class="card-content">
                            <?php foreach ($data['eligibility_post_wise'] as $item): ?>
                                <?php if (!empty($item['post_name']) && !empty($item['eligibility'])): ?>
                                    <strong><?php echo esc_html($item['post_name']); ?>:</strong> <?php echo esc_html($item['eligibility']); ?><br><br>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- How to Apply Section -->
                <?php if (!empty($data['how_to_apply'])): ?>
                    <div class="card section-highlight">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                                How to Apply
                            </h2>
                        </div>

                        <div class="card-content">
                            <?php echo wp_kses_post($data['how_to_apply']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FAQs Section -->
                <?php if (!empty($data['faqs']) && is_array($data['faqs'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                                Frequently Asked Questions
                            </h2>
                        </div>

                        <div class="card-content">
                            <?php foreach ($data['faqs'] as $faq): ?>
                                <?php if (!empty($faq['question']) && !empty($faq['answer'])): ?>
                                    <div style="margin-bottom: 1.5rem;">
                                        <strong>Q: <?php echo esc_html($faq['question']); ?></strong><br>
                                        A: <?php echo esc_html($faq['answer']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
            <!-- End Main Content Area -->

            <!-- Right Sidebar -->
            <aside class="post-sidebar">
                <!-- Action Buttons -->
                <?php if ($apply_link || $pdf_link || $admit_card_link || $result_link || $website_link): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-content">
                            <div class="sidebar-actions">
                                <?php if ($apply_link): ?>
                                    <a href="<?php echo esc_url($apply_link); ?>" target="_blank" rel="nofollow" class="btn-action btn-primary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <line x1="10" y1="14" x2="21" y2="3"></line>
                                        </svg>
                                        Apply Online
                                    </a>
                                <?php endif; ?>

                                <?php if ($pdf_link): ?>
                                    <a href="<?php echo esc_url($pdf_link); ?>" target="_blank" rel="nofollow" class="btn-action btn-secondary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Download Notification
                                    </a>
                                <?php endif; ?>

                                <?php if ($admit_card_link): ?>
                                    <a href="<?php echo esc_url($admit_card_link); ?>" target="_blank" rel="nofollow" class="btn-action btn-secondary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                                            <polyline points="17 2 12 7 7 2"></polyline>
                                        </svg>
                                        Download Admit Card
                                    </a>
                                <?php endif; ?>

                                <?php if ($result_link): ?>
                                    <a href="<?php echo esc_url($result_link); ?>" target="_blank" rel="nofollow" class="btn-action btn-secondary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                        Check Result
                                    </a>
                                <?php endif; ?>

                                <?php if ($website_link): ?>
                                    <a href="<?php echo esc_url($website_link); ?>" target="_blank" rel="nofollow" class="btn-action btn-secondary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="2" y1="12" x2="22" y2="12"></line>
                                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                        </svg>
                                        Official Website
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Important Links -->
                <?php if (!empty($additional_links) && is_array($additional_links)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Important Links</h3>
                        </div>
                        <div class="card-content">
                            <div class="sidebar-links">
                                <?php foreach ($additional_links as $link_item): ?>
                                    <?php if (!empty($link_item['title']) && !empty($link_item['url'])): ?>
                                        <a href="<?php echo esc_url($link_item['url']); ?>" target="_blank" rel="nofollow" class="sidebar-link">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                <polyline points="15 3 21 3 21 9"></polyline>
                                                <line x1="10" y1="14" x2="21" y2="3"></line>
                                            </svg>
                                            <span><?php echo esc_html($link_item['title']); ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>
            <!-- End Right Sidebar -->

        </div>

<?php endwhile;
endif; ?>
<?php get_footer(); ?>