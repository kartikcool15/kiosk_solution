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
        
    });
    
})(jQuery);
