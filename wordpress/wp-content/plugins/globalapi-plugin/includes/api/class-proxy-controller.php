<?php
/**
 * Controlador Proxy para APIs Externas - Plugin GlobalAPI
 *
 * Maneja las peticiones proxy hacia APIs externas configuradas,
 * agregando autenticación automática sin exponer credenciales.
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
 * Clase controladora para proxy de APIs externas
 *
 * @since 2.0.0
 */
class GlobalAPI_Proxy_Controller extends GlobalAPI_REST_Controller {

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Obtener base para las rutas REST
     *
     * @since 2.0.0
     * @return string Base de las rutas
     */
    protected function get_rest_base() {
        return 'proxy';
    }

    /**
     * Registrar rutas del controlador
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Ruta proxy para Groundhogg
        register_rest_route($this->namespace, '/proxy/groundhogg/(?P<endpoint>.+)', array(
            array(
                'methods' => WP_REST_Server::ALLMETHODS,
                'callback' => array($this, 'proxy_groundhogg'),
                'permission_callback' => array($this, 'check_proxy_permissions'),
                'args' => array(
                    'endpoint' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Endpoint de Groundhogg a llamar', 'globalapi')
                    ),
                    'credencial_id' => array(
                        'type' => 'integer',
                        'description' => __('ID de la credencial a usar', 'globalapi')
                    )
                )
            )
        ));

        // Ruta proxy para InvisionCommunity
        register_rest_route($this->namespace, '/proxy/invision/(?P<endpoint>.+)', array(
            array(
                'methods' => WP_REST_Server::ALLMETHODS,
                'callback' => array($this, 'proxy_invision'),
                'permission_callback' => array($this, 'check_proxy_permissions'),
                'args' => array(
                    'endpoint' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Endpoint de InvisionCommunity a llamar', 'globalapi')
                    ),
                    'credencial_id' => array(
                        'type' => 'integer',
                        'description' => __('ID de la credencial a usar', 'globalapi')
                    )
                )
            )
        ));

        // Ruta de test para verificar conectividad
        register_rest_route($this->namespace, '/proxy/test', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'test_proxy'),
                'permission_callback' => array($this, 'check_proxy_permissions')
            )
        ));

        // Ruta para debug del JWT token (temporal)
        register_rest_route($this->namespace, '/proxy/debug-token', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'debug_token'),
                'permission_callback' => '__return_true' // Público para debug
            )
        ));
    }

    /**
     * Verificar permisos para proxy
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_proxy_permissions($request) {
        // Verificar autenticación JWT
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

        // Verificar permisos específicos del endpoint
        $endpoint_path = $this->get_endpoint_path_from_request($request);
        
        if (!$this->check_endpoint_specific_permissions($endpoint_path, $token_data)) {
            return $this->error_response(
                'rest_insufficient_permissions',
                __('No tiene permisos para acceder a este endpoint.', 'globalapi'),
                null,
                403
            );
        }

        // No establecer usuario de WordPress ya que trabajamos con sesiones independientes
        // Los datos del usuario están en el token_data

        return true;
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
     * Extraer la ruta del endpoint de la petición
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return string Ruta del endpoint
     */
    protected function get_endpoint_path_from_request($request) {
        $endpoint_param = $request->get_param('endpoint');
        
        // Para rutas como /globalapi/v1/proxy/groundhogg/contacts
        // extraer solo la parte /contacts
        if (!empty($endpoint_param)) {
            return '/' . ltrim($endpoint_param, '/');
        }
        
        // Fallback: usar la ruta completa
        return $request->get_route();
    }

    /**
     * Verificar permisos específicos del endpoint
     *
     * @since 2.0.0
     * @param string $endpoint_path Ruta del endpoint
     * @param array $token_data Datos del token JWT
     * @return bool True si tiene permisos, false en caso contrario
     */
    protected function check_endpoint_specific_permissions($endpoint_path, $token_data) {
        // Debug log
        error_log('GlobalAPI: Validando permisos para endpoint: ' . $endpoint_path);
        error_log('GlobalAPI: Token data: ' . json_encode($token_data));
        
        // Obtener configuración de permisos de endpoints desde base de datos
        global $wpdb;
        $table = $wpdb->prefix . 'globalapi_endpoint_permissions';
        
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE endpoint_path = %s",
            $endpoint_path
        ));
        
        error_log('GlobalAPI: Permiso encontrado: ' . json_encode($permission));
        
        // Si no hay configuración específica para este endpoint
        if (!$permission) {
            // Verificar si es administrador de WordPress (bypass para administradores locales)
            $default_roles = isset($token_data['user_roles']) ? $token_data['user_roles'] : 
                           (isset($token_data['roles']) ? $token_data['roles'] : []);
            
            if (is_array($default_roles) && in_array('administrator', $default_roles)) {
                error_log('GlobalAPI: Acceso permitido - usuario administrador de WordPress (sin configuración específica)');
                return true;
            }
            
            error_log('GlobalAPI: Acceso denegado - sin configuración específica y no es administrador');
            return false;
        }
        
        // Obtener el parámetro JWT configurado (por defecto usar grupos de InvisionCommunity)
        $jwt_parameter = $permission->jwt_parameter ?: 'invision_groups';
        
        error_log('GlobalAPI: Verificando parámetro: ' . $jwt_parameter);
        
        // Obtener valores del usuario según el parámetro configurado
        $user_values = array();
        
        if (isset($token_data[$jwt_parameter])) {
            $user_values = is_array($token_data[$jwt_parameter]) ? $token_data[$jwt_parameter] : array($token_data[$jwt_parameter]);
        } else {
            // Fallbacks para compatibilidad
            switch ($jwt_parameter) {
                case 'invision_groups':
                    $user_values = $token_data['invision_groups'] ?? array();
                    break;
                case 'invision_primary_group':
                    if (isset($token_data['invision_primary_group']['name'])) {
                        $user_values = array($token_data['invision_primary_group']['name']);
                    } elseif (isset($token_data['invision_primary_group']['id'])) {
                        $user_values = array($token_data['invision_primary_group']['id']);
                    }
                    break;
                case 'invision_secondary_groups':
                    $user_values = $token_data['invision_secondary_groups'] ?? array();
                    break;
                case 'user_roles':
                case 'roles':
                    $user_values = $token_data['user_roles'] ?? $token_data['roles'] ?? array();
                    break;
                default:
                    $user_values = $token_data[$jwt_parameter] ?? array();
                    break;
            }
        }
        
        // Asegurar que sea array
        if (!is_array($user_values)) {
            $user_values = array($user_values);
        }
        
        $required_values = array_map('trim', explode(',', $permission->allowed_values));
        
        error_log('GlobalAPI: Valores requeridos: ' . json_encode($required_values));
        error_log('GlobalAPI: Valores del usuario: ' . json_encode($user_values));
        
        // Verificar si el usuario tiene alguno de los valores requeridos
        $has_permission = !empty(array_intersect($user_values, $required_values));
        
        if ($has_permission) {
            error_log('GlobalAPI: Acceso permitido - usuario tiene valor requerido');
        } else {
            error_log('GlobalAPI: Acceso denegado - usuario no tiene valor requerido');
        }
        
        return $has_permission;
    }

    /**
     * Proxy para Groundhogg
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function proxy_groundhogg($request) {
        $endpoint = $request->get_param('endpoint');
        $credencial_id = $request->get_param('credencial_id');
        $method = $request->get_method();
        
        // Log de la petición
        error_log('GlobalAPI Proxy - Groundhogg: ' . $method . ' ' . $endpoint);
        
        // Obtener credencial activa
        $credencial = $this->obtener_credencial_activa('groundhogg', $credencial_id);
        if (is_wp_error($credencial)) {
            return $credencial;
        }
        
        // Obtener credenciales
        $base_url_v3 = get_post_meta($credencial->ID, '_globalapi_gh_base_url_v3', true);
        $base_url_v4 = get_post_meta($credencial->ID, '_globalapi_gh_base_url_v4', true);
        $public_key = get_post_meta($credencial->ID, '_globalapi_gh_clave_publica', true);
        $token = get_post_meta($credencial->ID, '_globalapi_gh_token', true);
        $secret_key = get_post_meta($credencial->ID, '_globalapi_gh_llave_secreta', true);
        
        // Determinar qué versión usar basado en el endpoint
        $base_url = (strpos($endpoint, 'v4/') === 0 || strpos($endpoint, '/v4/') !== false) ? $base_url_v4 : $base_url_v3;
        
        if (empty($base_url) || empty($public_key) || empty($token)) {
            return $this->error_response(
                'proxy_missing_credentials',
                __('Credenciales incompletas para Groundhogg', 'globalapi'),
                null,
                500
            );
        }
        
        // Construir URL completa
        $full_url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Filtrar y agregar query params (excluyendo parámetros de WordPress REST API)
        $query_params = $request->get_query_params();
        unset($query_params['credencial_id']); // Remover param interno
        unset($query_params['rest_route']); // Remover param de WordPress REST API
        
        if (!empty($query_params)) {
            $full_url .= '?' . http_build_query($query_params);
        }
        
        // Preparar headers (Groundhogg requiere headers en minúsculas)
        $headers = array(
            'gh-public-key' => $public_key,
            'gh-token' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        if (!empty($secret_key)) {
            $headers['gh-secret-key'] = $secret_key;
        }
        
        // Preparar args para la petición
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => false
        );
        
        // Agregar body si es necesario
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $body = $request->get_json_params();
            if (!empty($body)) {
                $args['body'] = wp_json_encode($body);
            }
        }
        
        // Hacer la petición
        $response = wp_remote_request($full_url, $args);
        
        if (is_wp_error($response)) {
            return $this->error_response(
                'proxy_request_failed',
                __('Error al conectar con Groundhogg: ', 'globalapi') . $response->get_error_message(),
                array('url' => $full_url),
                502
            );
        }

        
        // Procesar respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Log de la respuesta
        $this->log_proxy_request($credencial->ID, 'groundhogg', $endpoint, $status_code);
        
        // Intentar decodificar JSON
        $data = json_decode($body, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // No es JSON, devolver como está
            $data = $body;
        }
        
        // Preparar respuesta proxy
        $proxy_response = new WP_REST_Response($data, $status_code);
        
        // Pasar algunos headers relevantes
        if (isset($response_headers['content-type'])) {
            $proxy_response->header('Content-Type', $response_headers['content-type']);
        }
        
        return $proxy_response;
    }

    /**
     * Proxy para InvisionCommunity
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function proxy_invision($request) {
        $endpoint = $request->get_param('endpoint');
        $credencial_id = $request->get_param('credencial_id');
        
        // Obtener credencial activa
        $credencial = $this->obtener_credencial_activa('invisioncommunity', $credencial_id);
        if (is_wp_error($credencial)) {
            return $credencial;
        }
        
        // Obtener credenciales
        $api_key = get_post_meta($credencial->ID, '_globalapi_ic_rest_api_key', true);
        
        if (empty($api_key)) {
            return $this->error_response(
                'proxy_missing_credentials',
                __('API Key no configurada para InvisionCommunity', 'globalapi'),
                null,
                500
            );
        }
        
        // TODO: Implementar proxy para InvisionCommunity
        
        return $this->error_response(
            'proxy_not_implemented',
            __('Proxy para InvisionCommunity aún no implementado', 'globalapi'),
            null,
            501
        );
    }

    /**
     * Test de conectividad del proxy
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function test_proxy($request) {
        return $this->success_response(
            array(
                'proxy_active' => true,
                'supported_services' => array('groundhogg', 'invisioncommunity'),
                'endpoints' => array(
                    'groundhogg' => '/wp-json/globalapi/v1/proxy/groundhogg/{endpoint}',
                    'invision' => '/wp-json/globalapi/v1/proxy/invision/{endpoint}'
                )
            ),
            __('Proxy activo y funcionando', 'globalapi')
        );
    }

    /**
     * Debug del JWT token (método temporal)
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function debug_token($request) {
        $token = $this->get_token_from_request($request);
        
        if (empty($token)) {
            return $this->error_response(
                'rest_token_missing',
                'Token requerido para debug',
                null,
                400
            );
        }

        $token_data = $this->verify_jwt_token($token);
        if (is_wp_error($token_data)) {
            return $token_data;
        }

        // Obtener datos de la sesión
        $session_id = $token_data['session_id'];
        
        global $wpdb;
        $table_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_sesiones} WHERE id = %s",
            $session_id
        ));
        
        $session_data = $session ? json_decode($session->datos_sesion, true) : null;

        return $this->success_response(
            array(
                'token_payload' => $token_data,
                'session_id' => $session_id,
                'session_data' => $session_data,
                'session_record' => $session,
                'architecture' => 'independent_from_wp_users'
            ),
            'Debug del token JWT'
        );
    }

    /**
     * Obtener credencial activa para un servicio
     *
     * @since 2.0.0
     * @param string $tipo_servicio
     * @param int|null $credencial_id
     * @return WP_Post|WP_Error
     */
    private function obtener_credencial_activa($tipo_servicio, $credencial_id = null) {
        if ($credencial_id) {
            // Usar credencial específica
            $credencial = get_post($credencial_id);
            if (!$credencial || $credencial->post_type !== 'globalapi_credencial') {
                return $this->error_response(
                    'proxy_credential_not_found',
                    __('Credencial no encontrada', 'globalapi'),
                    null,
                    404
                );
            }
            
            // Verificar que sea del tipo correcto
            $tipo = get_post_meta($credencial->ID, '_globalapi_tipo_servicio', true);
            if ($tipo !== $tipo_servicio) {
                return $this->error_response(
                    'proxy_credential_type_mismatch',
                    __('La credencial no es del tipo esperado', 'globalapi'),
                    array('expected' => $tipo_servicio, 'actual' => $tipo),
                    400
                );
            }
            
            return $credencial;
        }
        
        // Buscar primera credencial activa del tipo
        $credenciales = get_posts(array(
            'post_type' => 'globalapi_credencial',
            'post_status' => array('publish', 'private'),
            'numberposts' => 1,
            'meta_query' => array(
                array(
                    'key' => '_globalapi_tipo_servicio',
                    'value' => $tipo_servicio,
                    'compare' => '='
                ),
                array(
                    'key' => '_globalapi_estado',
                    'value' => 'activa',
                    'compare' => '='
                )
            )
        ));
        
        if (empty($credenciales)) {
            return $this->error_response(
                'proxy_no_active_credentials',
                sprintf(__('No hay credenciales activas para %s', 'globalapi'), $tipo_servicio),
                null,
                404
            );
        }
        
        return $credenciales[0];
    }

    /**
     * Registrar petición proxy en logs
     *
     * @since 2.0.0
     * @param int $credencial_id
     * @param string $servicio
     * @param string $endpoint
     * @param int $status_code
     */
    private function log_proxy_request($credencial_id, $servicio, $endpoint, $status_code) {
        if (class_exists('LogAuditoria')) {
            LogAuditoria::registrar_log(array(
                'tipo_evento' => 'proxy_request',
                'descripcion' => sprintf('Proxy %s: %s', $servicio, $endpoint),
                'severidad' => ($status_code >= 400) ? 'warning' : 'info',
                'datos_adicionales' => array(
                    'credencial_id' => $credencial_id,
                    'servicio' => $servicio,
                    'endpoint' => $endpoint,
                    'status_code' => $status_code,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                )
            ));
        }
    }
} 