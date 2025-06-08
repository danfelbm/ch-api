<?php namespace Autor\GlobalAPI;

use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

/**
 * GlobalAPI Plugin
 * 
 * Plugin de infraestructura para gestión de APIs externas
 * Maneja OAuth, proxy de APIs y gestión segura de credenciales
 * 
 * @package     Autor\GlobalAPI
 * @author      Colombia Humana
 * @since       1.0.0
 */
class Plugin extends PluginBase
{
    /**
     * Información del plugin
     * 
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'GlobalAPI',
            'description' => 'Plugin de infraestructura para gestión de APIs externas y OAuth',
            'author'      => 'Colombia Humana',
            'icon'        => 'icon-globe',
            'homepage'    => 'https://github.com/danfelbm/ch-api',
        ];
    }

    /**
     * Registro de servicios del plugin
     */
    public function register()
    {
        // Registro de servicios principales
        $this->app->singleton('globalapi.oauth', function ($app) {
            return new \Autor\GlobalAPI\Classes\ServicioOAuth();
        });

        $this->app->singleton('globalapi.groundhogg', function ($app) {
            return new \Autor\GlobalAPI\Classes\ConectorGroundhogg();
        });

        $this->app->singleton('globalapi.credenciales', function ($app) {
            return new \Autor\GlobalAPI\Classes\GestorCredenciales();
        });
    }

    /**
     * Inicialización del plugin
     */
    public function boot()
    {
        // Configuración de rutas API
        $this->configureApiRoutes();
        
        // Configuración de middleware de seguridad
        $this->configureSecurityMiddleware();
    }

    /**
     * Registro de componentes
     * 
     * @return array
     */
    public function registerComponents()
    {
        return [
            \Autor\GlobalAPI\Components\AuthWidget::class => 'authWidget',
            \Autor\GlobalAPI\Components\ApiStatus::class => 'apiStatus',
        ];
    }

    /**
     * Registro de permisos
     * 
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'autor.globalapi.acceso_configuracion' => [
                'label' => 'Acceso a configuración de GlobalAPI',
                'tab'   => 'GlobalAPI',
            ],
            'autor.globalapi.gestion_credenciales' => [
                'label' => 'Gestión de credenciales de APIs',
                'tab'   => 'GlobalAPI',
            ],
            'autor.globalapi.logs_auditoria' => [
                'label' => 'Acceso a logs de auditoría',
                'tab'   => 'GlobalAPI',
            ],
        ];
    }

    /**
     * Registro de navegación en backend
     * 
     * @return array
     */
    public function registerNavigation()
    {
        return [
            'globalapi' => [
                'label'       => 'GlobalAPI',
                'url'         => Backend::url('autor/globalapi/configuracion'),
                'icon'        => 'icon-globe',
                'order'       => 300,
                'permissions' => ['autor.globalapi.acceso_configuracion'],

                'sideMenu' => [
                    'configuracion' => [
                        'label'       => 'Configuración',
                        'icon'        => 'icon-cog',
                        'url'         => Backend::url('autor/globalapi/configuracion'),
                        'permissions' => ['autor.globalapi.acceso_configuracion'],
                    ],
                    'credenciales' => [
                        'label'       => 'Credenciales',
                        'icon'        => 'icon-key',
                        'url'         => Backend::url('autor/globalapi/credenciales'),
                        'permissions' => ['autor.globalapi.gestion_credenciales'],
                    ],
                    'logs' => [
                        'label'       => 'Logs de Auditoría',
                        'icon'        => 'icon-file-text',
                        'url'         => Backend::url('autor/globalapi/logs'),
                        'permissions' => ['autor.globalapi.logs_auditoria'],
                    ],
                    '_separator1' => [
                        'itemType' => 'ruler',
                    ],
                    'estado_apis' => [
                        'label'       => 'Estado de APIs',
                        'icon'        => 'icon-heartbeat',
                        'url'         => Backend::url('autor/globalapi/estado'),
                        'permissions' => ['autor.globalapi.acceso_configuracion'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Registro de configuraciones del plugin
     * 
     * @return array
     */
    public function registerSettings()
    {
        return [
            'credenciales' => [
                'label'       => 'Credenciales GlobalAPI',
                'description' => 'Configuración de credenciales para APIs externas',
                'category'    => 'GlobalAPI',
                'icon'        => 'icon-key',
                'class'       => 'Autor\GlobalAPI\Models\ConfiguracionCredenciales',
                'order'       => 100,
                'keywords'    => 'credenciales oauth groundhogg invision',
                'permissions' => ['autor.globalapi.gestion_credenciales'],
            ],
            'api_config' => [
                'label'       => 'Configuración API',
                'description' => 'Configuración general de comportamiento de APIs',
                'category'    => 'GlobalAPI',
                'icon'        => 'icon-cog',
                'class'       => 'Autor\GlobalAPI\Models\ConfiguracionAPI',
                'order'       => 200,
                'keywords'    => 'api rate limiting timeout seguridad',
                'permissions' => ['autor.globalapi.acceso_configuracion'],
            ],
        ];
    }

    /**
     * Configuración de rutas API
     * 
     * @return void
     */
    private function configureApiRoutes()
    {
        // Las rutas se definirán en un archivo separado para mejor organización
        if (file_exists($routesFile = __DIR__ . '/config/routes.php')) {
            require $routesFile;
        }
    }

    /**
     * Configuración de middleware de seguridad
     * 
     * @return void
     */
    private function configureSecurityMiddleware()
    {
        // Registro de middleware personalizado para autenticación OAuth
        $this->app['router']->aliasMiddleware('globalapi.auth', \Autor\GlobalAPI\Classes\MiddlewareAuth::class);
        $this->app['router']->aliasMiddleware('globalapi.rate', \Autor\GlobalAPI\Classes\MiddlewareRateLimit::class);
    }
} 