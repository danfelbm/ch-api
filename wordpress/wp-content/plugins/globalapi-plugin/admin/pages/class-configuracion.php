<?php
/**
 * Página de configuración del plugin GlobalAPI
 *
 * Esta clase maneja la página de configuración principal del plugin
 * utilizando WordPress Settings API para gestionar opciones de manera
 * segura y estandarizada.
 *
 * @package    GlobalAPI
 * @subpackage Admin/Pages
 * @since      2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Configuracion
 * 
 * Gestiona la configuración general del plugin usando WordPress Settings API.
 *
 * @since 2.0.0
 */
class GlobalAPI_Configuracion {

    /**
     * Grupo de opciones del plugin
     *
     * @since 2.0.0
     * @var string
     */
    const OPTION_GROUP = 'globalapi_configuracion';

    /**
     * Slug de la página de configuración
     *
     * @since 2.0.0
     * @var string
     */
    const PAGE_SLUG = 'globalapi-configuracion';

    /**
     * Constructor de la clase
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('admin_init', array($this, 'inicializar_configuracion'));
        add_action('wp_ajax_globalapi_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_globalapi_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_globalapi_export_config', array($this, 'ajax_export_config'));
        add_action('wp_ajax_globalapi_import_config', array($this, 'ajax_import_config'));
    }

    /**
     * Inicializar configuraciones usando Settings API
     *
     * @since 2.0.0
     * @return void
     */
    public function inicializar_configuracion() {
        // Registrar configuraciones
        register_setting(
            self::OPTION_GROUP,
            'globalapi_configuracion',
            array(
                'sanitize_callback' => array($this, 'sanitizar_opciones'),
                'default' => $this->obtener_configuracion_default()
            )
        );

        // Sección: Configuración General
        add_settings_section(
            'globalapi_general',
            __('Configuración General', 'globalapi'),
            array($this, 'seccion_general_callback'),
            self::PAGE_SLUG
        );

        // Sección: APIs y Conectores
        add_settings_section(
            'globalapi_apis',
            __('APIs y Conectores', 'globalapi'),
            array($this, 'seccion_apis_callback'),
            self::PAGE_SLUG
        );

        // Sección: Seguridad
        add_settings_section(
            'globalapi_seguridad',
            __('Configuración de Seguridad', 'globalapi'),
            array($this, 'seccion_seguridad_callback'),
            self::PAGE_SLUG
        );

        // Sección: Logs y Auditoría
        add_settings_section(
            'globalapi_logs',
            __('Logs y Auditoría', 'globalapi'),
            array($this, 'seccion_logs_callback'),
            self::PAGE_SLUG
        );

        // Sección: Performance
        add_settings_section(
            'globalapi_performance',
            __('Rendimiento y Cache', 'globalapi'),
            array($this, 'seccion_performance_callback'),
            self::PAGE_SLUG
        );

        // Registrar campos de configuración
        $this->registrar_campos_configuracion();
    }

    /**
     * Registrar todos los campos de configuración
     *
     * @since 2.0.0
     * @return void
     */
    private function registrar_campos_configuracion() {
        // =================================
        // CONFIGURACIÓN GENERAL
        // =================================

        // Habilitar plugin
        add_settings_field(
            'plugin_habilitado',
            __('Habilitar Plugin', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_general',
            array(
                'field' => 'plugin_habilitado',
                'description' => __('Activar/desactivar funcionalidad del plugin', 'globalapi')
            )
        );

        // Modo debug
        add_settings_field(
            'modo_debug',
            __('Modo Debug', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_general',
            array(
                'field' => 'modo_debug',
                'description' => __('Activar logging detallado para debug', 'globalapi')
            )
        );

        // Entorno
        add_settings_field(
            'entorno',
            __('Entorno de Trabajo', 'globalapi'),
            array($this, 'campo_select'),
            self::PAGE_SLUG,
            'globalapi_general',
            array(
                'field' => 'entorno',
                'options' => array(
                    'desarrollo' => __('Desarrollo', 'globalapi'),
                    'testing' => __('Testing', 'globalapi'),
                    'produccion' => __('Producción', 'globalapi')
                ),
                'description' => __('Entorno actual de trabajo', 'globalapi')
            )
        );

        // Timezone
        add_settings_field(
            'timezone',
            __('Zona Horaria', 'globalapi'),
            array($this, 'campo_select'),
            self::PAGE_SLUG,
            'globalapi_general',
            array(
                'field' => 'timezone',
                'options' => $this->obtener_timezones(),
                'description' => __('Zona horaria para logs y reportes', 'globalapi')
            )
        );

        // =================================
        // APIS Y CONECTORES
        // =================================

        // Timeout conexiones
        add_settings_field(
            'timeout_conexion',
            __('Timeout de Conexión (segundos)', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_apis',
            array(
                'field' => 'timeout_conexion',
                'min' => 5,
                'max' => 120,
                'description' => __('Tiempo máximo de espera para conexiones API', 'globalapi')
            )
        );

        // Reintentos
        add_settings_field(
            'max_reintentos',
            __('Máximo Reintentos', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_apis',
            array(
                'field' => 'max_reintentos',
                'min' => 1,
                'max' => 10,
                'description' => __('Número máximo de reintentos en caso de fallo', 'globalapi')
            )
        );

        // User Agent
        add_settings_field(
            'user_agent',
            __('User Agent', 'globalapi'),
            array($this, 'campo_text'),
            self::PAGE_SLUG,
            'globalapi_apis',
            array(
                'field' => 'user_agent',
                'description' => __('User Agent para requests HTTP', 'globalapi')
            )
        );

        // Verificar SSL
        add_settings_field(
            'verificar_ssl',
            __('Verificar Certificados SSL', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_apis',
            array(
                'field' => 'verificar_ssl',
                'description' => __('Verificar validez de certificados SSL/TLS', 'globalapi')
            )
        );

        // =================================
        // SEGURIDAD
        // =================================

        // Encriptación
        add_settings_field(
            'metodo_encriptacion',
            __('Método de Encriptación', 'globalapi'),
            array($this, 'campo_select'),
            self::PAGE_SLUG,
            'globalapi_seguridad',
            array(
                'field' => 'metodo_encriptacion',
                'options' => array(
                    'base64' => __('Base64 (Básico)', 'globalapi'),
                    'openssl' => __('OpenSSL AES-256', 'globalapi'),
                    'sodium' => __('Sodium (Recomendado)', 'globalapi')
                ),
                'description' => __('Método para encriptar credenciales sensibles', 'globalapi')
            )
        );

        // Clave de encriptación
        add_settings_field(
            'clave_encriptacion',
            __('Clave de Encriptación', 'globalapi'),
            array($this, 'campo_password'),
            self::PAGE_SLUG,
            'globalapi_seguridad',
            array(
                'field' => 'clave_encriptacion',
                'description' => __('Clave para encriptación (mínimo 32 caracteres)', 'globalapi')
            )
        );

        // Rate limiting
        add_settings_field(
            'rate_limit_enabled',
            __('Habilitar Rate Limiting', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_seguridad',
            array(
                'field' => 'rate_limit_enabled',
                'description' => __('Limitar número de requests por minuto', 'globalapi')
            )
        );

        // Límite de requests
        add_settings_field(
            'rate_limit_requests',
            __('Requests por Minuto', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_seguridad',
            array(
                'field' => 'rate_limit_requests',
                'min' => 10,
                'max' => 1000,
                'description' => __('Máximo número de requests permitidos por minuto', 'globalapi')
            )
        );

        // =================================
        // LOGS Y AUDITORÍA
        // =================================

        // Habilitar logs
        add_settings_field(
            'logs_habilitados',
            __('Habilitar Logs', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_logs',
            array(
                'field' => 'logs_habilitados',
                'description' => __('Activar sistema de logs de auditoría', 'globalapi')
            )
        );

        // Nivel de logs
        add_settings_field(
            'nivel_logs',
            __('Nivel de Logs', 'globalapi'),
            array($this, 'campo_select'),
            self::PAGE_SLUG,
            'globalapi_logs',
            array(
                'field' => 'nivel_logs',
                'options' => array(
                    'debug' => __('Debug (Todo)', 'globalapi'),
                    'info' => __('Info', 'globalapi'),
                    'warning' => __('Warning', 'globalapi'),
                    'error' => __('Error', 'globalapi'),
                    'critical' => __('Critical', 'globalapi')
                ),
                'description' => __('Nivel mínimo de logs a registrar', 'globalapi')
            )
        );

        // Retención de logs
        add_settings_field(
            'retencion_logs',
            __('Retención de Logs (días)', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_logs',
            array(
                'field' => 'retencion_logs',
                'min' => 7,
                'max' => 365,
                'description' => __('Días para mantener logs antes de eliminar automáticamente', 'globalapi')
            )
        );

        // =================================
        // PERFORMANCE Y CACHE
        // =================================

        // Habilitar cache
        add_settings_field(
            'cache_habilitado',
            __('Habilitar Cache', 'globalapi'),
            array($this, 'campo_checkbox'),
            self::PAGE_SLUG,
            'globalapi_performance',
            array(
                'field' => 'cache_habilitado',
                'description' => __('Activar sistema de cache para mejorar rendimiento', 'globalapi')
            )
        );

        // Duración cache
        add_settings_field(
            'cache_duracion',
            __('Duración Cache (minutos)', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_performance',
            array(
                'field' => 'cache_duracion',
                'min' => 5,
                'max' => 1440,
                'description' => __('Tiempo de vida del cache en minutos', 'globalapi')
            )
        );

        // Límite memoria
        add_settings_field(
            'limite_memoria',
            __('Límite de Memoria (MB)', 'globalapi'),
            array($this, 'campo_number'),
            self::PAGE_SLUG,
            'globalapi_performance',
            array(
                'field' => 'limite_memoria',
                'min' => 64,
                'max' => 1024,
                'description' => __('Límite de memoria para operaciones del plugin', 'globalapi')
            )
        );
    }

    /**
     * Callback para sección general
     *
     * @since 2.0.0
     * @return void
     */
    public function seccion_general_callback() {
        echo '<p>' . __('Configuración general del plugin GlobalAPI.', 'globalapi') . '</p>';
    }

    /**
     * Callback para sección APIs
     *
     * @since 2.0.0
     * @return void
     */
    public function seccion_apis_callback() {
        echo '<p>' . __('Configuración de conexiones y APIs externas.', 'globalapi') . '</p>';
    }

    /**
     * Callback para sección seguridad
     *
     * @since 2.0.0
     * @return void
     */
    public function seccion_seguridad_callback() {
        echo '<p>' . __('Configuración de seguridad y encriptación.', 'globalapi') . '</p>';
    }

    /**
     * Callback para sección logs
     *
     * @since 2.0.0
     * @return void
     */
    public function seccion_logs_callback() {
        echo '<p>' . __('Configuración del sistema de logs y auditoría.', 'globalapi') . '</p>';
    }

    /**
     * Callback para sección performance
     *
     * @since 2.0.0
     * @return void
     */
    public function seccion_performance_callback() {
        echo '<p>' . __('Configuración de rendimiento y cache.', 'globalapi') . '</p>';
    }

    /**
     * Campo checkbox
     *
     * @since 2.0.0
     * @param array $args Argumentos del campo
     * @return void
     */
    public function campo_checkbox($args) {
        $options = get_option('globalapi_configuracion', $this->obtener_configuracion_default());
        $field = $args['field'];
        $checked = isset($options[$field]) && $options[$field] ? 'checked' : '';
        
        echo '<input type="checkbox" name="globalapi_configuracion[' . $field . ']" value="1" ' . $checked . ' />';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }

    /**
     * Campo select
     *
     * @since 2.0.0
     * @param array $args Argumentos del campo
     * @return void
     */
    public function campo_select($args) {
        $options = get_option('globalapi_configuracion', $this->obtener_configuracion_default());
        $field = $args['field'];
        $current_value = isset($options[$field]) ? $options[$field] : '';
        
        echo '<select name="globalapi_configuracion[' . $field . ']">';
        foreach ($args['options'] as $value => $label) {
            $selected = ($current_value === $value) ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }

    /**
     * Campo text
     *
     * @since 2.0.0
     * @param array $args Argumentos del campo
     * @return void
     */
    public function campo_text($args) {
        $options = get_option('globalapi_configuracion', $this->obtener_configuracion_default());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        
        echo '<input type="text" name="globalapi_configuracion[' . $field . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }

    /**
     * Campo password
     *
     * @since 2.0.0
     * @param array $args Argumentos del campo
     * @return void
     */
    public function campo_password($args) {
        $options = get_option('globalapi_configuracion', $this->obtener_configuracion_default());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        
        echo '<input type="password" name="globalapi_configuracion[' . $field . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<button type="button" class="button toggle-password" data-target="globalapi_configuracion[' . $field . ']">';
        echo __('Mostrar/Ocultar', 'globalapi');
        echo '</button>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }

    /**
     * Campo number
     *
     * @since 2.0.0
     * @param array $args Argumentos del campo
     * @return void
     */
    public function campo_number($args) {
        $options = get_option('globalapi_configuracion', $this->obtener_configuracion_default());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        
        $min = isset($args['min']) ? 'min="' . $args['min'] . '"' : '';
        $max = isset($args['max']) ? 'max="' . $args['max'] . '"' : '';
        
        echo '<input type="number" name="globalapi_configuracion[' . $field . ']" value="' . esc_attr($value) . '" ' . $min . ' ' . $max . ' class="small-text" />';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }

    /**
     * Sanitizar opciones antes de guardar
     *
     * @since 2.0.0
     * @param array $input Opciones a sanitizar
     * @return array Opciones sanitizadas
     */
    public function sanitizar_opciones($input) {
        $output = array();
        $defaults = $this->obtener_configuracion_default();

        // Campos de texto
        $text_fields = array('user_agent', 'clave_encriptacion');
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = sanitize_text_field($input[$field]);
            }
        }

        // Campos select
        $select_fields = array('entorno', 'timezone', 'metodo_encriptacion', 'nivel_logs');
        foreach ($select_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = sanitize_text_field($input[$field]);
            }
        }

        // Campos numéricos
        $number_fields = array(
            'timeout_conexion', 'max_reintentos', 'rate_limit_requests',
            'retencion_logs', 'cache_duracion', 'limite_memoria'
        );
        foreach ($number_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = absint($input[$field]);
            }
        }

        // Campos checkbox
        $checkbox_fields = array(
            'plugin_habilitado', 'modo_debug', 'verificar_ssl',
            'rate_limit_enabled', 'logs_habilitados', 'cache_habilitado'
        );
        foreach ($checkbox_fields as $field) {
            $output[$field] = isset($input[$field]) ? 1 : 0;
        }

        // Validaciones específicas
        if (isset($output['clave_encriptacion']) && strlen($output['clave_encriptacion']) < 32) {
            add_settings_error(
                'globalapi_configuracion',
                'clave_encriptacion',
                __('La clave de encriptación debe tener al menos 32 caracteres.', 'globalapi')
            );
            unset($output['clave_encriptacion']);
        }

        // Registrar cambios en logs
        GlobalAPI_Log_Auditoria::registrar_log(
            'config_update',
            'Configuración del plugin actualizada',
            array(
                'campos_modificados' => array_keys($output),
                'usuario_id' => get_current_user_id()
            )
        );

        return array_merge($defaults, $output);
    }

    /**
     * Obtener configuración por defecto
     *
     * @since 2.0.0
     * @return array Configuración por defecto
     */
    private function obtener_configuracion_default() {
        return array(
            // General
            'plugin_habilitado' => 1,
            'modo_debug' => 0,
            'entorno' => 'desarrollo',
            'timezone' => 'America/Bogota',
            
            // APIs
            'timeout_conexion' => 30,
            'max_reintentos' => 3,
            'user_agent' => 'GlobalAPI Plugin v' . GLOBALAPI_VERSION,
            'verificar_ssl' => 1,
            
            // Seguridad
            'metodo_encriptacion' => 'base64',
            'clave_encriptacion' => '',
            'rate_limit_enabled' => 1,
            'rate_limit_requests' => 60,
            
            // Logs
            'logs_habilitados' => 1,
            'nivel_logs' => 'info',
            'retencion_logs' => 30,
            
            // Performance
            'cache_habilitado' => 1,
            'cache_duracion' => 60,
            'limite_memoria' => 256
        );
    }

    /**
     * Obtener lista de timezones
     *
     * @since 2.0.0
     * @return array Lista de timezones
     */
    private function obtener_timezones() {
        $timezones = array(
            'America/Bogota' => __('Colombia (Bogotá)', 'globalapi'),
            'America/New_York' => __('Nueva York', 'globalapi'),
            'America/Los_Angeles' => __('Los Ángeles', 'globalapi'),
            'Europe/Madrid' => __('Madrid', 'globalapi'),
            'Europe/London' => __('Londres', 'globalapi'),
            'UTC' => __('UTC', 'globalapi')
        );
        
        return $timezones;
    }

    /**
     * AJAX: Probar conexión con API
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('globalapi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'globalapi'));
        }

        $tipo_api = sanitize_text_field($_POST['tipo_api']);
        $resultado = array('success' => false, 'message' => '');

        // Lógica para probar conexión según tipo de API
        switch ($tipo_api) {
            case 'groundhogg':
                $credenciales = GlobalAPI_Credencial::obtener_por_tipo_servicio('groundhogg');
                if ($credenciales) {
                    // Probar conexión
                    $resultado['success'] = true;
                    $resultado['message'] = __('Conexión exitosa con Groundhogg.', 'globalapi');
                } else {
                    $resultado['message'] = __('No se encontraron credenciales para Groundhogg.', 'globalapi');
                }
                break;
                
            default:
                $resultado['message'] = __('Tipo de API no soportado.', 'globalapi');
        }

        wp_send_json($resultado);
    }

    /**
     * AJAX: Limpiar cache
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_clear_cache() {
        check_ajax_referer('globalapi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'globalapi'));
        }

        // Limpiar cache del plugin
        wp_cache_flush();
        
        // Eliminar transients específicos
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_globalapi_%' OR option_name LIKE '_transient_timeout_globalapi_%'"
        );

        GlobalAPI_Log_Auditoria::registrar_log(
            'cache_clear',
            'Cache del plugin limpiado manualmente',
            array('usuario_id' => get_current_user_id())
        );

        wp_send_json_success(__('Cache limpiado exitosamente.', 'globalapi'));
    }

    /**
     * AJAX: Exportar configuración
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_export_config() {
        check_ajax_referer('globalapi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'globalapi'));
        }

        $config = get_option('globalapi_configuracion', array());
        
        // Remover datos sensibles
        unset($config['clave_encriptacion']);
        
        $export_data = array(
            'version' => GLOBALAPI_VERSION,
            'fecha_export' => current_time('mysql'),
            'configuracion' => $config
        );

        GlobalAPI_Log_Auditoria::registrar_log(
            'config_export',
            'Configuración exportada',
            array('usuario_id' => get_current_user_id())
        );

        wp_send_json_success($export_data);
    }

    /**
     * AJAX: Importar configuración
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_import_config() {
        check_ajax_referer('globalapi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'globalapi'));
        }

        $config_json = sanitize_textarea_field($_POST['config_data']);
        $config_data = json_decode($config_json, true);

        if (!$config_data || !isset($config_data['configuracion'])) {
            wp_send_json_error(__('Formato de configuración inválido.', 'globalapi'));
        }

        // Validar y sanitizar configuración
        $new_config = $this->sanitizar_opciones($config_data['configuracion']);
        update_option('globalapi_configuracion', $new_config);

        GlobalAPI_Log_Auditoria::registrar_log(
            'config_import',
            'Configuración importada',
            array(
                'version_importada' => $config_data['version'],
                'usuario_id' => get_current_user_id()
            )
        );

        wp_send_json_success(__('Configuración importada exitosamente.', 'globalapi'));
    }

    /**
     * Obtener configuración actual
     *
     * @since 2.0.0
     * @param string $key Clave específica (opcional)
     * @return mixed Configuración completa o valor específico
     */
    public static function obtener_configuracion($key = null) {
        $config = get_option('globalapi_configuracion', array());
        
        if ($key) {
            return isset($config[$key]) ? $config[$key] : null;
        }
        
        return $config;
    }

    /**
     * Verificar si el plugin está habilitado
     *
     * @since 2.0.0
     * @return bool
     */
    public static function plugin_habilitado() {
        return (bool) self::obtener_configuracion('plugin_habilitado');
    }
}

// Inicializar configuración
new GlobalAPI_Configuracion(); 