<?php
/**
 * Plugin GlobalAPI - Infraestructura de APIs para Colombia Humana
 *
 * Plugin de infraestructura que gestiona APIs y credenciales de forma segura
 * para la integración con Groundhogg CRM e InvisionCommunity OAuth.
 * Diseñado específicamente para la aplicación móvil Flutter de Colombia Humana.
 *
 * @package     GlobalAPI
 * @author      Colombia Humana - Desarrollo Tecnológico
 * @copyright   2024 Colombia Humana
 * @license     GPL-2.0+
 * @since       2.0.0
 *
 * @wordpress-plugin
 * Plugin Name:         GlobalAPI - Infraestructura CRM
 * Plugin URI:          https://github.com/danfelbm/ch-api
 * Description:         Plugin de infraestructura para gestión segura de APIs (Groundhogg, InvisionCommunity) y credenciales. Diseñado para la aplicación móvil Flutter de Colombia Humana.
 * Version:             2.0.0
 * Requires at least:   6.4
 * Requires PHP:        8.1
 * Author:              Colombia Humana - Desarrollo Tecnológico
 * Author URI:          https://colombiahumana.co
 * Text Domain:         globalapi
 * Domain Path:         /languages
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Network:             false
 * Update URI:          false
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Información actual del plugin
 *
 * @since 2.0.0
 */
define('GLOBALAPI_VERSION', '2.0.0');
define('GLOBALAPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GLOBALAPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GLOBALAPI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GLOBALAPI_TEXT_DOMAIN', 'globalapi');

/**
 * Configuración mínima requerida
 *
 * @since 2.0.0
 */
define('GLOBALAPI_MIN_WP_VERSION', '6.4');
define('GLOBALAPI_MIN_PHP_VERSION', '8.1');

/**
 * Verificar requisitos mínimos del sistema
 *
 * @since 2.0.0
 * @return bool True si cumple requisitos, false si no
 */
function globalapi_verificar_requisitos() {
    global $wp_version;
    
    // Verificar versión de WordPress
    if (version_compare($wp_version, GLOBALAPI_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__('GlobalAPI Error:', 'globalapi'),
                sprintf(
                    esc_html__('Este plugin requiere WordPress %s o superior. Versión actual: %s', 'globalapi'),
                    GLOBALAPI_MIN_WP_VERSION,
                    $GLOBALS['wp_version']
                )
            );
        });
        return false;
    }
    
    // Verificar versión de PHP
    if (version_compare(PHP_VERSION, GLOBALAPI_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__('GlobalAPI Error:', 'globalapi'),
                sprintf(
                    esc_html__('Este plugin requiere PHP %s o superior. Versión actual: %s', 'globalapi'),
                    GLOBALAPI_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            );
        });
        return false;
    }
    
    return true;
}

/**
 * Función que se ejecuta cuando se activa el plugin
 *
 * @since 2.0.0
 */
function globalapi_activar_plugin() {
    // Verificar requisitos antes de activar
    if (!globalapi_verificar_requisitos()) {
        wp_die(
            esc_html__('No se puede activar el plugin GlobalAPI debido a requisitos no cumplidos.', 'globalapi'),
            esc_html__('Error de Activación', 'globalapi'),
            ['back_link' => true]
        );
    }
    
    // Registro de la activación en logs
    error_log('GlobalAPI: Plugin activado correctamente - Versión ' . GLOBALAPI_VERSION);
    
    // Crear tablas necesarias y configuraciones iniciales
    require_once GLOBALAPI_PLUGIN_PATH . 'includes/class-activador.php';
    
    if (class_exists('GlobalAPI_Activador')) {
        GlobalAPI_Activador::activar();
    }
}

/**
 * Función que se ejecuta cuando se desactiva el plugin
 *
 * @since 2.0.0
 */
function globalapi_desactivar_plugin() {
    // Limpiar trabajos programados y cache
    require_once GLOBALAPI_PLUGIN_PATH . 'includes/class-desactivador.php';
    
    if (class_exists('GlobalAPI_Desactivador')) {
        GlobalAPI_Desactivador::desactivar();
    }
    
    // Registro de la desactivación en logs
    error_log('GlobalAPI: Plugin desactivado - Versión ' . GLOBALAPI_VERSION);
}

/**
 * Inicialización principal del plugin
 *
 * @since 2.0.0
 */
function globalapi_inicializar_plugin() {
    // Verificar requisitos del sistema
    if (!globalapi_verificar_requisitos()) {
        return;
    }
    
    // Inicializar autoloader personalizado
    require_once GLOBALAPI_PLUGIN_PATH . 'includes/class-autoloader.php';
    if (class_exists('GlobalAPI_Autoloader')) {
        GlobalAPI_Autoloader::inicializar(GLOBALAPI_PLUGIN_PATH);
    }
    
    // Cargar composer autoloader si existe
    $composer_autoload = GLOBALAPI_PLUGIN_PATH . 'vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
    }
    
    // Cargar archivos de idioma
    load_plugin_textdomain(
        'globalapi',
        false,
        dirname(GLOBALAPI_PLUGIN_BASENAME) . '/languages/'
    );
    
    // Incluir clase principal del plugin
    require_once GLOBALAPI_PLUGIN_PATH . 'includes/class-globalapi.php';
    
    // Inicializar plugin si la clase existe
    if (class_exists('GlobalAPI')) {
        $globalapi = new GlobalAPI();
        $globalapi->ejecutar();
    }
}

/**
 * Registrar hooks de activación y desactivación
 */
register_activation_hook(__FILE__, 'globalapi_activar_plugin');
register_deactivation_hook(__FILE__, 'globalapi_desactivar_plugin');

/**
 * Inicializar el plugin después de que WordPress se haya cargado
 */
add_action('plugins_loaded', 'globalapi_inicializar_plugin');

/**
 * Hook para limpiar datos cuando se desinstala el plugin
 * (El archivo uninstall.php manejará la limpieza completa)
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    // Este hook solo se ejecuta durante la vida del plugin
    add_action('admin_init', function() {
        // Verificaciones adicionales de seguridad pueden ir aquí
    });
}

/**
 * Información de depuración - Solo en entornos de desarrollo
 *
 * @since 2.0.0
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options')) {
            printf(
                '<!-- GlobalAPI Debug: v%s | WP %s | PHP %s -->',
                GLOBALAPI_VERSION,
                $GLOBALS['wp_version'],
                PHP_VERSION
            );
        }
    });
} 