<?php
/**
 * Clase Desactivador del Plugin GlobalAPI
 *
 * Esta clase gestiona la desactivación del plugin, incluyendo la limpieza
 * de trabajos programados, cache y tareas de mantenimiento necesarias
 * para una desactivación limpia del plugin.
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
 * Clase GlobalAPI_Desactivador
 *
 * Gestiona todas las operaciones necesarias durante la desactivación del plugin.
 * NOTA: La desactivación NO elimina datos permanentes como tablas o configuraciones.
 * Para limpieza completa, ver el archivo uninstall.php
 *
 * @since 2.0.0
 */
class GlobalAPI_Desactivador {

    /**
     * Método principal de desactivación del plugin
     *
     * Se ejecuta cuando el plugin se desactiva. Limpia recursos temporales,
     * trabajos programados y cache, pero mantiene datos permanentes.
     *
     * @since 2.0.0
     * @static
     */
    public static function desactivar() {
        // Verificar capacidades del usuario actual
        if (!current_user_can('activate_plugins')) {
            wp_die(
                esc_html__('No tienes permisos suficientes para desactivar plugins.', 'globalapi'),
                esc_html__('Error de Permisos', 'globalapi'),
                ['back_link' => true]
            );
        }
        
        // Log del proceso de desactivación
        error_log('GlobalAPI: Iniciando proceso de desactivación del plugin');
        
        try {
            // 1. Cancelar trabajos programados (cron jobs)
            self::cancelar_tareas_programadas();
            
            // 2. Limpiar cache y transients
            self::limpiar_cache_transients();
            
            // 3. Cerrar sesiones activas de API
            self::cerrar_sesiones_activas();
            
            // 4. Limpiar rewrite rules (si se usan)
            self::limpiar_rewrite_rules();
            
            // 5. Limpiar trabajos en cola (si se usan)
            self::limpiar_trabajos_cola();
            
            // 6. Ejecutar hooks de desactivación personalizados
            self::ejecutar_hooks_desactivacion();
            
            // 7. Guardar información de desactivación
            self::guardar_info_desactivacion();
            
            // 8. Limpiar recursos temporales
            self::limpiar_recursos_temporales();
            
            error_log('GlobalAPI: Plugin desactivado exitosamente');
            
        } catch (Exception $e) {
            // Log del error pero no interrumpir la desactivación
            error_log('GlobalAPI Error durante desactivación: ' . $e->getMessage());
            
            // Mostrar advertencia pero permitir que continúe la desactivación
            add_action('admin_notices', function() use ($e) {
                printf(
                    '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
                    esc_html__('GlobalAPI Advertencia:', 'globalapi'),
                    sprintf(
                        esc_html__('Se produjo un error durante la desactivación: %s', 'globalapi'),
                        esc_html($e->getMessage())
                    )
                );
            });
        }
    }
    
    /**
     * Cancelar trabajos programados (cron jobs)
     *
     * Elimina todos los trabajos programados del plugin para evitar
     * que se ejecuten cuando el plugin esté desactivado.
     *
     * @since 2.0.0
     */
    private static function cancelar_tareas_programadas() {
        // Lista de eventos programados del plugin
        $eventos_programados = [
            'globalapi_limpiar_logs',
            'globalapi_limpiar_sesiones',
            'globalapi_health_check',
            'globalapi_backup_credenciales',
            'globalapi_sync_groundhogg',
            'globalapi_maintenance_task'
        ];
        
        foreach ($eventos_programados as $evento) {
            // Obtener timestamp del próximo evento programado
            $timestamp = wp_next_scheduled($evento);
            
            if ($timestamp) {
                // Cancelar el evento programado
                wp_unschedule_event($timestamp, $evento);
                error_log("GlobalAPI: Evento programado cancelado: $evento");
            }
            
            // Limpiar todos los hooks del evento (por si hay múltiples instancias)
            wp_clear_scheduled_hook($evento);
        }
        
        error_log('GlobalAPI: Todos los trabajos programados han sido cancelados');
    }
    
    /**
     * Limpiar cache y transients
     *
     * Elimina todos los transients y cache relacionados con el plugin
     * para evitar datos obsoletos cuando se reactive.
     *
     * @since 2.0.0
     */
    private static function limpiar_cache_transients() {
        global $wpdb;
        
        // Prefijos de transients del plugin
        $prefijos_transients = [
            'globalapi_cache_',
            'globalapi_token_',
            'globalapi_session_',
            'globalapi_groundhogg_',
            'globalapi_invision_',
            'globalapi_health_'
        ];
        
        foreach ($prefijos_transients as $prefijo) {
            // Eliminar transients regulares
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . $prefijo . '%'
                )
            );
            
            // Eliminar transients de timeout
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_' . $prefijo . '%'
                )
            );
            
            // Eliminar site transients (para multisitio)
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_site_transient_' . $prefijo . '%'
                )
            );
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_site_transient_timeout_' . $prefijo . '%'
                )
            );
        }
        
        // Limpiar cache de objeto si está disponible
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('globalapi');
        }
        
        error_log('GlobalAPI: Cache y transients limpiados');
    }
    
    /**
     * Cerrar sesiones activas de API
     *
     * Invalida todas las sesiones activas para forzar re-autenticación
     * cuando el plugin se reactive.
     *
     * @since 2.0.0
     */
    private static function cerrar_sesiones_activas() {
        global $wpdb;
        
        $tabla_sesiones = $wpdb->prefix . 'globalapi_sesiones';
        
        // Verificar que la tabla existe antes de intentar actualizar
        if ($wpdb->get_var("SHOW TABLES LIKE '$tabla_sesiones'") === $tabla_sesiones) {
            // Marcar todas las sesiones como inactivas
            $resultado = $wpdb->update(
                $tabla_sesiones,
                ['activo' => 0],
                ['activo' => 1],
                ['%d'],
                ['%d']
            );
            
            if ($resultado !== false) {
                error_log("GlobalAPI: {$resultado} sesiones activas cerradas");
            } else {
                error_log('GlobalAPI: Error al cerrar sesiones activas: ' . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Limpiar rewrite rules
     *
     * Limpia las reglas de reescritura personalizadas del plugin
     *
     * @since 2.0.0
     */
    private static function limpiar_rewrite_rules() {
        // Si el plugin registró rewrite rules personalizadas, limpiarlas
        flush_rewrite_rules();
        
        error_log('GlobalAPI: Reglas de reescritura limpiadas');
    }
    
    /**
     * Limpiar trabajos en cola
     *
     * Si se usan sistemas de cola como Action Scheduler, limpiar trabajos pendientes
     *
     * @since 2.0.0
     */
    private static function limpiar_trabajos_cola() {
        // Si se usa Action Scheduler u otro sistema de cola
        if (class_exists('ActionScheduler')) {
            // Cancelar acciones programadas del plugin
            as_unschedule_all_actions('', 'globalapi');
            error_log('GlobalAPI: Trabajos de Action Scheduler cancelados');
        }
        
        // Limpiar cualquier otro sistema de cola personalizado
        do_action('globalapi_limpiar_trabajos_cola');
    }
    
    /**
     * Ejecutar hooks de desactivación personalizados
     *
     * Permite que otros plugins o temas reaccionen a la desactivación
     *
     * @since 2.0.0
     */
    private static function ejecutar_hooks_desactivacion() {
        // Hook para que otros plugins puedan reaccionar
        do_action('globalapi_antes_desactivacion');
        
        // Ejecutar limpieza específica de módulos
        do_action('globalapi_desactivar_modulo_groundhogg');
        do_action('globalapi_desactivar_modulo_invision');
        do_action('globalapi_desactivar_modulo_auditoria');
        
        // Hook final
        do_action('globalapi_despues_desactivacion');
        
        error_log('GlobalAPI: Hooks de desactivación ejecutados');
    }
    
    /**
     * Guardar información de desactivación
     *
     * Guarda información sobre la desactivación para estadísticas
     * y posible restauración
     *
     * @since 2.0.0
     */
    private static function guardar_info_desactivacion() {
        // Guardar fecha de desactivación
        update_option('globalapi_fecha_desactivacion', current_time('mysql'));
        
        // Mantener fecha de activación para calcular tiempo de uso
        $fecha_activacion = get_option('globalapi_activado');
        if ($fecha_activacion) {
            $tiempo_uso = strtotime(current_time('mysql')) - strtotime($fecha_activacion);
            update_option('globalapi_ultimo_tiempo_uso', $tiempo_uso);
        }
        
        // Marcar como desactivado pero conservar configuraciones
        update_option('globalapi_estado', 'desactivado');
        
        error_log('GlobalAPI: Información de desactivación guardada');
    }
    
    /**
     * Limpiar recursos temporales
     *
     * Elimina archivos temporales y recursos que no se necesitan
     * cuando el plugin está desactivado
     *
     * @since 2.0.0
     */
    private static function limpiar_recursos_temporales() {
        // Limpiar archivos de log temporales si existen
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/globalapi-logs/';
        
        if (is_dir($log_dir)) {
            // Solo eliminar logs temporales, no permanentes
            $archivos_temporales = glob($log_dir . 'temp_*.log');
            foreach ($archivos_temporales as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
        }
        
        // Limpiar cache de archivos si existe
        $cache_dir = $upload_dir['basedir'] . '/globalapi-cache/';
        if (is_dir($cache_dir)) {
            $archivos_cache = glob($cache_dir . '*.cache');
            foreach ($archivos_cache as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
        }
        
        error_log('GlobalAPI: Recursos temporales limpiados');
    }
    
    /**
     * Obtener información sobre la desactivación
     *
     * Método útil para debugging o análisis
     *
     * @since 2.0.0
     * @return array Información sobre el estado de desactivación
     */
    public static function obtener_info_desactivacion() {
        return [
            'fecha_desactivacion' => get_option('globalapi_fecha_desactivacion'),
            'ultimo_tiempo_uso' => get_option('globalapi_ultimo_tiempo_uso'),
            'version_desactivada' => get_option('globalapi_version_bd'),
            'estado' => get_option('globalapi_estado'),
            'configuraciones_preservadas' => [
                'general' => get_option('globalapi_configuracion_general') ? 'si' : 'no',
                'seguridad' => get_option('globalapi_configuracion_seguridad') ? 'si' : 'no',
                'groundhogg' => get_option('globalapi_configuracion_groundhogg') ? 'si' : 'no',
                'invision' => get_option('globalapi_configuracion_invision') ? 'si' : 'no'
            ]
        ];
    }
} 