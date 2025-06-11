<?php
/**
 * Controlador REST API para Autenticación - Plugin GlobalAPI
 *
 * Maneja autenticación JWT para la aplicación móvil y servicios externos.
 * Proporciona endpoints para login, logout, verificación de tokens y refresh
 * de sesiones con integración OAuth para InvisionCommunity.
 *
 * @since 2.0.0
 * @package GlobalAPI
 * @subpackage API
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controlador REST API para autenticación JWT
 *
 * Endpoints disponibles:
 * - POST /globalapi/v1/auth/login - Iniciar sesión
 * - POST /globalapi/v1/auth/logout - Cerrar sesión
 * - POST /globalapi/v1/auth/refresh - Renovar token
 * - GET /globalapi/v1/auth/verify - Verificar token
 * - POST /globalapi/v1/auth/oauth/callback - Callback OAuth
 * - GET /globalapi/v1/auth/user - Datos del usuario actual
 *
 * @since 2.0.0
 */
class GlobalAPI_Auth_Controller extends GlobalAPI_REST_Controller {

    /**
     * Base del endpoint
     *
     * @since 2.0.0
     * @var string
     */
    protected $rest_base = 'auth';

    /**
     * Duración del token JWT en segundos (24 horas)
     *
     * @since 2.0.0
     * @var int
     */
    protected $token_duration = 86400;

    /**
     * Duración del refresh token en segundos (7 días)
     *
     * @since 2.0.0
     * @var int
     */
    protected $refresh_duration = 604800;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct();
        
        // Configurar duraciones desde opciones de WordPress
        $this->token_duration = get_option('globalapi_jwt_token_duration', $this->token_duration);
        $this->refresh_duration = get_option('globalapi_jwt_refresh_duration', $this->refresh_duration);
    }

    /**
     * Obtener base para las rutas REST
     *
     * @since 2.0.0
     * @return string Base de las rutas
     */
    protected function get_rest_base() {
        return $this->rest_base;
    }

    /**
     * Registrar las rutas del controlador
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Ruta para login
        register_rest_route($this->namespace, '/' . $this->rest_base . '/login', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'login'),
                'permission_callback' => '__return_true', // Endpoint público
                'args' => array(
                    'username' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Nombre de usuario o email.', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'password' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Contraseña del usuario.', 'globalapi')
                    ),
                    'remember' => array(
                        'type' => 'boolean',
                        'default' => false,
                        'description' => __('Recordar sesión (token de larga duración).', 'globalapi')
                    )
                )
            )
        ));

        // Ruta para logout
        register_rest_route($this->namespace, '/' . $this->rest_base . '/logout', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'logout'),
                'permission_callback' => array($this, 'check_auth_permissions')
            )
        ));

        // Ruta para refresh token
        register_rest_route($this->namespace, '/' . $this->rest_base . '/refresh', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'refresh_token'),
                'permission_callback' => '__return_true', // Verificación manual
                'args' => array(
                    'refresh_token' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Token de refresh.', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            )
        ));

        // Ruta para verificar token
        register_rest_route($this->namespace, '/' . $this->rest_base . '/verify', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'verify_token'),
                'permission_callback' => array($this, 'check_auth_permissions')
            )
        ));

        // Ruta para datos del usuario actual
        register_rest_route($this->namespace, '/' . $this->rest_base . '/user', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_current_user'),
                'permission_callback' => array($this, 'check_auth_permissions')
            )
        ));

        // Ruta para OAuth callback (InvisionCommunity) - acepta GET y POST
        register_rest_route($this->namespace, '/' . $this->rest_base . '/oauth/callback', array(
            array(
                'methods' => array(WP_REST_Server::READABLE, WP_REST_Server::CREATABLE),
                'callback' => array($this, 'oauth_callback'),
                'permission_callback' => '__return_true', // Endpoint público
                'args' => array(
                    'code' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Código de autorización OAuth.', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'state' => array(
                        'type' => 'string',
                        'description' => __('Estado OAuth para verificación CSRF.', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            )
        ));

        // Ruta para OAuth URL de autorización
        register_rest_route($this->namespace, '/' . $this->rest_base . '/oauth/url', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_oauth_url'),
                'permission_callback' => '__return_true', // Endpoint público
                'args' => array(
                    'redirect_uri' => array(
                        'type' => 'string',
                        'description' => __('URI de redirección personalizada.', 'globalapi'),
                        'sanitize_callback' => 'esc_url_raw'
                    )
                )
            )
        ));
    }

    /**
     * Verificar permisos de autenticación
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function check_auth_permissions($request) {
        $token = $this->get_token_from_request($request);
        
        if (empty($token)) {
            return $this->error_response(
                'rest_token_missing',
                __('Token de autorización requerido.', 'globalapi'),
                null,
                401
            );
        }

        $token_data = $this->verify_jwt_token($token);
        if (is_wp_error($token_data)) {
            return $token_data;
        }

        // Establecer usuario actual para el resto de la petición
        wp_set_current_user($token_data['user_id']);

        return true;
    }

    /**
     * Iniciar sesión
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function login($request) {
        // Verificar rate limiting
        $rate_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $remember = $request->get_param('remember');

        // Validar parámetros
        if (empty($username) || empty($password)) {
            return $this->error_response(
                'rest_login_invalid_credentials',
                __('Credenciales de login requeridas.', 'globalapi'),
                null,
                400
            );
        }

        // Intentar autenticación
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            // Log del intento fallido
            $this->log_auth_attempt($username, false, $user->get_error_message());
            
            return $this->error_response(
                'rest_login_failed',
                __('Credenciales inválidas.', 'globalapi'),
                array('attempts_remaining' => $this->get_remaining_attempts($username)),
                401
            );
        }

        // Verificar que el usuario tenga permisos para usar la API
        if (!user_can($user->ID, 'read')) {
            return $this->error_response(
                'rest_login_forbidden',
                __('El usuario no tiene permisos para usar la API.', 'globalapi'),
                null,
                403
            );
        }

        // Generar tokens JWT
        $token_duration = $remember ? ($this->token_duration * 7) : $this->token_duration;
        
        $access_token = $this->generate_jwt_token($user->ID, $token_duration);
        $refresh_token = $this->generate_refresh_token($user->ID);

        // Guardar refresh token en la base de datos
        $this->save_refresh_token($user->ID, $refresh_token);

        // Log del login exitoso
        $this->log_auth_attempt($username, true);

        // Preparar respuesta
        $response_data = array(
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => $token_duration,
            'user' => $this->prepare_user_data($user)
        );

        return $this->success_response(
            $response_data,
            sprintf(__('Bienvenido, %s.', 'globalapi'), $user->display_name)
        );
    }

    /**
     * Cerrar sesión
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function logout($request) {
        $user_id = get_current_user_id();
        
        // Invalidar refresh tokens del usuario
        $this->invalidate_user_refresh_tokens($user_id);

        // Log del logout
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'user_logout',
                sprintf(__('Usuario %s cerró sesión vía API.', 'globalapi'), wp_get_current_user()->user_login),
                array(
                    'usuario_id' => $user_id,
                    'ip_cliente' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ),
                'info'
            );
        }

        return $this->success_response(
            array('logged_out' => true),
            __('Sesión cerrada correctamente.', 'globalapi')
        );
    }

    /**
     * Renovar token de acceso
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function refresh_token($request) {
        $refresh_token = $request->get_param('refresh_token');

        if (empty($refresh_token)) {
            return $this->error_response(
                'rest_refresh_token_missing',
                __('Refresh token requerido.', 'globalapi'),
                null,
                400
            );
        }

        // Verificar refresh token
        $token_data = $this->verify_refresh_token($refresh_token);
        if (is_wp_error($token_data)) {
            return $token_data;
        }

        $user_id = $token_data['user_id'];
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return $this->error_response(
                'rest_user_not_found',
                __('Usuario no encontrado.', 'globalapi'),
                null,
                404
            );
        }

        // Generar nuevo access token
        $new_access_token = $this->generate_jwt_token($user_id, $this->token_duration);
        $new_refresh_token = $this->generate_refresh_token($user_id);

        // Actualizar refresh token en la base de datos
        $this->update_refresh_token($refresh_token, $new_refresh_token);

        $response_data = array(
            'access_token' => $new_access_token,
            'refresh_token' => $new_refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => $this->token_duration
        );

        return $this->success_response(
            $response_data,
            __('Token renovado correctamente.', 'globalapi')
        );
    }

    /**
     * Verificar token de acceso
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function verify_token($request) {
        $user_id = get_current_user_id();
        $user = get_user_by('ID', $user_id);

        $token_info = array(
            'valid' => true,
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'capabilities' => array_keys($user->allcaps, true)
        );

        return $this->success_response(
            $token_info,
            __('Token válido.', 'globalapi')
        );
    }

    /**
     * Obtener datos del usuario actual
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function get_current_user($request) {
        $user = wp_get_current_user();
        $user_data = $this->prepare_user_data($user);

        return $this->success_response(
            $user_data,
            __('Datos del usuario obtenidos correctamente.', 'globalapi')
        );
    }

    /**
     * Callback OAuth para InvisionCommunity
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function oauth_callback($request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');

        if (empty($code)) {
            return $this->error_response(
                'rest_oauth_invalid_code',
                __('Código de autorización OAuth inválido.', 'globalapi'),
                null,
                400
            );
        }

        // Verificar estado CSRF si se proporcionó
        if (!empty($state)) {
            $stored_state = get_transient('globalapi_oauth_state_' . $state);
            if (!$stored_state) {
                return $this->error_response(
                    'rest_oauth_invalid_state',
                    __('Estado OAuth inválido o expirado.', 'globalapi'),
                    null,
                    400
                );
            }
            delete_transient('globalapi_oauth_state_' . $state);
        }

        // Intercambiar código por token con InvisionCommunity
        $oauth_result = $this->exchange_oauth_code($code);
        if (is_wp_error($oauth_result)) {
            return $oauth_result;
        }

        // Obtener datos del usuario de InvisionCommunity
        $user_data = $this->get_oauth_user_data($oauth_result['access_token']);
        if (is_wp_error($user_data)) {
            return $user_data;
        }

        // Crear o actualizar sesión (sin crear wp_user)
        $session_data = $this->create_or_update_oauth_session($user_data, $oauth_result);
        if (is_wp_error($session_data)) {
            return $session_data;
        }

        // Generar tokens JWT para la sesión
        $access_token = $this->generate_jwt_token($session_data['session_id'], $this->token_duration);
        $refresh_token = $this->generate_refresh_token($session_data['session_id']);

        // Actualizar la sesión con los tokens
        $this->update_session_tokens($session_data['session_id'], $access_token, $refresh_token);

        $response_data = array(
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => $this->token_duration,
            'user' => $this->prepare_invision_user_data($user_data),
            'oauth_provider' => 'invision'
        );

        return $this->success_response(
            $response_data,
            sprintf(__('Bienvenido vía OAuth, %s.', 'globalapi'), $user_data['displayName'] ?? $user_data['name'] ?? 'Usuario')
        );
    }

    /**
     * Obtener URL de autorización OAuth
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function get_oauth_url($request) {
        // Obtener credenciales OAuth de InvisionCommunity
        $oauth_creds = $this->get_oauth_credentials();
        if (is_wp_error($oauth_creds)) {
            return $oauth_creds;
        }

        // Generar estado CSRF
        $state = wp_generate_password(32, false);
        set_transient('globalapi_oauth_state_' . $state, true, 300); // 5 minutos

        // Construir URL de autorización con redirect URI personalizable
        $default_redirect_uri = home_url('/index.php?rest_route=/' . $this->namespace . '/' . $this->rest_base . '/oauth/callback');
        $redirect_uri = $request->get_param('redirect_uri') ?: $default_redirect_uri;
        
        $auth_url = add_query_arg(array(
            'response_type' => 'code',
            'client_id' => $oauth_creds['client_id'],
            'redirect_uri' => urlencode($redirect_uri),
            'scope' => 'profile email',
            'state' => $state
        ), $oauth_creds['auth_url']);

        return $this->success_response(
            array(
                'auth_url' => $auth_url,
                'state' => $state,
                'redirect_uri' => $redirect_uri
            ),
            __('URL de autorización OAuth generada.', 'globalapi')
        );
    }

    /**
     * Generar token JWT
     *
     * @since 2.0.0
     * @param string $session_id ID de la sesión
     * @param int $duration Duración en segundos
     * @return string Token JWT
     */
    protected function generate_jwt_token($session_id, $duration = null) {
        if (!$duration) {
            $duration = $this->token_duration;
        }

        $issued_at = time();
        $expiration = $issued_at + $duration;

        // Obtener datos de la sesión
        global $wpdb;
        $table_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_sesiones} WHERE id = %s AND activo = 1",
            $session_id
        ));

        if (!$session) {
            return new WP_Error('session_not_found', 'Sesión no encontrada');
        }

        // Decodificar datos de la sesión
        $session_data = json_decode($session->datos_sesion, true);
        $invision_data = $session_data['invision_user'] ?? array();
        
        // Extraer información relevante de InvisionCommunity
        $invision_groups = array();
        $primary_group = null;
        $secondary_groups = array();
        
        if (!empty($invision_data)) {
            // Grupo primario
            if (isset($invision_data['primaryGroup'])) {
                $primary_group = $invision_data['primaryGroup'];
                $invision_groups[] = $primary_group['name'] ?? $primary_group['id'] ?? null;
            }
            
            // Grupos secundarios
            if (isset($invision_data['secondaryGroups']) && is_array($invision_data['secondaryGroups'])) {
                foreach ($invision_data['secondaryGroups'] as $group) {
                    $group_name = $group['name'] ?? $group['id'] ?? null;
                    if ($group_name) {
                        $secondary_groups[] = $group_name;
                        $invision_groups[] = $group_name;
                    }
                }
            }
        }

        $payload = array(
            'iss' => home_url(), // Emisor
            'aud' => 'globalapi', // Audiencia
            'iat' => $issued_at, // Emitido en
            'exp' => $expiration, // Expira en
            'session_id' => $session_id,
            'invision_user_id' => $invision_data['id'] ?? null,
            'invision_username' => $invision_data['name'] ?? null,
            'invision_email' => $invision_data['email'] ?? null,
            // Información de grupos de InvisionCommunity
            'invision_groups' => $invision_groups,
            'invision_primary_group' => $primary_group,
            'invision_secondary_groups' => $secondary_groups,
            // Para compatibilidad con validaciones existentes
            'user_roles' => $invision_groups,
            'roles' => $invision_groups,
            'jti' => wp_generate_password(32, false) // ID único del token
        );

        // En un entorno real, usar una librería JWT como Firebase JWT
        // Por ahora, usar un método simplificado con hash
        $header = base64_encode(json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
        $payload_encoded = base64_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $header . '.' . $payload_encoded, $this->get_jwt_secret());
        
        return $header . '.' . $payload_encoded . '.' . base64_encode($signature);
    }

    /**
     * Verificar token JWT
     *
     * @since 2.0.0
     * @param string $token Token JWT
     * @return array|WP_Error Datos del token o error
     */
    protected function verify_jwt_token($token) {
        $token_parts = explode('.', $token);
        
        if (count($token_parts) !== 3) {
            return $this->error_response(
                'rest_token_invalid_format',
                __('Formato de token inválido.', 'globalapi'),
                null,
                401
            );
        }

        list($header, $payload, $signature) = $token_parts;

        // Verificar firma
        $expected_signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, $this->get_jwt_secret()));
        
        if (!hash_equals($expected_signature, $signature)) {
            return $this->error_response(
                'rest_token_invalid_signature',
                __('Firma de token inválida.', 'globalapi'),
                null,
                401
            );
        }

        // Decodificar payload
        $payload_data = json_decode(base64_decode($payload), true);
        
        if (!$payload_data) {
            return $this->error_response(
                'rest_token_invalid_payload',
                __('Payload de token inválido.', 'globalapi'),
                null,
                401
            );
        }

        // Verificar expiración
        if ($payload_data['exp'] < time()) {
            return $this->error_response(
                'rest_token_expired',
                __('Token expirado.', 'globalapi'),
                null,
                401
            );
        }

        return $payload_data;
    }

    /**
     * Generar refresh token
     *
     * @since 2.0.0
     * @param string $session_id ID de la sesión
     * @return string Refresh token
     */
    protected function generate_refresh_token($session_id) {
        return wp_hash($session_id . time() . wp_generate_password(32, false));
    }

    /**
     * Actualizar tokens en la sesión
     *
     * @since 2.0.0
     * @param string $session_id ID de la sesión
     * @param string $access_token Token de acceso
     * @param string $refresh_token Token de refresh
     */
    protected function update_session_tokens($session_id, $access_token, $refresh_token) {
        global $wpdb;

        $table_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        $table_refresh = $wpdb->prefix . 'globalapi_refresh_tokens';
        
        $token_hash = wp_hash($access_token);
        $refresh_hash = wp_hash($refresh_token);
        $expires_at = date('Y-m-d H:i:s', time() + $this->token_duration);
        $refresh_expires_at = date('Y-m-d H:i:s', time() + $this->refresh_duration);

        // Actualizar hash del token en la sesión
        $wpdb->update(
            $table_sesiones,
            array('token_hash' => $token_hash),
            array('id' => $session_id)
        );

        // Insertar o actualizar refresh token (usar session_id como referencia)
        $existing_refresh = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_refresh} WHERE user_id = %s",
            $session_id // Reutilizamos el campo user_id para guardar session_id
        ));

        if ($existing_refresh) {
            $wpdb->update(
                $table_refresh,
                array(
                    'token_hash' => $refresh_hash,
                    'expires_at' => $refresh_expires_at,
                    'created_at' => current_time('mysql'),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ),
                array('user_id' => $session_id)
            );
        } else {
            $wpdb->insert(
                $table_refresh,
                array(
                    'user_id' => $session_id, // Guardamos session_id en lugar de user_id
                    'token_hash' => $refresh_hash,
                    'expires_at' => $refresh_expires_at,
                    'created_at' => current_time('mysql'),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                )
            );
        }
    }

    /**
     * Preparar datos del usuario de InvisionCommunity para respuesta
     *
     * @since 2.0.0
     * @param array $invision_user_data Datos del usuario de InvisionCommunity
     * @return array Datos del usuario formateados
     */
    protected function prepare_invision_user_data($invision_user_data) {
        return array(
            'id' => $invision_user_data['id'] ?? null,
            'username' => $invision_user_data['name'] ?? null,
            'email' => $invision_user_data['email'] ?? null,
            'display_name' => $invision_user_data['displayName'] ?? $invision_user_data['name'] ?? null,
            'primary_group' => $invision_user_data['primaryGroup'] ?? null,
            'secondary_groups' => $invision_user_data['secondaryGroups'] ?? array(),
            'avatar_url' => $invision_user_data['photoUrl'] ?? null,
            'profile_url' => $invision_user_data['profileUrl'] ?? null,
            'registered_date' => $invision_user_data['joined'] ?? null
        );
    }

    /**
     * Obtener token de la petición
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return string|null Token o null
     */
    protected function get_token_from_request($request) {
        // Buscar en header Authorization
        $auth_header = $request->get_header('authorization');
        
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }

        // Buscar en parámetro access_token
        return $request->get_param('access_token');
    }

    /**
     * Obtener clave secreta JWT
     *
     * @since 2.0.0
     * @return string Clave secreta
     */
    protected function get_jwt_secret() {
        $secret = get_option('globalapi_jwt_secret');
        
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('globalapi_jwt_secret', $secret);
        }
        
        return $secret;
    }

    /**
     * Preparar datos del usuario para respuesta
     *
     * @since 2.0.0
     * @param WP_User $user Usuario de WordPress
     * @return array Datos del usuario
     */
    protected function prepare_user_data($user) {
        return array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles,
            'avatar_url' => get_avatar_url($user->ID),
            'registered_date' => $user->user_registered
        );
    }

    /**
     * Registrar intento de autenticación
     *
     * @since 2.0.0
     * @param string $username Nombre de usuario
     * @param bool $success Si fue exitoso
     * @param string $error_message Mensaje de error (opcional)
     */
    protected function log_auth_attempt($username, $success, $error_message = '') {
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            $event_type = $success ? 'login_success' : 'login_failed';
            $message = $success 
                ? sprintf(__('Login exitoso para usuario: %s', 'globalapi'), $username)
                : sprintf(__('Login fallido para usuario: %s', 'globalapi'), $username);

            $context = array(
                'username' => $username,
                'ip_cliente' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            if (!$success && $error_message) {
                $context['error_message'] = $error_message;
            }

            GlobalAPI_Log_Auditoria::registrar_log(
                $event_type,
                $message,
                $context,
                $success ? 'info' : 'warning'
            );
        }
    }

    /**
     * Guardar refresh token en la base de datos
     *
     * @since 2.0.0
     * @param int $user_id ID del usuario
     * @param string $refresh_token Token de refresh
     */
    protected function save_refresh_token($user_id, $refresh_token) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'globalapi_refresh_tokens';
        
        // Crear tabla si no existe
        $this->create_refresh_tokens_table();

        // Insertar o actualizar refresh token
        $wpdb->replace($table_name, array(
            'user_id' => $user_id,
            'token_hash' => wp_hash($refresh_token),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->refresh_duration),
            'created_at' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ));
    }

    /**
     * Verificar refresh token
     *
     * @since 2.0.0
     * @param string $refresh_token Token de refresh
     * @return array|WP_Error Datos del token o error
     */
    protected function verify_refresh_token($refresh_token) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'globalapi_refresh_tokens';
        $token_hash = wp_hash($refresh_token);

        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE token_hash = %s AND expires_at > NOW()",
            $token_hash
        ));

        if (!$token_data) {
            return $this->error_response(
                'rest_refresh_token_invalid',
                __('Refresh token inválido o expirado.', 'globalapi'),
                null,
                401
            );
        }

        return array(
            'user_id' => $token_data->user_id,
            'expires_at' => $token_data->expires_at
        );
    }

    /**
     * Crear tabla de refresh tokens
     *
     * @since 2.0.0
     */
    protected function create_refresh_tokens_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'globalapi_refresh_tokens';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token_hash varchar(64) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY token_hash (token_hash),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Invalidar refresh tokens del usuario
     *
     * @since 2.0.0
     * @param int $user_id ID del usuario
     */
    protected function invalidate_user_refresh_tokens($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'globalapi_refresh_tokens';
        
        $wpdb->delete($table_name, array('user_id' => $user_id));
    }

    /**
     * Actualizar refresh token
     *
     * @since 2.0.0
     * @param string $old_token Token anterior
     * @param string $new_token Token nuevo
     */
    protected function update_refresh_token($old_token, $new_token) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'globalapi_refresh_tokens';
        $old_hash = wp_hash($old_token);
        $new_hash = wp_hash($new_token);

        $wpdb->update(
            $table_name,
            array(
                'token_hash' => $new_hash,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->refresh_duration),
                'created_at' => current_time('mysql'),
                'ip_address' => $this->get_client_ip()
            ),
            array('token_hash' => $old_hash)
        );
    }

    /**
     * Obtener credenciales OAuth de InvisionCommunity
     *
     * @since 2.0.0
     * @return array|WP_Error Credenciales o error
     */
    protected function get_oauth_credentials() {
        // Buscar credenciales de InvisionCommunity
        $credenciales = get_posts(array(
            'post_type' => 'globalapi_credencial',
            'meta_query' => array(
                array(
                    'key' => '_globalapi_tipo_servicio',
                    'value' => 'invisioncommunity',
                    'compare' => '='
                ),
                array(
                    'key' => '_globalapi_estado',
                    'value' => 'activa',
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));

        if (empty($credenciales)) {
            return $this->error_response(
                'rest_oauth_not_configured',
                __('OAuth no configurado para InvisionCommunity.', 'globalapi'),
                null,
                503
            );
        }

        $credencial = $credenciales[0];
        
        return array(
            'client_id' => get_post_meta($credencial->ID, '_globalapi_oauth_client_id', true),
            'client_secret' => base64_decode(get_post_meta($credencial->ID, '_globalapi_oauth_client_secret', true)),
            'auth_url' => rtrim(get_post_meta($credencial->ID, '_globalapi_url_base', true), '/') . '/oauth/authorize/',
            'token_url' => rtrim(get_post_meta($credencial->ID, '_globalapi_url_base', true), '/') . '/oauth/token/'
        );
    }

    /**
     * Intercambiar código OAuth por token
     *
     * @since 2.0.0
     * @param string $code Código de autorización
     * @return array|WP_Error Datos del token o error
     */
    protected function exchange_oauth_code($code) {
        $oauth_creds = $this->get_oauth_credentials();
        if (is_wp_error($oauth_creds)) {
            return $oauth_creds;
        }

        // El redirect_uri debe ser el mismo usado en la autorización inicial
        $redirect_uri = home_url('/index.php?rest_route=/' . $this->namespace . '/' . $this->rest_base . '/oauth/callback');

        $response = wp_remote_post($oauth_creds['token_url'], array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
                'client_id' => $oauth_creds['client_id'],
                'client_secret' => $oauth_creds['client_secret']
            ),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return $this->error_response(
                'rest_oauth_exchange_failed',
                __('Error al intercambiar código OAuth.', 'globalapi'),
                $response->get_error_message(),
                502
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            return $this->error_response(
                'rest_oauth_invalid_response',
                __('Respuesta OAuth inválida.', 'globalapi'),
                $body,
                502
            );
        }

        return $body;
    }

    /**
     * Obtener datos del usuario OAuth
     *
     * @since 2.0.0
     * @param string $access_token Token de acceso OAuth
     * @return array|WP_Error Datos del usuario o error
     */
    protected function get_oauth_user_data($access_token) {
        $oauth_creds = $this->get_oauth_credentials();
        if (is_wp_error($oauth_creds)) {
            return $oauth_creds;
        }

        $api_url = str_replace('/oauth/authorize/', '/api/core/me', $oauth_creds['auth_url']);
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $this->error_response(
                'rest_oauth_user_failed',
                __('Error al obtener datos del usuario OAuth.', 'globalapi'),
                $response->get_error_message(),
                502
            );
        }

        $user_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$user_data) {
            return $this->error_response(
                'rest_oauth_user_invalid',
                __('Datos de usuario OAuth inválidos.', 'globalapi'),
                null,
                502
            );
        }

        // Registrar información completa de InvisionCommunity para debug
        error_log('InvisionCommunity user data: ' . print_r($user_data, true));

        return $user_data;
    }

    /**
     * Crear o actualizar sesión OAuth (sin crear wp_user)
     *
     * @since 2.0.0
     * @param array $oauth_user_data Datos del usuario OAuth
     * @param array $oauth_result Resultado del token OAuth
     * @return array|WP_Error Datos de la sesión o error
     */
    protected function create_or_update_oauth_session($oauth_user_data, $oauth_result) {
        $email = $oauth_user_data['email'] ?? '';
        $username = $oauth_user_data['name'] ?? $oauth_user_data['username'] ?? '';
        
        if (empty($email) || empty($username)) {
            return $this->error_response(
                'rest_oauth_user_incomplete',
                __('Datos de usuario OAuth incompletos.', 'globalapi'),
                null,
                400
            );
        }

        global $wpdb;
        $table_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        
        $client_ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Datos completos de la sesión
        $session_data = array(
            'servicio' => 'invision',
            'invision_user' => $oauth_user_data,
            'oauth_tokens' => array(
                'access_token' => $oauth_result['access_token'],
                'refresh_token' => $oauth_result['refresh_token'] ?? null,
                'expires_in' => $oauth_result['expires_in'] ?? 3600
            ),
            'created_at' => current_time('mysql'),
            'ip_address' => $client_ip,
            'user_agent' => $user_agent
        );

        // Buscar sesión existente por email del usuario de InvisionCommunity
        $existing_session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_sesiones} 
             WHERE servicio = 'invision' AND activo = 1 
             AND JSON_EXTRACT(datos_sesion, '$.invision_user.email') = %s",
            $email
        ));

        if ($existing_session) {
            // Actualizar sesión existente
            $wpdb->update(
                $table_sesiones,
                array(
                    'datos_sesion' => json_encode($session_data),
                    'ip_address' => $client_ip,
                    'user_agent' => $user_agent,
                    'fecha_ultimo_uso' => current_time('mysql')
                ),
                array('id' => $existing_session->id)
            );
            
            return array('session_id' => $existing_session->id);
        } else {
            // Crear nueva sesión
            $result = $wpdb->insert(
                $table_sesiones,
                array(
                    'usuario_id' => 0, // No hay wp_user asociado
                    'token_hash' => '', // Se actualizará después
                    'servicio' => 'invision',
                    'expires_at' => date('Y-m-d H:i:s', time() + $this->token_duration),
                    'ip_address' => $client_ip,
                    'user_agent' => $user_agent,
                    'activo' => 1,
                    'datos_sesion' => json_encode($session_data),
                    'fecha_creacion' => current_time('mysql'),
                    'fecha_ultimo_uso' => current_time('mysql')
                )
            );
            
            if ($result === false) {
                return $this->error_response(
                    'rest_session_create_failed',
                    __('Error al crear sesión.', 'globalapi'),
                    null,
                    500
                );
            }
            
            return array('session_id' => $wpdb->insert_id);
        }
    }

    /**
     * Obtener intentos de login restantes
     *
     * @since 2.0.0
     * @param string $username Nombre de usuario
     * @return int Intentos restantes
     */
    protected function get_remaining_attempts($username) {
        $transient_key = 'globalapi_login_attempts_' . md5($username . $this->get_client_ip());
        $attempts = get_transient($transient_key) ?: 0;
        
        return max(0, 5 - $attempts); // Máximo 5 intentos
    }



    /**
     * Obtener IP del cliente
     *
     * @since 2.0.0
     * @return string IP del cliente
     */
    protected function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
} 