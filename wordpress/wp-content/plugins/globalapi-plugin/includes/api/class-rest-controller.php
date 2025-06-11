<?php
/**
 * Controlador Base REST API - Plugin GlobalAPI
 *
 * Controlador base que proporciona funcionalidad común para todos los
 * endpoints REST API del plugin GlobalAPI. Maneja autenticación, validación,
 * respuestas estándar y logging de auditoría.
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
 * Clase base para controladores REST API del plugin GlobalAPI
 *
 * Extiende WP_REST_Controller y proporciona funcionalidad común:
 * - Manejo de respuestas estándar (success, error, validation)
 * - Validación de permisos y capabilities
 * - Logging automático de peticiones API
 * - Sanitización y validación de parámetros
 * - Headers de seguridad y CORS
 * - Rate limiting básico
 *
 * @since 2.0.0
 */
class GlobalAPI_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace del REST API
     *
     * @since 2.0.0
     * @var string
     */
    protected $namespace = 'globalapi/v1';

    /**
     * Versión del API
     *
     * @since 2.0.0
     * @var string
     */
    protected $version = '1';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->rest_base = $this->get_rest_base();
        $this->init_hooks();
    }

    /**
     * Inicializar hooks de WordPress
     *
     * @since 2.0.0
     */
    protected function init_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_serve_request', array($this, 'add_security_headers'), 10, 4);
    }

    /**
     * Registrar rutas del controlador
     * Método abstracto que debe ser implementado por clases hijas
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Método abstracto - implementar en clases hijas
    }

    /**
     * Obtener base para las rutas REST
     * Método abstracto que debe ser implementado por clases hijas
     *
     * @since 2.0.0
     * @return string Base de las rutas
     */
    protected function get_rest_base() {
        return '';
    }

    /**
     * Verificar permisos básicos del plugin
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST actual
     * @return bool|WP_Error Verdadero si tiene permisos, WP_Error si no
     */
    public function check_permissions($request) {
        // Verificar si el usuario puede gestionar opciones
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('No tienes permisos para acceder a este recurso.', 'globalapi'),
                array('status' => 403)
            );
        }

        // Log de la petición
        $this->log_api_request($request);

        return true;
    }

    /**
     * Verificar permisos para operaciones específicas
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST actual
     * @param string $operation Operación (create, read, update, delete)
     * @return bool|WP_Error Verdadero si tiene permisos, WP_Error si no
     */
    public function check_operation_permissions($request, $operation = 'read') {
        $base_check = $this->check_permissions($request);
        if (is_wp_error($base_check)) {
            return $base_check;
        }

        // Verificar permisos específicos según operación
        switch ($operation) {
            case 'create':
            case 'update':
            case 'delete':
                if (!current_user_can('edit_posts')) {
                    return new WP_Error(
                        'rest_forbidden_operation',
                        sprintf(__('No tienes permisos para realizar la operación: %s', 'globalapi'), $operation),
                        array('status' => 403)
                    );
                }
                break;
            case 'read':
            default:
                // Permisos básicos ya verificados
                break;
        }

        return true;
    }

    /**
     * Preparar respuesta de éxito estándar
     *
     * @since 2.0.0
     * @param mixed $data Datos a incluir en la respuesta
     * @param string $message Mensaje opcional
     * @param int $status Código de estado HTTP
     * @return WP_REST_Response
     */
    protected function success_response($data = null, $message = '', $status = 200) {
        $response_data = array(
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'version' => $this->version
        );

        return new WP_REST_Response($response_data, $status);
    }

    /**
     * Preparar respuesta de error estándar
     *
     * @since 2.0.0
     * @param string $code Código de error
     * @param string $message Mensaje de error
     * @param mixed $data Datos adicionales del error
     * @param int $status Código de estado HTTP
     * @return WP_Error
     */
    protected function error_response($code, $message, $data = null, $status = 400) {
        $error_data = array(
            'status' => $status,
            'timestamp' => current_time('mysql'),
            'version' => $this->version
        );

        if ($data !== null) {
            $error_data['data'] = $data;
        }

        // Log del error
        $this->log_api_error($code, $message, $data);

        return new WP_Error($code, $message, $error_data);
    }

    /**
     * Validar parámetros de la petición
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @param array $required_params Parámetros requeridos
     * @return bool|WP_Error Verdadero si válido, WP_Error si no
     */
    protected function validate_request_params($request, $required_params = array()) {
        $errors = array();

        // Verificar parámetros requeridos
        foreach ($required_params as $param) {
            if (!$request->has_param($param) || empty($request->get_param($param))) {
                $errors[] = sprintf(__('El parámetro "%s" es requerido.', 'globalapi'), $param);
            }
        }

        if (!empty($errors)) {
            return $this->error_response(
                'rest_missing_parameters',
                __('Parámetros faltantes en la petición.', 'globalapi'),
                $errors,
                400
            );
        }

        return true;
    }

    /**
     * Sanitizar parámetros de entrada
     *
     * @since 2.0.0
     * @param array $params Parámetros a sanitizar
     * @return array Parámetros sanitizados
     */
    protected function sanitize_params($params) {
        $sanitized = array();

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_params($value);
            } elseif (is_string($value)) {
                // Sanitizar según el tipo de campo
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $sanitized[$key] = sanitize_email($value);
                } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                    $sanitized[$key] = esc_url_raw($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            } elseif (is_numeric($value)) {
                $sanitized[$key] = absint($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Agregar headers de seguridad a las respuestas
     *
     * @since 2.0.0
     * @param bool $served Si la petición ya fue servida
     * @param WP_HTTP_Response $result Resultado de la petición
     * @param WP_REST_Request $request Petición original
     * @param WP_REST_Server $server Servidor REST
     * @return bool
     */
    public function add_security_headers($served, $result, $request, $server) {
        // Solo agregar headers para endpoints de GlobalAPI
        if (strpos($request->get_route(), '/' . $this->namespace) !== 0) {
            return $served;
        }

        // Headers de seguridad
        $result->header('X-Content-Type-Options', 'nosniff');
        $result->header('X-Frame-Options', 'DENY');
        $result->header('X-XSS-Protection', '1; mode=block');
        $result->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // CORS básico (configurar según necesidades)
        $result->header('Access-Control-Allow-Origin', '*');
        $result->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $result->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-WP-Nonce');

        return $served;
    }

    /**
     * Registrar petición API en logs de auditoría
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     */
    protected function log_api_request($request) {
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            $user_data = wp_get_current_user();
            
            GlobalAPI_Log_Auditoria::registrar_log(
                'api_request',
                sprintf(__('Petición API: %s %s', 'globalapi'), $request->get_method(), $request->get_route()),
                array(
                    'usuario_id' => $user_data->ID,
                    'usuario_login' => $user_data->user_login,
                    'endpoint' => $request->get_route(),
                    'metodo_http' => $request->get_method(),
                    'parametros' => $this->sanitize_sensitive_data($request->get_params()),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip_cliente' => $this->get_client_ip()
                ),
                'info'
            );
        }
    }

    /**
     * Registrar error API en logs de auditoría
     *
     * @since 2.0.0
     * @param string $code Código del error
     * @param string $message Mensaje del error
     * @param mixed $data Datos adicionales del error
     */
    protected function log_api_error($code, $message, $data = null) {
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'api_error',
                sprintf(__('Error API: %s - %s', 'globalapi'), $code, $message),
                array(
                    'codigo_error' => $code,
                    'mensaje_error' => $message,
                    'datos_error' => $data,
                    'ip_cliente' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ),
                'error'
            );
        }
    }

    /**
     * Obtener IP del cliente
     *
     * @since 2.0.0
     * @return string IP del cliente
     */
    protected function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Sanitizar datos sensibles para logging
     *
     * @since 2.0.0
     * @param array $data Datos a sanitizar
     * @return array Datos sanitizados
     */
    protected function sanitize_sensitive_data($data) {
        $sensitive_keys = array('password', 'api_key', 'api_secret', 'token', 'client_secret');
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize_sensitive_data($value);
            }
        }
        
        return $data;
    }

    /**
     * Verificar rate limiting básico
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error Verdadero si permite, WP_Error si excede límite
     */
    protected function check_rate_limit($request) {
        $client_ip = $this->get_client_ip();
        $transient_key = 'globalapi_rate_limit_' . md5($client_ip);
        
        // Obtener contador actual
        $current_count = get_transient($transient_key);
        
        // Límite por defecto: 60 peticiones por hora
        $rate_limit = apply_filters('globalapi_rate_limit', 60);
        $time_window = apply_filters('globalapi_rate_limit_window', HOUR_IN_SECONDS);
        
        if ($current_count === false) {
            // Primera petición en la ventana de tiempo
            set_transient($transient_key, 1, $time_window);
            return true;
        }
        
        if ($current_count >= $rate_limit) {
            return $this->error_response(
                'rest_too_many_requests',
                __('Has excedido el límite de peticiones por hora.', 'globalapi'),
                array('limit' => $rate_limit, 'window' => $time_window),
                429
            );
        }
        
        // Incrementar contador
        set_transient($transient_key, $current_count + 1, $time_window);
        
        return true;
    }

    /**
     * Obtener esquema base para respuestas
     *
     * @since 2.0.0
     * @return array Esquema base
     */
    public function get_response_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'globalapi_response',
            'type' => 'object',
            'properties' => array(
                'success' => array(
                    'description' => __('Indica si la petición fue exitosa.', 'globalapi'),
                    'type' => 'boolean',
                    'context' => array('view', 'edit')
                ),
                'data' => array(
                    'description' => __('Datos de la respuesta.', 'globalapi'),
                    'type' => 'object',
                    'context' => array('view', 'edit')
                ),
                'message' => array(
                    'description' => __('Mensaje descriptivo de la respuesta.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                ),
                'timestamp' => array(
                    'description' => __('Marca de tiempo de la respuesta.', 'globalapi'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit')
                ),
                'version' => array(
                    'description' => __('Versión del API.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                )
            )
        );
    }

    /**
     * Obtener información del endpoint
     *
     * @since 2.0.0
     * @return array Información del endpoint
     */
    public function get_endpoint_info() {
        return array(
            'namespace' => $this->namespace,
            'version' => $this->version,
            'rest_base' => $this->rest_base,
            'methods' => $this->get_allowed_methods(),
            'schema' => $this->get_response_schema()
        );
    }

    /**
     * Obtener métodos HTTP permitidos
     * Método que puede ser sobrescrito por clases hijas
     *
     * @since 2.0.0
     * @return array Métodos HTTP permitidos
     */
    protected function get_allowed_methods() {
        return array('GET', 'POST', 'PUT', 'DELETE');
    }
} 