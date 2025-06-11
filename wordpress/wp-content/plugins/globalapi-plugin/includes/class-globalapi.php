<?php
/**
 * Clase Principal del Plugin GlobalAPI
 *
 * Esta es la clase principal que coordina todos los componentes del plugin.
 * Se encarga de cargar dependencias, definir hooks, y gestionar el ciclo
 * de vida del plugin desde la inicialización hasta la ejecución.
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
 * Clase GlobalAPI
 *
 * La clase principal del plugin que coordina todas las funcionalidades.
 * Actúa como el punto central para la inicialización y gestión de
 * todos los componentes del plugin.
 *
 * @since 2.0.0
 */
class GlobalAPI {

    /**
     * Cargador de acciones y filtros del plugin
     *
     * @since  2.0.0
     * @access protected
     * @var    GlobalAPI_Cargador $cargador Mantiene todos los hooks del plugin
     */
    protected $cargador;

    /**
     * Identificador único del plugin
     *
     * @since  2.0.0
     * @access protected
     * @var    string $nombre_plugin Identificador único del plugin
     */
    protected $nombre_plugin;

    /**
     * Versión actual del plugin
     *
     * @since  2.0.0
     * @access protected
     * @var    string $version Versión actual del plugin
     */
    protected $version;

    /**
     * Instancia singleton del plugin
     *
     * @since  2.0.0
     * @access private
     * @var    GlobalAPI $instancia Instancia única del plugin
     */
    private static $instancia = null;

    /**
     * Constructor de la clase principal
     *
     * Define las propiedades principales del plugin y carga las dependencias,
     * define la configuración regional, y establece los hooks para el
     * área administrativa y pública del sitio.
     *
     * @since 2.0.0
     */
    public function __construct() {
        if (defined('GLOBALAPI_VERSION')) {
            $this->version = GLOBALAPI_VERSION;
        } else {
            $this->version = '2.0.0';
        }
        
        $this->nombre_plugin = 'globalapi';
        
        $this->cargar_dependencias();
        $this->cargar_modelos();
        $this->establecer_configuracion_regional();
        $this->definir_hooks_admin();
        $this->definir_hooks_publicos();
        $this->definir_hooks_api();
    }

    /**
     * Obtener instancia singleton del plugin
     *
     * @since 2.0.0
     * @return GlobalAPI Instancia única del plugin
     */
    public static function obtener_instancia() {
        if (null === self::$instancia) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Cargar las dependencias requeridas para este plugin
     *
     * Incluye los archivos necesarios para definir una clase que contiene
     * todas las acciones y filtros, y luego crear una instancia del cargador
     * que ejecutará los hooks con WordPress.
     *
     * @since  2.0.0
     * @access private
     */
    private function cargar_dependencias() {
        // Log del proceso de carga
        error_log('GlobalAPI: Iniciando carga de dependencias');

        /**
         * Clase responsable de orquestar las acciones y filtros del plugin
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/class-cargador.php';

        /**
         * Clase responsable de definir funcionalidades específicas del área admin
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'admin/class-globalapi-admin.php';

        /**
         * Clase responsable de definir funcionalidades del área pública
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'public/class-globalapi-public.php';

        // Crear instancia del cargador
        $this->cargador = new GlobalAPI_Cargador();

        error_log('GlobalAPI: Dependencias básicas cargadas');
    }

    /**
     * Cargar los modelos del plugin
     *
     * Incluye y carga todos los modelos que definen Custom Post Types
     * y taxonomías personalizadas del plugin.
     *
     * @since  2.0.0
     * @access private
     */
    private function cargar_modelos() {
        // Log del proceso de carga
        error_log('GlobalAPI: Iniciando carga de modelos');

        /**
         * Modelo de credenciales - Custom Post Type principal
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/models/class-credencial.php';
        
        /**
         * Modelo de logs de auditoría
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/models/class-log-auditoria.php';
        
        /**
         * Taxonomías personalizadas
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/models/class-taxonomias.php';
        
        /**
         * Validaciones generales
         */
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/models/class-validaciones.php';

        // Instanciar modelos para activar sus hooks
        if (class_exists('Credencial')) {
            new Credencial();
        }
        
        if (class_exists('LogAuditoriaGlobalAPI')) {
            new LogAuditoriaGlobalAPI();
        }
        
        if (class_exists('TaxonomiasGlobalAPI')) {
            new TaxonomiasGlobalAPI();
        }

        error_log('GlobalAPI: Modelos cargados exitosamente');
    }

    /**
     * Definir la configuración regional del plugin
     *
     * Utiliza el cargador para ejecutar los filtros de WordPress que 
     * controlan la carga de archivos de idioma para el plugin.
     *
     * @since  2.0.0
     * @access private
     */
    private function establecer_configuracion_regional() {
        $this->cargador->agregar_accion(
            'plugins_loaded',
            $this,
            'cargar_idioma_plugin'
        );
    }

    /**
     * Registrar todos los hooks relacionados con el área administrativa
     *
     * @since  2.0.0
     * @access private
     */
    private function definir_hooks_admin() {
        // Verificar que estamos en el área administrativa o wp-cli
        if (!is_admin() && !defined('WP_CLI')) {
            return;
        }

        // Cargar e instanciar la clase admin
        require_once GLOBALAPI_PLUGIN_PATH . 'admin/class-globalapi-admin.php';
        $this->admin_plugin = new GlobalAPI_Admin();
        
        // Cargar configuración de endpoints (DESHABILITADO - investigando error 500)
        // require_once GLOBALAPI_PLUGIN_PATH . 'includes/admin/class-endpoint-settings.php';
        // if (class_exists('GlobalAPI_Endpoint_Settings')) {
        //     new GlobalAPI_Endpoint_Settings();
        // }
        
        // Hooks básicos de admin
        $this->cargador->agregar_accion(
            'admin_enqueue_scripts',
            $this,
            'enqueue_admin_styles'
        );

        $this->cargador->agregar_accion(
            'admin_enqueue_scripts',
            $this,
            'enqueue_admin_scripts'
        );

        error_log('GlobalAPI: Hooks administrativos definidos');
    }

    /**
     * Registrar todos los hooks relacionados con el área pública
     *
     * @since  2.0.0
     * @access private
     */
    private function definir_hooks_publicos() {
        // Crear instancia de la clase pública (cuando esté disponible)
        // $plugin_publico = new GlobalAPI_Public($this->obtener_nombre_plugin(), $this->obtener_version());

        // Por ahora, solo definir hooks básicos
        $this->cargador->agregar_accion(
            'wp_enqueue_scripts',
            $this,
            'enqueue_public_styles'
        );

        $this->cargador->agregar_accion(
            'wp_enqueue_scripts',
            $this,
            'enqueue_public_scripts'
        );

        error_log('GlobalAPI: Hooks públicos definidos');
    }

    /**
     * Registrar todos los hooks relacionados con la API REST
     *
     * @since  2.0.0
     * @access private
     */
    private function definir_hooks_api() {
        // Cargar clases de la API
        $this->cargar_clases_api();

        // Hook para registrar rutas de API REST
        $this->cargador->agregar_accion(
            'rest_api_init',
            $this,
            'registrar_rutas_api'
        );

        // Los controladores se instanciarán en rest_api_init

        // Hook para autenticación JWT
        $this->cargador->agregar_filter(
            'determine_current_user',
            $this,
            'determinar_usuario_jwt',
            20
        );

        // Inicializar Rate Limiter
        if (class_exists('GlobalAPI_Rate_Limiter')) {
            GlobalAPI_Rate_Limiter::init();
        }

        // Inicializar Auth Middleware
        if (class_exists('GlobalAPI_Auth_Middleware')) {
            new GlobalAPI_Auth_Middleware();
        }

        error_log('GlobalAPI: Hooks de API definidos');
    }

    /**
     * Cargar clases de la API REST
     *
     * @since  2.0.4
     * @access private
     */
    private function cargar_clases_api() {
        // Controlador base
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-rest-controller.php';

        // Controladores específicos
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-credenciales-controller.php';
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-auth-controller.php';
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-auditoria-controller.php';
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-proxy-controller.php';

        // Middleware y utilidades
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-auth-middleware.php';
        require_once GLOBALAPI_PLUGIN_PATH . 'includes/api/class-rate-limiter.php';

        error_log('GlobalAPI: Clases de API cargadas');
    }

    /**
     * Ejecutar el cargador para inicializar todos los hooks
     *
     * @since 2.0.0
     */
    public function ejecutar() {
        $this->cargador->ejecutar();
        
        // Log de inicialización exitosa
        error_log('GlobalAPI: Plugin inicializado exitosamente');
    }

    /**
     * Cargar el archivo de idioma del plugin
     *
     * @since 2.0.0
     */
    public function cargar_idioma_plugin() {
        load_plugin_textdomain(
            'globalapi',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    /**
     * Encolar estilos para el área administrativa
     *
     * @since 2.0.0
     */
    public function enqueue_admin_styles() {
        wp_enqueue_style(
            $this->nombre_plugin . '-admin',
            GLOBALAPI_PLUGIN_URL . 'admin/css/globalapi-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Encolar scripts para el área administrativa
     *
     * @since 2.0.0
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            $this->nombre_plugin . '-admin',
            GLOBALAPI_PLUGIN_URL . 'admin/js/globalapi-admin.js',
            ['jquery'],
            $this->version,
            false
        );
    }

    /**
     * Encolar estilos para el área pública
     *
     * @since 2.0.0
     */
    public function enqueue_public_styles() {
        // Solo cargar en páginas específicas si es necesario
        wp_enqueue_style(
            $this->nombre_plugin . '-public',
            GLOBALAPI_PLUGIN_URL . 'public/css/globalapi-public.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Encolar scripts para el área pública
     *
     * @since 2.0.0
     */
    public function enqueue_public_scripts() {
        // Solo cargar en páginas específicas si es necesario
        wp_enqueue_script(
            $this->nombre_plugin . '-public',
            GLOBALAPI_PLUGIN_URL . 'public/js/globalapi-public.js',
            ['jquery'],
            $this->version,
            false
        );
    }

    /**
     * Registrar rutas de la API REST
     *
     * @since 2.0.0
     */
    public function registrar_rutas_api() {
        // Registrar namespace base
        register_rest_route('globalapi/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'api_status'],
            'permission_callback' => '__return_true'
        ]);

        // Instanciar y registrar controladores cuando WordPress lo requiera
        if (class_exists('GlobalAPI_Credenciales_Controller')) {
            $credenciales_controller = new GlobalAPI_Credenciales_Controller();
            $credenciales_controller->register_routes();
        }

        if (class_exists('GlobalAPI_Auth_Controller')) {
            $auth_controller = new GlobalAPI_Auth_Controller();
            $auth_controller->register_routes();
        }

        if (class_exists('GlobalAPI_Auditoria_Controller')) {
            $auditoria_controller = new GlobalAPI_Auditoria_Controller();
            $auditoria_controller->register_routes();
        }

        if (class_exists('GlobalAPI_Proxy_Controller')) {
            $proxy_controller = new GlobalAPI_Proxy_Controller();
            $proxy_controller->register_routes();
        }

        error_log('GlobalAPI: Todas las rutas de API registradas');
    }

    /**
     * Endpoint de estado de la API
     *
     * @since 2.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_status($request) {
        return new WP_REST_Response([
            'status' => 'active',
            'version' => $this->version,
            'plugin' => $this->nombre_plugin,
            'timestamp' => current_time('mysql'),
            'endpoints' => [
                'status' => '/wp-json/globalapi/v1/status',
                'auth' => '/wp-json/globalapi/v1/auth/*',
                'credentials' => '/wp-json/globalapi/v1/credentials/*',
                'proxy' => '/wp-json/globalapi/v1/proxy/*'
            ],
            'proxy_services' => [
                'groundhogg' => '/wp-json/globalapi/v1/proxy/groundhogg/{endpoint}',
                'invision' => '/wp-json/globalapi/v1/proxy/invision/{endpoint}'
            ]
        ], 200);
    }

    /**
     * Determinar usuario actual via JWT
     *
     * @since 2.0.0
     * @param int|bool $user_id
     * @return int|bool
     */
    public function determinar_usuario_jwt($user_id) {
        // Por ahora retornar el user_id existente
        // Implementación JWT vendrá en futuras versiones
        return $user_id;
    }

    /**
     * Obtener el nombre del plugin
     *
     * @since  2.0.0
     * @return string Nombre del plugin
     */
    public function obtener_nombre_plugin() {
        return $this->nombre_plugin;
    }

    /**
     * Obtener la versión del plugin
     *
     * @since  2.0.0
     * @return string Versión del plugin
     */
    public function obtener_version() {
        return $this->version;
    }

    /**
     * Obtener el cargador de hooks
     *
     * @since  2.0.0
     * @return GlobalAPI_Cargador Instancia del cargador
     */
    public function obtener_cargador() {
        return $this->cargador;
    }

    /**
     * Obtener información completa del plugin
     *
     * @since 2.0.0
     * @return array Información del plugin
     */
    public function obtener_info_plugin() {
        return [
            'nombre' => $this->nombre_plugin,
            'version' => $this->version,
            'ruta' => GLOBALAPI_PLUGIN_PATH,
            'url' => GLOBALAPI_PLUGIN_URL,
            'text_domain' => GLOBALAPI_TEXT_DOMAIN,
            'estado' => 'activo',
            'hooks_registrados' => $this->cargador ? count($this->cargador->obtener_acciones()) + count($this->cargador->obtener_filtros()) : 0
        ];
    }
} 