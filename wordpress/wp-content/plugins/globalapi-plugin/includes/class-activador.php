<?php
/**
 * Clase Activador del Plugin GlobalAPI
 *
 * Esta clase gestiona la activación del plugin, incluyendo la creación
 * de tablas de base de datos, configuraciones iniciales y verificaciones
 * de compatibilidad necesarias para el funcionamiento del plugin.
 *
 * @package     GlobalAPI
 * @subpackage  GlobalAPI/includes
 * @since       2.0.0
 * @author      Colombia Humana - Desarrollo Tecnológico
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase GlobalAPI_Activador
 *
 * Gestiona todas las operaciones necesarias durante la activación del plugin.
 * Incluye creación de tablas, configuraciones por defecto, y verificaciones
 * de compatibilidad con otros plugins (especialmente Groundhogg).
 *
 * @since 2.0.0
 */
class GlobalAPI_Activador {

    /**
     * Método principal de activación del plugin
     *
     * Se ejecuta cuando el plugin se activa por primera vez o después
     * de una actualización. Coordina todas las tareas de inicialización.
     *
     * @since 2.0.0
     * @static
     */
    public static function activar() {
        global $wpdb;
        
        // Verificar capacidades del usuario actual
        if (!current_user_can('activate_plugins')) {
            wp_die(
                esc_html__('No tienes permisos suficientes para activar plugins.', 'globalapi'),
                esc_html__('Error de Permisos', 'globalapi'),
                ['back_link' => true]
            );
        }
        
        // Verificar que el plugin activado es el correcto
        $plugin_activado = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        if ($plugin_activado !== plugin_basename(__FILE__)) {
            // Verificación adicional de seguridad
            check_admin_referer('activate-plugin_' . $plugin_activado);
        }
        
        // Log del proceso de activación
        error_log('GlobalAPI: Iniciando proceso de activación del plugin');
        
        try {
            // 1. Verificar dependencias del sistema
            self::verificar_dependencias();
            
            // 2. Crear tablas personalizadas en la base de datos
            self::crear_tablas_bd();
            
            // 3. Insertar configuraciones por defecto
            self::insertar_configuraciones_defecto();
            
            // 4. Crear roles y capacidades personalizadas
            self::crear_roles_capacidades();
            
            // 5. Configurar cron jobs para tareas programadas
            self::configurar_tareas_programadas();
            
            // 6. Crear páginas necesarias (si aplica)
            self::crear_paginas_necesarias();
            
            // 7. Configurar opciones de plugin
            self::configurar_opciones_plugin();
            
            // 8. Verificar integridad de instalación
            self::verificar_integridad_instalacion();
            
            // Registrar activación exitosa
            update_option('globalapi_activado', current_time('mysql'));
            update_option('globalapi_version_bd', GLOBALAPI_VERSION);
            
            error_log('GlobalAPI: Plugin activado exitosamente - Versión ' . GLOBALAPI_VERSION);
            
        } catch (Exception $e) {
            // En caso de error durante la activación
            error_log('GlobalAPI Error durante activación: ' . $e->getMessage());
            
            wp_die(
                sprintf(
                    esc_html__('Error durante la activación del plugin GlobalAPI: %s', 'globalapi'),
                    esc_html($e->getMessage())
                ),
                esc_html__('Error de Activación', 'globalapi'),
                ['back_link' => true]
            );
        }
    }
    
    /**
     * Verificar dependencias del sistema
     *
     * Verifica que todas las dependencias necesarias estén disponibles,
     * incluyendo otros plugins requeridos y extensiones de PHP.
     *
     * @since 2.0.0
     * @throws Exception Si faltan dependencias críticas
     */
    private static function verificar_dependencias() {
        // Verificar extensiones PHP requeridas
        $extensiones_php_requeridas = ['curl', 'json', 'mbstring', 'openssl'];
        
        foreach ($extensiones_php_requeridas as $extension) {
            if (!extension_loaded($extension)) {
                throw new Exception(
                    sprintf(
                        __('La extensión PHP "%s" es requerida pero no está disponible.', 'globalapi'),
                        $extension
                    )
                );
            }
        }
        
        // Verificar que no haya conflictos con otros plugins
        $plugins_activos = get_option('active_plugins', []);
        $plugin_conflicts = [];
        
        // Lista de plugins que podrían causar conflictos
        $plugins_incompatibles = [
            // Agregar aquí plugins que causen conflictos conocidos
        ];
        
        foreach ($plugins_activos as $plugin) {
            if (in_array($plugin, $plugins_incompatibles)) {
                $plugin_conflicts[] = $plugin;
            }
        }
        
        if (!empty($plugin_conflicts)) {
            throw new Exception(
                sprintf(
                    __('Detectados plugins incompatibles: %s. Por favor desactiva estos plugins antes de activar GlobalAPI.', 'globalapi'),
                    implode(', ', $plugin_conflicts)
                )
            );
        }
        
        error_log('GlobalAPI: Verificación de dependencias completada exitosamente');
    }
    
    /**
     * Crear tablas personalizadas en la base de datos
     *
     * Crea las tablas necesarias para el funcionamiento del plugin,
     * incluyendo tablas para credenciales, logs de auditoría y sesiones.
     *
     * @since 2.0.0
     */
    private static function crear_tablas_bd() {
        global $wpdb;
        
        // Requerir archivo de upgrade para dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla para credenciales de APIs
        $tabla_credenciales = $wpdb->prefix . 'globalapi_credenciales';
        $sql_credenciales = "CREATE TABLE $tabla_credenciales (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nombre varchar(100) NOT NULL,
            servicio enum('groundhogg', 'invision', 'custom') NOT NULL DEFAULT 'custom',
            tipo_credencial enum('api_key', 'oauth', 'basic_auth', 'bearer_token') NOT NULL DEFAULT 'api_key',
            credenciales_cifradas longtext NOT NULL,
            activo tinyint(1) NOT NULL DEFAULT 1,
            ultimo_uso datetime DEFAULT NULL,
            configuracion_extra longtext DEFAULT NULL,
            creado_por bigint(20) unsigned NOT NULL,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_servicio (servicio),
            KEY idx_activo (activo),
            KEY idx_creado_por (creado_por)
        ) $charset_collate;";
        
        // Tabla para logs de auditoría
        $tabla_logs = $wpdb->prefix . 'globalapi_logs_auditoria';
        $sql_logs = "CREATE TABLE $tabla_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            usuario_id bigint(20) unsigned NOT NULL,
            accion varchar(100) NOT NULL,
            recurso varchar(100) NOT NULL,
            recurso_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            datos_adicionales longtext DEFAULT NULL,
            resultado enum('exito', 'error', 'advertencia') NOT NULL DEFAULT 'exito',
            mensaje text DEFAULT NULL,
            fecha_evento datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_usuario_id (usuario_id),
            KEY idx_accion (accion),
            KEY idx_fecha_evento (fecha_evento),
            KEY idx_resultado (resultado)
        ) $charset_collate;";
        
        // Tabla para sesiones de API y tokens
        $tabla_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        $sql_sesiones = "CREATE TABLE $tabla_sesiones (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            usuario_id bigint(20) unsigned NOT NULL,
            token_hash varchar(255) NOT NULL,
            servicio varchar(50) NOT NULL,
            expires_at datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            activo tinyint(1) NOT NULL DEFAULT 1,
            datos_sesion longtext DEFAULT NULL,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_ultimo_uso datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_token_hash (token_hash),
            KEY idx_usuario_id (usuario_id),
            KEY idx_expires_at (expires_at),
            KEY idx_activo (activo)
        ) $charset_collate;";
        
        // Ejecutar creación de tablas
        dbDelta($sql_credenciales);
        dbDelta($sql_logs);
        dbDelta($sql_sesiones);
        
        // Verificar que las tablas se crearon correctamente
        $tablas_creadas = [
            $tabla_credenciales,
            $tabla_logs,
            $tabla_sesiones
        ];
        
        foreach ($tablas_creadas as $tabla) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$tabla'") !== $tabla) {
                throw new Exception(
                    sprintf(
                        __('No se pudo crear la tabla %s', 'globalapi'),
                        $tabla
                    )
                );
            }
        }
        
        error_log('GlobalAPI: Tablas de base de datos creadas exitosamente');
    }
    
    /**
     * Insertar configuraciones por defecto
     *
     * @since 2.0.0
     */
    private static function insertar_configuraciones_defecto() {
        // Configuraciones por defecto del plugin
        $configuraciones_defecto = [
            'globalapi_configuracion_general' => [
                'debug_mode' => false,
                'log_level' => 'info',
                'cache_enabled' => true,
                'cache_ttl' => 3600,
                'rate_limit_per_minute' => 60,
                'rate_limit_per_hour' => 1000
            ],
            'globalapi_configuracion_seguridad' => [
                'require_https' => true,
                'jwt_secret_key' => wp_generate_password(64, true, true),
                'jwt_expiration_time' => 3600,
                'failed_attempts_limit' => 5,
                'lockout_duration' => 300
            ],
            'globalapi_configuracion_groundhogg' => [
                'base_url' => '',
                'api_version' => 'v4',
                'timeout' => 30,
                'retry_attempts' => 3
            ],
            'globalapi_configuracion_invision' => [
                'base_url' => '',
                'timeout' => 30,
                'retry_attempts' => 3
            ]
        ];
        
        foreach ($configuraciones_defecto as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
        
        error_log('GlobalAPI: Configuraciones por defecto establecidas');
    }
    
    /**
     * Crear roles y capacidades personalizadas
     *
     * @since 2.0.0
     */
    private static function crear_roles_capacidades() {
        // Crear capacidades personalizadas
        $capacidades = [
            'manage_globalapi_settings' => __('Gestionar configuraciones de GlobalAPI', 'globalapi'),
            'manage_globalapi_credentials' => __('Gestionar credenciales de APIs', 'globalapi'),
            'view_globalapi_logs' => __('Ver logs de auditoría', 'globalapi'),
            'manage_globalapi_sessions' => __('Gestionar sesiones de API', 'globalapi')
        ];
        
        // Agregar capacidades al rol de administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capacidades as $capability => $description) {
                $admin_role->add_cap($capability);
            }
        }
        
        error_log('GlobalAPI: Roles y capacidades configurados');
    }
    
    /**
     * Configurar tareas programadas
     *
     * @since 2.0.0
     */
    private static function configurar_tareas_programadas() {
        // Programar limpieza de logs antiguos
        if (!wp_next_scheduled('globalapi_limpiar_logs')) {
            wp_schedule_event(time(), 'daily', 'globalapi_limpiar_logs');
        }
        
        // Programar limpieza de sesiones expiradas
        if (!wp_next_scheduled('globalapi_limpiar_sesiones')) {
            wp_schedule_event(time(), 'hourly', 'globalapi_limpiar_sesiones');
        }
        
        error_log('GlobalAPI: Tareas programadas configuradas');
    }
    
    /**
     * Crear páginas necesarias
     *
     * @since 2.0.0
     */
    private static function crear_paginas_necesarias() {
        // Por ahora no necesitamos páginas públicas
        // Futuras implementaciones podrían requerir páginas de callback para OAuth
        error_log('GlobalAPI: Verificación de páginas completada');
    }
    
    /**
     * Configurar opciones del plugin
     *
     * @since 2.0.0
     */
    private static function configurar_opciones_plugin() {
        // Marcar que el plugin fue instalado correctamente
        add_option('globalapi_installed', '1');
        add_option('globalapi_install_date', current_time('mysql'));
        
        // Configurar versión de la base de datos
        add_option('globalapi_db_version', GLOBALAPI_VERSION);
        
        error_log('GlobalAPI: Opciones del plugin configuradas');
    }
    
    /**
     * Verificar integridad de la instalación
     *
     * @since 2.0.0
     */
    private static function verificar_integridad_instalacion() {
        global $wpdb;
        
        // Verificar que las tablas existen
        $tablas_requeridas = [
            $wpdb->prefix . 'globalapi_credenciales',
            $wpdb->prefix . 'globalapi_logs_auditoria',
            $wpdb->prefix . 'globalapi_sesiones'
        ];
        
        foreach ($tablas_requeridas as $tabla) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$tabla'") !== $tabla) {
                throw new Exception(
                    sprintf(
                        __('Tabla requerida %s no existe después de la instalación', 'globalapi'),
                        $tabla
                    )
                );
            }
        }
        
        // Verificar que las opciones fueron creadas
        $opciones_requeridas = [
            'globalapi_configuracion_general',
            'globalapi_configuracion_seguridad'
        ];
        
        foreach ($opciones_requeridas as $opcion) {
            if (!get_option($opcion)) {
                throw new Exception(
                    sprintf(
                        __('Opción requerida %s no fue creada durante la instalación', 'globalapi'),
                        $opcion
                    )
                );
            }
        }
        
        error_log('GlobalAPI: Verificación de integridad completada exitosamente');
    }
} 