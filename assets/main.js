/**
 * Main Theme JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Initialize SlimSelect for Organization Dropdown
         */
        var organizationSlim = null;
        var mobileOrganizationSlim = null;
        
        if ($('#organization-dropdown').length) {
            organizationSlim = new SlimSelect({
                select: '#organization-dropdown',
                settings: {
                    searchPlaceholder: 'Search organizations...',
                    searchText: 'No organizations found',
                    searchingText: 'Searching...',
                    closeOnSelect: true
                },
                events: {
                    afterChange: function(newVal) {
                        if (newVal && newVal.length > 0 && newVal[0].value !== '') {
                            window.location.href = newVal[0].value;
                        }
                    }
                }
            });
        }

        // Mobile organization dropdown
        if ($('.mobile-organization-dropdown').length) {
            mobileOrganizationSlim = new SlimSelect({
                select: '.mobile-organization-dropdown',
                settings: {
                    searchPlaceholder: 'Search organizations...',
                    searchText: 'No organizations found',
                    searchingText: 'Searching...',
                    closeOnSelect: true
                },
                events: {
                    afterChange: function(newVal) {
                        if (newVal && newVal.length > 0 && newVal[0].value !== '') {
                            window.location.href = newVal[0].value;
                        }
                    }
                }
            });
        }
        
        /**
         * Initialize SlimSelect for Education Dropdown
         */
        var educationSlim = null;
        var mobileEducationSlim = null;
        
        if ($('#education-dropdown').length) {
            educationSlim = new SlimSelect({
                select: '#education-dropdown',
                settings: {
                    searchPlaceholder: 'Search education...',
                    searchText: 'No education found',
                    searchingText: 'Searching...',
                    closeOnSelect: true
                },
                events: {
                    afterChange: function(newVal) {
                        if (newVal && newVal.length > 0 && newVal[0].value !== '') {
                            window.location.href = newVal[0].value;
                        } else {
                            // If "-- Filter by Education --" selected, go back to category without filter
                            var categoryUrl = $('#education-dropdown').data('category-url');
                            if (categoryUrl) {
                                window.location.href = categoryUrl;
                            }
                        }
                    }
                }
            });
        }

        // Mobile education dropdown
        if ($('.mobile-education-dropdown').length) {
            mobileEducationSlim = new SlimSelect({
                select: '.mobile-education-dropdown',
                settings: {
                    searchPlaceholder: 'Search education...',
                    searchText: 'No education found',
                    searchingText: 'Searching...',
                    closeOnSelect: true
                },
                events: {
                    afterChange: function(newVal) {
                        if (newVal && newVal.length > 0 && newVal[0].value !== '') {
                            window.location.href = newVal[0].value;
                        } else {
                            var categoryUrl = $('.mobile-education-dropdown').data('category-url');
                            if (categoryUrl) {
                                window.location.href = categoryUrl;
                            }
                        }
                    }
                }
            });
        }
        

        /**
         * Dynamic Post Search
         */
        var searchTimeout;
        var $searchInput = $('.post-search-input'); // Changed to class selector
        var $searchResults = $('.search-results-dropdown'); // Changed to class selector
        
        if ($searchInput.length) {
            // Handle input changes with debounce
            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                var searchQuery = $(this).val().trim();
                var $currentResults = $(this).siblings('.search-results-dropdown');
                
                // Clear results if less than 3 characters
                if (searchQuery.length < 3) {
                    $currentResults.hide().empty();
                    return;
                }
                
                // Show loading state
                $currentResults.html('<div class="search-loading">Searching...</div>').show();
                
                // Debounce the search request
                searchTimeout = setTimeout(function() {
                    performSearch(searchQuery, $currentResults);
                }, 300);
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    $('.search-results-dropdown').hide();
                }
            });
            
            // Prevent form submission
            $searchInput.on('keydown', function(e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                }
            });
        }
        
        function performSearch(query, $resultsContainer) {
            $.ajax({
                url: kioskSearch.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kiosk_search_posts',
                    search: query,
                    nonce: kioskSearch.nonce
                },
                success: function(response) {
                    if (response.success && response.data.posts) {
                        displaySearchResults(response.data.posts, $resultsContainer);
                    } else {
                        $resultsContainer.html('<div class="search-no-results">No results found</div>').show();
                    }
                },
                error: function() {
                    $resultsContainer.html('<div class="search-error">Search failed. Please try again.</div>').show();
                }
            });
        }
        
        function displaySearchResults(posts, $resultsContainer) {
            if (posts.length === 0) {
                $resultsContainer.html('<div class="search-no-results">No results found</div>').show();
                return;
            }
            
            var html = '<div class="search-results-list">';
            
            posts.forEach(function(post) {
                var categoryBadges = '';
                if (post.categories && post.categories.length > 0) {
                    post.categories.forEach(function(cat) {
                        categoryBadges += '<span class="search-category-badge category-' + cat.slug + '">' + cat.name + '</span>';
                    });
                }
                
                html += '<a href="' + post.url + '" class="search-result-item">' +
                        '<div class="search-result-categories">' + categoryBadges + '</div>' +
                        '<div class="search-result-title">' + post.title + '</div>' +
                        '</a>';
            });
            
            html += '</div>';
            $resultsContainer.html(html).show();
        }
        
        /**
         * Mobile Sidebar Toggle
         */
        var $sidebar = $('#custom-sidebar');
        var $sidebarToggle = $('.sidebar-toggle'); // This will select all toggle buttons
        var $body = $('body');
        
        // Toggle sidebar on button click (works for both topbar and sidebar buttons)
        $sidebarToggle.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $sidebar.toggleClass('sidebar-open');
            $body.toggleClass('sidebar-active');
            $sidebarToggle.toggleClass('active'); // Toggle all buttons
        });
        
        // Close sidebar when clicking on overlay (mobile)
        $body.on('click', function(e) {
            if ($sidebar.hasClass('sidebar-open') && 
                !$(e.target).closest('.custom-sidebar').length && 
                !$(e.target).closest('.sidebar-toggle').length) {
                $sidebar.removeClass('sidebar-open');
                $body.removeClass('sidebar-active');
                $sidebarToggle.removeClass('active');
            }
        });
        
        // Close sidebar when clicking on a link (mobile)
        $('.sidebar-link').on('click', function() {
            if (window.innerWidth <= 768) {
                $sidebar.removeClass('sidebar-open');
                $body.removeClass('sidebar-active');
                $sidebarToggle.removeClass('active');
            }
        });
        
        // Handle window resize
        var resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (window.innerWidth > 768) {
                    $sidebar.removeClass('sidebar-open');
                    $body.removeClass('sidebar-active');
                    $sidebarToggle.removeClass('active');
                }
            }, 250);
        });
        
        /**
         * Filter Sidebar Toggle
         */
        var $filterToggle = $('.filter-toggle');
        var $filterSidebar = $('#filter-sidebar');
        
        // Toggle filter sidebar on button click
        $filterToggle.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $filterSidebar.toggleClass('filter-sidebar-open');
            $(this).toggleClass('active');
        });
        
        // Close filter sidebar when clicking outside
        $(document).on('click', function(e) {
            if ($filterSidebar.hasClass('filter-sidebar-open') && 
                !$(e.target).closest('#filter-sidebar').length && 
                !$(e.target).closest('.filter-toggle').length) {
                $filterSidebar.removeClass('filter-sidebar-open');
                $filterToggle.removeClass('active');
            }
        });
        
        // Handle window resize for filter sidebar
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (window.innerWidth > 768) {
                    $filterSidebar.removeClass('filter-sidebar-open');
                    $filterToggle.removeClass('active');
                }
            }, 250);
        });
        
    });
    
})(jQuery);
