<?php
/**
 * Servicio OAuth para InvisionCommunity
 *
 * Gestiona el flujo OAuth 2.0 completo para autenticación con InvisionCommunity
 * de Colombia Humana. Integra con el GestorCredenciales para manejo seguro.
 *
 * @package GlobalAPI
 * @subpackage Services
 * @since 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase ServicioOAuth
 * 
 * Implementa OAuth 2.0 para InvisionCommunity:
 * - Authorization Code Grant Flow
 * - Manejo seguro de tokens (access + refresh)
 * - Integración con WordPress user sessions
 * - Cache inteligente para optimización
 * - Renovación automática de tokens
 * - Logging completo para auditoría
 */
class ServicioOAuth {

    /**
     * Versión del servicio OAuth
     */
    const VERSION = '2.0.0';

    /**
     * Timeout por defecto para requests
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * Scopes por defecto requeridos
     */
    const DEFAULT_SCOPES = array('read', 'profile');

    /**
     * Grant types soportados
     */
    const GRANT_TYPES = array(
        'authorization_code' => 'Authorization Code',
        'refresh_token' => 'Refresh Token',
        'client_credentials' => 'Client Credentials'
    );

    /**
     * Cache de tokens en memoria
     */
    private static $token_cache = array();

    /**
     * Cache de credenciales OAuth
     */
    private static $credentials_cache = null;

    /**
     * Estados de autorización activos
     */
    private static $auth_states = array();

    /**
     * Inicializar el servicio OAuth
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        add_action('wp_ajax_oauth_callback', array(__CLASS__, 'manejar_callback'));
        add_action('wp_ajax_nopriv_oauth_callback', array(__CLASS__, 'manejar_callback'));
        
        // Limpiar tokens expirados
        add_action('globalapi_limpiar_tokens_oauth', array(__CLASS__, 'limpiar_tokens_expirados'));
        
        // Programar limpieza de tokens
        if (!wp_next_scheduled('globalapi_limpiar_tokens_oauth')) {
            wp_schedule_event(time(), 'daily', 'globalapi_limpiar_tokens_oauth');
        }
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Hook para limpiar cache cuando se actualizan credenciales
        add_action('save_post', array(__CLASS__, 'limpiar_cache_credencial'), 10, 1);
        
        // Hook para logout - limpiar tokens del usuario
        add_action('wp_logout', array(__CLASS__, 'limpiar_tokens_usuario'));
    }

    /**
     * Iniciar flujo de autorización OAuth
     * 
     * @param array $args Argumentos opcionales
     * @return array|WP_Error URL de autorización o error
     * @since 2.0.0
     */
    public static function iniciar_autorizacion($args = array()) {
        $defaults = array(
            'redirect_uri' => self::obtener_redirect_uri(),
            'scopes' => self::DEFAULT_SCOPES,
            'user_id' => get_current_user_id(),
            'force_approval' => false
        );

        $args = wp_parse_args($args, $defaults);

        // Obtener credenciales OAuth
        $credenciales = self::obtener_credenciales_oauth();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        // Generar state único para CSRF protection
        $state = self::generar_state($args['user_id']);
        
        // Construir URL de autorización
        $auth_params = array(
            'response_type' => 'code',
            'client_id' => $credenciales['client_id'],
            'redirect_uri' => $args['redirect_uri'],
            'scope' => implode(' ', $args['scopes']),
            'state' => $state
        );

        if ($args['force_approval']) {
            $auth_params['approval_prompt'] = 'force';
        }

        $auth_url = $credenciales['authorization_url'] . '?' . http_build_query($auth_params);

        // Guardar state para validación posterior
        self::$auth_states[$state] = array(
            'user_id' => $args['user_id'],
            'redirect_uri' => $args['redirect_uri'],
            'scopes' => $args['scopes'],
            'timestamp' => time(),
            'expires' => time() + 600 // 10 minutos
        );

        // Registrar en logs
        self::registrar_log('auth_iniciada', 'Flujo OAuth iniciado', array(
            'user_id' => $args['user_id'],
            'scopes' => $args['scopes'],
            'state' => $state
        ));

        return array(
            'auth_url' => $auth_url,
            'state' => $state,
            'expires_in' => 600
        );
    }

    /**
     * Manejar callback de autorización OAuth
     * 
     * @since 2.0.0
     */
    public static function manejar_callback() {
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');
        $error = sanitize_text_field($_GET['error'] ?? '');

        // Verificar si hay error en el callback
        if (!empty($error)) {
            self::registrar_log('error', 'Error en callback OAuth', array(
                'error' => $error,
                'error_description' => $_GET['error_description'] ?? ''
            ));
            
            wp_die('Error de autorización OAuth: ' . $error);
            return;
        }

        // Validar parámetros requeridos
        if (empty($code) || empty($state)) {
            wp_die('Parámetros de callback OAuth inválidos');
            return;
        }

        // Validar state para CSRF protection
        $state_data = self::validar_state($state);
        if (is_wp_error($state_data)) {
            wp_die('State de OAuth inválido o expirado');
            return;
        }

        // Intercambiar código por token
        $token_result = self::intercambiar_codigo_por_token($code, $state_data);
        
        if (is_wp_error($token_result)) {
            self::registrar_log('error', 'Error intercambiando código por token', array(
                'error' => $token_result->get_error_message(),
                'code' => $code
            ));
            
            wp_die('Error obteniendo token de acceso: ' . $token_result->get_error_message());
            return;
        }

        // Guardar token del usuario
        self::guardar_token_usuario($state_data['user_id'], $token_result);

        // Registrar éxito en logs
        self::registrar_log('success', 'OAuth completado exitosamente', array(
            'user_id' => $state_data['user_id'],
            'token_type' => $token_result['token_type'] ?? 'bearer'
        ));

        // Redirigir al usuario
        $redirect_url = admin_url('admin.php?page=globalapi-credenciales&oauth_success=1');
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Intercambiar código de autorización por token de acceso
     * 
     * @param string $code Código de autorización
     * @param array $state_data Datos del state
     * @return array|WP_Error Token de acceso o error
     * @since 2.0.0
     */
    private static function intercambiar_codigo_por_token($code, $state_data) {
        $credenciales = self::obtener_credenciales_oauth();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        $token_params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $credenciales['client_id'],
            'client_secret' => $credenciales['client_secret'],
            'code' => $code,
            'redirect_uri' => $state_data['redirect_uri']
        );

        $response = wp_remote_post($credenciales['token_url'], array(
            'body' => $token_params,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'timeout' => self::DEFAULT_TIMEOUT
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('token_error', 'Error obteniendo token: HTTP ' . $response_code);
        }

        $token_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Respuesta JSON inválida del servidor OAuth');
        }

        if (isset($token_data['error'])) {
            return new WP_Error('oauth_error', $token_data['error_description'] ?? $token_data['error']);
        }

        // Añadir timestamp de creación y expiración
        $token_data['created_at'] = time();
        $token_data['expires_at'] = time() + ($token_data['expires_in'] ?? 3600);

        return $token_data;
    }

    /**
     * Renovar token de acceso usando refresh token
     * 
     * @param string $refresh_token Refresh token
     * @return array|WP_Error Nuevo token o error
     * @since 2.0.0
     */
    public static function renovar_token($refresh_token) {
        $credenciales = self::obtener_credenciales_oauth();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        $refresh_params = array(
            'grant_type' => 'refresh_token',
            'client_id' => $credenciales['client_id'],
            'client_secret' => $credenciales['client_secret'],
            'refresh_token' => $refresh_token
        );

        $response = wp_remote_post($credenciales['token_url'], array(
            'body' => $refresh_params,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'timeout' => self::DEFAULT_TIMEOUT
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('refresh_error', 'Error renovando token: HTTP ' . $response_code);
        }

        $token_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Respuesta JSON inválida al renovar token');
        }

        if (isset($token_data['error'])) {
            return new WP_Error('oauth_error', $token_data['error_description'] ?? $token_data['error']);
        }

        // Añadir timestamp de creación y expiración
        $token_data['created_at'] = time();
        $token_data['expires_at'] = time() + ($token_data['expires_in'] ?? 3600);

        // Mantener refresh token si no se devuelve uno nuevo
        if (!isset($token_data['refresh_token'])) {
            $token_data['refresh_token'] = $refresh_token;
        }

        return $token_data;
    }

    /**
     * Obtener token de acceso válido para un usuario
     * 
     * @param int $user_id ID del usuario (0 para usuario actual)
     * @param bool $auto_refresh Renovar automáticamente si está expirado
     * @return array|WP_Error Token válido o error
     * @since 2.0.0
     */
    public static function obtener_token_valido($user_id = 0, $auto_refresh = true) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return new WP_Error('no_user', 'Usuario no identificado');
        }

        // Verificar cache
        $cache_key = "oauth_token_{$user_id}";
        if (isset(self::$token_cache[$cache_key])) {
            $token_data = self::$token_cache[$cache_key];
            
            // Verificar si no está expirado
            if ($token_data['expires_at'] > time() + 300) { // 5 minutos de margen
                return $token_data;
            }
        }

        // Obtener token de la base de datos
        $token_data = get_user_meta($user_id, 'globalapi_oauth_token', true);
        
        if (empty($token_data) || !is_array($token_data)) {
            return new WP_Error('no_token', 'No hay token OAuth para este usuario');
        }

        // Verificar si el token está expirado
        if ($token_data['expires_at'] <= time()) {
            if (!$auto_refresh || empty($token_data['refresh_token'])) {
                return new WP_Error('token_expired', 'Token OAuth expirado');
            }

            // Intentar renovar el token
            $nuevo_token = self::renovar_token($token_data['refresh_token']);
            
            if (is_wp_error($nuevo_token)) {
                return $nuevo_token;
            }

            // Guardar el nuevo token
            self::guardar_token_usuario($user_id, $nuevo_token);
            $token_data = $nuevo_token;

            // Registrar renovación en logs
            self::registrar_log('token_renovado', 'Token OAuth renovado automáticamente', array(
                'user_id' => $user_id
            ));
        }

        // Guardar en cache
        self::$token_cache[$cache_key] = $token_data;

        return $token_data;
    }

    /**
     * Obtener información del usuario OAuth
     * 
     * @param string $access_token Token de acceso
     * @return array|WP_Error Información del usuario o error
     * @since 2.0.0
     */
    public static function obtener_info_usuario($access_token) {
        $credenciales = self::obtener_credenciales_oauth();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        // Construir URL de API para obtener perfil de usuario
        $api_url = rtrim($credenciales['api_base_url'], '/') . '/core/me';

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ),
            'timeout' => self::DEFAULT_TIMEOUT
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', 'Error obteniendo información del usuario: HTTP ' . $response_code);
        }

        $user_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Respuesta JSON inválida de la API de usuario');
        }

        // Procesar datos del usuario
        return array(
            'id' => $user_data['id'] ?? null,
            'name' => $user_data['name'] ?? '',
            'email' => $user_data['email'] ?? '',
            'profileUrl' => $user_data['profileUrl'] ?? '',
            'photoUrl' => $user_data['photoUrl'] ?? '',
            'coverPhotoUrl' => $user_data['coverPhotoUrl'] ?? '',
            'joined' => $user_data['joined'] ?? '',
            'lastVisit' => $user_data['lastVisit'] ?? '',
            'raw_data' => $user_data
        );
    }

    /**
     * Realizar request autenticado a la API de InvisionCommunity
     * 
     * @param string $endpoint Endpoint de la API
     * @param array $args Argumentos del request
     * @param int $user_id ID del usuario (0 para actual)
     * @return array|WP_Error Respuesta de la API o error
     * @since 2.0.0
     */
    public static function hacer_request_api($endpoint, $args = array(), $user_id = 0) {
        // Obtener token válido
        $token_data = self::obtener_token_valido($user_id);
        if (is_wp_error($token_data)) {
            return $token_data;
        }

        // Obtener credenciales para la URL base
        $credenciales = self::obtener_credenciales_oauth();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        // Construir URL completa
        $api_url = rtrim($credenciales['api_base_url'], '/') . '/' . ltrim($endpoint, '/');

        // Configurar argumentos del request
        $defaults = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token_data['access_token'],
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'timeout' => self::DEFAULT_TIMEOUT
        );

        $request_args = wp_parse_args($args, $defaults);

        // Realizar request
        $response = wp_remote_request($api_url, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Manejar códigos de error
        if ($response_code >= 400) {
            return new WP_Error('api_error', 'Error de API: HTTP ' . $response_code, array(
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
        }

        // Decodificar respuesta JSON
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Respuesta JSON inválida de la API');
        }

        return $data;
    }

    /**
     * Obtener credenciales OAuth de InvisionCommunity
     * 
     * @return array|WP_Error Credenciales o error
     * @since 2.0.0
     */
    private static function obtener_credenciales_oauth() {
        // Verificar cache
        if (self::$credentials_cache !== null) {
            return self::$credentials_cache;
        }

        // Verificar que existe la clase GestorCredenciales
        if (!class_exists('GestorCredenciales')) {
            return new WP_Error('gestor_no_disponible', 'GestorCredenciales no está disponible');
        }

        // Obtener credenciales de InvisionCommunity activas
        $credenciales_lista = GestorCredenciales::obtener_por_servicio('invisioncommunity', 'activa');
        
        if (empty($credenciales_lista)) {
            return new WP_Error('sin_credenciales', 'No hay credenciales de InvisionCommunity configuradas');
        }

        // Usar la primera credencial activa
        $credencial = reset($credenciales_lista);
        $credencial_completa = GestorCredenciales::obtener_credencial($credencial['id'], true);
        
        if (is_wp_error($credencial_completa)) {
            return $credencial_completa;
        }

        // Validar que tiene los campos necesarios
        $campos_requeridos = array('client_id', 'client_secret', 'authorization_url', 'token_url');
        foreach ($campos_requeridos as $campo) {
            if (empty($credencial_completa[$campo])) {
                return new WP_Error('credenciales_incompletas', "Campo requerido faltante: {$campo}");
            }
        }

        self::$credentials_cache = $credencial_completa;
        return $credencial_completa;
    }

    /**
     * Generar state único para CSRF protection
     * 
     * @param int $user_id ID del usuario
     * @return string State único
     * @since 2.0.0
     */
    private static function generar_state($user_id) {
        return wp_hash($user_id . time() . wp_generate_password(20, false));
    }

    /**
     * Validar state de OAuth
     * 
     * @param string $state State a validar
     * @return array|WP_Error Datos del state o error
     * @since 2.0.0
     */
    private static function validar_state($state) {
        if (!isset(self::$auth_states[$state])) {
            return new WP_Error('state_invalido', 'State de OAuth no encontrado');
        }

        $state_data = self::$auth_states[$state];

        // Verificar expiración
        if ($state_data['expires'] < time()) {
            unset(self::$auth_states[$state]);
            return new WP_Error('state_expirado', 'State de OAuth expirado');
        }

        // Limpiar state usado
        unset(self::$auth_states[$state]);

        return $state_data;
    }

    /**
     * Obtener URI de redirección
     * 
     * @return string URI de redirección
     * @since 2.0.0
     */
    private static function obtener_redirect_uri() {
        return admin_url('admin-ajax.php?action=oauth_callback');
    }

    /**
     * Guardar token de usuario
     * 
     * @param int $user_id ID del usuario
     * @param array $token_data Datos del token
     * @since 2.0.0
     */
    private static function guardar_token_usuario($user_id, $token_data) {
        update_user_meta($user_id, 'globalapi_oauth_token', $token_data);
        
        // Actualizar cache
        $cache_key = "oauth_token_{$user_id}";
        self::$token_cache[$cache_key] = $token_data;

        // Guardar también información del usuario si está disponible
        if (isset($token_data['user_info'])) {
            update_user_meta($user_id, 'globalapi_oauth_user_info', $token_data['user_info']);
        }
    }

    /**
     * Limpiar tokens del usuario
     * 
     * @param int $user_id ID del usuario
     * @since 2.0.0
     */
    public static function limpiar_tokens_usuario($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if ($user_id) {
            delete_user_meta($user_id, 'globalapi_oauth_token');
            delete_user_meta($user_id, 'globalapi_oauth_user_info');
            
            // Limpiar cache
            $cache_key = "oauth_token_{$user_id}";
            unset(self::$token_cache[$cache_key]);
        }
    }

    /**
     * Limpiar tokens expirados
     * 
     * @since 2.0.0
     */
    public static function limpiar_tokens_expirados() {
        global $wpdb;

        // Obtener todos los tokens OAuth
        $results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'globalapi_oauth_token'"
        );

        foreach ($results as $result) {
            $token_data = maybe_unserialize($result->meta_value);
            
            if (is_array($token_data) && isset($token_data['expires_at'])) {
                // Eliminar tokens expirados hace más de 24 horas
                if ($token_data['expires_at'] < (time() - 86400)) {
                    delete_user_meta($result->user_id, 'globalapi_oauth_token');
                }
            }
        }
    }

    /**
     * Limpiar cache cuando se actualiza credencial
     * 
     * @param int $post_id ID del post
     * @since 2.0.0
     */
    public static function limpiar_cache_credencial($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'globalapi_credencial') {
            $tipo_servicio = get_post_meta($post_id, 'tipo_servicio', true);
            if ($tipo_servicio === 'invisioncommunity') {
                self::$credentials_cache = null;
                self::$token_cache = array();
            }
        }
    }

    /**
     * Verificar si un usuario tiene token OAuth válido
     * 
     * @param int $user_id ID del usuario (0 para actual)
     * @return bool True si tiene token válido
     * @since 2.0.0
     */
    public static function tiene_token_valido($user_id = 0) {
        $token_data = self::obtener_token_valido($user_id, false);
        return !is_wp_error($token_data);
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
                'accion' => 'oauth_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Obtener estadísticas de OAuth
     * 
     * @return array Estadísticas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        global $wpdb;

        $stats = array(
            'usuarios_conectados' => 0,
            'tokens_activos' => 0,
            'tokens_expirados' => 0,
            'ultima_autorizacion' => null
        );

        // Contar usuarios con tokens OAuth
        $results = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'globalapi_oauth_token'"
        );

        $now = time();
        foreach ($results as $result) {
            $token_data = maybe_unserialize($result->meta_value);
            
            if (is_array($token_data) && isset($token_data['expires_at'])) {
                if ($token_data['expires_at'] > $now) {
                    $stats['tokens_activos']++;
                } else {
                    $stats['tokens_expirados']++;
                }

                // Actualizar última autorización
                $created_at = $token_data['created_at'] ?? 0;
                if ($created_at > ($stats['ultima_autorizacion'] ?? 0)) {
                    $stats['ultima_autorizacion'] = $created_at;
                }
            }
        }

        $stats['usuarios_conectados'] = $stats['tokens_activos'];

        return $stats;
    }

    /**
     * Probar conexión OAuth
     * 
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    public static function probar_conexion() {
        $inicio = microtime(true);
        $resultado = array(
            'exito' => false,
            'mensaje' => '',
            'tiempo_respuesta' => 0,
            'detalles' => array()
        );

        try {
            // Verificar credenciales
            $credenciales = self::obtener_credenciales_oauth();
            
            if (is_wp_error($credenciales)) {
                $resultado['mensaje'] = 'Error de credenciales: ' . $credenciales->get_error_message();
            } else {
                $resultado['exito'] = true;
                $resultado['mensaje'] = 'Credenciales OAuth válidas';
                $resultado['detalles'] = array(
                    'client_id' => substr($credenciales['client_id'], 0, 8) . '...',
                    'authorization_url' => $credenciales['authorization_url'],
                    'token_url' => $credenciales['token_url']
                );
            }
        } catch (Exception $e) {
            $resultado['mensaje'] = 'Excepción: ' . $e->getMessage();
        }

        $resultado['tiempo_respuesta'] = round((microtime(true) - $inicio) * 1000);

        return $resultado;
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    ServicioOAuth::init();
} 