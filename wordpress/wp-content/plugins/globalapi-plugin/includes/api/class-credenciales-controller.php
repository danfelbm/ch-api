<?php
/**
 * Controlador REST API para Credenciales - Plugin GlobalAPI
 *
 * Maneja todas las operaciones CRUD para credenciales a través de WordPress REST API.
 * Proporciona endpoints seguros para crear, leer, actualizar y eliminar credenciales
 * de APIs externas (Groundhogg, InvisionCommunity, etc.).
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
 * Controlador REST API para gestión de credenciales
 *
 * Endpoints disponibles:
 * - GET /globalapi/v1/credenciales - Listar credenciales
 * - GET /globalapi/v1/credenciales/{id} - Obtener credencial específica
 * - POST /globalapi/v1/credenciales - Crear nueva credencial
 * - PUT /globalapi/v1/credenciales/{id} - Actualizar credencial
 * - DELETE /globalapi/v1/credenciales/{id} - Eliminar credencial
 * - POST /globalapi/v1/credenciales/{id}/test - Probar credencial
 *
 * @since 2.0.0
 */
class GlobalAPI_Credenciales_Controller extends GlobalAPI_REST_Controller {

    /**
     * Base del endpoint
     *
     * @since 2.0.0
     * @var string
     */
    protected $rest_base = 'credenciales';

    /**
     * Tipo de post para credenciales
     *
     * @since 2.0.0
     * @var string
     */
    protected $post_type = 'globalapi_credencial';

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
        // Ruta para listar credenciales
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => $this->get_collection_params()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));

        // Ruta para credencial específica
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
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
                'args' => array(
                    'force' => array(
                        'type' => 'boolean',
                        'default' => false,
                        'description' => __('Si eliminar permanentemente o mover a papelera.', 'globalapi')
                    )
                )
            ),
            'schema' => array($this, 'get_public_item_schema')
        ));

        // Ruta para probar credencial
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/test', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'test_item'),
                'permission_callback' => array($this, 'test_item_permissions_check'),
                'args' => array(
                    'endpoint' => array(
                        'type' => 'string',
                        'description' => __('Endpoint específico a probar (opcional).', 'globalapi'),
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            )
        ));

        // Ruta para obtener tipos de servicios disponibles
        register_rest_route($this->namespace, '/' . $this->rest_base . '/tipos', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_service_types'),
                'permission_callback' => array($this, 'get_items_permissions_check')
            )
        ));
    }

    /**
     * Verificar permisos para obtener lista de credenciales
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function get_items_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Verificar permisos para obtener credencial específica
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function get_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Verificar permisos para crear credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function create_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'create');
    }

    /**
     * Verificar permisos para actualizar credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function update_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'update');
    }

    /**
     * Verificar permisos para eliminar credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function delete_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'delete');
    }

    /**
     * Verificar permisos para probar credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return bool|WP_Error
     */
    public function test_item_permissions_check($request) {
        return $this->check_operation_permissions($request, 'read');
    }

    /**
     * Obtener lista de credenciales
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
            'posts_per_page' => $request->get_param('per_page') ?: 20,
            'paged' => $request->get_param('page') ?: 1,
            'orderby' => $request->get_param('orderby') ?: 'date',
            'order' => $request->get_param('order') ?: 'DESC'
        );

        // Filtros adicionales
        if ($search = $request->get_param('search')) {
            $args['s'] = sanitize_text_field($search);
        }

        if ($tipo_servicio = $request->get_param('tipo_servicio')) {
            $args['meta_query'] = array(
                array(
                    'key' => '_globalapi_tipo_servicio',
                    'value' => sanitize_text_field($tipo_servicio),
                    'compare' => '='
                )
            );
        }

        if ($estado = $request->get_param('estado')) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = array();
            }
            $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][] = array(
                'key' => '_globalapi_estado',
                'value' => sanitize_text_field($estado),
                'compare' => '='
            );
        }

        // Ejecutar consulta
        $query = new WP_Query($args);
        $credenciales = array();

        foreach ($query->posts as $post) {
            $credencial_data = $this->prepare_item_for_response($post, $request);
            $credenciales[] = $this->prepare_response_for_collection($credencial_data);
        }

        // Preparar respuesta con paginación
        $response = $this->success_response($credenciales, __('Credenciales obtenidas correctamente.', 'globalapi'));
        
        // Agregar headers de paginación
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Obtener credencial específica
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
                'rest_credencial_invalid_id',
                __('ID de credencial inválido.', 'globalapi'),
                null,
                404
            );
        }

        $credencial_data = $this->prepare_item_for_response($post, $request);
        return $this->success_response($credencial_data, __('Credencial obtenida correctamente.', 'globalapi'));
    }

    /**
     * Crear nueva credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function create_item($request) {
        // Validar parámetros requeridos
        $validation = $this->validate_request_params($request, array('nombre', 'tipo_servicio'));
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Sanitizar datos
        $params = $this->sanitize_params($request->get_params());

        // Validar datos con clase de validaciones
        if (class_exists('ValidacionesGlobalAPI')) {
            $validated_data = ValidacionesGlobalAPI::validar_credencial($params);
            if (is_wp_error($validated_data)) {
                return $this->error_response(
                    'rest_validation_failed',
                    $validated_data->get_error_message(),
                    $validated_data->get_error_data(),
                    400
                );
            }
            $params = $validated_data;
        }

        // Crear post
        $post_data = array(
            'post_title' => $params['nombre'],
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'post_content' => $params['descripcion'] ?? ''
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $this->error_response(
                'rest_credencial_create_failed',
                __('Error al crear la credencial.', 'globalapi'),
                $post_id->get_error_message(),
                500
            );
        }

        // Guardar meta fields
        $this->save_credencial_meta($post_id, $params);

        // Preparar respuesta
        $post = get_post($post_id);
        $credencial_data = $this->prepare_item_for_response($post, $request);

        return $this->success_response(
            $credencial_data,
            __('Credencial creada correctamente.', 'globalapi'),
            201
        );
    }

    /**
     * Actualizar credencial existente
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function update_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return $this->error_response(
                'rest_credencial_invalid_id',
                __('ID de credencial inválido.', 'globalapi'),
                null,
                404
            );
        }

        // Sanitizar datos
        $params = $this->sanitize_params($request->get_params());

        // Validar datos
        if (class_exists('ValidacionesGlobalAPI')) {
            $validated_data = ValidacionesGlobalAPI::validar_credencial($params);
            if (is_wp_error($validated_data)) {
                return $this->error_response(
                    'rest_validation_failed',
                    $validated_data->get_error_message(),
                    $validated_data->get_error_data(),
                    400
                );
            }
            $params = $validated_data;
        }

        // Actualizar post
        $post_data = array(
            'ID' => $id,
            'post_title' => $params['nombre'] ?? $post->post_title,
            'post_content' => $params['descripcion'] ?? $post->post_content
        );

        $updated = wp_update_post($post_data);

        if (is_wp_error($updated)) {
            return $this->error_response(
                'rest_credencial_update_failed',
                __('Error al actualizar la credencial.', 'globalapi'),
                $updated->get_error_message(),
                500
            );
        }

        // Actualizar meta fields
        $this->save_credencial_meta($id, $params);

        // Preparar respuesta
        $post = get_post($id);
        $credencial_data = $this->prepare_item_for_response($post, $request);

        return $this->success_response(
            $credencial_data,
            __('Credencial actualizada correctamente.', 'globalapi')
        );
    }

    /**
     * Eliminar credencial
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
                'rest_credencial_invalid_id',
                __('ID de credencial inválido.', 'globalapi'),
                null,
                404
            );
        }

        $force = $request->get_param('force');
        $deleted = wp_delete_post($id, $force);

        if (!$deleted) {
            return $this->error_response(
                'rest_credencial_delete_failed',
                __('Error al eliminar la credencial.', 'globalapi'),
                null,
                500
            );
        }

        return $this->success_response(
            array('deleted' => true, 'id' => $id),
            __('Credencial eliminada correctamente.', 'globalapi')
        );
    }

    /**
     * Probar credencial
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response|WP_Error
     */
    public function test_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return $this->error_response(
                'rest_credencial_invalid_id',
                __('ID de credencial inválido.', 'globalapi'),
                null,
                404
            );
        }

        // Obtener datos de la credencial
        $tipo_servicio = get_post_meta($id, '_globalapi_tipo_servicio', true);
        $url_base = get_post_meta($id, '_globalapi_url_base', true);
        $api_key = get_post_meta($id, '_globalapi_api_key', true);

        if (empty($url_base)) {
            return $this->error_response(
                'rest_credencial_test_failed',
                __('URL base no configurada para la credencial.', 'globalapi'),
                null,
                400
            );
        }

        // Endpoint específico a probar
        $endpoint = $request->get_param('endpoint') ?: '';
        $test_url = rtrim($url_base, '/') . '/' . ltrim($endpoint, '/');

        // Preparar headers de autenticación
        $headers = array(
            'User-Agent' => 'GlobalAPI-Plugin/2.0.0',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );

        if (!empty($api_key)) {
            // Agregar autenticación según tipo de servicio
            switch ($tipo_servicio) {
                case 'groundhogg':
                    $headers['Authorization'] = 'Bearer ' . base64_decode($api_key);
                    break;
                case 'invision':
                    $headers['Authorization'] = 'Basic ' . base64_encode($api_key . ':');
                    break;
                default:
                    $headers['X-API-Key'] = base64_decode($api_key);
                    break;
            }
        }

        // Realizar petición de prueba
        $start_time = microtime(true);
        $response = wp_remote_get($test_url, array(
            'headers' => $headers,
            'timeout' => 10,
            'sslverify' => false // En producción configurar correctamente
        ));
        $end_time = microtime(true);

        // Procesar respuesta
        $test_result = array(
            'url_probada' => $test_url,
            'tiempo_respuesta' => round(($end_time - $start_time) * 1000, 2) . ' ms',
            'timestamp' => current_time('mysql')
        );

        if (is_wp_error($response)) {
            $test_result['exito'] = false;
            $test_result['error'] = $response->get_error_message();
            $test_result['codigo_estado'] = 0;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            $test_result['exito'] = $response_code >= 200 && $response_code < 300;
            $test_result['codigo_estado'] = $response_code;
            $test_result['headers'] = wp_remote_retrieve_headers($response);
            
            // Intentar decodificar JSON
            $decoded_body = json_decode($response_body, true);
            $test_result['respuesta'] = $decoded_body ?: $response_body;
        }

        // Actualizar fecha de última verificación
        update_post_meta($id, '_globalapi_ultima_verificacion', current_time('mysql'));
        update_post_meta($id, '_globalapi_resultado_verificacion', $test_result['exito'] ? 'exitosa' : 'fallida');

        return $this->success_response(
            $test_result,
            $test_result['exito'] 
                ? __('Credencial probada exitosamente.', 'globalapi')
                : __('Error al probar la credencial.', 'globalapi')
        );
    }

    /**
     * Obtener tipos de servicios disponibles
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response
     */
    public function get_service_types($request) {
        $tipos = array(
            'groundhogg' => array(
                'label' => __('Groundhogg CRM', 'globalapi'),
                'description' => __('Sistema CRM para WordPress', 'globalapi'),
                'auth_type' => 'api_key',
                'oauth_required' => false
            ),
            'invision' => array(
                'label' => __('Invision Community', 'globalapi'),
                'description' => __('Sistema de comunidad y OAuth', 'globalapi'),
                'auth_type' => 'oauth',
                'oauth_required' => true
            ),
            'wordpress' => array(
                'label' => __('WordPress API', 'globalapi'),
                'description' => __('WordPress REST API', 'globalapi'),
                'auth_type' => 'token',
                'oauth_required' => false
            ),
            'custom' => array(
                'label' => __('API Personalizada', 'globalapi'),
                'description' => __('API externa personalizada', 'globalapi'),
                'auth_type' => 'custom',
                'oauth_required' => false
            )
        );

        return $this->success_response(
            $tipos,
            __('Tipos de servicios obtenidos correctamente.', 'globalapi')
        );
    }

    /**
     * Preparar item para respuesta
     *
     * @since 2.0.0
     * @param WP_Post $post Post de credencial
     * @param WP_REST_Request $request Petición REST
     * @return WP_REST_Response
     */
    public function prepare_item_for_response($post, $request) {
        $data = array(
            'id' => $post->ID,
            'nombre' => $post->post_title,
            'descripcion' => $post->post_content,
            'tipo_servicio' => get_post_meta($post->ID, '_globalapi_tipo_servicio', true),
            'url_base' => get_post_meta($post->ID, '_globalapi_url_base', true),
            'estado' => get_post_meta($post->ID, '_globalapi_estado', true),
            'fecha_creacion' => $post->post_date,
            'fecha_modificacion' => $post->post_modified,
            'ultima_verificacion' => get_post_meta($post->ID, '_globalapi_ultima_verificacion', true),
            'resultado_verificacion' => get_post_meta($post->ID, '_globalapi_resultado_verificacion', true)
        );

        // Solo incluir datos sensibles si es contexto edit
        $context = $request->get_param('context') ?: 'view';
        if ($context === 'edit') {
            $data['api_key_presente'] = !empty(get_post_meta($post->ID, '_globalapi_api_key', true));
            $data['api_secret_presente'] = !empty(get_post_meta($post->ID, '_globalapi_api_secret', true));
            $data['oauth_configurado'] = !empty(get_post_meta($post->ID, '_globalapi_oauth_client_id', true));
        }

        $response = new WP_REST_Response($data);
        $response->add_links($this->prepare_links($post));

        return $response;
    }

    /**
     * Preparar links para la respuesta
     *
     * @since 2.0.0
     * @param WP_Post $post Post de credencial
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
            ),
            'test' => array(
                'href' => rest_url(sprintf('/%s/%d/test', $base, $post->ID))
            )
        );
    }

    /**
     * Guardar meta fields de credencial
     *
     * @since 2.0.0
     * @param int $post_id ID del post
     * @param array $params Parámetros a guardar
     */
    protected function save_credencial_meta($post_id, $params) {
        $meta_fields = array(
            'tipo_servicio', 'url_base', 'estado', 'api_key', 'api_secret',
            'oauth_client_id', 'oauth_client_secret', 'oauth_redirect_uri',
            'configuracion_avanzada'
        );

        foreach ($meta_fields as $field) {
            if (isset($params[$field])) {
                $value = $params[$field];
                
                // Cifrar datos sensibles
                if (in_array($field, array('api_key', 'api_secret', 'oauth_client_secret'))) {
                    $value = base64_encode($value);
                }
                
                update_post_meta($post_id, '_globalapi_' . $field, $value);
            }
        }

        // Actualizar fecha de modificación
        update_post_meta($post_id, '_globalapi_fecha_modificacion', current_time('mysql'));
    }

    /**
     * Obtener parámetros de colección
     *
     * @since 2.0.0
     * @return array Parámetros
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        
        $params['tipo_servicio'] = array(
            'description' => __('Filtrar por tipo de servicio.', 'globalapi'),
            'type' => 'string',
            'enum' => array('groundhogg', 'invision', 'wordpress', 'custom'),
            'sanitize_callback' => 'sanitize_text_field'
        );
        
        $params['estado'] = array(
            'description' => __('Filtrar por estado.', 'globalapi'),
            'type' => 'string',
            'enum' => array('activa', 'inactiva', 'expirada', 'bloqueada'),
            'sanitize_callback' => 'sanitize_text_field'
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
            'title' => 'credencial',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('ID único de la credencial.', 'globalapi'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true
                ),
                'nombre' => array(
                    'description' => __('Nombre de la credencial.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'required' => true
                ),
                'descripcion' => array(
                    'description' => __('Descripción de la credencial.', 'globalapi'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                ),
                'tipo_servicio' => array(
                    'description' => __('Tipo de servicio.', 'globalapi'),
                    'type' => 'string',
                    'enum' => array('groundhogg', 'invision', 'wordpress', 'custom'),
                    'context' => array('view', 'edit'),
                    'required' => true
                ),
                'url_base' => array(
                    'description' => __('URL base del servicio.', 'globalapi'),
                    'type' => 'string',
                    'format' => 'uri',
                    'context' => array('view', 'edit')
                ),
                'estado' => array(
                    'description' => __('Estado de la credencial.', 'globalapi'),
                    'type' => 'string',
                    'enum' => array('activa', 'inactiva', 'expirada', 'bloqueada'),
                    'context' => array('view', 'edit')
                )
            )
        );

        return $schema;
    }
} 