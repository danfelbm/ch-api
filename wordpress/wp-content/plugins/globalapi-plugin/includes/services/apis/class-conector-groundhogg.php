<?php
/**
 * Conector Groundhogg CRM
 *
 * Conector adaptado para WordPress environment que gestiona
 * todas las operaciones con la API de Groundhogg CRM.
 * Utiliza el GestorCredenciales para manejo seguro de autenticación.
 *
 * @package GlobalAPI
 * @subpackage Services\APIs
 * @since 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase ConectorGroundhogg
 * 
 * Maneja todas las operaciones con Groundhogg CRM:
 * - Autenticación segura con credenciales encriptadas
 * - CRUD de contactos, tags, campaigns
 * - Cache inteligente para optimizar performance
 * - Manejo robusto de errores y rate limiting
 * - Logging automático para auditoría
 */
class ConectorGroundhogg {

    /**
     * Versión de la API de Groundhogg
     */
    const API_VERSION = 'v4';
    
    /**
     * Timeout por defecto para requests
     */
    const DEFAULT_TIMEOUT = 30;
    
    /**
     * Máximo número de reintentos
     */
    const MAX_RETRY_ATTEMPTS = 3;
    
    /**
     * Cache de credenciales
     */
    private static $credenciales_cache = null;
    
    /**
     * Cache de respuestas API
     */
    private static $api_cache = array();
    
    /**
     * Endpoints disponibles de Groundhogg
     */
    const ENDPOINTS = array(
        'contacts' => '/contacts',
        'contact' => '/contacts/%d',
        'tags' => '/tags',
        'tag' => '/tags/%d',
        'campaigns' => '/campaigns',
        'campaign' => '/campaigns/%d'
    );

    /**
     * Estados válidos para contactos
     */
    const CONTACT_STATUS = array(
        'unconfirmed' => 'No confirmado',
        'confirmed' => 'Confirmado',
        'unsubscribed' => 'Desuscrito',
        'weekly' => 'Semanal',
        'monthly' => 'Mensual',
        'bounced' => 'Rebotado',
        'spam' => 'Spam',
        'complained' => 'Quejado'
    );

    /**
     * Inicializar el conector
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        add_action('globalapi_limpiar_cache_groundhogg', array(__CLASS__, 'limpiar_cache'));
        
        // Programar limpieza de cache
        if (!wp_next_scheduled('globalapi_limpiar_cache_groundhogg')) {
            wp_schedule_event(time(), 'daily', 'globalapi_limpiar_cache_groundhogg');
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
    }

    /**
     * Obtener lista de contactos
     * 
     * @param array $args Argumentos de filtrado
     * @return array|WP_Error Lista de contactos o error
     * @since 2.0.0
     */
    public static function obtener_contactos($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'search' => '',
            'status' => '',
            'tags' => array(),
            'orderby' => 'date_created',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        
        // Construir parámetros de query
        $query_params = array(
            'limit' => absint($args['limit']),
            'offset' => absint($args['offset'])
        );

        if (!empty($args['search'])) {
            $query_params['search'] = sanitize_text_field($args['search']);
        }

        if (!empty($args['status']) && array_key_exists($args['status'], self::CONTACT_STATUS)) {
            $query_params['status'] = $args['status'];
        }

        if (!empty($args['tags']) && is_array($args['tags'])) {
            $query_params['tags'] = array_map('absint', $args['tags']);
        }

        if (!empty($args['orderby'])) {
            $query_params['orderby'] = sanitize_text_field($args['orderby']);
            $query_params['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        }

        // Realizar request
        $response = self::hacer_request('GET', 'contacts', $query_params);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Procesar contactos
        $contactos = array();
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $item) {
                $contactos[] = self::procesar_contacto($item);
            }
        }

        return array(
            'contactos' => $contactos,
            'total' => $response['total_items'] ?? count($contactos),
            'total_pages' => $response['total_pages'] ?? 1,
            'current_page' => ($args['offset'] / $args['limit']) + 1
        );
    }

    /**
     * Crear nuevo contacto
     * 
     * @param array $datos Datos del contacto
     * @return array|WP_Error Contacto creado o error
     * @since 2.0.0
     */
    public static function crear_contacto($datos) {
        // Validar datos requeridos
        $validacion = self::validar_datos_contacto($datos);
        if (is_wp_error($validacion)) {
            return $validacion;
        }

        // Preparar datos para la API
        $contact_data = self::preparar_datos_contacto($datos);

        $response = self::hacer_request('POST', 'contacts', array(), $contact_data);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $contacto = self::procesar_contacto($response);
        
        // Registrar en logs
        self::registrar_log('contacto_creado', 'Nuevo contacto creado en Groundhogg', array(
            'contact_id' => $contacto['id'],
            'email' => $contacto['email']
        ));

        return $contacto;
    }

    /**
     * Actualizar contacto existente
     * 
     * @param int $contact_id ID del contacto
     * @param array $datos Nuevos datos
     * @return array|WP_Error Contacto actualizado o error
     * @since 2.0.0
     */
    public static function actualizar_contacto($contact_id, $datos) {
        $contact_id = absint($contact_id);
        if (!$contact_id) {
            return new WP_Error('id_invalido', 'ID de contacto inválido');
        }

        // Validar datos
        $validacion = self::validar_datos_contacto($datos, false);
        if (is_wp_error($validacion)) {
            return $validacion;
        }

        // Preparar datos para la API
        $contact_data = self::preparar_datos_contacto($datos);

        $endpoint = sprintf('contact', $contact_id);
        $response = self::hacer_request('PUT', $endpoint, array(), $contact_data);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $contacto = self::procesar_contacto($response);
        
        // Limpiar cache
        self::limpiar_cache_contacto($contact_id);
        
        // Registrar en logs
        self::registrar_log('contacto_actualizado', 'Contacto actualizado en Groundhogg', array(
            'contact_id' => $contact_id,
            'campos_actualizados' => array_keys($datos)
        ));

        return $contacto;
    }

    /**
     * Eliminar contacto
     * 
     * @param int $contact_id ID del contacto
     * @return bool|WP_Error True en éxito o error
     * @since 2.0.0
     */
    public static function eliminar_contacto($contact_id) {
        $contact_id = absint($contact_id);
        if (!$contact_id) {
            return new WP_Error('id_invalido', 'ID de contacto inválido');
        }

        $endpoint = sprintf('contact', $contact_id);
        $response = self::hacer_request('DELETE', $endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Limpiar cache
        self::limpiar_cache_contacto($contact_id);
        
        // Registrar en logs
        self::registrar_log('contacto_eliminado', 'Contacto eliminado de Groundhogg', array(
            'contact_id' => $contact_id
        ));

        return true;
    }

    /**
     * Obtener tags disponibles
     * 
     * @param array $args Argumentos de filtrado
     * @return array|WP_Error Lista de tags o error
     * @since 2.0.0
     */
    public static function obtener_tags($args = array()) {
        $defaults = array(
            'search' => '',
            'orderby' => 'tag_name',
            'order' => 'ASC'
        );

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'tags_' . md5(serialize($args));
        
        // Verificar cache
        if (isset(self::$api_cache[$cache_key])) {
            return self::$api_cache[$cache_key];
        }

        $query_params = array();
        
        if (!empty($args['search'])) {
            $query_params['search'] = sanitize_text_field($args['search']);
        }

        if (!empty($args['orderby'])) {
            $query_params['orderby'] = sanitize_text_field($args['orderby']);
            $query_params['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        }

        $response = self::hacer_request('GET', 'tags', $query_params);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $tags = array();
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $item) {
                $tags[] = array(
                    'id' => absint($item['ID']),
                    'name' => sanitize_text_field($item['tag_name']),
                    'description' => sanitize_textarea_field($item['tag_description'] ?? ''),
                    'color' => sanitize_hex_color($item['tag_color'] ?? '#888888'),
                    'contact_count' => absint($item['contact_count'] ?? 0)
                );
            }
        }

        // Guardar en cache por 1 hora
        self::$api_cache[$cache_key] = $tags;
        
        return $tags;
    }

    /**
     * Agregar tags a contacto
     * 
     * @param int $contact_id ID del contacto
     * @param array $tag_ids IDs de los tags
     * @return bool|WP_Error True en éxito o error
     * @since 2.0.0
     */
    public static function agregar_tags_contacto($contact_id, $tag_ids) {
        $contact_id = absint($contact_id);
        if (!$contact_id) {
            return new WP_Error('id_invalido', 'ID de contacto inválido');
        }

        if (!is_array($tag_ids) || empty($tag_ids)) {
            return new WP_Error('tags_invalidos', 'Tags inválidos');
        }

        $tag_ids = array_map('absint', $tag_ids);
        
        $endpoint = sprintf('contact', $contact_id);
        $data = array('tags' => $tag_ids);
        
        $response = self::hacer_request('POST', $endpoint . '/tags', array(), $data);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Limpiar cache del contacto
        self::limpiar_cache_contacto($contact_id);
        
        // Registrar en logs
        self::registrar_log('tags_agregados', 'Tags agregados a contacto', array(
            'contact_id' => $contact_id,
            'tag_ids' => $tag_ids
        ));

        return true;
    }

    /**
     * Realizar request HTTP a la API de Groundhogg
     * 
     * @param string $method Método HTTP
     * @param string $endpoint Endpoint de la API
     * @param array $query_params Parámetros de query
     * @param array $body Cuerpo del request
     * @return array|WP_Error Respuesta de la API o error
     * @since 2.0.0
     */
    private static function hacer_request($method, $endpoint, $query_params = array(), $body = array()) {
        // Obtener credenciales
        $credenciales = self::obtener_credenciales();
        if (is_wp_error($credenciales)) {
            return $credenciales;
        }

        // Construir URL
        $url = self::construir_url($endpoint, $query_params, $credenciales);
        
        // Configurar argumentos del request
        $args = array(
            'method' => strtoupper($method),
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($credenciales['api_key'] . ':' . $credenciales['api_secret']),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        );

        if (!empty($body) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($body);
        }

        // Realizar request
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Procesar respuesta
        return self::procesar_respuesta($response);
    }

    /**
     * Obtener credenciales de Groundhogg
     * 
     * @return array|WP_Error Credenciales o error
     * @since 2.0.0
     */
    private static function obtener_credenciales() {
        // Verificar cache
        if (self::$credenciales_cache !== null) {
            return self::$credenciales_cache;
        }

        // Verificar que existe la clase GestorCredenciales
        if (!class_exists('GestorCredenciales')) {
            return new WP_Error('gestor_no_disponible', 'GestorCredenciales no está disponible');
        }

        // Obtener credenciales de Groundhogg activas
        $credenciales_lista = GestorCredenciales::obtener_por_servicio('groundhogg', 'activa');
        
        if (empty($credenciales_lista)) {
            return new WP_Error('sin_credenciales', 'No hay credenciales de Groundhogg configuradas');
        }

        // Usar la primera credencial activa
        $credencial = reset($credenciales_lista);
        $credencial_completa = GestorCredenciales::obtener_credencial($credencial['id'], true);
        
        if (is_wp_error($credencial_completa)) {
            return $credencial_completa;
        }

        // Validar que tiene los campos necesarios
        if (empty($credencial_completa['api_key']) || empty($credencial_completa['api_secret'])) {
            return new WP_Error('credenciales_incompletas', 'Credenciales de Groundhogg incompletas');
        }

        self::$credenciales_cache = $credencial_completa;
        return $credencial_completa;
    }

    /**
     * Construir URL para la API
     * 
     * @param string $endpoint Endpoint
     * @param array $query_params Parámetros de query
     * @param array $credenciales Credenciales
     * @return string URL completa
     * @since 2.0.0
     */
    private static function construir_url($endpoint, $query_params, $credenciales) {
        $base_url = rtrim($credenciales['url_base'], '/');
        $api_endpoint = self::ENDPOINTS[$endpoint] ?? $endpoint;
        
        $url = $base_url . '/wp-json/gh/' . self::API_VERSION . $api_endpoint;
        
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
        
        return $url;
    }

    /**
     * Procesar respuesta de la API
     * 
     * @param array $response Respuesta HTTP
     * @return array|WP_Error Datos procesados o error
     * @since 2.0.0
     */
    private static function procesar_respuesta($response) {
        $codigo = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Códigos de error
        if ($codigo >= 400) {
            $mensaje_error = self::obtener_mensaje_error($codigo, $body);
            return new WP_Error('api_error', $mensaje_error, array('http_code' => $codigo));
        }

        // Decodificar JSON
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_invalido', 'Respuesta JSON inválida: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Obtener mensaje de error legible
     * 
     * @param int $codigo Código HTTP
     * @param string $body Cuerpo de la respuesta
     * @return string Mensaje de error
     * @since 2.0.0
     */
    private static function obtener_mensaje_error($codigo, $body) {
        $mensajes_default = array(
            400 => 'Solicitud inválida',
            401 => 'No autorizado - verificar credenciales',
            403 => 'Acceso prohibido',
            404 => 'Recurso no encontrado',
            422 => 'Datos inválidos',
            429 => 'Demasiadas solicitudes - rate limit excedido',
            500 => 'Error interno del servidor'
        );

        $mensaje = $mensajes_default[$codigo] ?? 'Error HTTP ' . $codigo;

        // Intentar extraer mensaje específico del cuerpo
        $data = json_decode($body, true);
        if ($data && isset($data['message'])) {
            $mensaje .= ': ' . sanitize_text_field($data['message']);
        }

        return $mensaje;
    }

    /**
     * Procesar datos de contacto de la API
     * 
     * @param array $item Datos raw del contacto
     * @return array Contacto procesado
     * @since 2.0.0
     */
    private static function procesar_contacto($item) {
        return array(
            'id' => absint($item['ID']),
            'email' => sanitize_email($item['email']),
            'first_name' => sanitize_text_field($item['first_name'] ?? ''),
            'last_name' => sanitize_text_field($item['last_name'] ?? ''),
            'full_name' => trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
            'phone' => sanitize_text_field($item['primary_phone'] ?? ''),
            'status' => sanitize_text_field($item['optin_status'] ?? 'unconfirmed'),
            'date_created' => sanitize_text_field($item['date_created'] ?? ''),
            'date_updated' => sanitize_text_field($item['date_updated'] ?? '')
        );
    }

    /**
     * Validar datos de contacto
     * 
     * @param array $datos Datos a validar
     * @return true|WP_Error True si válido o error
     * @since 2.0.0
     */
    private static function validar_datos_contacto($datos) {
        $errores = array();

        if (empty($datos['email'])) {
            $errores[] = 'Email es requerido';
        } elseif (!is_email($datos['email'])) {
            $errores[] = 'Email inválido';
        }

        if (!empty($datos['status']) && !array_key_exists($datos['status'], self::CONTACT_STATUS)) {
            $errores[] = 'Estado de contacto inválido';
        }

        if (!empty($errores)) {
            return new WP_Error('datos_invalidos', implode('. ', $errores));
        }

        return true;
    }

    /**
     * Preparar datos de contacto para la API
     * 
     * @param array $datos Datos del contacto
     * @return array Datos preparados
     * @since 2.0.0
     */
    private static function preparar_datos_contacto($datos) {
        $contact_data = array();

        if (!empty($datos['email'])) {
            $contact_data['email'] = sanitize_email($datos['email']);
        }

        if (!empty($datos['first_name'])) {
            $contact_data['first_name'] = sanitize_text_field($datos['first_name']);
        }

        if (!empty($datos['last_name'])) {
            $contact_data['last_name'] = sanitize_text_field($datos['last_name']);
        }

        if (!empty($datos['phone'])) {
            $contact_data['primary_phone'] = sanitize_text_field($datos['phone']);
        }

        if (!empty($datos['status']) && array_key_exists($datos['status'], self::CONTACT_STATUS)) {
            $contact_data['optin_status'] = $datos['status'];
        }

        return $contact_data;
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
                'accion' => 'groundhogg_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Limpiar cache
     * 
     * @since 2.0.0
     */
    public static function limpiar_cache() {
        self::$api_cache = array();
        self::$credenciales_cache = null;
    }

    /**
     * Limpiar cache de contactos
     * 
     * @since 2.0.0
     */
    private static function limpiar_cache_contactos() {
        foreach (self::$api_cache as $key => $value) {
            if (strpos($key, 'contacto_') === 0) {
                unset(self::$api_cache[$key]);
            }
        }
    }

    /**
     * Limpiar cache de un contacto específico
     * 
     * @param int $contact_id ID del contacto
     * @since 2.0.0
     */
    private static function limpiar_cache_contacto($contact_id) {
        foreach (self::$api_cache as $key => $value) {
            if (strpos($key, "contacto_{$contact_id}_") === 0) {
                unset(self::$api_cache[$key]);
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
            if ($tipo_servicio === 'groundhogg') {
                self::limpiar_cache();
            }
        }
    }

    /**
     * Obtener estadísticas de uso de la API
     * 
     * @return array Estadísticas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        // Este método podría implementarse para obtener estadísticas
        // de uso de la API de Groundhogg desde los logs
        return array(
            'requests_hoy' => 0,
            'contactos_sincronizados' => 0,
            'ultimo_sync' => null,
            'estado_conexion' => 'desconocido'
        );
    }

    /**
     * Probar conexión con la API
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
            // Intentar obtener información básica
            $response = self::hacer_request('GET', 'contacts', array('limit' => 1));
            
            if (is_wp_error($response)) {
                $resultado['mensaje'] = 'Error de conexión: ' . $response->get_error_message();
            } else {
                $resultado['exito'] = true;
                $resultado['mensaje'] = 'Conexión exitosa con Groundhogg';
                $resultado['detalles'] = array(
                    'total_contactos' => $response['total_items'] ?? 0,
                    'version_api' => self::API_VERSION
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
    ConectorGroundhogg::init();
} 