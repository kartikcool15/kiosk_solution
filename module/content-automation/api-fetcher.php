<?php

/**
 * API Fetcher Module
 * Handles all API communication with external data sources
 */

if (!defined('ABSPATH')) exit;

class Kiosk_API_Fetcher
{
    private $api_base_url;

    public function __construct()
    {
        $this->api_base_url = $this->get_api_base_url();
    }

    /**
     * Get API base URL from settings
     */
    private function get_api_base_url()
    {
        if (!$this->api_base_url) {
            $settings = get_option('kiosk_automation_settings', array());
            $this->api_base_url = isset($settings['api_base_url']) ? rtrim($settings['api_base_url'], '/') : 'https://sarkariresult.com.cm/wp-json/wp/v2';
        }
        return $this->api_base_url;
    }

    /**
     * Fetch posts from external API
     * 
     * @param int $page Page number
     * @param int $per_page Posts per page
     * @param array $categories Category filter
     * @param string $modified_after ISO 8601 timestamp for modified posts
     * @param string $created_after ISO 8601 timestamp for new posts only
     * @return array|false Array of posts or false on error
     */
    public function fetch_posts($page = 1, $per_page = 10, $categories = array(), $modified_after = '', $created_after = '')
    {
        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            '_embed' => 1,
            'acf_format' => 'standard' // Request ACF fields
        );

        // Add category filter if provided
        if (!empty($categories) && is_array($categories)) {
            $args['categories'] = implode(',', $categories);
        }

        // Fetch recently modified posts (for updates)
        if (!empty($modified_after)) {
            $args['modified_after'] = $modified_after;
        }

        // Fetch recently created posts only (new posts)
        if (!empty($created_after)) {
            $args['after'] = $created_after;
        }

        $url = add_query_arg($args, $this->api_base_url . '/posts');

        // Log the API request URL for debugging
        error_log('Kiosk API Request: ' . $url);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Kiosk API Error: ' . $error_message);
            
            // Log to Sentry
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($error_message, $url, $page, $per_page, $modified_after, $created_after) {
                $scope->setContext('api_error', [
                    'error_message' => $error_message,
                    'url' => $url,
                    'page' => $page,
                    'per_page' => $per_page,
                    'modified_after' => $modified_after,
                    'created_after' => $created_after
                ]);
                \Sentry\captureMessage('API Fetch Failed: WP_Error', \Sentry\Severity::error());
            });
            
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response for debugging
        if ($response_code !== 200) {
            error_log('Kiosk API Response Code: ' . $response_code);
            error_log('Kiosk API Response Body: ' . substr($body, 0, 500));
            
            // Log to Sentry
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($response_code, $body, $url, $page, $per_page, $modified_after, $created_after) {
                $scope->setContext('api_error', [
                    'response_code' => $response_code,
                    'response_body' => substr($body, 0, 500),
                    'url' => $url,
                    'page' => $page,
                    'per_page' => $per_page,
                    'modified_after' => $modified_after,
                    'created_after' => $created_after
                ]);
                \Sentry\captureMessage('API Fetch Failed: Non-200 Response', \Sentry\Severity::error());
            });
            
            return false;
        }

        $posts = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('Kiosk API JSON Error: ' . $json_error);
            
            // Log to Sentry
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($json_error, $body, $url, $page, $per_page) {
                $scope->setContext('api_error', [
                    'json_error' => $json_error,
                    'response_body' => substr($body, 0, 500),
                    'url' => $url,
                    'page' => $page,
                    'per_page' => $per_page
                ]);
                \Sentry\captureMessage('API Fetch Failed: JSON Parse Error', \Sentry\Severity::error());
            });
            
            return false;
        }

        // Log number of posts fetched
        $post_count = is_array($posts) ? count($posts) : 0;
        error_log('Kiosk API: Fetched ' . $post_count . ' posts');

        return $posts;
    }

    /**
     * Test API connection
     * 
     * @return array Connection test results
     */
    public function test_connection()
    {
        $url = $this->api_base_url . '/posts?per_page=1';

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $api_base_url = $this->api_base_url;
            
            // Log to Sentry
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($error_message, $url, $api_base_url) {
                $scope->setContext('api_error', [
                    'error_message' => $error_message,
                    'url' => $url,
                    'api_base_url' => $api_base_url
                ]);
                \Sentry\captureMessage('API Connection Test Failed: WP_Error', \Sentry\Severity::error());
            });
            
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $error_message
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $api_base_url = $this->api_base_url;
            
            // Log to Sentry
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($response_code, $url, $api_base_url) {
                $scope->setContext('api_error', [
                    'response_code' => $response_code,
                    'url' => $url,
                    'api_base_url' => $api_base_url
                ]);
                \Sentry\captureMessage('API Connection Test Failed: Non-200 Response', \Sentry\Severity::error());
            });
            
            return array(
                'success' => false,
                'message' => 'API returned error code: ' . $response_code
            );
        }

        return array(
            'success' => true,
            'message' => 'Connection successful!',
            'api_url' => $this->api_base_url
        );
    }
}
