<?php
/**
 * Authentication Middleware
 * 
 * Handles JWT token validation for protected endpoints
 * 
 * @package GlobalAPI
 * @subpackage API
 * @since 2.0.4
 */

defined('ABSPATH') || exit;

/**
 * Class GlobalAPI_Auth_Middleware
 * 
 * Middleware for JWT authentication
 */
class GlobalAPI_Auth_Middleware {

    /**
     * JWT Secret Key
     * 
     * @var string
     */
    private $jwt_secret;

    /**
     * Protected endpoints patterns
     * 
     * @var array
     */
    private $protected_endpoints = array(
        '/globalapi/v1/credentials',
        '/globalapi/v1/logs',
        '/globalapi/v1/auth/validate',
        '/globalapi/v1/auth/refresh',
        '/globalapi/v1/auth/logout',
        '/globalapi/v1/auth/me',
        // '/globalapi/v1/proxy', // Los endpoints del proxy manejan su propia autenticación
    );

    /**
     * Public endpoints (no authentication required)
     * 
     * @var array
     */
    private $public_endpoints = array(
        '/globalapi/v1/auth/login',
        '/globalapi/v1/auth/password-reset',
        '/globalapi/v1/auth/oauth/url',
        '/globalapi/v1/auth/oauth/callback',
        '/globalapi/v1/status', // Status endpoint público
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->jwt_secret = $this->get_jwt_secret();
        
        // Hook into REST API request process
        add_filter('rest_request_before_callbacks', array($this, 'authenticate_request'), 10, 3);
        add_filter('rest_authentication_errors', array($this, 'check_authentication_error'));
        add_action('rest_api_init', array($this, 'add_cors_support'));
    }

    /**
     * Authenticate REST API request
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Response to replace the requested version with
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
     */
    public function authenticate_request($response, $server, $request) {
        $route = $request->get_route();

        // Debug logging
        error_log('GlobalAPI Auth Middleware - Route: ' . $route);
        error_log('GlobalAPI Auth Middleware - Is GlobalAPI: ' . ($this->is_globalapi_endpoint($route) ? 'YES' : 'NO'));
        error_log('GlobalAPI Auth Middleware - Is Protected: ' . ($this->is_protected_endpoint($route) ? 'YES' : 'NO'));
        error_log('GlobalAPI Auth Middleware - Is Public: ' . ($this->is_public_endpoint($route) ? 'YES' : 'NO'));

        // Skip authentication for public endpoints
        if ($this->is_public_endpoint($route)) {
            return $response;
        }

        // Skip authentication for non-GlobalAPI endpoints
        if (!$this->is_globalapi_endpoint($route)) {
            return $response;
        }

        // Check if endpoint requires authentication
        if (!$this->is_protected_endpoint($route)) {
            return $response;
        }

        // Get JWT token from request
        $token = $this->get_jwt_token_from_request($request);

        if (!$token) {
            return new WP_Error(
                'jwt_auth_no_auth_header',
                __('Authorization header not found.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Validate JWT token
        $user = $this->validate_jwt_token($token);

        if (is_wp_error($user)) {
            return $user;
        }

        // Set current user for the request
        wp_set_current_user($user->ID);

        // Log successful authentication
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::log_api_activity(
                'api_authenticated',
                array(
                    'route' => $route,
                    'method' => $request->get_method()
                ),
                $user->ID
            );
        }

        return $response;
    }

    /**
     * Check for authentication errors
     * 
     * @param WP_Error|mixed $result Authentication result
     * @return WP_Error|mixed
     */
    public function check_authentication_error($result) {
        // If there's already an error, return it
        if (is_wp_error($result)) {
            return $result;
        }

        // If no user is set and we're on a protected GlobalAPI endpoint
        if (!get_current_user_id()) {
            $request = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
            
            error_log('GlobalAPI Auth Middleware - Check Auth Error - Route: ' . $request);
            error_log('GlobalAPI Auth Middleware - Check Auth Error - User ID: ' . get_current_user_id());
            
            if ($this->is_globalapi_endpoint($request) && $this->is_protected_endpoint($request)) {
                error_log('GlobalAPI Auth Middleware - Check Auth Error - Returning jwt_auth_no_user error');
                return new WP_Error(
                    'jwt_auth_no_user',
                    __('Authentication required.', 'globalapi'),
                    array('status' => 401)
                );
            }
        }

        return $result;
    }

    /**
     * Add CORS support for API requests
     */
    public function add_cors_support() {
        // Remove default CORS headers to avoid conflicts
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        
        // Add custom CORS headers
        add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 15, 4);
    }

    /**
     * Add CORS headers to response
     * 
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Result to send to the client
     * @param WP_REST_Request $request Request object
     * @param WP_REST_Server $server Server instance
     * @return bool
     */
    public function add_cors_headers($served, $result, $request, $server) {
        $origin = get_http_origin();
        $allowed_origins = $this->get_allowed_origins();

        // Check if origin is allowed
        if ($origin && in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif (empty($allowed_origins) || in_array('*', $allowed_origins)) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages');

        // Handle preflight requests
        if ($request->get_method() === 'OPTIONS') {
            status_header(200);
            exit;
        }

        return $served;
    }

    /**
     * Check if endpoint is public (no authentication required)
     * 
     * @param string $route Route path
     * @return bool
     */
    private function is_public_endpoint($route) {
        foreach ($this->public_endpoints as $pattern) {
            if (strpos($route, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if endpoint is protected (authentication required)
     * 
     * @param string $route Route path
     * @return bool
     */
    private function is_protected_endpoint($route) {
        foreach ($this->protected_endpoints as $pattern) {
            if (strpos($route, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if endpoint belongs to GlobalAPI
     * 
     * @param string $route Route path
     * @return bool
     */
    private function is_globalapi_endpoint($route) {
        return strpos($route, '/globalapi/') === 0;
    }

    /**
     * Get JWT token from request
     * 
     * @param WP_REST_Request $request Request object
     * @return string|null
     */
    private function get_jwt_token_from_request($request) {
        // Try Authorization header first
        $auth_header = $request->get_header('Authorization');

        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        // Try X-Authorization header as fallback
        $x_auth_header = $request->get_header('X-Authorization');

        if ($x_auth_header && preg_match('/Bearer\s+(.*)$/i', $x_auth_header, $matches)) {
            return $matches[1];
        }

        // Try query parameter as last resort (not recommended for production)
        $token = $request->get_param('access_token');

        if ($token) {
            return sanitize_text_field($token);
        }

        return null;
    }

    /**
     * Validate JWT token (usando el mismo método que AuthController)
     * 
     * @param string $token JWT token
     * @return WP_User|WP_Error
     */
    private function validate_jwt_token($token) {
        $token_parts = explode('.', $token);
        
        if (count($token_parts) !== 3) {
            return new WP_Error(
                'jwt_invalid_format',
                __('Invalid token format.', 'globalapi'),
                array('status' => 401)
            );
        }

        list($header, $payload, $signature) = $token_parts;

        // Verificar firma
        $expected_signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, $this->jwt_secret));
        
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error(
                'jwt_invalid_signature',
                __('Invalid token signature.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Decodificar payload
        $payload_data = json_decode(base64_decode($payload), true);
        
        if (!$payload_data) {
            return new WP_Error(
                'jwt_invalid_payload',
                __('Invalid token payload.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Verificar expiración
        if ($payload_data['exp'] < time()) {
            return new WP_Error(
                'jwt_token_expired',
                __('Token has expired.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Verificar que tenga user_id
        if (!isset($payload_data['user_id'])) {
            return new WP_Error(
                'jwt_invalid_token',
                __('Invalid token structure.', 'globalapi'),
                array('status' => 401)
            );
        }

        $user = get_user_by('id', $payload_data['user_id']);

        if (!$user) {
            return new WP_Error(
                'jwt_user_not_found',
                __('User not found.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Check if user is still active
        if (!user_can($user, 'read')) {
            return new WP_Error(
                'jwt_user_inactive',
                __('User account is inactive.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Check if token has been invalidated
        $invalidated = get_user_meta($user->ID, 'jwt_token_invalidated', true);
        if ($invalidated && strtotime($invalidated) > $payload_data['iat']) {
            return new WP_Error(
                'jwt_token_invalidated',
                __('Token has been invalidated.', 'globalapi'),
                array('status' => 401)
            );
        }

        // Update last activity
        update_user_meta($user->ID, 'last_api_activity', current_time('mysql'));

        return $user;
    }

    /**
     * Get JWT secret key
     * 
     * @return string
     */
    private function get_jwt_secret() {
        $secret = get_option('globalapi_jwt_secret');

        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('globalapi_jwt_secret', $secret);
        }

        return $secret;
    }

    /**
     * Get allowed origins for CORS
     * 
     * @return array
     */
    private function get_allowed_origins() {
        $origins = get_option('globalapi_allowed_origins', array());

        // Add default allowed origins
        $default_origins = array(
            home_url(),
            admin_url(),
        );

        // Add mobile app origins if configured
        $mobile_origins = get_option('globalapi_mobile_origins', array());

        $all_origins = array_merge($default_origins, $origins, $mobile_origins);

        return apply_filters('globalapi_allowed_origins', $all_origins);
    }

    /**
     * Add protected endpoint pattern
     * 
     * @param string $pattern Endpoint pattern
     */
    public function add_protected_endpoint($pattern) {
        if (!in_array($pattern, $this->protected_endpoints)) {
            $this->protected_endpoints[] = $pattern;
        }
    }

    /**
     * Add public endpoint pattern
     * 
     * @param string $pattern Endpoint pattern
     */
    public function add_public_endpoint($pattern) {
        if (!in_array($pattern, $this->public_endpoints)) {
            $this->public_endpoints[] = $pattern;
        }
    }

    /**
     * Remove protected endpoint pattern
     * 
     * @param string $pattern Endpoint pattern
     */
    public function remove_protected_endpoint($pattern) {
        $key = array_search($pattern, $this->protected_endpoints);
        if ($key !== false) {
            unset($this->protected_endpoints[$key]);
        }
    }
} 