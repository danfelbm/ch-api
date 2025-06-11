<?php
/**
 * Gestor de Cache WordPress
 *
 * Sistema de cache inteligente usando WordPress Transients API
 * para optimizar el rendimiento del plugin GlobalAPI.
 * Soporte para cache jerárquico, invalidación selectiva y estadísticas.
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
 * Clase GestorCache
 * 
 * Gestiona el sistema de cache del plugin usando WordPress Transients:
 * - Cache jerárquico con grupos y tags
 * - Invalidación selectiva e inteligente
 * - Compresión automática de datos grandes
 * - Estadísticas detalladas de uso
 * - Limpieza automática programada
 * - Integración con WordPress Object Cache
 */
class GestorCache {

    /**
     * Versión del gestor de cache
     */
    const VERSION = '2.0.0';

    /**
     * Prefijo para todas las claves de cache
     */
    const CACHE_PREFIX = 'globalapi_';

    /**
     * TTL por defecto (en segundos)
     */
    const DEFAULT_TTL = 3600; // 1 hora

    /**
     * TTL máximo permitido (en segundos)
     */
    const MAX_TTL = 86400; // 24 horas

    /**
     * Tamaño máximo de datos para comprimir (en bytes)
     */
    const COMPRESSION_THRESHOLD = 1024; // 1KB

    /**
     * Grupos de cache disponibles
     */
    const CACHE_GROUPS = array(
        'credenciales' => 'Credenciales de servicios',
        'oauth' => 'Tokens OAuth',
        'api' => 'Respuestas de APIs externas',
        'groundhogg' => 'Datos de Groundhogg CRM',
        'invision' => 'Datos de InvisionCommunity',
        'config' => 'Configuraciones del sistema',
        'logs' => 'Logs y auditoría',
        'stats' => 'Estadísticas',
        'temp' => 'Datos temporales'
    );

    /**
     * Estadísticas de cache en memoria
     */
    private static $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0
    );

    /**
     * Cache local en memoria para evitar consultas duplicadas
     */
    private static $memory_cache = array();

    /**
     * Índice de grupos y tags para invalidación selectiva
     */
    private static $cache_index = null;

    /**
     * Inicializar el gestor de cache
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        add_action('globalapi_limpiar_cache', array(__CLASS__, 'limpiar_cache_expirado'));
        add_action('shutdown', array(__CLASS__, 'guardar_estadisticas'));
        
        // Programar limpieza automática
        if (!wp_next_scheduled('globalapi_limpiar_cache')) {
            wp_schedule_event(time(), 'hourly', 'globalapi_limpiar_cache');
        }

        // Cargar estadísticas
        self::cargar_estadisticas();
        
        // Cargar índice de cache
        self::cargar_indice_cache();
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Limpiar cache cuando se actualizan credenciales
        add_action('save_post', array(__CLASS__, 'invalidar_cache_post'), 10, 1);
        add_action('delete_post', array(__CLASS__, 'invalidar_cache_post'), 10, 1);
        
        // Limpiar cache cuando se actualiza configuración
        add_action('update_option', array(__CLASS__, 'invalidar_cache_opcion'), 10, 2);
        
        // Hook para debug de cache
        if (WP_DEBUG) {
            add_action('wp_footer', array(__CLASS__, 'mostrar_debug_cache'));
        }
    }

    /**
     * Obtener valor del cache
     * 
     * @param string $key Clave del cache
     * @param string $group Grupo del cache
     * @return mixed|false Valor del cache o false si no existe
     * @since 2.0.0
     */
    public static function get($key, $group = 'default') {
        $cache_key = self::generar_clave($key, $group);
        
        // Verificar memoria local primero
        if (isset(self::$memory_cache[$cache_key])) {
            self::$stats['hits']++;
            return self::$memory_cache[$cache_key];
        }

        // Obtener de WordPress Transients
        $data = get_transient($cache_key);
        
        if ($data !== false) {
            // Verificar si los datos están comprimidos
            if (is_array($data) && isset($data['__compressed']) && $data['__compressed']) {
                $data = self::descomprimir_datos($data['data']);
            }
            
            // Guardar en memoria local
            self::$memory_cache[$cache_key] = $data;
            self::$stats['hits']++;
            
            // Registrar hit en logs si es debug
            if (WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                self::registrar_log('cache_hit', "Cache hit para clave: {$cache_key}");
            }
            
            return $data;
        }

        self::$stats['misses']++;
        return false;
    }

    /**
     * Establecer valor en el cache
     * 
     * @param string $key Clave del cache
     * @param mixed $data Datos a cachear
     * @param int $ttl Tiempo de vida en segundos
     * @param string $group Grupo del cache
     * @param array $tags Tags para invalidación selectiva
     * @return bool True en éxito, false en error
     * @since 2.0.0
     */
    public static function set($key, $data, $ttl = null, $group = 'default', $tags = array()) {
        if ($ttl === null) {
            $ttl = self::DEFAULT_TTL;
        }

        // Limitar TTL máximo
        $ttl = min($ttl, self::MAX_TTL);
        
        $cache_key = self::generar_clave($key, $group);
        
        // Comprimir datos grandes
        $data_to_store = $data;
        if (self::debe_comprimir($data)) {
            $compressed = self::comprimir_datos($data);
            if ($compressed !== false) {
                $data_to_store = array(
                    '__compressed' => true,
                    'data' => $compressed
                );
            }
        }

        // Establecer en WordPress Transients
        $result = set_transient($cache_key, $data_to_store, $ttl);
        
        if ($result) {
            // Guardar en memoria local
            self::$memory_cache[$cache_key] = $data;
            self::$stats['sets']++;
            
            // Actualizar índice de cache
            self::actualizar_indice_cache($cache_key, $group, $tags, time() + $ttl);
            
            // Registrar en logs si es debug
            if (WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                self::registrar_log('cache_set', "Cache set para clave: {$cache_key}, TTL: {$ttl}s");
            }
        }

        return $result;
    }

    /**
     * Eliminar valor del cache
     * 
     * @param string $key Clave del cache
     * @param string $group Grupo del cache
     * @return bool True en éxito, false en error
     * @since 2.0.0
     */
    public static function delete($key, $group = 'default') {
        $cache_key = self::generar_clave($key, $group);
        
        // Eliminar de WordPress Transients
        $result = delete_transient($cache_key);
        
        // Eliminar de memoria local
        unset(self::$memory_cache[$cache_key]);
        
        // Actualizar índice
        self::eliminar_de_indice($cache_key);
        
        if ($result) {
            self::$stats['deletes']++;
        }

        return $result;
    }

    /**
     * Invalidar cache por grupo
     * 
     * @param string $group Grupo a invalidar
     * @return int Número de entradas eliminadas
     * @since 2.0.0
     */
    public static function invalidar_grupo($group) {
        $count = 0;
        $indice = self::obtener_indice_cache();
        
        if (isset($indice['grupos'][$group])) {
            foreach ($indice['grupos'][$group] as $cache_key) {
                if (delete_transient($cache_key)) {
                    $count++;
                    unset(self::$memory_cache[$cache_key]);
                }
            }
            
            // Limpiar índice del grupo
            unset($indice['grupos'][$group]);
            self::guardar_indice_cache($indice);
        }

        if ($count > 0) {
            self::$stats['deletes'] += $count;
            self::registrar_log('cache_invalidate_group', "Invalidado grupo {$group}: {$count} entradas");
        }

        return $count;
    }

    /**
     * Invalidar cache por tags
     * 
     * @param array $tags Tags a invalidar
     * @return int Número de entradas eliminadas
     * @since 2.0.0
     */
    public static function invalidar_tags($tags) {
        $count = 0;
        $indice = self::obtener_indice_cache();
        
        foreach ($tags as $tag) {
            if (isset($indice['tags'][$tag])) {
                foreach ($indice['tags'][$tag] as $cache_key) {
                    if (delete_transient($cache_key)) {
                        $count++;
                        unset(self::$memory_cache[$cache_key]);
                    }
                }
                
                // Limpiar índice del tag
                unset($indice['tags'][$tag]);
            }
        }
        
        if ($count > 0) {
            self::guardar_indice_cache($indice);
            self::$stats['deletes'] += $count;
            self::registrar_log('cache_invalidate_tags', "Invalidados tags: " . implode(', ', $tags) . " ({$count} entradas)");
        }

        return $count;
    }

    /**
     * Limpiar todo el cache del plugin
     * 
     * @return int Número de entradas eliminadas
     * @since 2.0.0
     */
    public static function flush_all() {
        global $wpdb;
        
        $prefix = self::CACHE_PREFIX;
        
        // Eliminar todos los transients del plugin
        $count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '%_transient_' . $prefix . '%',
            '%_transient_timeout_' . $prefix . '%'
        ));

        // Limpiar memoria local
        self::$memory_cache = array();
        
        // Limpiar índice
        self::limpiar_indice_cache();
        
        self::$stats['flushes']++;
        self::registrar_log('cache_flush', "Cache completo eliminado: {$count} entradas");

        return $count;
    }

    /**
     * Limpiar cache expirado
     * 
     * @since 2.0.0
     */
    public static function limpiar_cache_expirado() {
        global $wpdb;
        
        $now = time();
        
        // Eliminar transients expirados
        $count = $wpdb->query($wpdb->prepare(
            "DELETE t1, t2 FROM {$wpdb->options} t1 
             LEFT JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
             WHERE t1.option_name LIKE %s 
             AND t1.option_value < %d",
            '%_transient_timeout_' . self::CACHE_PREFIX . '%',
            $now
        ));

        // Limpiar índice de entradas expiradas
        self::limpiar_indice_expirado();

        if ($count > 0) {
            self::registrar_log('cache_cleanup', "Limpieza automática: {$count} entradas expiradas eliminadas");
        }
    }

    /**
     * Obtener estadísticas del cache
     * 
     * @return array Estadísticas completas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        global $wpdb;
        
        // Estadísticas en tiempo real
        $stats = self::$stats;
        
        // Contar entradas activas en cache
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%_transient_' . self::CACHE_PREFIX . '%'
        ));

        $stats['entradas_activas'] = intval($active_count);
        
        // Calcular hit ratio
        $total_requests = $stats['hits'] + $stats['misses'];
        $stats['hit_ratio'] = $total_requests > 0 ? round(($stats['hits'] / $total_requests) * 100, 2) : 0;
        
        // Tamaño estimado del cache
        $cache_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%_transient_' . self::CACHE_PREFIX . '%'
        ));
        
        $stats['tamano_cache'] = intval($cache_size);
        $stats['tamano_cache_mb'] = round($cache_size / 1024 / 1024, 2);
        
        // Estadísticas por grupo
        $indice = self::obtener_indice_cache();
        $stats['grupos'] = array();
        
        foreach (self::CACHE_GROUPS as $grupo => $descripcion) {
            $stats['grupos'][$grupo] = array(
                'descripcion' => $descripcion,
                'entradas' => isset($indice['grupos'][$grupo]) ? count($indice['grupos'][$grupo]) : 0
            );
        }

        return $stats;
    }

    /**
     * Cache de función con callback
     * 
     * @param string $key Clave única para el cache
     * @param callable $callback Función a ejecutar si no hay cache
     * @param int $ttl Tiempo de vida del cache
     * @param string $group Grupo del cache
     * @param array $tags Tags para invalidación
     * @return mixed Resultado de la función o cache
     * @since 2.0.0
     */
    public static function remember($key, $callback, $ttl = null, $group = 'default', $tags = array()) {
        $data = self::get($key, $group);
        
        if ($data !== false) {
            return $data;
        }

        // Ejecutar callback y cachear resultado
        $result = call_user_func($callback);
        
        if ($result !== false && $result !== null) {
            self::set($key, $result, $ttl, $group, $tags);
        }

        return $result;
    }

    /**
     * Generar clave de cache única
     * 
     * @param string $key Clave base
     * @param string $group Grupo
     * @return string Clave completa
     * @since 2.0.0
     */
    private static function generar_clave($key, $group) {
        return self::CACHE_PREFIX . $group . '_' . md5($key);
    }

    /**
     * Verificar si los datos deben comprimirse
     * 
     * @param mixed $data Datos a verificar
     * @return bool True si debe comprimirse
     * @since 2.0.0
     */
    private static function debe_comprimir($data) {
        if (!function_exists('gzcompress')) {
            return false;
        }

        $serialized = serialize($data);
        return strlen($serialized) > self::COMPRESSION_THRESHOLD;
    }

    /**
     * Comprimir datos
     * 
     * @param mixed $data Datos a comprimir
     * @return string|false Datos comprimidos o false en error
     * @since 2.0.0
     */
    private static function comprimir_datos($data) {
        if (!function_exists('gzcompress')) {
            return false;
        }

        $serialized = serialize($data);
        return gzcompress($serialized, 6);
    }

    /**
     * Descomprimir datos
     * 
     * @param string $compressed_data Datos comprimidos
     * @return mixed Datos descomprimidos
     * @since 2.0.0
     */
    private static function descomprimir_datos($compressed_data) {
        if (!function_exists('gzuncompress')) {
            return false;
        }

        $decompressed = gzuncompress($compressed_data);
        if ($decompressed === false) {
            return false;
        }

        return unserialize($decompressed);
    }

    /**
     * Cargar índice de cache
     * 
     * @since 2.0.0
     */
    private static function cargar_indice_cache() {
        self::$cache_index = get_option('globalapi_cache_index', array(
            'grupos' => array(),
            'tags' => array(),
            'expires' => array()
        ));
    }

    /**
     * Obtener índice de cache
     * 
     * @return array Índice de cache
     * @since 2.0.0
     */
    private static function obtener_indice_cache() {
        if (self::$cache_index === null) {
            self::cargar_indice_cache();
        }
        return self::$cache_index;
    }

    /**
     * Actualizar índice de cache
     * 
     * @param string $cache_key Clave de cache
     * @param string $group Grupo
     * @param array $tags Tags
     * @param int $expires Timestamp de expiración
     * @since 2.0.0
     */
    private static function actualizar_indice_cache($cache_key, $group, $tags, $expires) {
        $indice = self::obtener_indice_cache();
        
        // Añadir a grupo
        if (!isset($indice['grupos'][$group])) {
            $indice['grupos'][$group] = array();
        }
        $indice['grupos'][$group][] = $cache_key;
        
        // Añadir a tags
        foreach ($tags as $tag) {
            if (!isset($indice['tags'][$tag])) {
                $indice['tags'][$tag] = array();
            }
            $indice['tags'][$tag][] = $cache_key;
        }
        
        // Guardar expiración
        $indice['expires'][$cache_key] = $expires;
        
        self::guardar_indice_cache($indice);
    }

    /**
     * Guardar índice de cache
     * 
     * @param array $indice Índice a guardar
     * @since 2.0.0
     */
    private static function guardar_indice_cache($indice) {
        self::$cache_index = $indice;
        update_option('globalapi_cache_index', $indice, false);
    }

    /**
     * Eliminar entrada del índice
     * 
     * @param string $cache_key Clave a eliminar
     * @since 2.0.0
     */
    private static function eliminar_de_indice($cache_key) {
        $indice = self::obtener_indice_cache();
        
        // Eliminar de grupos
        foreach ($indice['grupos'] as $grupo => $claves) {
            $key = array_search($cache_key, $claves);
            if ($key !== false) {
                unset($indice['grupos'][$grupo][$key]);
                $indice['grupos'][$grupo] = array_values($indice['grupos'][$grupo]);
            }
        }
        
        // Eliminar de tags
        foreach ($indice['tags'] as $tag => $claves) {
            $key = array_search($cache_key, $claves);
            if ($key !== false) {
                unset($indice['tags'][$tag][$key]);
                $indice['tags'][$tag] = array_values($indice['tags'][$tag]);
            }
        }
        
        // Eliminar expiración
        unset($indice['expires'][$cache_key]);
        
        self::guardar_indice_cache($indice);
    }

    /**
     * Limpiar índice de entradas expiradas
     * 
     * @since 2.0.0
     */
    private static function limpiar_indice_expirado() {
        $indice = self::obtener_indice_cache();
        $now = time();
        $changed = false;
        
        foreach ($indice['expires'] as $cache_key => $expires) {
            if ($expires < $now) {
                self::eliminar_de_indice($cache_key);
                $changed = true;
            }
        }
        
        if ($changed) {
            self::guardar_indice_cache($indice);
        }
    }

    /**
     * Limpiar índice completo
     * 
     * @since 2.0.0
     */
    private static function limpiar_indice_cache() {
        delete_option('globalapi_cache_index');
        self::$cache_index = array(
            'grupos' => array(),
            'tags' => array(),
            'expires' => array()
        );
    }

    /**
     * Cargar estadísticas
     * 
     * @since 2.0.0
     */
    private static function cargar_estadisticas() {
        $stats = get_option('globalapi_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'flushes' => 0
        ));
        
        self::$stats = $stats;
    }

    /**
     * Guardar estadísticas
     * 
     * @since 2.0.0
     */
    public static function guardar_estadisticas() {
        update_option('globalapi_cache_stats', self::$stats, false);
    }

    /**
     * Invalidar cache cuando se actualiza un post
     * 
     * @param int $post_id ID del post
     * @since 2.0.0
     */
    public static function invalidar_cache_post($post_id) {
        $post = get_post($post_id);
        
        if ($post && $post->post_type === 'globalapi_credencial') {
            self::invalidar_grupo('credenciales');
            self::invalidar_tags(array('credencial_' . $post_id));
        }
        
        if ($post && $post->post_type === 'globalapi_log') {
            self::invalidar_grupo('logs');
        }
    }

    /**
     * Invalidar cache cuando se actualiza una opción
     * 
     * @param string $option Nombre de la opción
     * @param mixed $value Nuevo valor
     * @since 2.0.0
     */
    public static function invalidar_cache_opcion($option, $value) {
        if (strpos($option, 'globalapi_') === 0) {
            self::invalidar_grupo('config');
        }
    }

    /**
     * Mostrar debug de cache en footer
     * 
     * @since 2.0.0
     */
    public static function mostrar_debug_cache() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = self::obtener_estadisticas();
        echo "<!-- GlobalAPI Cache Debug:\n";
        echo "Hits: {$stats['hits']}, Misses: {$stats['misses']}, Hit Ratio: {$stats['hit_ratio']}%\n";
        echo "Sets: {$stats['sets']}, Deletes: {$stats['deletes']}, Flushes: {$stats['flushes']}\n";
        echo "Active Entries: {$stats['entradas_activas']}, Size: {$stats['tamano_cache_mb']} MB\n";
        echo "-->";
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
                'accion' => 'cache_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Probar funcionamiento del sistema de cache
     * 
     * @return array Resultado de la prueba
     * @since 2.0.0
     */
    public static function probar_cache() {
        $inicio = microtime(true);
        $resultado = array(
            'exito' => false,
            'mensaje' => '',
            'tiempo_respuesta' => 0,
            'detalles' => array()
        );

        try {
            // Test 1: Set de cache básico
            $test_key = 'health_check_test';
            $test_data = 'test_data_' . time();
            $test_group = 'health';
            
            $set_result = self::set($test_key, $test_data, 300, $test_group);
            if (!$set_result) {
                $resultado['mensaje'] = 'Error escribiendo en cache';
                return $resultado;
            }

            // Test 2: Get de cache
            $retrieved_data = self::get($test_key, $test_group);
            if ($retrieved_data !== $test_data) {
                $resultado['mensaje'] = 'Error leyendo desde cache - datos no coinciden';
                return $resultado;
            }

            // Test 3: Invalidación por grupo
            self::invalidar_grupo($test_group);
            $after_invalidation = self::get($test_key, $test_group);
            if ($after_invalidation !== false) {
                $resultado['mensaje'] = 'Error en invalidación de grupo - cache no se limpió';
                return $resultado;
            }

            // Test 4: Cache con tags
            $tag_test_key = 'health_check_tags';
            $tag_test_data = 'tag_test_data_' . time();
            $test_tags = array('health_tag_1', 'health_tag_2');
            
            self::set($tag_test_key, $tag_test_data, 300, $test_group, $test_tags);
            $tagged_data = self::get($tag_test_key, $test_group);
            
            if ($tagged_data !== $tag_test_data) {
                $resultado['mensaje'] = 'Error en cache con tags';
                return $resultado;
            }

            // Test 5: Invalidación por tags
            self::invalidar_tags(array('health_tag_1'));
            $after_tag_invalidation = self::get($tag_test_key, $test_group);
            if ($after_tag_invalidation !== false) {
                $resultado['mensaje'] = 'Error en invalidación por tags';
                return $resultado;
            }

            // Test 6: Compresión (si está disponible)
            $compression_test = '';
            if (function_exists('gzcompress')) {
                $large_data = str_repeat('test data for compression ', 100); // >1KB
                $compression_key = 'health_compression_test';
                
                self::set($compression_key, $large_data, 300, $test_group);
                $compressed_data = self::get($compression_key, $test_group);
                
                if ($compressed_data !== $large_data) {
                    $resultado['mensaje'] = 'Error en compresión/descompresión de datos';
                    return $resultado;
                }
                
                $compression_test = 'Compresión funcionando';
                self::delete($compression_key, $test_group);
            }

            // Test 7: Estadísticas
            $stats = self::obtener_estadisticas();
            if (!is_array($stats) || !isset($stats['hits'])) {
                $resultado['mensaje'] = 'Error obteniendo estadísticas de cache';
                return $resultado;
            }

            // Limpiar datos de test
            self::invalidar_grupo($test_group);

            // Todo correcto
            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Sistema de cache funcionando correctamente';
            $resultado['detalles'] = array(
                'transients_api' => 'Disponible',
                'object_cache' => wp_using_ext_object_cache() ? 'Activo' : 'No activo',
                'compression' => function_exists('gzcompress') ? 'Disponible' : 'No disponible',
                'compression_test' => $compression_test,
                'grupos_soportados' => count(self::CACHE_GROUPS),
                'estadisticas' => array(
                    'hits' => $stats['hits'],
                    'misses' => $stats['misses'],
                    'hit_ratio' => $stats['hit_ratio'] . '%',
                    'entradas_activas' => $stats['entradas_activas']
                ),
                'version' => self::VERSION
            );

        } catch (Exception $e) {
            $resultado['mensaje'] = 'Excepción en prueba de cache: ' . $e->getMessage();
        }

        $resultado['tiempo_respuesta'] = round((microtime(true) - $inicio) * 1000);
        return $resultado;
    }

    /**
     * Obtener información de configuración del cache
     * 
     * @return array Información de configuración
     * @since 2.0.0
     */
    public static function obtener_info_config() {
        return array(
            'version' => self::VERSION,
            'prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
            'max_ttl' => self::MAX_TTL,
            'compression_threshold' => self::COMPRESSION_THRESHOLD,
            'compression_available' => function_exists('gzcompress'),
            'grupos_disponibles' => self::CACHE_GROUPS,
            'object_cache_available' => wp_using_ext_object_cache()
        );
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    GestorCache::init();
} 