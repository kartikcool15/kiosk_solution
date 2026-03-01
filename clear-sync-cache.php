<?php
/**
 * Clear Sync Cache - Run this once to clear cached sync data
 * Delete this file after running
 */

// Load WordPress
require_once('../../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Delete and re-set the sync options to force cache clear
delete_option('kiosk_last_sync');
delete_option('kiosk_last_created_sync');
delete_option('kiosk_last_modified_sync');

// Clear object cache if it exists
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

echo '<h1>Cache Cleared Successfully!</h1>';
echo '<p>The sync cache has been cleared. You can now:</p>';
echo '<ol>';
echo '<li>Delete this file (clear-sync-cache.php)</li>';
echo '<li>Go back to the <a href="' . admin_url('admin.php?page=kiosk-automation') . '">Content Sync page</a></li>';
echo '<li>Click "Fetch Recently Modified" to run a fresh sync</li>';
echo '</ol>';
