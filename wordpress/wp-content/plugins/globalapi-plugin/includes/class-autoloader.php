<?php
/**
 * Autoloader personalizado para Plugin GlobalAPI
 *
 * Maneja la carga automática de clases siguiendo estándares PSR-4
 * y convenciones de WordPress Plugin Development.
 *
 * @package    GlobalAPI
 * @subpackage Core
 * @since      2.0.0
 * @author     Colombia Humana
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

/**
 * Clase Autoloader para GlobalAPI
 *
 * Implementa PSR-4 autoloader personalizado para el plugin
 * con soporte para namespace GlobalAPI y todas sus subclases.
 *
 * @since 2.0.0
 */
class GlobalAPI_Autoloader {

    /**
     * Namespace base del plugin
     *
     * @since 2.0.0
     * @var string
     */
    private static $namespace_base = 'GlobalAPI\\';

    /**
     * Directorio base del plugin
     *
     * @since 2.0.0
     * @var string
     */
    private static $directorio_base;

    /**
     * Mapeo de namespaces a directorios
     *
     * @since 2.0.0
     * @var array
     */
    private static $mapeo_namespaces = array(
        'GlobalAPI\\Models\\'    => 'includes/models/',
        'GlobalAPI\\API\\'       => 'includes/api/',
        'GlobalAPI\\Services\\'  => 'includes/services/',
        'GlobalAPI\\Security\\'  => 'includes/security/',
        'GlobalAPI\\Admin\\'     => 'admin/',
        'GlobalAPI\\Public\\'    => 'public/',
        'GlobalAPI\\Tests\\'     => 'tests/',
        'GlobalAPI\\'            => 'includes/'
    );

    /**
     * Clases cargadas (para debugging)
     *
     * @since 2.0.0
     * @var array
     */
    private static $clases_cargadas = array();

    /**
     * Inicializa el autoloader
     *
     * @since 2.0.0
     * @param string $directorio_plugin Directorio base del plugin
     * @return bool True si se registró correctamente
     */
    public static function inicializar($directorio_plugin) {
        self::$directorio_base = trailingslashit($directorio_plugin);
        
        // Registrar autoloader con spl_autoload_register
        if (!spl_autoload_register(array(__CLASS__, 'cargar_clase'))) {
            self::registrar_error('No se pudo registrar el autoloader');
            return false;
        }

        // Log en modo debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::registrar_debug('Autoloader GlobalAPI inicializado correctamente');
        }

        return true;
    }

    /**
     * Desregistra el autoloader
     *
     * @since 2.0.0
     * @return bool True si se desregistró correctamente
     */
    public static function desregistrar() {
        $resultado = spl_autoload_unregister(array(__CLASS__, 'cargar_clase'));
        
        if ($resultado && defined('WP_DEBUG') && WP_DEBUG) {
            self::registrar_debug('Autoloader GlobalAPI desregistrado');
        }

        return $resultado;
    }

    /**
     * Carga una clase específica
     *
     * @since 2.0.0
     * @param string $nombre_clase Nombre completo de la clase con namespace
     * @return bool True si la clase se cargó exitosamente
     */
    public static function cargar_clase($nombre_clase) {
        // Verificar si pertenece a nuestro namespace
        if (strpos($nombre_clase, self::$namespace_base) !== 0) {
            return false;
        }

        // Buscar el archivo correspondiente
        $archivo = self::obtener_ruta_archivo($nombre_clase);
        
        if (!$archivo) {
            return false;
        }

        // Intentar cargar el archivo
        if (file_exists($archivo)) {
            require_once $archivo;
            
            // Verificar que la clase existe después de cargar
            if (class_exists($nombre_clase) || interface_exists($nombre_clase)) {
                self::$clases_cargadas[] = $nombre_clase;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    self::registrar_debug("Clase cargada: {$nombre_clase} desde {$archivo}");
                }
                
                return true;
            } else {
                self::registrar_error("Archivo cargado pero clase no encontrada: {$nombre_clase}");
                return false;
            }
        }

        // Log de archivo no encontrado solo en debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::registrar_debug("Archivo no encontrado para clase: {$nombre_clase} - Buscado en: {$archivo}");
        }

        return false;
    }

    /**
     * Obtiene la ruta del archivo para una clase
     *
     * @since 2.0.0
     * @param string $nombre_clase Nombre completo de la clase
     * @return string|false Ruta del archivo o false si no se encontró
     */
    private static function obtener_ruta_archivo($nombre_clase) {
        // Buscar el namespace más específico que coincida
        foreach (self::$mapeo_namespaces as $namespace => $directorio) {
            if (strpos($nombre_clase, $namespace) === 0) {
                // Remover el namespace y obtener el nombre relativo
                $nombre_relativo = substr($nombre_clase, strlen($namespace));
                
                // Convertir namespace a ruta de archivo
                $ruta_relativa = str_replace('\\', '/', $nombre_relativo);
                
                // Convertir CamelCase a formato WordPress (class-nombre.php)
                $nombre_archivo = self::convertir_a_formato_wordpress($ruta_relativa);
                
                // Construir ruta completa
                $ruta_completa = self::$directorio_base . $directorio . $nombre_archivo;
                
                return $ruta_completa;
            }
        }

        return false;
    }

    /**
     * Convierte nombre de clase a formato WordPress
     *
     * @since 2.0.0
     * @param string $nombre_clase Nombre de la clase
     * @return string Nombre en formato class-nombre.php
     */
    private static function convertir_a_formato_wordpress($nombre_clase) {
        // Convertir CamelCase a snake_case
        $nombre_snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $nombre_clase);
        $nombre_snake = strtolower($nombre_snake);
        
        // Agregar prefijo class- si no existe
        if (strpos($nombre_snake, 'class-') !== 0) {
            $nombre_snake = 'class-' . $nombre_snake;
        }
        
        // Agregar extensión .php
        return $nombre_snake . '.php';
    }

    /**
     * Obtiene lista de clases cargadas
     *
     * @since 2.0.0
     * @return array Lista de clases cargadas por el autoloader
     */
    public static function obtener_clases_cargadas() {
        return self::$clases_cargadas;
    }

    /**
     * Obtiene estadísticas del autoloader
     *
     * @since 2.0.0
     * @return array Estadísticas de uso
     */
    public static function obtener_estadisticas() {
        return array(
            'clases_cargadas' => count(self::$clases_cargadas),
            'namespaces_registrados' => count(self::$mapeo_namespaces),
            'directorio_base' => self::$directorio_base,
            'lista_clases' => self::$clases_cargadas
        );
    }

    /**
     * Verifica si el autoloader está registrado
     *
     * @since 2.0.0
     * @return bool True si está registrado
     */
    public static function esta_registrado() {
        $funciones = spl_autoload_functions();
        
        if (!$funciones) {
            return false;
        }
        
        foreach ($funciones as $funcion) {
            if (is_array($funcion) && $funcion[0] === __CLASS__ && $funcion[1] === 'cargar_clase') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Registra un error en el log de WordPress
     *
     * @since 2.0.0
     * @param string $mensaje Mensaje de error
     */
    private static function registrar_error($mensaje) {
        if (function_exists('error_log')) {
            error_log("GlobalAPI Autoloader Error: {$mensaje}");
        }
    }

    /**
     * Registra información de debug
     *
     * @since 2.0.0
     * @param string $mensaje Mensaje de debug
     */
    private static function registrar_debug($mensaje) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GlobalAPI Autoloader Debug: {$mensaje}");
        }
    }

    /**
     * Limpia el caché del autoloader
     *
     * @since 2.0.0
     */
    public static function limpiar_cache() {
        self::$clases_cargadas = array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::registrar_debug('Cache del autoloader limpiado');
        }
    }

    /**
     * Agrega un mapeo de namespace personalizado
     *
     * @since 2.0.0
     * @param string $namespace Namespace a mapear
     * @param string $directorio Directorio correspondiente
     */
    public static function agregar_mapeo($namespace, $directorio) {
        // Asegurar que el namespace termine con \
        if (substr($namespace, -1) !== '\\') {
            $namespace .= '\\';
        }
        
        // Asegurar que el directorio termine con /
        $directorio = trailingslashit($directorio);
        
        self::$mapeo_namespaces[$namespace] = $directorio;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::registrar_debug("Mapeo agregado: {$namespace} -> {$directorio}");
        }
    }
} 