/**
 * Main Theme JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Organization Dropdown - Redirect to Archive Page
         */
        $('#organization-dropdown').on('change', function() {
            var selectedUrl = $(this).val();
            
            if (selectedUrl && selectedUrl !== '') {
                window.location.href = selectedUrl;
            }
        });
        
    });
    
})(jQuery);
