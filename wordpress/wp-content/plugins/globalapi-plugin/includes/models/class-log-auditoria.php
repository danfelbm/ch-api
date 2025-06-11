<?php
/**
 * Modelo para gestión de logs de auditoría
 *
 * Define el Custom Post Type para almacenar y gestionar
 * registros de auditoría de todas las operaciones del sistema,
 * incluyendo accesos API, cambios de configuración y errores.
 *
 * @since 2.0.0
 * @package GlobalAPI
 * @subpackage Models
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase para gestionar logs de auditoría como Custom Post Type
 *
 * Maneja el registro automático de actividades del sistema,
 * categorización de eventos y consultas de logs para análisis
 * y troubleshooting.
 *
 * @since 2.0.0
 */
class LogAuditoria {

    /**
     * Tipo de post personalizado para logs de auditoría
     *
     * @since 2.0.0
     * @var string
     */
    const POST_TYPE = 'globalapi_log';

    /**
     * Taxonomía para tipos de eventos
     *
     * @since 2.0.0
     * @var string
     */
    const TAXONOMY_TIPO_EVENTO = 'globalapi_tipo_evento';

    /**
     * Taxonomía para nivel de severidad
     *
     * @since 2.0.0
     * @var string
     */
    const TAXONOMY_SEVERIDAD = 'globalapi_severidad';

    /**
     * Tipos de eventos disponibles
     *
     * @since 2.0.0
     * @var array
     */
    const TIPOS_EVENTO = [
        'login'          => 'Inicio de Sesión',
        'logout'         => 'Cierre de Sesión',
        'api_call'       => 'Llamada API',
        'credencial_update' => 'Actualización Credenciales',
        'config_change'  => 'Cambio Configuración',
        'error'          => 'Error del Sistema',
        'warning'        => 'Advertencia',
        'security'       => 'Evento de Seguridad',
        'sync'           => 'Sincronización',
        'export'         => 'Exportación de Datos',
        'import'         => 'Importación de Datos'
    ];

    /**
     * Niveles de severidad disponibles
     *
     * @since 2.0.0
     * @var array
     */
    const NIVELES_SEVERIDAD = [
        'info'     => 'Información',
        'debug'    => 'Debug',
        'warning'  => 'Advertencia',
        'error'    => 'Error',
        'critical' => 'Crítico',
        'alert'    => 'Alerta',
        'emergency' => 'Emergencia'
    ];

    /**
     * Estados de procesamiento del log
     *
     * @since 2.0.0
     * @var array
     */
    const ESTADOS = [
        'nuevo'      => 'Nuevo',
        'procesado'  => 'Procesado',
        'revisado'   => 'Revisado',
        'archivado'  => 'Archivado',
        'ignorado'   => 'Ignorado'
    ];

    /**
     * Constructor - registra hooks
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action( 'init', [ $this, 'registrar_post_type' ] );
        add_action( 'init', [ $this, 'registrar_taxonomias' ] );
        add_action( 'init', [ $this, 'registrar_meta_fields' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'personalizar_columnas_admin' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'mostrar_contenido_columnas' ], 10, 2 );
        add_filter( 'bulk_actions-edit-' . self::POST_TYPE, [ $this, 'agregar_acciones_masivas' ] );
        add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, [ $this, 'manejar_acciones_masivas' ], 10, 3 );
    }

    /**
     * Registra el Custom Post Type para logs de auditoría
     *
     * @since 2.0.0
     * @return void
     */
    public function registrar_post_type() {
        $etiquetas = [
            'name'                  => _x( 'Logs de Auditoría', 'Nombre general', 'globalapi' ),
            'singular_name'         => _x( 'Log de Auditoría', 'Nombre singular', 'globalapi' ),
            'menu_name'            => _x( 'Auditoría', 'Menú admin', 'globalapi' ),
            'name_admin_bar'       => _x( 'Log Auditoría', 'Barra admin', 'globalapi' ),
            'add_new'              => _x( 'Agregar Nuevo Log', 'log auditoría', 'globalapi' ),
            'add_new_item'         => __( 'Agregar Nuevo Log de Auditoría', 'globalapi' ),
            'new_item'             => __( 'Nuevo Log de Auditoría', 'globalapi' ),
            'edit_item'            => __( 'Editar Log de Auditoría', 'globalapi' ),
            'view_item'            => __( 'Ver Log de Auditoría', 'globalapi' ),
            'all_items'            => __( 'Todos los Logs', 'globalapi' ),
            'search_items'         => __( 'Buscar Logs', 'globalapi' ),
            'parent_item_colon'    => __( 'Log Padre:', 'globalapi' ),
            'not_found'            => __( 'No se encontraron logs.', 'globalapi' ),
            'not_found_in_trash'   => __( 'No se encontraron logs en la papelera.', 'globalapi' ),
            'archives'             => _x( 'Archivo de logs', 'globalapi' ),
            'insert_into_item'     => _x( 'Insertar en log', 'globalapi' ),
            'uploaded_to_this_item' => _x( 'Subido a este log', 'globalapi' ),
            'filter_items_list'    => _x( 'Filtrar lista de logs', 'globalapi' ),
            'items_list_navigation' => _x( 'Navegación de lista de logs', 'globalapi' ),
            'items_list'           => _x( 'Lista de logs', 'globalapi' ),
        ];

        $argumentos = [
            'labels'             => $etiquetas,
            'description'        => __( 'Registro de auditoría del sistema GlobalAPI', 'globalapi' ),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'globalapi-main',
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'capabilities'       => [
                'create_posts'       => 'manage_options',
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
            ],
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title', 'editor', 'custom-fields' ],
            'show_in_rest'       => false, // No exponer en REST API por seguridad
            'menu_icon'          => 'dashicons-visibility',
        ];

        register_post_type( self::POST_TYPE, $argumentos );
    }

    /**
     * Registra las taxonomías personalizadas
     *
     * @since 2.0.0
     * @return void
     */
    public function registrar_taxonomias() {
        // Taxonomía para tipo de evento
        $etiquetas_tipo = [
            'name'              => _x( 'Tipos de Evento', 'taxonomy general name', 'globalapi' ),
            'singular_name'     => _x( 'Tipo de Evento', 'taxonomy singular name', 'globalapi' ),
            'search_items'      => __( 'Buscar Tipos de Evento', 'globalapi' ),
            'all_items'         => __( 'Todos los Tipos de Evento', 'globalapi' ),
            'edit_item'         => __( 'Editar Tipo de Evento', 'globalapi' ),
            'update_item'       => __( 'Actualizar Tipo de Evento', 'globalapi' ),
            'add_new_item'      => __( 'Agregar Nuevo Tipo de Evento', 'globalapi' ),
            'new_item_name'     => __( 'Nuevo Nombre de Tipo de Evento', 'globalapi' ),
            'menu_name'         => __( 'Tipos de Evento', 'globalapi' ),
        ];

        register_taxonomy( self::TAXONOMY_TIPO_EVENTO, [ self::POST_TYPE ], [
            'hierarchical'      => false,
            'labels'            => $etiquetas_tipo,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => false,
            'capabilities'      => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
        ] );

        // Taxonomía para nivel de severidad
        $etiquetas_severidad = [
            'name'              => _x( 'Niveles de Severidad', 'taxonomy general name', 'globalapi' ),
            'singular_name'     => _x( 'Nivel de Severidad', 'taxonomy singular name', 'globalapi' ),
            'search_items'      => __( 'Buscar Niveles de Severidad', 'globalapi' ),
            'all_items'         => __( 'Todos los Niveles de Severidad', 'globalapi' ),
            'edit_item'         => __( 'Editar Nivel de Severidad', 'globalapi' ),
            'update_item'       => __( 'Actualizar Nivel de Severidad', 'globalapi' ),
            'add_new_item'      => __( 'Agregar Nuevo Nivel de Severidad', 'globalapi' ),
            'new_item_name'     => __( 'Nuevo Nombre de Nivel de Severidad', 'globalapi' ),
            'menu_name'         => __( 'Niveles de Severidad', 'globalapi' ),
        ];

        register_taxonomy( self::TAXONOMY_SEVERIDAD, [ self::POST_TYPE ], [
            'hierarchical'      => true,
            'labels'            => $etiquetas_severidad,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => false,
            'capabilities'      => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
        ] );
    }

    /**
     * Registra los meta fields personalizados
     *
     * @since 2.0.0
     * @return void
     */
    public function registrar_meta_fields() {
        // Meta fields para información del evento
        register_post_meta( self::POST_TYPE, '_globalapi_timestamp', [
            'type'              => 'string',
            'description'       => 'Timestamp exacto del evento',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_usuario_id', [
            'type'              => 'integer',
            'description'       => 'ID del usuario que generó el evento',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_ip_address', [
            'type'              => 'string',
            'description'       => 'Dirección IP del cliente',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_user_agent', [
            'type'              => 'string',
            'description'       => 'User Agent del cliente',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para detalles del evento
        register_post_meta( self::POST_TYPE, '_globalapi_endpoint', [
            'type'              => 'string',
            'description'       => 'Endpoint API accedido',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_metodo_http', [
            'type'              => 'string',
            'description'       => 'Método HTTP utilizado (GET, POST, etc.)',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_parametros_request', [
            'type'              => 'string',
            'description'       => 'Parámetros de la request en JSON',
            'single'            => true,
            'sanitize_callback' => [ $this, 'sanitizar_json' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_response_code', [
            'type'              => 'integer',
            'description'       => 'Código de respuesta HTTP',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_tiempo_ejecucion', [
            'type'              => 'number',
            'description'       => 'Tiempo de ejecución en milisegundos',
            'single'            => true,
            'sanitize_callback' => [ __CLASS__, 'sanitizar_float' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para contexto adicional
        register_post_meta( self::POST_TYPE, '_globalapi_servicio_externo', [
            'type'              => 'string',
            'description'       => 'Servicio externo involucrado (Groundhogg, etc.)',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_datos_adicionales', [
            'type'              => 'string',
            'description'       => 'Datos adicionales del contexto en JSON',
            'single'            => true,
            'sanitize_callback' => [ $this, 'sanitizar_json' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_error_details', [
            'type'              => 'string',
            'description'       => 'Detalles del error si aplica',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_stack_trace', [
            'type'              => 'string',
            'description'       => 'Stack trace del error si aplica',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para procesamiento
        register_post_meta( self::POST_TYPE, '_globalapi_estado_procesamiento', [
            'type'              => 'string',
            'description'       => 'Estado de procesamiento del log',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_procesado_por', [
            'type'              => 'integer',
            'description'       => 'ID del usuario que procesó el log',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_fecha_procesamiento', [
            'type'              => 'string',
            'description'       => 'Fecha de procesamiento del log',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );
    }

    /**
     * Sanitiza y valida JSON
     *
     * @since 2.0.0
     * @param string $valor JSON a validar
     * @return string JSON válido o string vacío
     */
    public function sanitizar_json( $valor ) {
        if ( empty( $valor ) ) {
            return '';
        }

        $json_decodificado = json_decode( $valor, true );
        
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return wp_json_encode( $json_decodificado );
        }

        return '';
    }

    /**
     * Sanitiza valores float para meta fields
     *
     * @since 2.0.0
     * @param mixed $valor Valor a sanitizar
     * @return float Valor float sanitizado
     */
    public static function sanitizar_float( $valor ) {
        return floatval( $valor );
    }

    /**
     * Encola scripts y estilos del admin
     *
     * @since 2.0.0
     * @param string $hook_suffix Sufijo del hook actual
     * @return void
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        $pantallas_objetivo = [
            'edit.php',
            'post.php'
        ];

        if ( ! in_array( $hook_suffix, $pantallas_objetivo ) ) {
            return;
        }

        $pantalla = get_current_screen();
        if ( $pantalla->post_type !== self::POST_TYPE ) {
            return;
        }

        wp_enqueue_script(
            'globalapi-log-admin',
            plugin_dir_url( __FILE__ ) . '../../admin/js/log-admin.js',
            [ 'jquery' ],
            '2.0.0',
            true
        );

        wp_enqueue_style(
            'globalapi-log-admin',
            plugin_dir_url( __FILE__ ) . '../../admin/css/log-admin.css',
            [],
            '2.0.0'
        );

        // Localizar script con datos
        wp_localize_script( 'globalapi-log-admin', 'globalapi_log', [
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'globalapi_log_action' ),
            'tipos_evento'      => self::TIPOS_EVENTO,
            'niveles_severidad' => self::NIVELES_SEVERIDAD,
            'estados'           => self::ESTADOS,
        ] );
    }

    /**
     * Personaliza las columnas del listado en admin
     *
     * @since 2.0.0
     * @param array $columnas Columnas existentes
     * @return array Columnas modificadas
     */
    public function personalizar_columnas_admin( $columnas ) {
        // Remover columnas innecesarias
        unset( $columnas['date'] );

        // Agregar columnas personalizadas
        $nuevas_columnas = [
            'cb'           => $columnas['cb'],
            'title'        => $columnas['title'],
            'tipo_evento'  => __( 'Tipo de Evento', 'globalapi' ),
            'severidad'    => __( 'Severidad', 'globalapi' ),
            'usuario'      => __( 'Usuario', 'globalapi' ),
            'ip_address'   => __( 'IP Address', 'globalapi' ),
            'endpoint'     => __( 'Endpoint', 'globalapi' ),
            'response_code' => __( 'Código Resp.', 'globalapi' ),
            'timestamp'    => __( 'Timestamp', 'globalapi' ),
            'estado'       => __( 'Estado', 'globalapi' ),
        ];

        return $nuevas_columnas;
    }

    /**
     * Muestra el contenido de las columnas personalizadas
     *
     * @since 2.0.0
     * @param string $columna Nombre de la columna
     * @param int $post_id ID del post
     * @return void
     */
    public function mostrar_contenido_columnas( $columna, $post_id ) {
        switch ( $columna ) {
            case 'tipo_evento':
                $terminos = wp_get_post_terms( $post_id, self::TAXONOMY_TIPO_EVENTO );
                if ( ! empty( $terminos ) && ! is_wp_error( $terminos ) ) {
                    $nombres = wp_list_pluck( $terminos, 'name' );
                    echo esc_html( implode( ', ', $nombres ) );
                } else {
                    echo '<span class="description">' . __( 'Sin categorizar', 'globalapi' ) . '</span>';
                }
                break;

            case 'severidad':
                $terminos = wp_get_post_terms( $post_id, self::TAXONOMY_SEVERIDAD );
                if ( ! empty( $terminos ) && ! is_wp_error( $terminos ) ) {
                    $severidad = $terminos[0]->name;
                    $clase_severidad = 'globalapi-severidad-' . sanitize_html_class( $terminos[0]->slug );
                    echo '<span class="' . esc_attr( $clase_severidad ) . '">' . esc_html( $severidad ) . '</span>';
                } else {
                    echo '<span class="description">' . __( 'No definida', 'globalapi' ) . '</span>';
                }
                break;

            case 'usuario':
                $usuario_id = get_post_meta( $post_id, '_globalapi_usuario_id', true );
                if ( $usuario_id ) {
                    $usuario = get_user_by( 'id', $usuario_id );
                    if ( $usuario ) {
                        echo esc_html( $usuario->display_name );
                    } else {
                        echo '<span class="description">' . __( 'Usuario eliminado', 'globalapi' ) . '</span>';
                    }
                } else {
                    echo '<span class="description">' . __( 'Sistema', 'globalapi' ) . '</span>';
                }
                break;

            case 'ip_address':
                $ip = get_post_meta( $post_id, '_globalapi_ip_address', true );
                echo esc_html( $ip ?: __( 'No disponible', 'globalapi' ) );
                break;

            case 'endpoint':
                $endpoint = get_post_meta( $post_id, '_globalapi_endpoint', true );
                $metodo = get_post_meta( $post_id, '_globalapi_metodo_http', true );
                if ( $endpoint ) {
                    echo '<code>' . esc_html( $metodo ) . ' ' . esc_html( $endpoint ) . '</code>';
                } else {
                    echo '<span class="description">' . __( 'No aplicable', 'globalapi' ) . '</span>';
                }
                break;

            case 'response_code':
                $code = get_post_meta( $post_id, '_globalapi_response_code', true );
                if ( $code ) {
                    $clase_code = $code >= 400 ? 'error' : ( $code >= 300 ? 'warning' : 'success' );
                    echo '<span class="globalapi-response-' . esc_attr( $clase_code ) . '">' . esc_html( $code ) . '</span>';
                } else {
                    echo '<span class="description">-</span>';
                }
                break;

            case 'timestamp':
                $timestamp = get_post_meta( $post_id, '_globalapi_timestamp', true );
                if ( $timestamp ) {
                    $fecha = date_i18n( 'Y-m-d H:i:s', strtotime( $timestamp ) );
                    echo '<time datetime="' . esc_attr( $timestamp ) . '">' . esc_html( $fecha ) . '</time>';
                } else {
                    echo esc_html( get_the_date( 'Y-m-d H:i:s', $post_id ) );
                }
                break;

            case 'estado':
                $estado = get_post_meta( $post_id, '_globalapi_estado_procesamiento', true );
                $nombre_estado = isset( self::ESTADOS[ $estado ] ) ? self::ESTADOS[ $estado ] : $estado;
                $clase_estado = 'globalapi-estado-' . sanitize_html_class( $estado );
                echo '<span class="' . esc_attr( $clase_estado ) . '">' . esc_html( $nombre_estado ?: 'Nuevo' ) . '</span>';
                break;
        }
    }

    /**
     * Agrega acciones masivas personalizadas
     *
     * @since 2.0.0
     * @param array $acciones Acciones existentes
     * @return array Acciones modificadas
     */
    public function agregar_acciones_masivas( $acciones ) {
        $acciones['marcar_procesado'] = __( 'Marcar como Procesado', 'globalapi' );
        $acciones['marcar_revisado'] = __( 'Marcar como Revisado', 'globalapi' );
        $acciones['archivar'] = __( 'Archivar', 'globalapi' );
        
        return $acciones;
    }

    /**
     * Maneja las acciones masivas personalizadas
     *
     * @since 2.0.0
     * @param string $sendback URL de retorno
     * @param string $doaction Acción seleccionada
     * @param array $post_ids IDs de posts seleccionados
     * @return string URL de retorno modificada
     */
    public function manejar_acciones_masivas( $sendback, $doaction, $post_ids ) {
        if ( ! in_array( $doaction, [ 'marcar_procesado', 'marcar_revisado', 'archivar' ] ) ) {
            return $sendback;
        }

        $estado_map = [
            'marcar_procesado' => 'procesado',
            'marcar_revisado'  => 'revisado',
            'archivar'         => 'archivado'
        ];

        $nuevo_estado = $estado_map[ $doaction ];
        $contador = 0;

        foreach ( $post_ids as $post_id ) {
            if ( get_post_type( $post_id ) === self::POST_TYPE ) {
                update_post_meta( $post_id, '_globalapi_estado_procesamiento', $nuevo_estado );
                update_post_meta( $post_id, '_globalapi_procesado_por', get_current_user_id() );
                update_post_meta( $post_id, '_globalapi_fecha_procesamiento', current_time( 'mysql' ) );
                $contador++;
            }
        }

        $sendback = add_query_arg( [
            'bulk_logs_updated' => $contador,
            'bulk_action' => $doaction
        ], $sendback );

        return $sendback;
    }

    /**
     * Registra un nuevo log de auditoría
     *
     * @since 2.0.0
     * @param array $args Argumentos del log
     * @return int|WP_Error ID del post creado o error
     */
    public static function registrar_log( $args ) {
        $defaults = [
            'titulo'               => '',
            'descripcion'          => '',
            'tipo_evento'          => 'info',
            'severidad'            => 'info',
            'usuario_id'           => get_current_user_id(),
            'ip_address'           => self::obtener_ip_cliente(),
            'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'endpoint'             => '',
            'metodo_http'          => isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '',
            'parametros_request'   => [],
            'response_code'        => 200,
            'tiempo_ejecucion'     => 0,
            'servicio_externo'     => '',
            'datos_adicionales'    => [],
            'error_details'        => '',
            'stack_trace'          => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        // Crear el post
        $post_data = [
            'post_title'   => $args['titulo'] ?: 'Log - ' . date( 'Y-m-d H:i:s' ),
            'post_content' => $args['descripcion'],
            'post_status'  => 'publish',
            'post_type'    => self::POST_TYPE,
            'post_author'  => 1, // Usuario del sistema
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Asignar taxonomías
        wp_set_object_terms( $post_id, $args['tipo_evento'], self::TAXONOMY_TIPO_EVENTO );
        wp_set_object_terms( $post_id, $args['severidad'], self::TAXONOMY_SEVERIDAD );

        // Guardar meta fields
        $meta_fields = [
            '_globalapi_timestamp'           => current_time( 'mysql' ),
            '_globalapi_usuario_id'          => $args['usuario_id'],
            '_globalapi_ip_address'          => $args['ip_address'],
            '_globalapi_user_agent'          => sanitize_text_field( $args['user_agent'] ),
            '_globalapi_endpoint'            => sanitize_text_field( $args['endpoint'] ),
            '_globalapi_metodo_http'         => sanitize_text_field( $args['metodo_http'] ),
            '_globalapi_parametros_request'  => wp_json_encode( $args['parametros_request'] ),
            '_globalapi_response_code'       => absint( $args['response_code'] ),
            '_globalapi_tiempo_ejecucion'    => floatval( $args['tiempo_ejecucion'] ),
            '_globalapi_servicio_externo'    => sanitize_text_field( $args['servicio_externo'] ),
            '_globalapi_datos_adicionales'   => wp_json_encode( $args['datos_adicionales'] ),
            '_globalapi_error_details'       => sanitize_textarea_field( $args['error_details'] ),
            '_globalapi_stack_trace'         => sanitize_textarea_field( $args['stack_trace'] ),
            '_globalapi_estado_procesamiento' => 'nuevo',
        ];

        foreach ( $meta_fields as $meta_key => $meta_value ) {
            update_post_meta( $post_id, $meta_key, $meta_value );
        }

        return $post_id;
    }

    /**
     * Obtiene la dirección IP del cliente
     *
     * @since 2.0.0
     * @return string Dirección IP
     */
    private static function obtener_ip_cliente() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /**
     * Obtiene logs filtrados por criterios
     *
     * @since 2.0.0
     * @param array $filtros Filtros de búsqueda
     * @return array Lista de logs
     */
    public static function obtener_logs( $filtros = [] ) {
        $defaults = [
            'limite'         => 50,
            'offset'         => 0,
            'tipo_evento'    => '',
            'severidad'      => '',
            'usuario_id'     => 0,
            'fecha_desde'    => '',
            'fecha_hasta'    => '',
            'endpoint'       => '',
            'response_code'  => 0,
            'estado'         => '',
            'order'          => 'DESC',
            'orderby'        => 'date'
        ];

        $filtros = wp_parse_args( $filtros, $defaults );

        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $filtros['limite'],
            'offset'         => $filtros['offset'],
            'order'          => $filtros['order'],
            'orderby'        => $filtros['orderby'],
            'meta_query'     => [],
            'tax_query'      => [],
        ];

        // Aplicar filtros de taxonomía
        if ( $filtros['tipo_evento'] ) {
            $args['tax_query'][] = [
                'taxonomy' => self::TAXONOMY_TIPO_EVENTO,
                'field'    => 'slug',
                'terms'    => $filtros['tipo_evento']
            ];
        }

        if ( $filtros['severidad'] ) {
            $args['tax_query'][] = [
                'taxonomy' => self::TAXONOMY_SEVERIDAD,
                'field'    => 'slug',
                'terms'    => $filtros['severidad']
            ];
        }

        // Aplicar filtros de meta
        if ( $filtros['usuario_id'] ) {
            $args['meta_query'][] = [
                'key'     => '_globalapi_usuario_id',
                'value'   => $filtros['usuario_id'],
                'compare' => '='
            ];
        }

        if ( $filtros['endpoint'] ) {
            $args['meta_query'][] = [
                'key'     => '_globalapi_endpoint',
                'value'   => $filtros['endpoint'],
                'compare' => 'LIKE'
            ];
        }

        if ( $filtros['response_code'] ) {
            $args['meta_query'][] = [
                'key'     => '_globalapi_response_code',
                'value'   => $filtros['response_code'],
                'compare' => '='
            ];
        }

        if ( $filtros['estado'] ) {
            $args['meta_query'][] = [
                'key'     => '_globalapi_estado_procesamiento',
                'value'   => $filtros['estado'],
                'compare' => '='
            ];
        }

        // Filtros de fecha
        if ( $filtros['fecha_desde'] || $filtros['fecha_hasta'] ) {
            $date_query = [];
            
            if ( $filtros['fecha_desde'] ) {
                $date_query['after'] = $filtros['fecha_desde'];
            }
            
            if ( $filtros['fecha_hasta'] ) {
                $date_query['before'] = $filtros['fecha_hasta'];
            }
            
            $args['date_query'] = [ $date_query ];
        }

        $query = new WP_Query( $args );
        $logs = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $logs[] = self::obtener_log_completo( get_the_ID() );
            }
            wp_reset_postdata();
        }

        return $logs;
    }

    /**
     * Obtiene un log completo con todos los metadatos
     *
     * @since 2.0.0
     * @param int $log_id ID del log
     * @return array|false Datos del log o false si no existe
     */
    public static function obtener_log_completo( $log_id ) {
        $post = get_post( $log_id );
        
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        $tipo_evento = wp_get_post_terms( $post->ID, self::TAXONOMY_TIPO_EVENTO );
        $severidad = wp_get_post_terms( $post->ID, self::TAXONOMY_SEVERIDAD );

        return [
            'id'                    => $post->ID,
            'titulo'                => $post->post_title,
            'descripcion'           => $post->post_content,
            'tipo_evento'           => ! empty( $tipo_evento ) ? $tipo_evento[0]->slug : '',
            'severidad'             => ! empty( $severidad ) ? $severidad[0]->slug : '',
            'timestamp'             => get_post_meta( $post->ID, '_globalapi_timestamp', true ),
            'usuario_id'            => get_post_meta( $post->ID, '_globalapi_usuario_id', true ),
            'ip_address'            => get_post_meta( $post->ID, '_globalapi_ip_address', true ),
            'user_agent'            => get_post_meta( $post->ID, '_globalapi_user_agent', true ),
            'endpoint'              => get_post_meta( $post->ID, '_globalapi_endpoint', true ),
            'metodo_http'           => get_post_meta( $post->ID, '_globalapi_metodo_http', true ),
            'parametros_request'    => get_post_meta( $post->ID, '_globalapi_parametros_request', true ),
            'response_code'         => get_post_meta( $post->ID, '_globalapi_response_code', true ),
            'tiempo_ejecucion'      => get_post_meta( $post->ID, '_globalapi_tiempo_ejecucion', true ),
            'servicio_externo'      => get_post_meta( $post->ID, '_globalapi_servicio_externo', true ),
            'datos_adicionales'     => get_post_meta( $post->ID, '_globalapi_datos_adicionales', true ),
            'error_details'         => get_post_meta( $post->ID, '_globalapi_error_details', true ),
            'stack_trace'           => get_post_meta( $post->ID, '_globalapi_stack_trace', true ),
            'estado_procesamiento'  => get_post_meta( $post->ID, '_globalapi_estado_procesamiento', true ),
            'procesado_por'         => get_post_meta( $post->ID, '_globalapi_procesado_por', true ),
            'fecha_procesamiento'   => get_post_meta( $post->ID, '_globalapi_fecha_procesamiento', true ),
            'fecha_creacion'        => $post->post_date,
        ];
    }

    /**
     * Limpia logs antiguos según configuración
     *
     * @since 2.0.0
     * @param int $dias_antiguedad Días de antigüedad para limpiar
     * @return int Número de logs eliminados
     */
    public static function limpiar_logs_antiguos( $dias_antiguedad = 90 ) {
        $fecha_limite = date( 'Y-m-d', strtotime( "-{$dias_antiguedad} days" ) );

        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'date_query'     => [
                [
                    'before' => $fecha_limite,
                ]
            ],
            'meta_query'     => [
                [
                    'key'     => '_globalapi_estado_procesamiento',
                    'value'   => [ 'procesado', 'revisado', 'archivado' ],
                    'compare' => 'IN'
                ]
            ]
        ];

        $query = new WP_Query( $args );
        $eliminados = 0;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                if ( wp_delete_post( get_the_ID(), true ) ) {
                    $eliminados++;
                }
            }
            wp_reset_postdata();
        }

        return $eliminados;
    }
}

// Inicializar la clase
new LogAuditoria();