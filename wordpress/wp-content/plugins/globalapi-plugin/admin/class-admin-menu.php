<?php
/**
 * Gestión del menú de administración del plugin GlobalAPI
 *
 * Esta clase maneja la creación y configuración del menú principal
 * del plugin en el dashboard de WordPress, incluyendo submenús
 * y permisos de acceso.
 *
 * @package    GlobalAPI
 * @subpackage Admin
 * @since      2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase AdminMenu
 * 
 * Gestiona la creación del menú principal y submenús del plugin
 * en el área administrativa de WordPress.
 *
 * @since 2.0.0
 */
class GlobalAPI_Admin_Menu {

    /**
     * Slug del menú principal
     *
     * @since 2.0.0
     * @var string
     */
    const MENU_SLUG = 'globalapi';

    /**
     * Capacidad requerida para acceder al plugin
     *
     * @since 2.0.0
     * @var string
     */
    const CAPABILITY = 'manage_options';

    /**
     * Constructor de la clase
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'crear_menu'));
        add_action('admin_enqueue_scripts', array($this, 'cargar_assets'));
    }

    /**
     * Crear el menú principal y submenús del plugin
     *
     * Registra el menú principal "GlobalAPI" en el dashboard de WordPress
     * y todos sus submenús con las configuraciones correspondientes.
     *
     * @since 2.0.0
     * @return void
     */
    public function crear_menu() {
        // Menú principal
        add_menu_page(
            __('GlobalAPI - Gestión de APIs', 'globalapi'),           // Título de la página
            __('GlobalAPI', 'globalapi'),                             // Título del menú
            self::CAPABILITY,                                         // Capacidad requerida
            self::MENU_SLUG,                                         // Slug del menú
            array($this, 'mostrar_dashboard'),                       // Función callback
            'dashicons-admin-settings',                              // Icono del menú
            30                                                       // Posición en el menú
        );

        // Submenú: Dashboard principal
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard - GlobalAPI', 'globalapi'),
            __('Dashboard', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'mostrar_dashboard')
        );

        // Submenú: Credenciales
        add_submenu_page(
            self::MENU_SLUG,
            __('Credenciales de APIs - GlobalAPI', 'globalapi'),
            __('Credenciales', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG . '-credenciales',
            array($this, 'mostrar_credenciales')
        );

        // Submenú: Configuración
        add_submenu_page(
            self::MENU_SLUG,
            __('Configuración - GlobalAPI', 'globalapi'),
            __('Configuración', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG . '-configuracion',
            array($this, 'mostrar_configuracion')
        );

        // Submenú: Logs de Auditoría
        add_submenu_page(
            self::MENU_SLUG,
            __('Logs de Auditoría - GlobalAPI', 'globalapi'),
            __('Logs Auditoría', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG . '-logs',
            array($this, 'mostrar_logs')
        );

        // Submenú: Estado del Sistema
        add_submenu_page(
            self::MENU_SLUG,
            __('Estado del Sistema - GlobalAPI', 'globalapi'),
            __('Estado Sistema', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG . '-estado',
            array($this, 'mostrar_estado')
        );

        // Submenú: Documentación
        add_submenu_page(
            self::MENU_SLUG,
            __('Documentación - GlobalAPI', 'globalapi'),
            __('Documentación', 'globalapi'),
            self::CAPABILITY,
            self::MENU_SLUG . '-documentacion',
            array($this, 'mostrar_documentacion')
        );

        // Hook para que otros plugins puedan agregar submenús
        do_action('globalapi_admin_menu_after', self::MENU_SLUG);
    }

    /**
     * Mostrar el dashboard principal
     *
     * Renderiza la página principal del plugin con estadísticas
     * y resumen del estado del sistema.
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_dashboard() {
        // Verificar permisos
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        // Registrar actividad en logs
        if (class_exists('LogAuditoria')) {
            LogAuditoria::registrar_log(array(
                'tipo_evento' => 'admin_access',
                'descripcion' => 'Acceso al dashboard principal de GlobalAPI',
                'severidad' => 'info',
                'datos_adicionales' => array(
                    'pagina' => 'dashboard',
                    'usuario_id' => get_current_user_id()
                )
            ));
        }

        // Incluir el template del dashboard
        $this->incluir_template('dashboard', array(
            'titulo' => __('Dashboard Principal - GlobalAPI', 'globalapi'),
            'estadisticas' => $this->obtener_estadisticas_dashboard()
        ));
    }

    /**
     * Mostrar la página de credenciales
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_credenciales() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        // Registrar actividad
        if (class_exists('LogAuditoria')) {
            LogAuditoria::registrar_log(array(
                'tipo_evento' => 'admin_access',
                'descripcion' => 'Acceso a la gestión de credenciales',
                'severidad' => 'info',
                'datos_adicionales' => array('pagina' => 'credenciales')
            ));
        }

        $this->incluir_template('credenciales', array(
            'titulo' => __('Gestión de Credenciales', 'globalapi')
        ));
    }

    /**
     * Mostrar la página de configuración
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_configuracion() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        // Registrar actividad
        if (class_exists('LogAuditoria')) {
            LogAuditoria::registrar_log(array(
                'tipo_evento' => 'admin_access',
                'descripcion' => 'Acceso a la configuración del plugin',
                'severidad' => 'info',
                'datos_adicionales' => array('pagina' => 'configuracion')
            ));
        }

        $this->incluir_template('configuracion', array(
            'titulo' => __('Configuración del Plugin', 'globalapi')
        ));
    }

    /**
     * Mostrar la página de logs de auditoría
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_logs() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        $this->incluir_template('logs', array(
            'titulo' => __('Logs de Auditoría', 'globalapi')
        ));
    }

    /**
     * Mostrar la página de estado del sistema
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_estado() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        $this->incluir_template('estado', array(
            'titulo' => __('Estado del Sistema', 'globalapi'),
            'estado_apis' => $this->verificar_estado_apis()
        ));
    }

    /**
     * Mostrar la página de documentación
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_documentacion() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'globalapi'));
        }

        $this->incluir_template('documentacion', array(
            'titulo' => __('Documentación del Plugin', 'globalapi')
        ));
    }

    /**
     * Incluir template de página admin
     *
     * Incluye el archivo de template correspondiente pasando las variables necesarias.
     *
     * @since 2.0.0
     * @param string $template_name Nombre del template sin extensión
     * @param array $variables Variables a pasar al template
     * @return void
     */
    private function incluir_template($template_name, $variables = array()) {
        // Extraer variables para el template
        extract($variables);

        // Incluir encabezado común
        include_once GLOBALAPI_PLUGIN_PATH . 'admin/pages/header.php';

        // Incluir template específico
        $template_path = GLOBALAPI_PLUGIN_PATH . "admin/pages/{$template_name}.php";
        
        if (file_exists($template_path)) {
            include_once $template_path;
        } else {
            // Mostrar error si el template no existe
            echo '<div class="notice notice-error"><p>';
            echo sprintf(__('Template no encontrado: %s', 'globalapi'), $template_name);
            echo '</p></div>';
        }

        // Incluir pie común
        include_once GLOBALAPI_PLUGIN_PATH . 'admin/pages/footer.php';
    }

    /**
     * Obtener estadísticas para el dashboard
     *
     * @since 2.0.0
     * @return array Array con estadísticas del sistema
     */
    private function obtener_estadisticas_dashboard() {
        $estadisticas = array();

        // Contar credenciales por estado
        $credenciales = get_posts(array(
            'post_type' => 'globalapi_credencial',
            'post_status' => array('publish', 'private'),
            'numberposts' => -1,
            'meta_query' => array()
        ));

        $estadisticas['credenciales'] = array(
            'total' => count($credenciales),
            'activas' => 0,
            'inactivas' => 0,
            'expiradas' => 0
        );

        foreach ($credenciales as $credencial) {
            $estado = get_post_meta($credencial->ID, '_globalapi_estado', true);
            switch ($estado) {
                case 'activa':
                    $estadisticas['credenciales']['activas']++;
                    break;
                case 'inactiva':
                    $estadisticas['credenciales']['inactivas']++;
                    break;
                case 'expirada':
                    $estadisticas['credenciales']['expiradas']++;
                    break;
            }
        }

        // Contar logs por gravedad en las últimas 24 horas
        $fecha_limite = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $logs_recientes = get_posts(array(
            'post_type' => 'globalapi_log',
            'numberposts' => -1,
            'date_query' => array(
                array(
                    'after' => $fecha_limite,
                    'inclusive' => true
                )
            )
        ));

        $estadisticas['logs'] = array(
            'total_24h' => count($logs_recientes),
            'errores_24h' => 0,
            'warnings_24h' => 0,
            'info_24h' => 0
        );

        foreach ($logs_recientes as $log) {
            $severidad = wp_get_post_terms($log->ID, 'globalapi_severidad', array('fields' => 'slugs'));
            if (!empty($severidad)) {
                $nivel = $severidad[0];
                switch ($nivel) {
                    case 'error':
                    case 'critical':
                        $estadisticas['logs']['errores_24h']++;
                        break;
                    case 'warning':
                        $estadisticas['logs']['warnings_24h']++;
                        break;
                    case 'info':
                    case 'debug':
                        $estadisticas['logs']['info_24h']++;
                        break;
                }
            }
        }

        // Estado general del sistema
        $estadisticas['sistema'] = array(
            'version_plugin' => GLOBALAPI_VERSION,
            'version_wp' => get_bloginfo('version'),
            'version_php' => PHP_VERSION,
            'memoria_php' => ini_get('memory_limit'),
            'timezone' => wp_timezone_string()
        );

        return $estadisticas;
    }

    /**
     * Verificar estado de las APIs configuradas
     *
     * @since 2.0.0
     * @return array Estado de conexión de cada API
     */
    private function verificar_estado_apis() {
        $estado_apis = array();

        // Obtener todas las credenciales activas usando WP_Query temporalmente
        $credenciales = get_posts(array(
            'post_type' => 'globalapi_credencial',
            'post_status' => array('publish', 'private'),
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_globalapi_estado',
                    'value' => 'activa',
                    'compare' => '='
                )
            )
        ));

        foreach ($credenciales as $credencial) {
            $tipo_servicio = get_post_meta($credencial->ID, '_globalapi_tipo_servicio', true);
            $url_base = get_post_meta($credencial->ID, '_globalapi_url_base', true);

            $estado_apis[$tipo_servicio] = array(
                'nombre' => $tipo_servicio,
                'url' => $url_base,
                'estado' => 'verificando',
                'ultimo_check' => get_post_meta($credencial->ID, '_globalapi_ultima_verificacion', true),
                'credencial_id' => $credencial->ID
            );
        }

        return $estado_apis;
    }

    /**
     * Cargar assets CSS y JS para las páginas admin
     *
     * @since 2.0.0
     * @param string $hook Página actual del admin
     * @return void
     */
    public function cargar_assets($hook) {
        // Solo cargar en páginas del plugin
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        // CSS del admin
        wp_enqueue_style(
            'globalapi-admin',
            GLOBALAPI_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            GLOBALAPI_VERSION
        );

        // JavaScript del admin
        wp_enqueue_script(
            'globalapi-admin',
            GLOBALAPI_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            GLOBALAPI_VERSION,
            true
        );

        // Localizar script con datos necesarios
        wp_localize_script('globalapi-admin', 'globalapi_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('globalapi_admin_nonce'),
            'strings' => array(
                'confirmar_eliminacion' => __('¿Estás seguro de eliminar este elemento?', 'globalapi'),
                'procesando' => __('Procesando...', 'globalapi'),
                'error_generico' => __('Ha ocurrido un error. Inténtalo de nuevo.', 'globalapi')
            )
        ));

        // Cargar scripts específicos según la página
        $this->cargar_assets_especificos($hook);
    }

    /**
     * Cargar assets específicos según la página
     *
     * @since 2.0.0
     * @param string $hook Página actual
     * @return void
     */
    private function cargar_assets_especificos($hook) {
        // Página de credenciales
        if (strpos($hook, 'credenciales') !== false) {
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-color-picker');
        }

        // Página de logs
        if (strpos($hook, 'logs') !== false) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-datepicker');
        }

        // Editor de código en configuración
        if (strpos($hook, 'configuracion') !== false) {
            wp_enqueue_code_editor(array('type' => 'application/json'));
        }
    }

    /**
     * Obtener URL del menú
     *
     * @since 2.0.0
     * @param string $submenu Submenú específico (opcional)
     * @return string URL completa del menú
     */
    public static function obtener_url_menu($submenu = '') {
        $base_url = admin_url('admin.php?page=' . self::MENU_SLUG);
        
        if (!empty($submenu)) {
            $base_url = admin_url('admin.php?page=' . self::MENU_SLUG . '-' . $submenu);
        }

        return $base_url;
    }

    /**
     * Verificar si estamos en una página del plugin
     *
     * @since 2.0.0
     * @return bool True si estamos en una página del plugin
     */
    public static function es_pagina_plugin() {
        $screen = get_current_screen();
        return ($screen && strpos($screen->id, self::MENU_SLUG) !== false);
    }
}

// NOTA: No instanciar automáticamente - se instancia desde GlobalAPI_Admin 