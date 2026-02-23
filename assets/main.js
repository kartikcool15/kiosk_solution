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
        
        /**
         * Initialize SlimSelect for Education Dropdown
         */
        var educationSlim = null;
        
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
                        var url = new URL(window.location.href);
                        
                        // Remove existing education parameters
                        url.searchParams.delete('education');
                        url.searchParams.delete('education[]');
                        
                        // Add new education parameter if selected
                        if (newVal && newVal.length > 0 && newVal[0].value !== '') {
                            url.searchParams.set('education', newVal[0].value);
                        }
                        
                        // Reload page with new filter
                        window.location.href = url.toString();
                    }
                }
            });
        }
        

        
    });
    
})(jQuery);
