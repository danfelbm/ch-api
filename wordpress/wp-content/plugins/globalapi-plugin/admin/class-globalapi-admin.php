<?php
/**
 * Clase Admin del Plugin GlobalAPI
 *
 * Gestiona todas las funcionalidades del área administrativa
 * del plugin GlobalAPI, incluyendo menús, páginas de configuración
 * y integraciones con el dashboard de WordPress.
 *
 * @package     GlobalAPI
 * @subpackage  GlobalAPI/admin
 * @since       2.0.0
 * @author      Colombia Humana - Desarrollo Tecnológico
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase GlobalAPI_Admin
 *
 * Coordina todas las funcionalidades administrativas del plugin.
 *
 * @since 2.0.0
 */
class GlobalAPI_Admin {
    
    /**
     * Instancia del menú de administración
     *
     * @since 2.0.0
     * @var GlobalAPI_Admin_Menu
     */
    private $menu;

    /**
     * Constructor
     *
     * Inicializa todas las funcionalidades administrativas del plugin.
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->cargar_dependencias();
        $this->inicializar_componentes();
        $this->definir_hooks_admin();
    }

    /**
     * Cargar las dependencias del área administrativa
     *
     * @since 2.0.0
     * @return void
     */
    private function cargar_dependencias() {
        /**
         * Cargar la clase de menú de administración
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-admin-menu.php';
    }

    /**
     * Inicializar los componentes administrativos
     *
     * @since 2.0.0
     * @return void
     */
    private function inicializar_componentes() {
        // Instanciar el menú de administración
        $this->menu = new GlobalAPI_Admin_Menu();
        
        // Log de inicialización
        error_log('GlobalAPI Admin: Componentes administrativos inicializados');
    }

    /**
     * Definir hooks específicos del área administrativa
     *
     * @since 2.0.0
     * @return void
     */
    private function definir_hooks_admin() {
        // Hook para agregar estilos admin globales
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Hook para notices administrativos
        add_action('admin_notices', array($this, 'mostrar_notices_admin'));
        
        // Hook para procesar formularios admin
        add_action('admin_post_globalapi_configuracion', array($this, 'procesar_configuracion'));
    }

    /**
     * Cargar estilos administrativos
     *
     * @since 2.0.0
     * @param string $hook La página actual del admin
     * @return void
     */
    public function enqueue_admin_styles($hook) {
        // Solo cargar en páginas del plugin
        if (strpos($hook, 'globalapi') !== false) {
            wp_enqueue_style(
                'globalapi-admin',
                plugin_dir_url(dirname(__FILE__)) . 'admin/css/admin.css',
                array(),
                GLOBALAPI_VERSION,
                'all'
            );
        }
    }

    /**
     * Mostrar notices administrativos
     *
     * @since 2.0.0
     * @return void
     */
    public function mostrar_notices_admin() {
        // Solo en páginas del plugin
        if (!GlobalAPI_Admin_Menu::es_pagina_plugin()) {
            return;
        }

        // Notice de activación exitosa
        if (get_transient('globalapi_plugin_activado')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>GlobalAPI:</strong> ' . __('Plugin activado correctamente. ¡Bienvenido!', 'globalapi') . '</p>';
            echo '</div>';
            delete_transient('globalapi_plugin_activado');
        }
    }

    /**
     * Procesar formularios de configuración
     *
     * @since 2.0.0
     * @return void
     */
    public function procesar_configuracion() {
        // Verificar nonce y permisos
        if (!wp_verify_nonce($_POST['_wpnonce'], 'globalapi_configuracion') || 
            !current_user_can('manage_options')) {
            wp_die(__('Acceso denegado.', 'globalapi'));
        }

        // Procesar la configuración
        // TODO: Implementar procesamiento específico

        // Redirect de vuelta
        wp_redirect(admin_url('admin.php?page=globalapi-configuracion&mensaje=guardado'));
        exit;
    }

    /**
     * Obtener la instancia del menú
     *
     * @since 2.0.0
     * @return GlobalAPI_Admin_Menu
     */
    public function get_menu() {
        return $this->menu;
    }
} 