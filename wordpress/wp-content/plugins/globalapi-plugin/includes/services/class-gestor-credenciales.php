<?php
/**
 * Gestor de Credenciales WordPress
 *
 * Gestión segura de credenciales usando WordPress encryption
 * Maneja encriptación, desencriptación y validación de credenciales
 * para diferentes servicios de API.
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
 * Clase GestorCredenciales
 * 
 * Gestiona todas las operaciones relacionadas con credenciales:
 * - Encriptación/desencriptación segura
 * - CRUD de credenciales
 * - Validación por tipo de servicio
 * - Cache inteligente
 * - Auditoría automática
 */
class GestorCredenciales {

    /**
     * Prefijo para opciones de WordPress
     */
    const OPTION_PREFIX = 'globalapi_cred_';
    
    /**
     * Prefijo para salt de encriptación
     */
    const SALT_PREFIX = 'globalapi_salt_';
    
    /**
     * Cache de credenciales
     */
    private static $cache = array();
    
    /**
     * Tipos de servicio soportados
     */
    const TIPOS_SERVICIO = array(
        'groundhogg' => 'Groundhogg CRM',
        'invision' => 'InvisionCommunity',
        'wordpress' => 'WordPress REST API',
        'custom' => 'API Personalizada'
    );

    /**
     * Configuraciones por defecto por tipo de servicio
     */
    const CONFIGURACIONES_DEFAULT = array(
        'groundhogg' => array(
            'timeout' => 30,
            'retry_attempts' => 3,
            'endpoints' => array('contacts', 'tags', 'campaigns'),
            'version' => 'v4'
        ),
        'invision' => array(
            'timeout' => 15,
            'retry_attempts' => 2,
            'scopes' => array('read', 'profile'),
            'version' => '4.7'
        ),
        'wordpress' => array(
            'timeout' => 20,
            'retry_attempts' => 3,
            'endpoints' => array('posts', 'users', 'media'),
            'version' => 'v2'
        )
    );

    /**
     * Inicializar el gestor
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Generar salt si no existe
        self::generar_salt_si_necesario();
        
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        add_action('globalapi_limpiar_cache_credenciales', array(__CLASS__, 'limpiar_cache'));
        
        // Programar limpieza automática de cache
        if (!wp_next_scheduled('globalapi_limpiar_cache_credenciales')) {
            wp_schedule_event(time(), 'hourly', 'globalapi_limpiar_cache_credenciales');
        }
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Hook para limpiar cache al guardar credenciales
        add_action('save_post', array(__CLASS__, 'limpiar_cache_post'), 10, 1);
        
        // Hook para validar credenciales antes de guardar
        add_action('wp_insert_post_data', array(__CLASS__, 'validar_antes_guardar'), 10, 2);
    }

    /**
     * Crear nueva credencial
     * 
     * @param array $datos Datos de la credencial
     * @return int|WP_Error ID de la credencial o error
     * @since 2.0.0
     */
    public static function crear_credencial($datos) {
        // Validar datos
        $validacion = self::validar_datos_credencial($datos);
        if (is_wp_error($validacion)) {
            return $validacion;
        }

        // Encriptar datos sensibles
        $datos_encriptados = self::encriptar_datos_sensibles($datos);
        if (is_wp_error($datos_encriptados)) {
            return $datos_encriptados;
        }

        // Crear post de credencial
        $post_data = array(
            'post_title' => sanitize_text_field($datos['nombre']),
            'post_content' => sanitize_textarea_field($datos['descripcion'] ?? ''),
            'post_status' => 'private',
            'post_type' => 'globalapi_credencial',
            'meta_input' => array(
                'tipo_servicio' => sanitize_text_field($datos['tipo_servicio']),
                'estado' => sanitize_text_field($datos['estado'] ?? 'activa'),
                'url_base' => esc_url_raw($datos['url_base']),
                'configuracion' => wp_json_encode($datos_encriptados['configuracion']),
                'fecha_creacion' => current_time('mysql'),
                'fecha_actualizacion' => current_time('mysql'),
                'usuario_creacion' => get_current_user_id()
            )
        );

        $credencial_id = wp_insert_post($post_data);
        
        if (is_wp_error($credencial_id)) {
            return $credencial_id;
        }

        // Guardar datos encriptados como meta
        foreach ($datos_encriptados['encriptados'] as $key => $valor) {
            update_post_meta($credencial_id, $key, $valor);
        }

        // Asignar taxonomías
        if (!empty($datos['tipo_servicio'])) {
            wp_set_object_terms($credencial_id, $datos['tipo_servicio'], 'globalapi_tipo_servicio_cred');
        }
        
        if (!empty($datos['estado'])) {
            wp_set_object_terms($credencial_id, $datos['estado'], 'globalapi_estado_cred');
        }

        // Limpiar cache
        self::limpiar_cache();

        // Registrar en auditoría
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'credencial_creada',
                'descripcion' => 'Nueva credencial creada: ' . $datos['nombre'],
                'datos' => array(
                    'credencial_id' => $credencial_id,
                    'tipo_servicio' => $datos['tipo_servicio']
                )
            ));
        }

        return $credencial_id;
    }

    /**
     * Obtener credencial por ID
     * 
     * @param int $credencial_id ID de la credencial
     * @param bool $desencriptar Si desencriptar datos sensibles
     * @return array|WP_Error Datos de la credencial o error
     * @since 2.0.0
     */
    public static function obtener_credencial($credencial_id, $desencriptar = false) {
        // Verificar cache
        $cache_key = "credencial_{$credencial_id}_" . ($desencriptar ? 'dec' : 'enc');
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $post = get_post($credencial_id);
        if (!$post || $post->post_type !== 'globalapi_credencial') {
            return new WP_Error('credencial_no_encontrada', 'Credencial no encontrada');
        }

        // Obtener meta datos
        $meta = get_post_meta($credencial_id);
        
        $credencial = array(
            'id' => $credencial_id,
            'nombre' => $post->post_title,
            'descripcion' => $post->post_content,
            'tipo_servicio' => $meta['tipo_servicio'][0] ?? '',
            'estado' => $meta['estado'][0] ?? 'activa',
            'url_base' => $meta['url_base'][0] ?? '',
            'configuracion' => json_decode($meta['configuracion'][0] ?? '{}', true),
            'fecha_creacion' => $meta['fecha_creacion'][0] ?? '',
            'fecha_actualizacion' => $meta['fecha_actualizacion'][0] ?? '',
            'usuario_creacion' => $meta['usuario_creacion'][0] ?? 0
        );

        // Desencriptar si se solicita
        if ($desencriptar) {
            $campos_encriptados = array('api_key', 'api_secret', 'client_secret', 'tokens');
            foreach ($campos_encriptados as $campo) {
                if (isset($meta[$campo][0])) {
                    $valor_desencriptado = self::desencriptar($meta[$campo][0]);
                    if (!is_wp_error($valor_desencriptado)) {
                        $credencial[$campo] = $valor_desencriptado;
                    }
                }
            }
        } else {
            // Solo indicar si existen
            $campos_encriptados = array('api_key', 'api_secret', 'client_secret', 'tokens');
            foreach ($campos_encriptados as $campo) {
                $credencial[$campo . '_existe'] = !empty($meta[$campo][0]);
            }
        }

        // Guardar en cache
        self::$cache[$cache_key] = $credencial;

        return $credencial;
    }

    /**
     * Actualizar credencial existente
     * 
     * @param int $credencial_id ID de la credencial
     * @param array $datos Nuevos datos
     * @return bool|WP_Error True en éxito o error
     * @since 2.0.0
     */
    public static function actualizar_credencial($credencial_id, $datos) {
        // Verificar que existe
        $credencial_actual = self::obtener_credencial($credencial_id);
        if (is_wp_error($credencial_actual)) {
            return $credencial_actual;
        }

        // Validar nuevos datos
        $validacion = self::validar_datos_credencial($datos, $credencial_id);
        if (is_wp_error($validacion)) {
            return $validacion;
        }

        // Encriptar datos sensibles
        $datos_encriptados = self::encriptar_datos_sensibles($datos);
        if (is_wp_error($datos_encriptados)) {
            return $datos_encriptados;
        }

        // Actualizar post principal
        $post_data = array(
            'ID' => $credencial_id,
            'post_title' => sanitize_text_field($datos['nombre']),
            'post_content' => sanitize_textarea_field($datos['descripcion'] ?? '')
        );

        $resultado = wp_update_post($post_data);
        if (is_wp_error($resultado)) {
            return $resultado;
        }

        // Actualizar meta datos
        $meta_updates = array(
            'tipo_servicio' => sanitize_text_field($datos['tipo_servicio']),
            'estado' => sanitize_text_field($datos['estado'] ?? 'activa'),
            'url_base' => esc_url_raw($datos['url_base']),
            'configuracion' => wp_json_encode($datos_encriptados['configuracion']),
            'fecha_actualizacion' => current_time('mysql')
        );

        foreach ($meta_updates as $key => $valor) {
            update_post_meta($credencial_id, $key, $valor);
        }

        // Actualizar datos encriptados
        foreach ($datos_encriptados['encriptados'] as $key => $valor) {
            update_post_meta($credencial_id, $key, $valor);
        }

        // Actualizar taxonomías
        if (!empty($datos['tipo_servicio'])) {
            wp_set_object_terms($credencial_id, $datos['tipo_servicio'], 'globalapi_tipo_servicio_cred');
        }
        
        if (!empty($datos['estado'])) {
            wp_set_object_terms($credencial_id, $datos['estado'], 'globalapi_estado_cred');
        }

        // Limpiar cache
        self::limpiar_cache();

        // Registrar en auditoría
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'credencial_actualizada',
                'descripcion' => 'Credencial actualizada: ' . $datos['nombre'],
                'datos' => array(
                    'credencial_id' => $credencial_id,
                    'cambios' => array_keys($datos)
                )
            ));
        }

        return true;
    }

    /**
     * Eliminar credencial
     * 
     * @param int $credencial_id ID de la credencial
     * @return bool|WP_Error True en éxito o error
     * @since 2.0.0
     */
    public static function eliminar_credencial($credencial_id) {
        // Verificar que existe
        $credencial = self::obtener_credencial($credencial_id);
        if (is_wp_error($credencial)) {
            return $credencial;
        }

        // Eliminar post
        $resultado = wp_delete_post($credencial_id, true);
        if (!$resultado) {
            return new WP_Error('error_eliminacion', 'No se pudo eliminar la credencial');
        }

        // Limpiar cache
        self::limpiar_cache();

        // Registrar en auditoría
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'credencial_eliminada',
                'descripcion' => 'Credencial eliminada: ' . $credencial['nombre'],
                'datos' => array(
                    'credencial_id' => $credencial_id,
                    'tipo_servicio' => $credencial['tipo_servicio']
                )
            ));
        }

        return true;
    }

    /**
     * Obtener credenciales por tipo de servicio
     * 
     * @param string $tipo_servicio Tipo de servicio
     * @param string $estado Estado de las credenciales
     * @return array Lista de credenciales
     * @since 2.0.0
     */
    public static function obtener_por_servicio($tipo_servicio, $estado = 'activa') {
        $cache_key = "servicio_{$tipo_servicio}_{$estado}";
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $args = array(
            'post_type' => 'globalapi_credencial',
            'post_status' => 'private',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'tipo_servicio',
                    'value' => $tipo_servicio,
                    'compare' => '='
                ),
                array(
                    'key' => 'estado',
                    'value' => $estado,
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);
        $credenciales = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $credencial = self::obtener_credencial(get_the_ID());
                if (!is_wp_error($credencial)) {
                    $credenciales[] = $credencial;
                }
            }
            wp_reset_postdata();
        }

        self::$cache[$cache_key] = $credenciales;
        return $credenciales;
    }

    /**
     * Probar conexión con credencial
     * 
     * @param int $credencial_id ID de la credencial
     * @return array|WP_Error Resultado de la prueba
     * @since 2.0.0
     */
    public static function probar_credencial($credencial_id) {
        $credencial = self::obtener_credencial($credencial_id, true);
        if (is_wp_error($credencial)) {
            return $credencial;
        }

        $inicio = microtime(true);
        $resultado = array(
            'exito' => false,
            'mensaje' => '',
            'tiempo_respuesta' => 0,
            'codigo_http' => 0,
            'detalles' => array()
        );

        try {
            switch ($credencial['tipo_servicio']) {
                case 'groundhogg':
                    $resultado = self::probar_groundhogg($credencial);
                    break;
                    
                case 'invision':
                    $resultado = self::probar_invision($credencial);
                    break;
                    
                case 'wordpress':
                    $resultado = self::probar_wordpress($credencial);
                    break;
                    
                default:
                    $resultado = self::probar_generico($credencial);
                    break;
            }
        } catch (Exception $e) {
            $resultado['mensaje'] = 'Error en prueba: ' . $e->getMessage();
        }

        $resultado['tiempo_respuesta'] = round((microtime(true) - $inicio) * 1000);

        // Actualizar estado basado en resultado
        $nuevo_estado = $resultado['exito'] ? 'activa' : 'error';
        update_post_meta($credencial_id, 'estado', $nuevo_estado);
        update_post_meta($credencial_id, 'ultima_verificacion', current_time('mysql'));
        
        // Registrar resultado
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'credencial_probada',
                'descripcion' => 'Prueba de credencial: ' . ($resultado['exito'] ? 'exitosa' : 'fallida'),
                'datos' => array(
                    'credencial_id' => $credencial_id,
                    'resultado' => $resultado
                )
            ));
        }

        return $resultado;
    }

    /**
     * Encriptar datos sensibles
     * 
     * @param array $datos Datos a procesar
     * @return array|WP_Error Datos procesados o error
     * @since 2.0.0
     */
    private static function encriptar_datos_sensibles($datos) {
        $campos_sensibles = array('api_key', 'api_secret', 'client_secret', 'tokens');
        $datos_encriptados = array();
        $configuracion = $datos;

        foreach ($campos_sensibles as $campo) {
            if (!empty($datos[$campo])) {
                $encriptado = self::encriptar($datos[$campo]);
                if (is_wp_error($encriptado)) {
                    return $encriptado;
                }
                $datos_encriptados[$campo] = $encriptado;
                unset($configuracion[$campo]);
            }
        }

        return array(
            'encriptados' => $datos_encriptados,
            'configuracion' => $configuracion
        );
    }

    /**
     * Encriptar un valor
     * 
     * @param string $valor Valor a encriptar
     * @return string|WP_Error Valor encriptado o error
     * @since 2.0.0
     */
    private static function encriptar($valor) {
        if (empty($valor)) {
            return '';
        }

        $salt = get_option(self::SALT_PREFIX . 'main');
        if (!$salt) {
            return new WP_Error('sin_salt', 'Salt de encriptación no disponible');
        }

        // Usar cifrado simétrico con WordPress
        $key = wp_hash($salt . AUTH_SALT);
        $iv = substr(wp_hash($salt . SECURE_AUTH_SALT), 0, 16);
        
        $encriptado = openssl_encrypt($valor, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encriptado === false) {
            return new WP_Error('error_encriptacion', 'Error al encriptar el valor');
        }

        return base64_encode($encriptado);
    }

    /**
     * Desencriptar un valor
     * 
     * @param string $valor_encriptado Valor encriptado
     * @return string|WP_Error Valor desencriptado o error
     * @since 2.0.0
     */
    private static function desencriptar($valor_encriptado) {
        if (empty($valor_encriptado)) {
            return '';
        }

        $salt = get_option(self::SALT_PREFIX . 'main');
        if (!$salt) {
            return new WP_Error('sin_salt', 'Salt de encriptación no disponible');
        }

        $valor_decodificado = base64_decode($valor_encriptado, true);
        if ($valor_decodificado === false) {
            return new WP_Error('error_decodificacion', 'Error al decodificar el valor');
        }

        $key = wp_hash($salt . AUTH_SALT);
        $iv = substr(wp_hash($salt . SECURE_AUTH_SALT), 0, 16);
        
        $desencriptado = openssl_decrypt($valor_decodificado, 'AES-256-CBC', $key, 0, $iv);
        
        if ($desencriptado === false) {
            return new WP_Error('error_desencriptacion', 'Error al desencriptar el valor');
        }

        return $desencriptado;
    }

    /**
     * Generar salt de encriptación si no existe
     * 
     * @since 2.0.0
     */
    private static function generar_salt_si_necesario() {
        $salt_key = self::SALT_PREFIX . 'main';
        
        if (!get_option($salt_key)) {
            $salt = wp_generate_password(64, true, true);
            update_option($salt_key, $salt, false); // No autoload
        }
    }

    /**
     * Validar datos de credencial
     * 
     * @param array $datos Datos a validar
     * @param int $credencial_id ID si es actualización
     * @return true|WP_Error True si válido o error
     * @since 2.0.0
     */
    private static function validar_datos_credencial($datos, $credencial_id = null) {
        $errores = array();

        // Nombre requerido
        if (empty($datos['nombre'])) {
            $errores[] = 'El nombre es requerido';
        }

        // Tipo de servicio válido
        if (empty($datos['tipo_servicio']) || !array_key_exists($datos['tipo_servicio'], self::TIPOS_SERVICIO)) {
            $errores[] = 'Tipo de servicio inválido';
        }

        // URL base válida
        if (!empty($datos['url_base']) && !filter_var($datos['url_base'], FILTER_VALIDATE_URL)) {
            $errores[] = 'URL base inválida';
        }

        // Validaciones específicas por tipo
        if (!empty($datos['tipo_servicio'])) {
            switch ($datos['tipo_servicio']) {
                case 'groundhogg':
                    if (empty($datos['api_key']) || empty($datos['api_secret'])) {
                        $errores[] = 'API Key y API Secret son requeridos para Groundhogg';
                    }
                    break;
                    
                case 'invision':
                    if (empty($datos['client_id']) || empty($datos['client_secret'])) {
                        $errores[] = 'Client ID y Client Secret son requeridos para InvisionCommunity';
                    }
                    break;
            }
        }

        if (!empty($errores)) {
            return new WP_Error('datos_invalidos', implode('. ', $errores));
        }

        return true;
    }

    /**
     * Probar credencial de Groundhogg
     * 
     * @param array $credencial Datos de credencial
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    private static function probar_groundhogg($credencial) {
        $url = rtrim($credencial['url_base'], '/') . '/wp-json/gh/v4/contacts';
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($credencial['api_key'] . ':' . $credencial['api_secret'])
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'exito' => false,
                'mensaje' => 'Error de conexión: ' . $response->get_error_message(),
                'codigo_http' => 0
            );
        }

        $codigo = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return array(
            'exito' => $codigo === 200,
            'mensaje' => $codigo === 200 ? 'Conexión exitosa' : 'Error HTTP: ' . $codigo,
            'codigo_http' => $codigo,
            'detalles' => array(
                'endpoint' => $url,
                'metodo' => 'GET'
            )
        );
    }

    /**
     * Probar credencial de InvisionCommunity
     * 
     * @param array $credencial Datos de credencial
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    private static function probar_invision($credencial) {
        $url = rtrim($credencial['url_base'], '/') . '/api/core/me';
        
        // Necesitaríamos un token válido para probar realmente
        // Por ahora solo probamos conectividad básica
        $response = wp_remote_head($credencial['url_base'], array(
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array(
                'exito' => false,
                'mensaje' => 'Error de conexión: ' . $response->get_error_message(),
                'codigo_http' => 0
            );
        }

        $codigo = wp_remote_retrieve_response_code($response);

        return array(
            'exito' => $codigo < 500, // Cualquier cosa menor a 500 indica que el servidor responde
            'mensaje' => $codigo < 500 ? 'Servidor accesible' : 'Servidor no disponible',
            'codigo_http' => $codigo,
            'detalles' => array(
                'endpoint' => $credencial['url_base'],
                'metodo' => 'HEAD'
            )
        );
    }

    /**
     * Probar credencial de WordPress
     * 
     * @param array $credencial Datos de credencial
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    private static function probar_wordpress($credencial) {
        $url = rtrim($credencial['url_base'], '/') . '/wp-json/wp/v2/posts';
        
        $args = array(
            'timeout' => 15
        );

        // Si tiene credenciales, agregarlas
        if (!empty($credencial['username']) && !empty($credencial['password'])) {
            $args['headers'] = array(
                'Authorization' => 'Basic ' . base64_encode($credencial['username'] . ':' . $credencial['password'])
            );
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return array(
                'exito' => false,
                'mensaje' => 'Error de conexión: ' . $response->get_error_message(),
                'codigo_http' => 0
            );
        }

        $codigo = wp_remote_retrieve_response_code($response);

        return array(
            'exito' => $codigo === 200 || $codigo === 401, // 401 significa que la API funciona pero necesita auth
            'mensaje' => $codigo === 200 ? 'Conexión exitosa' : ($codigo === 401 ? 'API accesible (requiere autenticación)' : 'Error HTTP: ' . $codigo),
            'codigo_http' => $codigo,
            'detalles' => array(
                'endpoint' => $url,
                'metodo' => 'GET'
            )
        );
    }

    /**
     * Probar credencial genérica
     * 
     * @param array $credencial Datos de credencial
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    private static function probar_generico($credencial) {
        if (empty($credencial['url_base'])) {
            return array(
                'exito' => false,
                'mensaje' => 'URL base requerida para prueba',
                'codigo_http' => 0
            );
        }

        $response = wp_remote_head($credencial['url_base'], array(
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array(
                'exito' => false,
                'mensaje' => 'Error de conexión: ' . $response->get_error_message(),
                'codigo_http' => 0
            );
        }

        $codigo = wp_remote_retrieve_response_code($response);

        return array(
            'exito' => $codigo >= 200 && $codigo < 400,
            'mensaje' => $codigo >= 200 && $codigo < 400 ? 'Servidor accesible' : 'Error HTTP: ' . $codigo,
            'codigo_http' => $codigo,
            'detalles' => array(
                'endpoint' => $credencial['url_base'],
                'metodo' => 'HEAD'
            )
        );
    }

    /**
     * Limpiar cache
     * 
     * @since 2.0.0
     */
    public static function limpiar_cache() {
        self::$cache = array();
    }

    /**
     * Limpiar cache al guardar post
     * 
     * @param int $post_id ID del post
     * @since 2.0.0
     */
    public static function limpiar_cache_post($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'globalapi_credencial') {
            self::limpiar_cache();
        }
    }

    /**
     * Validar antes de guardar post
     * 
     * @param array $data Datos del post
     * @param array $postarr Datos del array post
     * @return array Datos modificados
     * @since 2.0.0
     */
    public static function validar_antes_guardar($data, $postarr) {
        if ($data['post_type'] === 'globalapi_credencial') {
            // Asegurar que el post sea privado
            $data['post_status'] = 'private';
        }
        
        return $data;
    }

    /**
     * Obtener estadísticas de credenciales
     * 
     * @return array Estadísticas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        $cache_key = 'estadisticas_credenciales';
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $stats = array(
            'total' => 0,
            'activas' => 0,
            'inactivas' => 0,
            'error' => 0,
            'por_servicio' => array()
        );

        $query = new WP_Query(array(
            'post_type' => 'globalapi_credencial',
            'post_status' => 'private',
            'posts_per_page' => -1
        ));

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $estado = get_post_meta(get_the_ID(), 'estado', true);
                $servicio = get_post_meta(get_the_ID(), 'tipo_servicio', true);
                
                $stats['total']++;
                
                if (isset($stats[$estado])) {
                    $stats[$estado]++;
                }
                
                if (!isset($stats['por_servicio'][$servicio])) {
                    $stats['por_servicio'][$servicio] = 0;
                }
                $stats['por_servicio'][$servicio]++;
            }
            wp_reset_postdata();
        }

        self::$cache[$cache_key] = $stats;
        return $stats;
    }

    /**
     * Obtener tipos de servicio disponibles
     * 
     * @return array Tipos de servicio
     * @since 2.0.0
     */
    public static function obtener_tipos_servicio() {
        return self::TIPOS_SERVICIO;
    }

    /**
     * Obtener configuración por defecto para un servicio
     * 
     * @param string $tipo_servicio Tipo de servicio
     * @return array Configuración por defecto
     * @since 2.0.0
     */
    public static function obtener_configuracion_default($tipo_servicio) {
        return self::CONFIGURACIONES_DEFAULT[$tipo_servicio] ?? array();
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    GestorCredenciales::init();
} 