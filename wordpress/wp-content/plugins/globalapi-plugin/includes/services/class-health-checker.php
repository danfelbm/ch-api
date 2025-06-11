<?php
/**
 * Health Checker para APIs
 *
 * Sistema de monitoreo de salud para todas las APIs conectadas
 * al plugin GlobalAPI. Verifica estado, latencia y disponibilidad
 * de servicios externos como Groundhogg e InvisionCommunity.
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
 * Clase HealthChecker
 * 
 * Monitorea el estado de las APIs externas:
 * - Verificación automática programada
 * - Métricas de rendimiento y disponibilidad
 * - Alertas y notificaciones
 * - Histórico de estados
 * - Dashboard de salud
 * - Integración con cache para optimización
 */
class HealthChecker {

    /**
     * Versión del health checker
     */
    const VERSION = '2.0.0';

    /**
     * Intervalos de verificación disponibles
     */
    const CHECK_INTERVALS = array(
        'every_5_minutes' => '5 minutos',
        'every_15_minutes' => '15 minutos',
        'every_30_minutes' => '30 minutos',
        'hourly' => '1 hora',
        'twicedaily' => '12 horas',
        'daily' => '24 horas'
    );

    /**
     * Estados de salud posibles
     */
    const HEALTH_STATUS = array(
        'healthy' => array(
            'label' => 'Saludable',
            'color' => '#00a32a',
            'icon' => '✅'
        ),
        'warning' => array(
            'label' => 'Advertencia',
            'color' => '#dba617',
            'icon' => '⚠️'
        ),
        'critical' => array(
            'label' => 'Crítico',
            'color' => '#d63638',
            'icon' => '❌'
        ),
        'unknown' => array(
            'label' => 'Desconocido',
            'color' => '#8c8f94',
            'icon' => '❓'
        )
    );

    /**
     * Timeouts para diferentes tipos de verificación
     */
    const TIMEOUTS = array(
        'quick' => 5,    // 5 segundos
        'normal' => 15,  // 15 segundos
        'extended' => 30 // 30 segundos
    );

    /**
     * Cache de resultados de salud
     */
    private static $health_cache = array();

    /**
     * Estadísticas de verificaciones
     */
    private static $check_stats = array(
        'total_checks' => 0,
        'successful_checks' => 0,
        'failed_checks' => 0,
        'average_response_time' => 0
    );

    /**
     * Inicializar el health checker
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        add_action('globalapi_health_check', array(__CLASS__, 'ejecutar_verificacion_automatica'));
        add_action('admin_notices', array(__CLASS__, 'mostrar_notificaciones_salud'));
        
        // Programar verificaciones automáticas
        self::programar_verificaciones();
        
        // Cargar estadísticas
        self::cargar_estadisticas();
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // AJAX para verificaciones manuales
        add_action('wp_ajax_globalapi_health_check', array(__CLASS__, 'manejar_ajax_health_check'));
        
        // Dashboard widget
        add_action('wp_dashboard_setup', array(__CLASS__, 'agregar_dashboard_widget'));
        
        // Cleanup de datos antiguos
        add_action('globalapi_cleanup_health_data', array(__CLASS__, 'limpiar_datos_antiguos'));
        
        // Programar limpieza semanal
        if (!wp_next_scheduled('globalapi_cleanup_health_data')) {
            wp_schedule_event(time(), 'weekly', 'globalapi_cleanup_health_data');
        }
    }

    /**
     * Verificar salud de todas las APIs
     * 
     * @param array $options Opciones de verificación
     * @return array Resultados de todas las verificaciones
     * @since 2.0.0
     */
    public static function verificar_todas_las_apis($options = array()) {
        $defaults = array(
            'timeout' => self::TIMEOUTS['normal'],
            'cache_results' => true,
            'include_details' => true,
            'force_check' => false
        );

        $options = wp_parse_args($options, $defaults);
        $results = array();
        $inicio_global = microtime(true);

        // Verificar si hay cache válido y no es forzado
        if (!$options['force_check'] && $options['cache_results']) {
            $cached_results = self::obtener_resultados_cache();
            if ($cached_results !== false) {
                return $cached_results;
            }
        }

        // APIs a verificar
        $apis_to_check = array(
            'groundhogg' => array(
                'name' => 'Groundhogg CRM',
                'class' => 'ConectorGroundhogg',
                'method' => 'probar_conexion'
            ),
            'invisioncommunity' => array(
                'name' => 'InvisionCommunity OAuth',
                'class' => 'ServicioOAuth',
                'method' => 'probar_conexion'
            ),
            'wordpress' => array(
                'name' => 'WordPress Database',
                'class' => __CLASS__,
                'method' => 'verificar_wordpress_db'
            ),
            'cache' => array(
                'name' => 'Sistema de Cache',
                'class' => 'GestorCache',
                'method' => 'probar_cache'
            )
        );

        // Ejecutar verificaciones
        foreach ($apis_to_check as $api_key => $api_config) {
            $inicio = microtime(true);
            
            try {
                $result = self::ejecutar_verificacion_api($api_key, $api_config, $options);
                $result['response_time'] = round((microtime(true) - $inicio) * 1000);
                $results[$api_key] = $result;
                
            } catch (Exception $e) {
                $results[$api_key] = array(
                    'status' => 'critical',
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'response_time' => round((microtime(true) - $inicio) * 1000),
                    'healthy' => false,
                    'timestamp' => time()
                );
            }
        }

        // Calcular estado general
        $overall_status = self::calcular_estado_general($results);
        $tiempo_total = round((microtime(true) - $inicio_global) * 1000);

        $health_report = array(
            'overall_status' => $overall_status,
            'total_response_time' => $tiempo_total,
            'apis' => $results,
            'timestamp' => time(),
            'version' => self::VERSION
        );

        // Cachear resultados
        if ($options['cache_results']) {
            self::guardar_resultados_cache($health_report);
        }

        // Guardar en histórico
        self::guardar_historico($health_report);

        // Actualizar estadísticas
        self::actualizar_estadisticas($health_report);

        // Verificar alertas
        self::verificar_alertas($health_report);

        return $health_report;
    }

    /**
     * Ejecutar verificación de una API específica
     * 
     * @param string $api_key Clave de la API
     * @param array $api_config Configuración de la API
     * @param array $options Opciones de verificación
     * @return array Resultado de la verificación
     * @since 2.0.0
     */
    private static function ejecutar_verificacion_api($api_key, $api_config, $options) {
        $class_name = $api_config['class'];
        $method_name = $api_config['method'];

        // Verificar que la clase existe
        if (!class_exists($class_name)) {
            return array(
                'status' => 'critical',
                'message' => "Clase {$class_name} no encontrada",
                'healthy' => false,
                'timestamp' => time(),
                'details' => array('error' => 'Class not found')
            );
        }

        // Verificar que el método existe
        if (!method_exists($class_name, $method_name)) {
            return array(
                'status' => 'critical',
                'message' => "Método {$method_name} no encontrado en {$class_name}",
                'healthy' => false,
                'timestamp' => time(),
                'details' => array('error' => 'Method not found')
            );
        }

        // Ejecutar verificación
        $result = call_user_func(array($class_name, $method_name));

        // Procesar resultado
        if (is_array($result)) {
            $status = isset($result['exito']) && $result['exito'] ? 'healthy' : 'critical';
            $message = $result['mensaje'] ?? 'Verificación completada';
            $details = $result['detalles'] ?? array();
            
            return array(
                'status' => $status,
                'message' => $message,
                'healthy' => $status === 'healthy',
                'timestamp' => time(),
                'details' => $options['include_details'] ? $details : array(),
                'api_response_time' => $result['tiempo_respuesta'] ?? 0
            );
        }

        // Resultado booleano simple
        $is_healthy = (bool) $result;
        return array(
            'status' => $is_healthy ? 'healthy' : 'critical',
            'message' => $is_healthy ? 'API funcionando correctamente' : 'API no disponible',
            'healthy' => $is_healthy,
            'timestamp' => time(),
            'details' => array()
        );
    }

    /**
     * Verificar estado de WordPress Database
     * 
     * @return array Resultado de la verificación
     * @since 2.0.0
     */
    public static function verificar_wordpress_db() {
        global $wpdb;
        
        $inicio = microtime(true);
        $resultado = array(
            'exito' => false,
            'mensaje' => '',
            'tiempo_respuesta' => 0,
            'detalles' => array()
        );

        try {
            // Test básico de conexión
            $test_query = $wpdb->get_var("SELECT 1");
            if ($test_query === null) {
                $resultado['mensaje'] = 'Error en consulta básica a la base de datos';
                return $resultado;
            }

            // Verificar tablas principales del plugin
            $tables_to_check = array(
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->options,
                $wpdb->users,
                $wpdb->usermeta
            );

            foreach ($tables_to_check as $table) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                    DB_NAME,
                    $table
                ));
                
                if (!$exists) {
                    $resultado['mensaje'] = "Tabla requerida {$table} no encontrada";
                    return $resultado;
                }
            }

            // Verificar Custom Post Types del plugin
            $cpt_check = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('globalapi_credencial', 'globalapi_log') LIMIT 1"
            );

            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Base de datos WordPress funcionando correctamente';
            $resultado['detalles'] = array(
                'database_name' => DB_NAME,
                'tables_verified' => count($tables_to_check),
                'custom_post_types_available' => $cpt_check !== null,
                'database_version' => $wpdb->get_var("SELECT VERSION()")
            );

        } catch (Exception $e) {
            $resultado['mensaje'] = 'Excepción en verificación DB: ' . $e->getMessage();
        }

        $resultado['tiempo_respuesta'] = round((microtime(true) - $inicio) * 1000);
        return $resultado;
    }

    /**
     * Calcular estado general del sistema
     * 
     * @param array $results Resultados individuales
     * @return string Estado general
     * @since 2.0.0
     */
    private static function calcular_estado_general($results) {
        $critical_count = 0;
        $warning_count = 0;
        $total_count = count($results);

        foreach ($results as $result) {
            switch ($result['status']) {
                case 'critical':
                    $critical_count++;
                    break;
                case 'warning':
                    $warning_count++;
                    break;
            }
        }

        // Si hay críticos, el estado general es crítico
        if ($critical_count > 0) {
            return 'critical';
        }

        // Si hay advertencias, el estado general es advertencia
        if ($warning_count > 0) {
            return 'warning';
        }

        // Si todo está bien, estado saludable
        return 'healthy';
    }

    /**
     * Obtener resultados desde cache
     * 
     * @return array|false Resultados cacheados o false
     * @since 2.0.0
     */
    private static function obtener_resultados_cache() {
        if (class_exists('GestorCache')) {
            return GestorCache::get('health_check_results', 'health');
        }
        
        return get_transient('globalapi_health_results');
    }

    /**
     * Guardar resultados en cache
     * 
     * @param array $results Resultados a cachear
     * @since 2.0.0
     */
    private static function guardar_resultados_cache($results) {
        $cache_ttl = 300; // 5 minutos
        
        if (class_exists('GestorCache')) {
            GestorCache::set('health_check_results', $results, $cache_ttl, 'health');
        } else {
            set_transient('globalapi_health_results', $results, $cache_ttl);
        }
    }

    /**
     * Guardar en histórico de salud
     * 
     * @param array $health_report Reporte de salud
     * @since 2.0.0
     */
    private static function guardar_historico($health_report) {
        $historico = get_option('globalapi_health_history', array());
        
        // Agregar nueva entrada
        $historico[] = array(
            'timestamp' => $health_report['timestamp'],
            'overall_status' => $health_report['overall_status'],
            'total_response_time' => $health_report['total_response_time'],
            'apis_count' => count($health_report['apis']),
            'healthy_apis' => count(array_filter($health_report['apis'], function($api) {
                return $api['healthy'];
            }))
        );

        // Mantener solo las últimas 100 entradas
        if (count($historico) > 100) {
            $historico = array_slice($historico, -100);
        }

        update_option('globalapi_health_history', $historico, false);
    }

    /**
     * Actualizar estadísticas de verificaciones
     * 
     * @param array $health_report Reporte de salud
     * @since 2.0.0
     */
    private static function actualizar_estadisticas($health_report) {
        $stats = get_option('globalapi_health_stats', self::$check_stats);
        
        $stats['total_checks']++;
        
        if ($health_report['overall_status'] === 'healthy') {
            $stats['successful_checks']++;
        } else {
            $stats['failed_checks']++;
        }

        // Calcular promedio de tiempo de respuesta
        $total_time = ($stats['average_response_time'] * ($stats['total_checks'] - 1)) + $health_report['total_response_time'];
        $stats['average_response_time'] = round($total_time / $stats['total_checks']);

        // Agregar timestamp de última verificación
        $stats['last_check'] = $health_report['timestamp'];
        $stats['last_status'] = $health_report['overall_status'];

        update_option('globalapi_health_stats', $stats, false);
        self::$check_stats = $stats;
    }

    /**
     * Verificar alertas y enviar notificaciones
     * 
     * @param array $health_report Reporte de salud
     * @since 2.0.0
     */
    private static function verificar_alertas($health_report) {
        $alertas_config = get_option('globalapi_health_alerts', array(
            'critical_alerts' => true,
            'warning_alerts' => false,
            'email_notifications' => true,
            'admin_notifications' => true
        ));

        // Solo procesar si las alertas están habilitadas
        if (!$alertas_config['critical_alerts'] && !$alertas_config['warning_alerts']) {
            return;
        }

        $should_alert = false;
        $alert_level = '';

        // Verificar si debe alertar
        if ($health_report['overall_status'] === 'critical' && $alertas_config['critical_alerts']) {
            $should_alert = true;
            $alert_level = 'critical';
        } elseif ($health_report['overall_status'] === 'warning' && $alertas_config['warning_alerts']) {
            $should_alert = true;
            $alert_level = 'warning';
        }

        if ($should_alert) {
            // Evitar spam de alertas - verificar última alerta
            $last_alert = get_option('globalapi_last_health_alert', 0);
            $alert_cooldown = 3600; // 1 hora
            
            if ((time() - $last_alert) > $alert_cooldown) {
                self::enviar_alerta($health_report, $alert_level, $alertas_config);
                update_option('globalapi_last_health_alert', time());
            }
        }
    }

    /**
     * Enviar alerta de salud
     * 
     * @param array $health_report Reporte de salud
     * @param string $alert_level Nivel de alerta
     * @param array $alertas_config Configuración de alertas
     * @since 2.0.0
     */
    private static function enviar_alerta($health_report, $alert_level, $alertas_config) {
        $status_info = self::HEALTH_STATUS[$alert_level];
        $site_name = get_bloginfo('name');
        
        // Notificación admin
        if ($alertas_config['admin_notifications']) {
            $notice_key = 'globalapi_health_alert_' . time();
            set_transient($notice_key, array(
                'type' => $alert_level === 'critical' ? 'error' : 'warning',
                'message' => sprintf(
                    '%s Estado de APIs: %s - %s',
                    $status_info['icon'],
                    $status_info['label'],
                    'Verificar sistema GlobalAPI'
                ),
                'timestamp' => time()
            ), 86400); // 24 horas
        }

        // Email notification
        if ($alertas_config['email_notifications']) {
            $admin_email = get_option('admin_email');
            $subject = sprintf('[%s] Alerta de Salud GlobalAPI - %s', $site_name, $status_info['label']);
            
            $apis_fallidas = array();
            foreach ($health_report['apis'] as $api_key => $api_result) {
                if (!$api_result['healthy']) {
                    $apis_fallidas[] = "- {$api_key}: {$api_result['message']}";
                }
            }

            $message = sprintf(
                "Se ha detectado un problema en el sistema GlobalAPI de %s.\n\n" .
                "Estado General: %s\n" .
                "Timestamp: %s\n" .
                "Tiempo de Respuesta Total: %dms\n\n" .
                "APIs con Problemas:\n%s\n\n" .
                "Por favor, revise el panel de administración para más detalles.",
                $site_name,
                $status_info['label'],
                date('Y-m-d H:i:s', $health_report['timestamp']),
                $health_report['total_response_time'],
                implode("\n", $apis_fallidas)
            );

            wp_mail($admin_email, $subject, $message);
        }

        // Registrar alerta en logs
        self::registrar_log('alert_sent', "Alerta de salud enviada: {$alert_level}", array(
            'overall_status' => $health_report['overall_status'],
            'failed_apis' => count(array_filter($health_report['apis'], function($api) {
                return !$api['healthy'];
            }))
        ));
    }

    /**
     * Ejecutar verificación automática programada
     * 
     * @since 2.0.0
     */
    public static function ejecutar_verificacion_automatica() {
        $options = array(
            'timeout' => self::TIMEOUTS['quick'],
            'cache_results' => true,
            'include_details' => false,
            'force_check' => true
        );

        $results = self::verificar_todas_las_apis($options);
        
        // Registrar verificación automática
        self::registrar_log('auto_check', 'Verificación automática ejecutada', array(
            'overall_status' => $results['overall_status'],
            'total_response_time' => $results['total_response_time']
        ));
    }

    /**
     * Programar verificaciones automáticas
     * 
     * @since 2.0.0
     */
    private static function programar_verificaciones() {
        $interval = get_option('globalapi_health_check_interval', 'every_15_minutes');
        
        // Limpiar evento anterior si existe
        wp_clear_scheduled_hook('globalapi_health_check');
        
        // Programar nuevo evento
        if (!wp_next_scheduled('globalapi_health_check')) {
            wp_schedule_event(time(), $interval, 'globalapi_health_check');
        }
    }

    /**
     * Manejar AJAX para verificación manual
     * 
     * @since 2.0.0
     */
    public static function manejar_ajax_health_check() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos suficientes');
        }

        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'globalapi_health_check')) {
            wp_die('Nonce inválido');
        }

        $options = array(
            'timeout' => self::TIMEOUTS['normal'],
            'cache_results' => false,
            'include_details' => true,
            'force_check' => true
        );

        $results = self::verificar_todas_las_apis($options);
        
        wp_send_json_success(array(
            'results' => $results,
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Agregar widget al dashboard de WordPress
     * 
     * @since 2.0.0
     */
    public static function agregar_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'globalapi_health_widget',
                'Estado de APIs GlobalAPI',
                array(__CLASS__, 'mostrar_dashboard_widget')
            );
        }
    }

    /**
     * Mostrar widget del dashboard
     * 
     * @since 2.0.0
     */
    public static function mostrar_dashboard_widget() {
        $cached_results = self::obtener_resultados_cache();
        
        if ($cached_results === false) {
            $cached_results = array(
                'overall_status' => 'unknown',
                'apis' => array(),
                'timestamp' => 0
            );
        }

        $status_info = self::HEALTH_STATUS[$cached_results['overall_status']];
        $last_check = $cached_results['timestamp'] ? human_time_diff($cached_results['timestamp']) . ' ago' : 'Nunca';

        echo '<div class="globalapi-health-widget">';
        echo '<div class="health-status" style="display: flex; align-items: center; margin-bottom: 10px;">';
        echo '<span style="font-size: 20px; margin-right: 8px;">' . $status_info['icon'] . '</span>';
        echo '<span style="color: ' . $status_info['color'] . '; font-weight: bold;">' . $status_info['label'] . '</span>';
        echo '</div>';
        
        echo '<p><strong>Última verificación:</strong> ' . $last_check . '</p>';
        
        if (!empty($cached_results['apis'])) {
            echo '<div class="apis-status">';
            foreach ($cached_results['apis'] as $api_key => $api_result) {
                $api_status = self::HEALTH_STATUS[$api_result['status']];
                echo '<div style="margin: 5px 0;">';
                echo '<span style="margin-right: 5px;">' . $api_status['icon'] . '</span>';
                echo '<span>' . ucfirst($api_key) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '<p style="margin-top: 15px;">';
        echo '<button type="button" class="button" onclick="globalapi_refresh_health_check()">Verificar Ahora</button>';
        echo '</p>';
        echo '</div>';

        // JavaScript para verificación manual
        echo '<script>
        function globalapi_refresh_health_check() {
            jQuery.post(ajaxurl, {
                action: "globalapi_health_check",
                nonce: "' . wp_create_nonce('globalapi_health_check') . '"
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert("Error al verificar APIs");
                }
            });
        }
        </script>';
    }

    /**
     * Mostrar notificaciones de salud en admin
     * 
     * @since 2.0.0
     */
    public static function mostrar_notificaciones_salud() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Buscar alertas activas
        global $wpdb;
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_globalapi_health_alert_%'
             ORDER BY option_name DESC LIMIT 5"
        );

        foreach ($transients as $transient) {
            $alert_data = maybe_unserialize($transient->option_value);
            if (is_array($alert_data)) {
                $class = $alert_data['type'] === 'error' ? 'notice-error' : 'notice-warning';
                echo '<div class="notice ' . $class . ' is-dismissible">';
                echo '<p>' . esc_html($alert_data['message']) . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Limpiar datos antiguos de salud
     * 
     * @since 2.0.0
     */
    public static function limpiar_datos_antiguos() {
        // Limpiar histórico antiguo (más de 30 días)
        $historico = get_option('globalapi_health_history', array());
        $cutoff_time = time() - (30 * 24 * 3600);
        
        $historico_filtrado = array_filter($historico, function($entry) use ($cutoff_time) {
            return $entry['timestamp'] > $cutoff_time;
        });
        
        if (count($historico_filtrado) !== count($historico)) {
            update_option('globalapi_health_history', array_values($historico_filtrado), false);
        }

        // Limpiar transients de alertas expirados
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_globalapi_health_alert_%' 
             AND option_value < " . time()
        );

        self::registrar_log('cleanup', 'Limpieza de datos antiguos de salud completada');
    }

    /**
     * Cargar estadísticas
     * 
     * @since 2.0.0
     */
    private static function cargar_estadisticas() {
        self::$check_stats = get_option('globalapi_health_stats', self::$check_stats);
    }

    /**
     * Obtener estadísticas de salud
     * 
     * @return array Estadísticas completas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        $stats = self::$check_stats;
        
        // Agregar información adicional
        $stats['uptime_percentage'] = $stats['total_checks'] > 0 
            ? round(($stats['successful_checks'] / $stats['total_checks']) * 100, 2)
            : 0;
            
        $stats['last_check_formatted'] = isset($stats['last_check']) 
            ? date('Y-m-d H:i:s', $stats['last_check'])
            : 'Nunca';
            
        $stats['interval_configured'] = get_option('globalapi_health_check_interval', 'every_15_minutes');
        $stats['alerts_enabled'] = get_option('globalapi_health_alerts', array());

        return $stats;
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
                'accion' => 'health_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Obtener información de configuración
     * 
     * @return array Información de configuración
     * @since 2.0.0
     */
    public static function obtener_info_config() {
        return array(
            'version' => self::VERSION,
            'check_intervals' => self::CHECK_INTERVALS,
            'health_status' => self::HEALTH_STATUS,
            'timeouts' => self::TIMEOUTS,
            'current_interval' => get_option('globalapi_health_check_interval', 'every_15_minutes'),
            'next_scheduled_check' => wp_next_scheduled('globalapi_health_check'),
            'alerts_config' => get_option('globalapi_health_alerts', array())
        );
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    HealthChecker::init();
} 