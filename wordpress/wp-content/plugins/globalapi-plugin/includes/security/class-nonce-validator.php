<?php
/**
 * Sistema de Validación de Nonces para WordPress
 *
 * Proporciona protección CSRF completa para el plugin GlobalAPI mediante
 * la generación, validación y gestión de nonces de WordPress. Incluye
 * integración automática con formularios, API REST y operaciones sensibles.
 *
 * @package GlobalAPI
 * @subpackage Security
 * @since 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase NonceValidator
 * 
 * Maneja la validación de nonces para protección CSRF:
 * - Generación automática de nonces por contexto
 * - Validación en endpoints API REST
 * - Protección CSRF en formularios admin
 * - Gestión de tiempos de vida personalizados
 * - Integración con sistema de capacidades
 * - Auditoría de intentos de validación
 */
class NonceValidator {

    /**
     * Versión del sistema de nonces
     */
    const VERSION = '2.0.0';

    /**
     * Prefijo para nonces del plugin
     */
    const NONCE_PREFIX = 'globalapi_';

    /**
     * Tiempo de vida por defecto (en segundos)
     * WordPress default: 86400 (24 horas)
     */
    const DEFAULT_LIFETIME = 43200; // 12 horas

    /**
     * Tiempo de vida corto para operaciones críticas
     */
    const SHORT_LIFETIME = 3600; // 1 hora

    /**
     * Tiempo de vida largo para operaciones menos críticas
     */
    const LONG_LIFETIME = 86400; // 24 horas

    /**
     * Contextos de nonce del sistema
     */
    const CONTEXTS = array(
        // API REST Endpoints
        'api_auth_login' => array(
            'description' => 'Login vía API REST',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => null
        ),
        'api_contacts_list' => array(
            'description' => 'Listar contactos vía API',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_contacts_view'
        ),
        'api_contacts_create' => array(
            'description' => 'Crear contacto vía API',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_contacts_create'
        ),
        'api_contacts_edit' => array(
            'description' => 'Editar contacto vía API',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_contacts_edit'
        ),
        'api_contacts_delete' => array(
            'description' => 'Eliminar contacto vía API',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_contacts_delete'
        ),
        
        // Configuración del sistema
        'config_save' => array(
            'description' => 'Guardar configuración',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_config_configure'
        ),
        'config_credentials' => array(
            'description' => 'Configurar credenciales',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_config_manage'
        ),
        
        // Operaciones de seguridad
        'security_encrypt' => array(
            'description' => 'Operaciones de cifrado',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_security_manage'
        ),
        'security_roles' => array(
            'description' => 'Gestión de roles',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_security_configure'
        ),
        
        // Auditoría y logs
        'audit_view' => array(
            'description' => 'Ver logs de auditoría',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_logs_audit'
        ),
        'audit_export' => array(
            'description' => 'Exportar logs',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_logs_export'
        ),
        
        // Health checks y monitoreo
        'health_check' => array(
            'description' => 'Ejecutar health check',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_health_test'
        ),
        'health_configure' => array(
            'description' => 'Configurar monitoreo',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_health_configure'
        ),
        
        // Cache management
        'cache_clear' => array(
            'description' => 'Limpiar cache',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_cache_manage'
        ),
        'cache_test' => array(
            'description' => 'Probar cache',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_cache_test'
        ),
        
        // Integraciones externas
        'oauth_config' => array(
            'description' => 'Configurar OAuth',
            'lifetime' => self::SHORT_LIFETIME,
            'required_capability' => 'globalapi_oauth_configure'
        ),
        'groundhogg_sync' => array(
            'description' => 'Sincronizar con Groundhogg',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_groundhogg_manage'
        ),
        
        // Formularios admin generales
        'admin_form' => array(
            'description' => 'Formularios admin generales',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_view_dashboard'
        ),
        'admin_ajax' => array(
            'description' => 'Llamadas AJAX admin',
            'lifetime' => self::DEFAULT_LIFETIME,
            'required_capability' => 'globalapi_view_dashboard'
        )
    );

    /**
     * Cache de nonces generados
     */
    private static $nonce_cache = array();

    /**
     * Estadísticas de validación
     */
    private static $validation_stats = array(
        'generated' => 0,
        'validated' => 0,
        'failed' => 0,
        'expired' => 0,
        'invalid_capability' => 0
    );

    /**
     * Inicializar sistema de nonces
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        
        // Modificar tiempo de vida de nonces
        add_filter('nonce_life', array(__CLASS__, 'filtrar_tiempo_vida_nonce'));
        
        // Cargar estadísticas
        self::cargar_estadisticas();
        
        // Cleanup programado
        add_action('globalapi_nonce_cleanup', array(__CLASS__, 'limpiar_nonces_expirados'));
        
        // Programar limpieza diaria
        if (!wp_next_scheduled('globalapi_nonce_cleanup')) {
            wp_schedule_event(time(), 'daily', 'globalapi_nonce_cleanup');
        }
        
        // Hook para REST API
        add_action('rest_api_init', array(__CLASS__, 'registrar_middleware_rest'));
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Hook para validación automática en formularios admin
        add_action('admin_post_globalapi_form', array(__CLASS__, 'validar_formulario_admin'));
        add_action('wp_ajax_globalapi_action', array(__CLASS__, 'validar_ajax_action'));
        add_action('wp_ajax_nopriv_globalapi_action', array(__CLASS__, 'validar_ajax_action'));
        
        // Hook para agregar nonces a formularios
        add_action('globalapi_form_start', array(__CLASS__, 'agregar_nonce_formulario'));
        
        // Debug en footer para administradores
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array(__CLASS__, 'mostrar_debug_nonces'));
            add_action('admin_footer', array(__CLASS__, 'mostrar_debug_nonces'));
        }
    }

    /**
     * Generar nonce para contexto específico
     * 
     * @param string $context Contexto del nonce
     * @param int|null $user_id ID del usuario (null para actual)
     * @return string|false Nonce generado o false en error
     * @since 2.0.0
     */
    public static function generar_nonce($context, $user_id = null) {
        if (!isset(self::CONTEXTS[$context])) {
            self::registrar_log('nonce_invalid_context', 'Contexto de nonce inválido', array(
                'context' => $context
            ));
            return false;
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $context_config = self::CONTEXTS[$context];

        // Verificar capacidad requerida
        if ($context_config['required_capability']) {
            if (!self::verificar_capacidad_contexto($context, $user_id)) {
                self::$validation_stats['invalid_capability']++;
                self::registrar_log('nonce_capability_denied', 'Capacidad insuficiente para nonce', array(
                    'context' => $context,
                    'user_id' => $user_id,
                    'required_capability' => $context_config['required_capability']
                ));
                return false;
            }
        }

        // Generar action único
        $action = self::NONCE_PREFIX . $context;
        
        // Verificar cache
        $cache_key = $user_id . '_' . $action;
        if (isset(self::$nonce_cache[$cache_key])) {
            return self::$nonce_cache[$cache_key];
        }

        // Generar nonce de WordPress
        $nonce = wp_create_nonce($action);
        
        // Cache el nonce
        self::$nonce_cache[$cache_key] = $nonce;
        
        // Actualizar estadísticas
        self::$validation_stats['generated']++;
        self::actualizar_estadisticas();

        // Log de generación
        self::registrar_log('nonce_generated', 'Nonce generado exitosamente', array(
            'context' => $context,
            'user_id' => $user_id,
            'action' => $action,
            'lifetime' => $context_config['lifetime']
        ));

        return $nonce;
    }

    /**
     * Validar nonce para contexto específico
     * 
     * @param string $nonce Nonce a validar
     * @param string $context Contexto del nonce
     * @param int|null $user_id ID del usuario (null para actual)
     * @return bool True si el nonce es válido
     * @since 2.0.0
     */
    public static function validar_nonce($nonce, $context, $user_id = null) {
        $inicio = microtime(true);
        
        if (!isset(self::CONTEXTS[$context])) {
            self::$validation_stats['failed']++;
            self::registrar_log('nonce_validation_invalid_context', 'Contexto inválido en validación', array(
                'context' => $context
            ));
            return false;
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $context_config = self::CONTEXTS[$context];

        // Verificar capacidad requerida
        if ($context_config['required_capability']) {
            if (!self::verificar_capacidad_contexto($context, $user_id)) {
                self::$validation_stats['invalid_capability']++;
                self::registrar_log('nonce_validation_capability_denied', 'Capacidad insuficiente en validación', array(
                    'context' => $context,
                    'user_id' => $user_id
                ));
                return false;
            }
        }

        // Generar action único
        $action = self::NONCE_PREFIX . $context;
        
        // Validar con WordPress
        $validation_result = wp_verify_nonce($nonce, $action);
        
        if ($validation_result === false) {
            self::$validation_stats['failed']++;
            self::registrar_log('nonce_validation_failed', 'Validación de nonce fallida', array(
                'context' => $context,
                'user_id' => $user_id,
                'action' => $action,
                'time_taken' => round((microtime(true) - $inicio) * 1000)
            ));
            return false;
        }

        if ($validation_result === 2) {
            // Nonce expirado pero aún en ventana de gracia
            self::$validation_stats['expired']++;
            self::registrar_log('nonce_validation_expired', 'Nonce expirado pero aceptado', array(
                'context' => $context,
                'user_id' => $user_id,
                'action' => $action
            ));
        }

        // Validación exitosa
        self::$validation_stats['validated']++;
        self::actualizar_estadisticas();

        self::registrar_log('nonce_validation_success', 'Nonce validado exitosamente', array(
            'context' => $context,
            'user_id' => $user_id,
            'action' => $action,
            'result' => $validation_result,
            'time_taken' => round((microtime(true) - $inicio) * 1000)
        ));

        return true;
    }

    /**
     * Verificar nonce desde request HTTP
     * 
     * @param string $context Contexto del nonce
     * @param string $nonce_field Nombre del campo (default: '_wpnonce')
     * @param int|null $user_id ID del usuario
     * @return bool True si el nonce es válido
     * @since 2.0.0
     */
    public static function verificar_request($context, $nonce_field = '_wpnonce', $user_id = null) {
        // Obtener nonce del request
        $nonce = null;
        
        if (isset($_POST[$nonce_field])) {
            $nonce = sanitize_text_field($_POST[$nonce_field]);
        } elseif (isset($_GET[$nonce_field])) {
            $nonce = sanitize_text_field($_GET[$nonce_field]);
        } elseif (isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = sanitize_text_field($_SERVER['HTTP_X_WP_NONCE']);
        }

        if (!$nonce) {
            self::registrar_log('nonce_request_missing', 'Nonce faltante en request', array(
                'context' => $context,
                'field' => $nonce_field,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ));
            return false;
        }

        return self::validar_nonce($nonce, $context, $user_id);
    }

    /**
     * Verificar capacidad para contexto
     * 
     * @param string $context Contexto del nonce
     * @param int $user_id ID del usuario
     * @return bool True si tiene la capacidad
     * @since 2.0.0
     */
    private static function verificar_capacidad_contexto($context, $user_id) {
        if (!isset(self::CONTEXTS[$context]['required_capability'])) {
            return true;
        }

        $required_capability = self::CONTEXTS[$context]['required_capability'];
        
        // Usar sistema de capacidades del plugin si está disponible
        if (class_exists('Capabilities')) {
            return Capabilities::usuario_puede($required_capability, $user_id);
        }

        // Fallback a capacidades de WordPress
        $user = get_user_by('ID', $user_id);
        return $user && $user->has_cap($required_capability);
    }

    /**
     * Registrar middleware para REST API
     * 
     * @since 2.0.0
     */
    public static function registrar_middleware_rest() {
        // Registrar filtro para validación automática
        add_filter('rest_pre_dispatch', array(__CLASS__, 'validar_nonce_rest_api'), 10, 3);
    }

    /**
     * Validar nonce en REST API
     * 
     * @param mixed $result Resultado de la request
     * @param WP_REST_Server $server Servidor REST
     * @param WP_REST_Request $request Request
     * @return mixed Resultado o error
     * @since 2.0.0
     */
    public static function validar_nonce_rest_api($result, $server, $request) {
        // Solo validar rutas de GlobalAPI
        $route = $request->get_route();
        if (strpos($route, '/globalapi/') === false) {
            return $result;
        }

        // Determinar contexto basado en ruta
        $context = self::determinar_contexto_api($route, $request->get_method());
        
        if (!$context) {
            return $result; // No requiere nonce
        }

        // Verificar nonce
        if (!self::verificar_request($context, 'X-WP-Nonce')) {
            return new WP_Error(
                'nonce_verification_failed',
                'Nonce verification failed for this request',
                array('status' => 403)
            );
        }

        return $result;
    }

    /**
     * Determinar contexto basado en ruta API
     * 
     * @param string $route Ruta de la API
     * @param string $method Método HTTP
     * @return string|false Contexto o false si no requiere
     * @since 2.0.0
     */
    private static function determinar_contexto_api($route, $method) {
        // Mapeo de rutas a contextos
        $route_contexts = array(
            '/globalapi/v1/auth/login' => 'api_auth_login',
            '/globalapi/v1/contacts' => array(
                'GET' => 'api_contacts_list',
                'POST' => 'api_contacts_create'
            ),
            '/globalapi/v1/contacts/(?P<id>\d+)' => array(
                'GET' => 'api_contacts_list',
                'PUT' => 'api_contacts_edit',
                'PATCH' => 'api_contacts_edit',
                'DELETE' => 'api_contacts_delete'
            ),
            '/globalapi/v1/config' => 'config_save',
            '/globalapi/v1/health' => 'health_check',
            '/globalapi/v1/cache/clear' => 'cache_clear'
        );

        foreach ($route_contexts as $pattern => $context_info) {
            if (preg_match('#^' . $pattern . '$#', $route)) {
                if (is_array($context_info)) {
                    return $context_info[$method] ?? false;
                }
                return $context_info;
            }
        }

        return false;
    }

    /**
     * Agregar nonce a formulario
     * 
     * @param string $context Contexto del formulario
     * @param string $field_name Nombre del campo (default: '_wpnonce')
     * @since 2.0.0
     */
    public static function agregar_nonce_formulario($context, $field_name = '_wpnonce') {
        $nonce = self::generar_nonce($context);
        if ($nonce) {
            echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($nonce) . '" />';
        }
    }

    /**
     * Validar formulario admin
     * 
     * @since 2.0.0
     */
    public static function validar_formulario_admin() {
        $context = isset($_POST['globalapi_context']) ? sanitize_text_field($_POST['globalapi_context']) : 'admin_form';
        
        if (!self::verificar_request($context)) {
            wp_die('Nonce verification failed', 'Security Error', array('response' => 403));
        }

        // Continuar con procesamiento del formulario
        do_action('globalapi_form_validated', $context);
    }

    /**
     * Validar acción AJAX
     * 
     * @since 2.0.0
     */
    public static function validar_ajax_action() {
        $context = isset($_POST['globalapi_context']) ? sanitize_text_field($_POST['globalapi_context']) : 'admin_ajax';
        
        if (!self::verificar_request($context)) {
            wp_die(json_encode(array(
                'success' => false,
                'error' => 'Nonce verification failed'
            )), 403);
        }

        // Continuar con procesamiento AJAX
        do_action('globalapi_ajax_validated', $context);
    }

    /**
     * Filtrar tiempo de vida de nonce
     * 
     * @param int $lifespan Tiempo de vida actual
     * @return int Tiempo de vida modificado
     * @since 2.0.0
     */
    public static function filtrar_tiempo_vida_nonce($lifespan) {
        // Verificar si es un nonce de GlobalAPI
        $action = $_REQUEST['action'] ?? '';
        
        if (strpos($action, self::NONCE_PREFIX) === 0) {
            $context = substr($action, strlen(self::NONCE_PREFIX));
            
            if (isset(self::CONTEXTS[$context])) {
                return self::CONTEXTS[$context]['lifetime'];
            }
        }

        return $lifespan;
    }

    /**
     * Obtener URL con nonce
     * 
     * @param string $url URL base
     * @param string $context Contexto del nonce
     * @param string $field_name Nombre del campo
     * @return string URL con nonce
     * @since 2.0.0
     */
    public static function obtener_url_con_nonce($url, $context, $field_name = '_wpnonce') {
        $nonce = self::generar_nonce($context);
        if ($nonce) {
            return add_query_arg($field_name, $nonce, $url);
        }
        return $url;
    }

    /**
     * Generar campo nonce para formulario
     * 
     * @param string $context Contexto del nonce
     * @param string $field_name Nombre del campo
     * @param bool $referer Incluir referer
     * @return string HTML del campo
     * @since 2.0.0
     */
    public static function campo_nonce($context, $field_name = '_wpnonce', $referer = true) {
        $nonce = self::generar_nonce($context);
        $output = '';
        
        if ($nonce) {
            $output .= '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($nonce) . '" />';
            
            if ($referer) {
                $output .= wp_referer_field(false);
            }
        }
        
        return $output;
    }

    /**
     * Obtener estadísticas de validación
     * 
     * @return array Estadísticas completas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        $stats = self::$validation_stats;
        
        // Calcular métricas adicionales
        $total_validations = $stats['validated'] + $stats['failed'];
        $stats['total_validations'] = $total_validations;
        $stats['success_rate'] = $total_validations > 0 
            ? round(($stats['validated'] / $total_validations) * 100, 2)
            : 100;
        
        $stats['contexts_available'] = count(self::CONTEXTS);
        $stats['cache_size'] = count(self::$nonce_cache);
        $stats['contexts'] = array_keys(self::CONTEXTS);

        return $stats;
    }

    /**
     * Limpiar nonces expirados
     * 
     * @since 2.0.0
     */
    public static function limpiar_nonces_expirados() {
        // Limpiar cache de nonces
        self::$nonce_cache = array();
        
        // Guardar estadísticas
        self::actualizar_estadisticas();

        self::registrar_log('nonces_cleanup', 'Limpieza de nonces expirados completada');
    }

    /**
     * Mostrar debug de nonces en footer
     * 
     * @since 2.0.0
     */
    public static function mostrar_debug_nonces() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = self::obtener_estadisticas();
        echo "<!-- GlobalAPI Nonces Debug:\n";
        echo "Generated: {$stats['generated']}, Validated: {$stats['validated']}\n";
        echo "Failed: {$stats['failed']}, Success Rate: {$stats['success_rate']}%\n";
        echo "Contexts Available: {$stats['contexts_available']}, Cache Size: {$stats['cache_size']}\n";
        echo "Current User ID: " . get_current_user_id() . "\n";
        echo "-->";
    }

    /**
     * Cargar estadísticas
     * 
     * @since 2.0.0
     */
    private static function cargar_estadisticas() {
        self::$validation_stats = get_option('globalapi_nonce_stats', self::$validation_stats);
    }

    /**
     * Actualizar estadísticas
     * 
     * @since 2.0.0
     */
    private static function actualizar_estadisticas() {
        update_option('globalapi_nonce_stats', self::$validation_stats, false);
    }

    /**
     * Registrar evento en logs
     * 
     * @param string $accion Acción realizada
     * @param string $descripcion Descripción del evento
     * @param array $datos Datos adicionales
     * @since 2.0.0
     */
    private static function registrar_log($accion, $descripcion, $datos = array()) {
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'nonce_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Obtener información de configuración
     * 
     * @return array Información de configuración
     * @since 2.0.0
     */
    public static function obtener_info_config() {
        return array(
            'version' => self::VERSION,
            'nonce_prefix' => self::NONCE_PREFIX,
            'default_lifetime' => self::DEFAULT_LIFETIME,
            'short_lifetime' => self::SHORT_LIFETIME,
            'long_lifetime' => self::LONG_LIFETIME,
            'contexts_count' => count(self::CONTEXTS),
            'contexts' => self::CONTEXTS,
            'cache_size' => count(self::$nonce_cache),
            'stats' => self::$validation_stats
        );
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    NonceValidator::init();
}