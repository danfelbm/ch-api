<?php
/**
 * Rate Limiter for WordPress API
 * 
 * Controls request rates using WordPress transients
 * 
 * @package GlobalAPI
 * @subpackage API
 * @since 2.0.4
 */

defined('ABSPATH') || exit;

/**
 * Class GlobalAPI_Rate_Limiter
 * 
 * Rate limiting functionality for API endpoints
 */
class GlobalAPI_Rate_Limiter {

    /**
     * Default rate limits configuration
     * 
     * @var array
     */
    private static $default_limits = array(
        'login' => array('max' => 5, 'window' => 300), // 5 attempts per 5 minutes
        'api_general' => array('max' => 100, 'window' => 3600), // 100 requests per hour
        'api_read' => array('max' => 200, 'window' => 3600), // 200 read requests per hour
        'api_write' => array('max' => 50, 'window' => 3600), // 50 write requests per hour
        'password_reset' => array('max' => 3, 'window' => 900), // 3 attempts per 15 minutes
        'export' => array('max' => 5, 'window' => 3600), // 5 exports per hour
        'cleanup' => array('max' => 2, 'window' => 86400), // 2 cleanups per day
    );

    /**
     * Rate limit enabled/disabled
     * 
     * @var bool
     */
    private static $enabled = true;

    /**
     * Initialize rate limiter
     */
    public static function init() {
        self::$enabled = get_option('globalapi_rate_limit_enabled', true);
        
        // Hook into API requests
        add_action('rest_api_init', array(__CLASS__, 'setup_rate_limiting'));
        
        // Add admin hooks for configuration
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // Cleanup expired rate limit entries
        add_action('globalapi_cleanup_rate_limits', array(__CLASS__, 'cleanup_expired_entries'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('globalapi_cleanup_rate_limits')) {
            wp_schedule_event(time(), 'hourly', 'globalapi_cleanup_rate_limits');
        }
    }

    /**
     * Setup rate limiting hooks
     */
    public static function setup_rate_limiting() {
        if (!self::$enabled) {
            return;
        }

        // Hook into REST request before processing
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'check_rate_limit_before_request'), 5, 3);
    }

    /**
     * Check rate limit before processing request
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Response
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
     */
    public static function check_rate_limit_before_request($response, $server, $request) {
        $route = $request->get_route();

        // Only apply to GlobalAPI endpoints
        if (strpos($route, '/globalapi/') !== 0) {
            return $response;
        }

        $action = self::get_action_from_route($route, $request->get_method());
        $identifier = self::get_identifier($request);

        if (!self::check_limit($action, $identifier)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'globalapi'),
                array(
                    'status' => 429,
                    'headers' => self::get_rate_limit_headers($action, $identifier)
                )
            );
        }

        return $response;
    }

    /**
     * Check if action is within rate limit
     * 
     * @param string $action Action identifier
     * @param string|null $identifier Unique identifier (IP, user, etc.)
     * @return bool
     */
    public static function check_limit($action, $identifier = null) {
        if (!self::$enabled) {
            return true;
        }

        if (!$identifier) {
            $identifier = self::get_default_identifier();
        }

        $limits = self::get_limits_for_action($action);
        $key = self::get_cache_key($action, $identifier);

        $current_count = get_transient($key) ?: 0;

        if ($current_count >= $limits['max']) {
            // Log rate limit violation
            self::log_rate_limit_violation($action, $identifier, $current_count, $limits);
            return false;
        }

        // Increment counter
        set_transient($key, $current_count + 1, $limits['window']);

        // Set additional tracking
        self::track_usage($action, $identifier);

        return true;
    }

    /**
     * Force check rate limit and increment counter
     * 
     * @param string $action Action identifier
     * @param string|null $identifier Unique identifier
     * @return bool
     */
    public static function increment_and_check($action, $identifier = null) {
        if (!self::$enabled) {
            return true;
        }

        if (!$identifier) {
            $identifier = self::get_default_identifier();
        }

        $limits = self::get_limits_for_action($action);
        $key = self::get_cache_key($action, $identifier);

        $current_count = get_transient($key) ?: 0;
        $new_count = $current_count + 1;

        // Set new count
        set_transient($key, $new_count, $limits['window']);

        if ($new_count > $limits['max']) {
            self::log_rate_limit_violation($action, $identifier, $new_count, $limits);
            return false;
        }

        self::track_usage($action, $identifier);
        return true;
    }

    /**
     * Get current usage for action
     * 
     * @param string $action Action identifier
     * @param string|null $identifier Unique identifier
     * @return array
     */
    public static function get_usage($action, $identifier = null) {
        if (!$identifier) {
            $identifier = self::get_default_identifier();
        }

        $limits = self::get_limits_for_action($action);
        $key = self::get_cache_key($action, $identifier);

        $current_count = get_transient($key) ?: 0;
        $remaining = max(0, $limits['max'] - $current_count);
        $reset_time = self::get_reset_time($key);

        return array(
            'action' => $action,
            'identifier' => $identifier,
            'limit' => $limits['max'],
            'used' => $current_count,
            'remaining' => $remaining,
            'reset_time' => $reset_time,
            'window' => $limits['window'],
        );
    }

    /**
     * Reset rate limit for specific action and identifier
     * 
     * @param string $action Action identifier
     * @param string|null $identifier Unique identifier
     * @return bool
     */
    public static function reset_limit($action, $identifier = null) {
        if (!$identifier) {
            $identifier = self::get_default_identifier();
        }

        $key = self::get_cache_key($action, $identifier);
        return delete_transient($key);
    }

    /**
     * Get rate limit headers for HTTP response
     * 
     * @param string $action Action identifier
     * @param string|null $identifier Unique identifier
     * @return array
     */
    public static function get_rate_limit_headers($action, $identifier = null) {
        $usage = self::get_usage($action, $identifier);

        return array(
            'X-RateLimit-Limit' => $usage['limit'],
            'X-RateLimit-Remaining' => $usage['remaining'],
            'X-RateLimit-Reset' => $usage['reset_time'],
            'X-RateLimit-Window' => $usage['window'],
        );
    }

    /**
     * Get action identifier from route and method
     * 
     * @param string $route Route path
     * @param string $method HTTP method
     * @return string
     */
    private static function get_action_from_route($route, $method) {
        // Authentication endpoints
        if (strpos($route, '/auth/login') !== false) {
            return 'login';
        }
        if (strpos($route, '/auth/password-reset') !== false) {
            return 'password_reset';
        }

        // Export endpoints
        if (strpos($route, '/export') !== false) {
            return 'export';
        }

        // Cleanup endpoints
        if (strpos($route, '/cleanup') !== false) {
            return 'cleanup';
        }

        // Determine by HTTP method
        switch (strtoupper($method)) {
            case 'GET':
            case 'HEAD':
            case 'OPTIONS':
                return 'api_read';
            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                return 'api_write';
            default:
                return 'api_general';
        }
    }

    /**
     * Get unique identifier for rate limiting
     * 
     * @param WP_REST_Request|null $request Request object
     * @return string
     */
    private static function get_identifier($request = null) {
        $identifier_parts = array();

        // Add user ID if authenticated
        $user_id = get_current_user_id();
        if ($user_id) {
            $identifier_parts[] = 'user_' . $user_id;
        }

        // Add IP address
        $ip = self::get_client_ip();
        if ($ip) {
            $identifier_parts[] = 'ip_' . $ip;
        }

        // Add user agent hash for additional uniqueness
        if ($request) {
            $user_agent = $request->get_header('User-Agent');
            if ($user_agent) {
                $identifier_parts[] = 'ua_' . substr(md5($user_agent), 0, 8);
            }
        }

        return implode('_', $identifier_parts) ?: 'anonymous';
    }

    /**
     * Get default identifier when none provided
     * 
     * @return string
     */
    private static function get_default_identifier() {
        return self::get_identifier();
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get rate limits for specific action
     * 
     * @param string $action Action identifier
     * @return array
     */
    private static function get_limits_for_action($action) {
        $custom_limits = get_option('globalapi_rate_limits', array());
        
        if (isset($custom_limits[$action])) {
            return $custom_limits[$action];
        }

        return self::$default_limits[$action] ?? self::$default_limits['api_general'];
    }

    /**
     * Get cache key for rate limiting
     * 
     * @param string $action Action identifier
     * @param string $identifier Unique identifier
     * @return string
     */
    private static function get_cache_key($action, $identifier) {
        return 'globalapi_rate_limit_' . $action . '_' . $identifier;
    }

    /**
     * Get reset time for cache key
     * 
     * @param string $key Cache key
     * @return int
     */
    private static function get_reset_time($key) {
        global $wpdb;

        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            '_transient_timeout_' . $key
        ));

        return $timeout ? (int) $timeout : (time() + 3600);
    }

    /**
     * Track usage statistics
     * 
     * @param string $action Action identifier
     * @param string $identifier Unique identifier
     */
    private static function track_usage($action, $identifier) {
        $stats_key = 'globalapi_rate_limit_stats_' . date('Y-m-d');
        $stats = get_transient($stats_key) ?: array();

        if (!isset($stats[$action])) {
            $stats[$action] = 0;
        }

        $stats[$action]++;
        set_transient($stats_key, $stats, DAY_IN_SECONDS);
    }

    /**
     * Log rate limit violation
     * 
     * @param string $action Action identifier
     * @param string $identifier Unique identifier
     * @param int $count Current count
     * @param array $limits Limit configuration
     */
    private static function log_rate_limit_violation($action, $identifier, $count, $limits) {
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::log_api_activity(
                'rate_limit_exceeded',
                array(
                    'action' => $action,
                    'identifier' => $identifier,
                    'count' => $count,
                    'limit' => $limits['max'],
                    'window' => $limits['window']
                ),
                get_current_user_id() ?: null
            );
        }

        // Also log to error log for monitoring
        error_log(sprintf(
            'GlobalAPI Rate Limit Exceeded: action=%s, identifier=%s, count=%d, limit=%d',
            $action,
            $identifier,
            $count,
            $limits['max']
        ));
    }

    /**
     * Cleanup expired rate limit entries
     */
    public static function cleanup_expired_entries() {
        global $wpdb;

        // Delete expired transients related to rate limiting
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_globalapi_rate_limit_%' 
            AND option_name NOT LIKE '_transient_timeout_%'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->options} timeout_option 
                WHERE timeout_option.option_name = CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
                AND timeout_option.option_value > UNIX_TIMESTAMP()
            )
        ");

        // Also cleanup the timeout entries
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_globalapi_rate_limit_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
    }

    /**
     * Get rate limit statistics
     * 
     * @param string $period Period ('today', 'week', 'month')
     * @return array
     */
    public static function get_statistics($period = 'today') {
        $stats = array();

        switch ($period) {
            case 'today':
                $key = 'globalapi_rate_limit_stats_' . date('Y-m-d');
                $stats = get_transient($key) ?: array();
                break;
                
            case 'week':
                for ($i = 0; $i < 7; $i++) {
                    $date = date('Y-m-d', strtotime("-{$i} days"));
                    $day_stats = get_transient('globalapi_rate_limit_stats_' . $date) ?: array();
                    
                    foreach ($day_stats as $action => $count) {
                        if (!isset($stats[$action])) {
                            $stats[$action] = 0;
                        }
                        $stats[$action] += $count;
                    }
                }
                break;
                
            case 'month':
                for ($i = 0; $i < 30; $i++) {
                    $date = date('Y-m-d', strtotime("-{$i} days"));
                    $day_stats = get_transient('globalapi_rate_limit_stats_' . $date) ?: array();
                    
                    foreach ($day_stats as $action => $count) {
                        if (!isset($stats[$action])) {
                            $stats[$action] = 0;
                        }
                        $stats[$action] += $count;
                    }
                }
                break;
        }

        return $stats;
    }

    /**
     * Register admin settings
     */
    public static function register_settings() {
        register_setting('globalapi_settings', 'globalapi_rate_limit_enabled');
        register_setting('globalapi_settings', 'globalapi_rate_limits');
    }

    /**
     * Enable rate limiting
     */
    public static function enable() {
        self::$enabled = true;
        update_option('globalapi_rate_limit_enabled', true);
    }

    /**
     * Disable rate limiting
     */
    public static function disable() {
        self::$enabled = false;
        update_option('globalapi_rate_limit_enabled', false);
    }

    /**
     * Check if rate limiting is enabled
     * 
     * @return bool
     */
    public static function is_enabled() {
        return self::$enabled;
    }

    /**
     * Set custom limits for action
     * 
     * @param string $action Action identifier
     * @param int $max Maximum requests
     * @param int $window Time window in seconds
     */
    public static function set_limit($action, $max, $window) {
        $limits = get_option('globalapi_rate_limits', array());
        $limits[$action] = array('max' => (int) $max, 'window' => (int) $window);
        update_option('globalapi_rate_limits', $limits);
    }

    /**
     * Remove custom limit for action
     * 
     * @param string $action Action identifier
     */
    public static function remove_limit($action) {
        $limits = get_option('globalapi_rate_limits', array());
        if (isset($limits[$action])) {
            unset($limits[$action]);
            update_option('globalapi_rate_limits', $limits);
        }
    }

    /**
     * Get all configured limits
     * 
     * @return array
     */
    public static function get_all_limits() {
        $custom_limits = get_option('globalapi_rate_limits', array());
        return array_merge(self::$default_limits, $custom_limits);
    }

    /**
     * Reset all rate limits
     */
    public static function reset_all_limits() {
        global $wpdb;

        // Delete all rate limit transients
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_globalapi_rate_limit_%' 
            OR option_name LIKE '_transient_timeout_globalapi_rate_limit_%'
        ");
    }
} 