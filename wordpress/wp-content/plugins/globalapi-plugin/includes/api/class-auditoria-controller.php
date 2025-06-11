<?php
/**
 * Controlador REST API para Auditoría - Plugin GlobalAPI
 *
 * Maneja todas las operaciones de consulta y gestión de logs de auditoría
 * del sistema a través de WordPress REST API. Proporciona endpoints seguros
 * para consultar, filtrar y exportar logs de actividad del sistema.
 *
 * @since 2.0.0
 * @package GlobalAPI
 * @subpackage API
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controlador REST API para gestión de auditoría
 *
 * Endpoints disponibles:
 * - GET /globalapi/v1/auditoria - Listar logs de auditoría
 * - GET /globalapi/v1/auditoria/{id} - Obtener log específico
 * - DELETE /globalapi/v1/auditoria/{id} - Eliminar log
 * - POST /globalapi/v1/auditoria/limpiar - Limpiar logs antiguos
 * - GET /globalapi/v1/auditoria/estadisticas - Estadísticas de auditoría
 * - GET /globalapi/v1/auditoria/exportar - Exportar logs
 *
 * @since 2.0.0
 */
class GlobalAPI_Auditoria_Controller extends GlobalAPI_REST_Controller {

    /**
     * Base del endpoint
     *
     * @since 2.0.0
     * @var string
     */
    protected $rest_base = 'auditoria';

    /**
     * Tipo de post para logs
     *
     * @since 2.0.0
     * @var string
     */
    protected $post_type = 'globalapi_log';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Obtener base para las rutas REST
     *
     * @since 2.0.0
     * @return string Base de las rutas
     */
    protected function get_rest_base() {
        return $this->rest_base;
    }

    /**
     * Registrar las rutas del controlador
     *
     * @since 2.0.0
     */
    public function register_routes() {
        // Ruta para listar logs
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => $this->get_collection_params()
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));

        // Ruta para log específico
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(
                    'context' => $this->get_context_param(array('default' => 'view'))
                )
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
                'args' => array(
                    'force' => array(
                        'type' => 'boolean',
                        'default' => true,
                        'description' => __('Si eliminar permanentemente.', 'globalapi')
                    )
                )
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));

        // Ruta para limpiar logs antiguos
        register_rest_route($this->namespace, '/' . $this->rest_base . '/limpiar', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'cleanup_logs'),
                'permission_callback' => array($this, 'cleanup_permissions_check'),
                'args' => array(
                    'dias' => array(
                        'type' => 'integer',
                        'default' => 30,
                        'minimum' => 1,
                        'maximum' => 365,
                        'description' => __('Días de antigüedad para eliminar logs.', 'globalapi')
                    ),
                    'tipo_evento' => array(
                        'type' => 'string',
                        'description' => __('Tipo específico de evento a limpiar.', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'severidad' => array(
                        'type' => 'string',
                        'enum' => array('debug', 'info', 'warning', 'error', 'critical'),
                        'description' => __('Severidad específica a limpiar.', 'globalapi')
                    )
                )
            )
        ));

        // Ruta para estadísticas
        register_rest_route($this->namespace, '/' . $this->rest_base . '/estadisticas', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_statistics'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => array(
                    'periodo' => array(
                        'type' => 'string',
                        'enum' => array('hoy', 'semana', 'mes', 'trimestre', 'año'),
                        'default' => 'semana',
                        'description' => __('Período para las estadísticas.', 'globalapi')
                    )
                )
            )
        ));

        // Ruta para exportar logs
        register_rest_route($this->namespace, '/' . $this->rest_base . '/exportar', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'export_logs'),
                'permission_callback' => array($this, 'export_permissions_check'),
                'args' => array(
                    'formato' => array(
                        'type' => 'string',
                        'enum' => array('json', 'csv', 'xml'),
                        'default' => 'json',
                        'description' => __('Formato de exportación.', 'globalapi')
                    ),
                    'fecha_inicio' => array(
                        'type' => 'string',
                        'format' => 'date',
                        'description' => __('Fecha de inicio (YYYY-MM-DD).', 'globalapi')
                    ),
                    'fecha_fin' => array(
                        'type' => 'string',
                        'format' => 'date',
                        'description' => __('Fecha de fin (YYYY-MM-DD).', 'globalapi')
                    ),
                    'limite' => array(
                        'type' => 'integer',
                        'default' => 1000,
                        'minimum' => 1,
                        'maximum' => 10000,
                        'description' => __('Límite de registros a exportar.', 'globalapi')
                    )
                )
            )
        ));

        // Ruta para tipos de eventos disponibles
        register_rest_route($this->namespace, '/' . $this->rest_base . '/tipos', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_event_types'),
                'permission_callback' => array($this, 'get_items_permissions_check')
            )
        ));
    }

    /**
     * Verificar permisos para obtener lista de logs
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function get_items_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Verificar permisos para obtener log específico
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function get_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Verificar permisos para eliminar log
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function delete_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'delete');
    }

    /**
     * Verificar permisos para limpiar logs
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function cleanup_permissions_check($request) {
        return $this->check_operation_permissions($request, 'delete');
    }

    /**
     * Verificar permisos para exportar logs
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function export_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Obtener lista de logs de auditoría
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function get_items($request) {
        // Verificar rate limiting
        $rate_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Parámetros de consulta
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 50,
            'paged' => $request->get_param('page') ?: 1,
            'orderby' => $request->get_param('orderby') ?: 'date',
            'order' => $request->get_param('order') ?: 'DESC'
        );

        // Filtros adicionales
        if ($search = $request->get_param('search')) {
            $args['s'] = sanitize_text_field($search);
        }

        // Filtro por tipo de evento
        if ($tipo_evento = $request->get_param('tipo_evento')) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'globalapi_tipo_evento',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($tipo_evento)
                )
            );
        }

        // Filtro por severidad
        if ($severidad = $request->get_param('severidad')) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array('relation' => 'AND');
            }
            $args['tax_query'][] = array(
                'taxonomy' => 'globalapi_severidad',
                'field' => 'slug',
                'terms' => sanitize_text_field($severidad)
            );
        }

        // Filtro por usuario
        if ($usuario_id = $request->get_param('usuario_id')) {
            $args['meta_query'] = array(
                array(
                    'key' => '_globalapi_usuario_id',
                    'value' => absint($usuario_id),
                    'compare' => '='
                )
            );
        }

        // Filtro por rango de fechas
        if ($fecha_inicio = $request->get_param('fecha_inicio')) {
            $args['date_query'] = array(
                'after' => $fecha_inicio,
                'inclusive' => true
            );
        }

        if ($fecha_fin = $request->get_param('fecha_fin')) {
            if (!isset($args['date_query'])) {
                $args['date_query'] = array();
            }
            $args['date_query']['before'] = $fecha_fin;
            $args['date_query']['inclusive'] = true;
        }

        // Filtro por IP
        if ($ip_cliente = $request->get_param('ip_cliente')) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = array();
            }
            $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][] = array(
                'key' => '_globalapi_ip_cliente',
                'value' => sanitize_text_field($ip_cliente),
                'compare' => '='
            );
        }

        // Ejecutar consulta
        $query = new WP_Query($args);
        $logs = array();

        foreach ($query->posts as $post) {
            $log_data = $this->prepare_item_for_response($post, $request);
            $logs[] = $this->prepare_response_for_collection($log_data);
        }

        // Preparar respuesta con paginación
        $response = $this->success_response($logs, __('Logs de auditoría obtenidos correctamente.', 'globalapi'));
        
        // Agregar headers de paginación
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Obtener log específico
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return $this->error_response(
                'rest_log_invalid_id',
                __('ID de log inválido.', 'globalapi'),
                null,
                404
            );
        }

        $log_data = $this->prepare_item_for_response($post, $request);
        return $this->success_response($log_data, __('Log obtenido correctamente.', 'globalapi'));
    }

    /**
     * Eliminar log
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return $this->error_response(
                'rest_log_invalid_id',
                __('ID de log inválido.', 'globalapi'),
                null,
                404
            );
        }

        $force = $request->get_param('force');
        $deleted = wp_delete_post($id, $force);

        if (!$deleted) {
            return $this->error_response(
                'rest_log_delete_failed',
                __('Error al eliminar el log.', 'globalapi'),
                null,
                500
            );
        }

        // Registrar la eliminación
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'log_deleted',
                sprintf(__('Log ID %d eliminado manualmente.', 'globalapi'), $id),
                array(
                    'log_id_eliminado' => $id,
                    'usuario_eliminador' => get_current_user_id(),
                    'forzado' => $force
                ),
                'warning'
            );
        }

        return $this->success_response(
            array('deleted' => true, 'id' => $id),
            __('Log eliminado correctamente.', 'globalapi')
        );
    }

    /**
     * Limpiar logs antiguos
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function cleanup_logs($request) {
        $dias = $request->get_param('dias');
        $tipo_evento = $request->get_param('tipo_evento');
        $severidad = $request->get_param('severidad');

        // Calcular fecha límite
        $fecha_limite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

        // Construir consulta
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'before' => $fecha_limite,
                    'inclusive' => false
                )
            ),
            'fields' => 'ids'
        );

        // Filtros adicionales
        if ($tipo_evento) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'globalapi_tipo_evento',
                    'field' => 'slug',
                    'terms' => $tipo_evento
                )
            );
        }

        if ($severidad) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array('relation' => 'AND');
            }
            $args['tax_query'][] = array(
                'taxonomy' => 'globalapi_severidad',
                'field' => 'slug',
                'terms' => $severidad
            );
        }

        // Obtener posts a eliminar
        $posts_to_delete = get_posts($args);
        $deleted_count = 0;

        // Eliminar posts
        foreach ($posts_to_delete as $post_id) {
            if (wp_delete_post($post_id, true)) {
                $deleted_count++;
            }
        }

        // Registrar la limpieza
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'logs_cleanup',
                sprintf(__('Limpieza masiva de logs: %d registros eliminados.', 'globalapi'), $deleted_count),
                array(
                    'logs_eliminados' => $deleted_count,
                    'criterios' => array(
                        'dias_antiguedad' => $dias,
                        'tipo_evento' => $tipo_evento,
                        'severidad' => $severidad
                    ),
                    'fecha_limite' => $fecha_limite
                ),
                'info'
            );
        }

        return $this->success_response(
            array(
                'deleted_count' => $deleted_count,
                'criteria' => array(
                    'dias' => $dias,
                    'tipo_evento' => $tipo_evento,
                    'severidad' => $severidad,
                    'fecha_limite' => $fecha_limite
                )
            ),
            sprintf(__('Limpieza completada: %d logs eliminados.', 'globalapi'), $deleted_count)
        );
    }

    /**
     * Obtener estadísticas de auditoría
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function get_statistics($request) {
        $periodo = $request->get_param('periodo');

        // Calcular fechas según período
        switch ($periodo) {
            case 'hoy':
                $fecha_inicio = date('Y-m-d 00:00:00');
                break;
            case 'semana':
                $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'mes':
                $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case 'trimestre':
                $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-90 days'));
                break;
            case 'año':
                $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-365 days'));
                break;
            default:
                $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-7 days'));
        }

        // Estadísticas básicas
        $stats = array(
            'periodo' => $periodo,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => current_time('mysql'),
            'total_logs' => 0,
            'por_severidad' => array(),
            'por_tipo_evento' => array(),
            'por_usuario' => array(),
            'por_ip' => array(),
            'tendencia_diaria' => array()
        );

        // Consulta base
        $base_args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $fecha_inicio,
                    'inclusive' => true
                )
            ),
            'fields' => 'ids'
        );

        // Total de logs
        $total_posts = get_posts($base_args);
        $stats['total_logs'] = count($total_posts);

        // Estadísticas por severidad
        $severidades = get_terms(array(
            'taxonomy' => 'globalapi_severidad',
            'hide_empty' => false
        ));

        foreach ($severidades as $severidad) {
            $args = $base_args;
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'globalapi_severidad',
                    'terms' => $severidad->term_id
                )
            );
            
            $count = count(get_posts($args));
            $stats['por_severidad'][$severidad->slug] = array(
                'nombre' => $severidad->name,
                'cantidad' => $count,
                'porcentaje' => $stats['total_logs'] > 0 ? round(($count / $stats['total_logs']) * 100, 2) : 0
            );
        }

        // Estadísticas por tipo de evento
        $tipos_evento = get_terms(array(
            'taxonomy' => 'globalapi_tipo_evento',
            'hide_empty' => false,
            'number' => 10 // Top 10
        ));

        foreach ($tipos_evento as $tipo) {
            $args = $base_args;
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'globalapi_tipo_evento',
                    'terms' => $tipo->term_id
                )
            );
            
            $count = count(get_posts($args));
            $stats['por_tipo_evento'][$tipo->slug] = array(
                'nombre' => $tipo->name,
                'cantidad' => $count,
                'porcentaje' => $stats['total_logs'] > 0 ? round(($count / $stats['total_logs']) * 100, 2) : 0
            );
        }

        // Estadísticas por usuario (top 10)
        global $wpdb;
        $user_stats = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as user_id, COUNT(*) as count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND p.post_date >= %s
            AND pm.meta_key = '_globalapi_usuario_id'
            GROUP BY pm.meta_value
            ORDER BY count DESC
            LIMIT 10
        ", $this->post_type, $fecha_inicio));

        foreach ($user_stats as $user_stat) {
            $user = get_user_by('ID', $user_stat->user_id);
            $username = $user ? $user->user_login : 'Usuario eliminado';
            
            $stats['por_usuario'][$user_stat->user_id] = array(
                'username' => $username,
                'cantidad' => (int) $user_stat->count,
                'porcentaje' => $stats['total_logs'] > 0 ? round(($user_stat->count / $stats['total_logs']) * 100, 2) : 0
            );
        }

        // Tendencia diaria
        $dias = array();
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $count_args = $base_args;
            $count_args['date_query'] = array(
                array(
                    'year' => date('Y', strtotime($fecha)),
                    'month' => date('m', strtotime($fecha)),
                    'day' => date('d', strtotime($fecha))
                )
            );
            
            $count = count(get_posts($count_args));
            $stats['tendencia_diaria'][$fecha] = $count;
        }

        return $this->success_response(
            $stats,
            __('Estadísticas de auditoría obtenidas correctamente.', 'globalapi')
        );
    }

    /**
     * Exportar logs
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function export_logs($request) {
        $formato = $request->get_param('formato');
        $fecha_inicio = $request->get_param('fecha_inicio');
        $fecha_fin = $request->get_param('fecha_fin');
        $limite = $request->get_param('limite');

        // Construir consulta
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limite,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Filtros de fecha
        if ($fecha_inicio || $fecha_fin) {
            $args['date_query'] = array();
            
            if ($fecha_inicio) {
                $args['date_query']['after'] = $fecha_inicio;
            }
            
            if ($fecha_fin) {
                $args['date_query']['before'] = $fecha_fin;
            }
            
            $args['date_query']['inclusive'] = true;
        }

        // Obtener logs
        $query = new WP_Query($args);
        $logs_data = array();

        foreach ($query->posts as $post) {
            $log_item = $this->prepare_item_for_response($post, $request);
            $logs_data[] = $log_item->get_data();
        }

        // Generar exportación según formato
        switch ($formato) {
            case 'csv':
                $export_data = $this->generate_csv_export($logs_data);
                $content_type = 'text/csv';
                $file_extension = 'csv';
                break;
            case 'xml':
                $export_data = $this->generate_xml_export($logs_data);
                $content_type = 'application/xml';
                $file_extension = 'xml';
                break;
            case 'json':
            default:
                $export_data = json_encode($logs_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $content_type = 'application/json';
                $file_extension = 'json';
                break;
        }

        // Registrar exportación
        if (class_exists('GlobalAPI_Log_Auditoria')) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'logs_export',
                sprintf(__('Exportación de logs en formato %s: %d registros.', 'globalapi'), $formato, count($logs_data)),
                array(
                    'formato' => $formato,
                    'registros_exportados' => count($logs_data),
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'limite' => $limite
                ),
                'info'
            );
        }

        // Preparar respuesta
        $filename = sprintf('auditoria_logs_%s.%s', date('Y-m-d_H-i-s'), $file_extension);
        
        $response = $this->success_response(
            array(
                'data' => $export_data,
                'metadata' => array(
                    'formato' => $formato,
                    'registros' => count($logs_data),
                    'fecha_exportacion' => current_time('mysql'),
                    'filename' => $filename
                )
            ),
            sprintf(__('Exportación completada: %d logs en formato %s.', 'globalapi'), count($logs_data), $formato)
        );

        $response->header('Content-Type', $content_type);
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Obtener tipos de eventos disponibles
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response
     */
    public function get_event_types($request) {
        // Obtener tipos de evento
        $tipos_evento = get_terms(array(
            'taxonomy' => 'globalapi_tipo_evento',
            'hide_empty' => false
        ));

        // Obtener severidades
        $severidades = get_terms(array(
            'taxonomy' => 'globalapi_severidad',
            'hide_empty' => false
        ));

        $tipos_data = array();
        foreach ($tipos_evento as $tipo) {
            $tipos_data[$tipo->slug] = array(
                'nombre' => $tipo->name,
                'descripcion' => $tipo->description,
                'cantidad' => $tipo->count
            );
        }

        $severidades_data = array();
        foreach ($severidades as $severidad) {
            $severidades_data[$severidad->slug] = array(
                'nombre' => $severidad->name,
                'descripcion' => $severidad->description,
                'cantidad' => $severidad->count
            );
        }

        return $this->success_response(
            array(
                'tipos_evento' => $tipos_data,
                'severidades' => $severidades_data
            ),
            __('Tipos de eventos obtenidos correctamente.', 'globalapi')
        );
    }

    /**
     * Preparar item para respuesta
     *
     * @since 2.0.0
     * @param WP_Post $post Post de log
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response
     */
    public function prepare_item_for_response($post, $request) {
        // Obtener taxonomías
        $tipos_evento = wp_get_post_terms($post->ID, 'globalapi_tipo_evento', array('fields' => 'names'));
        $severidades = wp_get_post_terms($post->ID, 'globalapi_severidad', array('fields' => 'names'));

        $data = array(
            'id' => $post->ID,
            'mensaje' => $post->post_title,
            'descripcion' => $post->post_content,
            'tipo_evento' => !empty($tipos_evento) ? $tipos_evento[0] : '',
            'severidad' => !empty($severidades) ? $severidades[0] : '',
            'fecha' => $post->post_date,
            'usuario_id' => get_post_meta($post->ID, '_globalapi_usuario_id', true),
            'usuario_login' => get_post_meta($post->ID, '_globalapi_usuario_login', true),
            'ip_cliente' => get_post_meta($post->ID, '_globalapi_ip_cliente', true),
            'user_agent' => get_post_meta($post->ID, '_globalapi_user_agent', true),
            'endpoint' => get_post_meta($post->ID, '_globalapi_endpoint', true),
            'metodo_http' => get_post_meta($post->ID, '_globalapi_metodo_http', true),
            'codigo_respuesta' => get_post_meta($post->ID, '_globalapi_codigo_respuesta', true),
            'tiempo_ejecucion' => get_post_meta($post->ID, '_globalapi_tiempo_ejecucion', true),
            'contexto_adicional' => get_post_meta($post->ID, '_globalapi_contexto', true)
        );

        // Decodificar contexto si es JSON
        if (is_string($data['contexto_adicional'])) {
            $decoded_context = json_decode($data['contexto_adicional'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['contexto_adicional'] = $decoded_context;
            }
        }

        $response = new WP_REST_Response($data);
        $response->add_links($this->prepare_links($post));

        return $response;
    }

    /**
     * Preparar links para la respuesta
     *
     * @since 2.0.0
     * @param WP_Post $post Post de log
     * @return array Links
     */
    protected function prepare_links($post) {
        $base = sprintf('%s/%s', $this->namespace, $this->rest_base);

        return array(
            'self' => array(
                'href' => rest_url(sprintf('/%s/%d', $base, $post->ID))
            ),
            'collection' => array(
                'href' => rest_url(sprintf('/%s', $base))
            )
        );
    }

    /**
     * Generar exportación CSV
     *
     * @since 2.0.0
     * @param array $logs_data Datos de logs
     * @return string CSV
     */
    protected function generate_csv_export($logs_data) {
        if (empty($logs_data)) {
            return '';
        }

        $csv_output = fopen('php://temp', 'r+');
        
        // Headers
        $headers = array_keys($logs_data[0]);
        fputcsv($csv_output, $headers);

        // Datos
        foreach ($logs_data as $log) {
            $row = array();
            foreach ($log as $value) {
                $row[] = is_array($value) ? json_encode($value) : $value;
            }
            fputcsv($csv_output, $row);
        }

        rewind($csv_output);
        $csv_content = stream_get_contents($csv_output);
        fclose($csv_output);

        return $csv_content;
    }

    /**
     * Generar exportación XML
     *
     * @since 2.0.0
     * @param array $logs_data Datos de logs
     * @return string XML
     */
    protected function generate_xml_export($logs_data) {
        $xml = new SimpleXMLElement('<auditoria_logs/>');
        $xml->addAttribute('timestamp', current_time('mysql'));
        $xml->addAttribute('total', count($logs_data));

        foreach ($logs_data as $log) {
            $log_node = $xml->addChild('log');
            
            foreach ($log as $key => $value) {
                if (is_array($value)) {
                    $log_node->addChild($key, htmlspecialchars(json_encode($value)));
                } else {
                    $log_node->addChild($key, htmlspecialchars($value));
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Obtener parámetros de colección
     *
     * @since 2.0.0
     * @return array Parámetros
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        
        $params['tipo_evento'] = array(
            'description' => __('Filtrar por tipo de evento.', 'globalapi'),
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        );
        
        $params['severidad'] = array(
            'description' => __('Filtrar por severidad.', 'globalapi'),
            'type' => 'string',
            'enum' => array('debug', 'info', 'warning', 'error', 'critical'),
            'sanitize_callback' => 'sanitize_text_field'
        );

        $params['usuario_id'] = array(
            'description' => __('Filtrar por ID de usuario.', 'globalapi'),
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        );

        $params['ip_cliente'] = array(
            'description' => __('Filtrar por IP del cliente.', 'globalapi'),
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        );

        $params['fecha_inicio'] = array(
            'description' => __('Fecha de inicio (YYYY-MM-DD).', 'globalapi'),
            'type' => 'string',
            'format' => 'date'
        );

        $params['fecha_fin'] = array(
            'description' => __('Fecha de fin (YYYY-MM-DD).', 'globalapi'),
            'type' => 'string',
            'format' => 'date'
        );

        return $params;
    }

    /**
     * Obtener esquema del item
     *
     * @since 2.0.0
     * @return array Esquema
     */
    public function get_item_schema() {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'log_auditoria',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('ID único del log.', 'globalapi'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                ),
                'mensaje' => array(
                    'description' => __('Mensaje del log.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                ),
                'tipo_evento' => array(
                    'description' => __('Tipo de evento.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                ),
                'severidad' => array(
                    'description' => __('Severidad del evento.', 'globalapi'),
                    'type' => 'string',
                    'enum' => array('debug', 'info', 'warning', 'error', 'critical'),
                    'context' => array('view', 'edit')
                ),
                'fecha' => array(
                    'description' => __('Fecha del evento.', 'globalapi'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                ),
                'usuario_id' => array(
                    'description' => __('ID del usuario que generó el evento.', 'globalapi'),
                    'type' => 'integer',
                    'context' => array('view', 'edit')
                ),
                'ip_cliente' => array(
                    'description' => __('IP del cliente.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                )
            )
        );

        return $schema;
    }
} 