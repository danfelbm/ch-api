<?php
/**
 * Modelo para gestión de credenciales de APIs
 *
 * Define el Custom Post Type para almacenar y gestionar
 * credenciales de servicios externos como Groundhogg,
 * InvisionCommunity y otros sistemas integrados.
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
 * Clase para gestionar credenciales como Custom Post Type
 *
 * Maneja el registro, configuración y operaciones del CPT
 * para credenciales de APIs externas, incluyendo cifrado
 * y validación de datos sensibles.
 *
 * @since 2.0.0
 */
class Credencial {

    /**
     * Tipo de post personalizado para credenciales
     *
     * @since 2.0.0
     * @var string
     */
    const POST_TYPE = 'globalapi_credencial';

    /**
     * Taxonomía para tipos de servicio
     *
     * @since 2.0.0
     * @var string
     */
    const TAXONOMY_SERVICIO = 'globalapi_tipo_servicio';

    /**
     * Estados disponibles para credenciales
     *
     * @since 2.0.0
     * @var array
     */
    const ESTADOS = [
        'activa'     => 'Activa',
        'inactiva'   => 'Inactiva',
        'expirada'   => 'Expirada',
        'bloqueada'  => 'Bloqueada',
        'testing'    => 'En Pruebas'
    ];

    /**
     * Tipos de servicios soportados
     *
     * @since 2.0.0
     * @var array
     */
    const TIPOS_SERVICIO = [
        'groundhogg'         => 'Groundhogg CRM',
        'invision_community' => 'InvisionCommunity OAuth',
        'wordpress_api'      => 'WordPress REST API',
        'oauth_generico'     => 'OAuth Genérico',
        'api_rest'           => 'API REST Genérica'
    ];

    /**
     * Constructor - registra hooks
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action( 'init', [ $this, 'registrar_post_type' ] );
        add_action( 'init', [ $this, 'registrar_meta_fields' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'guardar_meta_fields' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'personalizar_columnas_admin' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'mostrar_contenido_columnas' ], 10, 2 );
    }

    /**
     * Registra el Custom Post Type para credenciales
     *
     * @since 2.0.0
     * @return void
     */
    public function registrar_post_type() {
        $etiquetas = [
            'name'                  => _x( 'Credenciales API', 'Nombre general', 'globalapi' ),
            'singular_name'         => _x( 'Credencial API', 'Nombre singular', 'globalapi' ),
            'menu_name'            => _x( 'Credenciales', 'Menú admin', 'globalapi' ),
            'name_admin_bar'       => _x( 'Credencial', 'Barra admin', 'globalapi' ),
            'add_new'              => _x( 'Agregar Nueva', 'credencial', 'globalapi' ),
            'add_new_item'         => __( 'Agregar Nueva Credencial', 'globalapi' ),
            'new_item'             => __( 'Nueva Credencial', 'globalapi' ),
            'edit_item'            => __( 'Editar Credencial', 'globalapi' ),
            'view_item'            => __( 'Ver Credencial', 'globalapi' ),
            'all_items'            => __( 'Todas las Credenciales', 'globalapi' ),
            'search_items'         => __( 'Buscar Credenciales', 'globalapi' ),
            'parent_item_colon'    => __( 'Credencial Padre:', 'globalapi' ),
            'not_found'            => __( 'No se encontraron credenciales.', 'globalapi' ),
            'not_found_in_trash'   => __( 'No se encontraron credenciales en la papelera.', 'globalapi' ),
            'featured_image'       => _x( 'Icono del Servicio', 'globalapi' ),
            'set_featured_image'   => _x( 'Establecer icono del servicio', 'globalapi' ),
            'remove_featured_image' => _x( 'Quitar icono del servicio', 'globalapi' ),
            'use_featured_image'   => _x( 'Usar como icono del servicio', 'globalapi' ),
            'archives'             => _x( 'Archivo de credenciales', 'globalapi' ),
            'insert_into_item'     => _x( 'Insertar en credencial', 'globalapi' ),
            'uploaded_to_this_item' => _x( 'Subido a esta credencial', 'globalapi' ),
            'filter_items_list'    => _x( 'Filtrar lista de credenciales', 'globalapi' ),
            'items_list_navigation' => _x( 'Navegación de lista de credenciales', 'globalapi' ),
            'items_list'           => _x( 'Lista de credenciales', 'globalapi' ),
        ];

        $argumentos = [
            'labels'             => $etiquetas,
            'description'        => __( 'Gestión de credenciales para APIs externas', 'globalapi' ),
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
            'supports'           => [ 'title', 'editor', 'author', 'custom-fields' ],
            'show_in_rest'       => false, // No exponer en REST API por seguridad
            'menu_icon'          => 'dashicons-admin-network',
        ];

        register_post_type( self::POST_TYPE, $argumentos );
    }

    /**
     * Registra los meta fields personalizados
     *
     * @since 2.0.0
     * @return void
     */
    public function registrar_meta_fields() {
        // Meta fields para configuración básica
        register_post_meta( self::POST_TYPE, '_globalapi_tipo_servicio', [
            'type'              => 'string',
            'description'       => 'Tipo de servicio (groundhogg, invision_community, etc.)',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false', // No exponer en REST API
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_estado', [
            'type'              => 'string',
            'description'       => 'Estado de la credencial (activa, inactiva, etc.)',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_url_base', [
            'type'              => 'string',
            'description'       => 'URL base del servicio',
            'single'            => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para credenciales (cifrados)
        register_post_meta( self::POST_TYPE, '_globalapi_api_key', [
            'type'              => 'string',
            'description'       => 'API Key (cifrada)',
            'single'            => true,
            'sanitize_callback' => [ $this, 'cifrar_valor' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_api_secret', [
            'type'              => 'string',
            'description'       => 'API Secret (cifrado)',
            'single'            => true,
            'sanitize_callback' => [ $this, 'cifrar_valor' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_token_acceso', [
            'type'              => 'string',
            'description'       => 'Token de acceso (cifrado)',
            'single'            => true,
            'sanitize_callback' => [ $this, 'cifrar_valor' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_refresh_token', [
            'type'              => 'string',
            'description'       => 'Refresh token (cifrado)',
            'single'            => true,
            'sanitize_callback' => [ $this, 'cifrar_valor' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para configuración OAuth
        register_post_meta( self::POST_TYPE, '_globalapi_client_id', [
            'type'              => 'string',
            'description'       => 'Client ID para OAuth',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_redirect_uri', [
            'type'              => 'string',
            'description'       => 'URI de redirección OAuth',
            'single'            => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_scopes', [
            'type'              => 'string',
            'description'       => 'Scopes OAuth separados por comas',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para auditoría
        register_post_meta( self::POST_TYPE, '_globalapi_fecha_creacion', [
            'type'              => 'string',
            'description'       => 'Fecha de creación de la credencial',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_ultima_verificacion', [
            'type'              => 'string',
            'description'       => 'Última vez que se verificó la credencial',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        register_post_meta( self::POST_TYPE, '_globalapi_fecha_expiracion', [
            'type'              => 'string',
            'description'       => 'Fecha de expiración del token',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );

        // Meta fields para configuración avanzada
        register_post_meta( self::POST_TYPE, '_globalapi_configuracion_extra', [
            'type'              => 'string',
            'description'       => 'Configuración adicional en JSON',
            'single'            => true,
            'sanitize_callback' => [ $this, 'sanitizar_json' ],
            'auth_callback'     => '__return_false',
            'show_in_rest'      => false,
        ] );
    }

    /**
     * Guarda los meta fields personalizados
     *
     * @since 2.0.0
     * @param int $post_id ID del post
     * @param WP_Post $post Objeto del post
     * @return void
     */
    public function guardar_meta_fields( $post_id, $post ) {
        // Verificar nonce de seguridad
        if ( ! isset( $_POST['globalapi_credencial_nonce'] ) ||
             ! wp_verify_nonce( $_POST['globalapi_credencial_nonce'], 'globalapi_guardar_credencial' ) ) {
            return;
        }

        // Verificar capacidades del usuario
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Evitar auto-guardado
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Lista de campos a guardar
        $campos_meta = [
            '_globalapi_tipo_servicio',
            '_globalapi_estado',
            '_globalapi_url_base',
            '_globalapi_api_key',
            '_globalapi_api_secret',
            '_globalapi_token_acceso',
            '_globalapi_refresh_token',
            '_globalapi_client_id',
            '_globalapi_redirect_uri',
            '_globalapi_scopes',
            '_globalapi_fecha_expiracion',
            '_globalapi_configuracion_extra'
        ];

        // Guardar cada campo
        foreach ( $campos_meta as $campo ) {
            if ( isset( $_POST[ $campo ] ) ) {
                $valor = $_POST[ $campo ];
                
                // Aplicar sanitización específica según el campo
                switch ( $campo ) {
                    case '_globalapi_url_base':
                    case '_globalapi_redirect_uri':
                        $valor = esc_url_raw( $valor );
                        break;
                    
                    case '_globalapi_api_key':
                    case '_globalapi_api_secret':
                    case '_globalapi_token_acceso':
                    case '_globalapi_refresh_token':
                        $valor = $this->cifrar_valor( $valor );
                        break;
                    
                    case '_globalapi_configuracion_extra':
                        $valor = $this->sanitizar_json( $valor );
                        break;
                    
                    default:
                        $valor = sanitize_text_field( $valor );
                        break;
                }
                
                update_post_meta( $post_id, $campo, $valor );
            }
        }

        // Actualizar fecha de creación si es nuevo
        if ( 'auto-draft' === $post->post_status ) {
            update_post_meta( $post_id, '_globalapi_fecha_creacion', current_time( 'mysql' ) );
        }
    }

    /**
     * Cifra un valor sensible antes de guardarlo
     *
     * @since 2.0.0
     * @param string $valor Valor a cifrar
     * @return string Valor cifrado
     */
    public function cifrar_valor( $valor ) {
        if ( empty( $valor ) ) {
            return '';
        }

        // Usar la función de cifrado de WordPress si está disponible
        if ( function_exists( 'wp_hash' ) ) {
            // Nota: En producción usar una librería de cifrado más robusta
            return base64_encode( $valor );
        }

        return sanitize_text_field( $valor );
    }

    /**
     * Descifra un valor sensible al recuperarlo
     *
     * @since 2.0.0
     * @param string $valor_cifrado Valor cifrado
     * @return string Valor descifrado
     */
    public function descifrar_valor( $valor_cifrado ) {
        if ( empty( $valor_cifrado ) ) {
            return '';
        }

        // Descifrar usando base64 (temporal - mejorar en producción)
        $valor_descifrado = base64_decode( $valor_cifrado );
        
        return $valor_descifrado !== false ? $valor_descifrado : '';
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
     * Encola scripts y estilos del admin
     *
     * @since 2.0.0
     * @param string $hook_suffix Sufijo del hook actual
     * @return void
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        $pantallas_objetivo = [
            'post-new.php',
            'post.php',
            'edit.php'
        ];

        if ( ! in_array( $hook_suffix, $pantallas_objetivo ) ) {
            return;
        }

        $pantalla = get_current_screen();
        if ( $pantalla->post_type !== self::POST_TYPE ) {
            return;
        }

        wp_enqueue_script(
            'globalapi-credencial-admin',
            plugin_dir_url( __FILE__ ) . '../../admin/js/credencial-admin.js',
            [ 'jquery' ],
            '2.0.0',
            true
        );

        wp_enqueue_style(
            'globalapi-credencial-admin',
            plugin_dir_url( __FILE__ ) . '../../admin/css/credencial-admin.css',
            [],
            '2.0.0'
        );

        // Localizar script con datos
        wp_localize_script( 'globalapi-credencial-admin', 'globalapi_credencial', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'globalapi_verificar_credencial' ),
            'tipos_servicio' => self::TIPOS_SERVICIO,
            'estados'        => self::ESTADOS,
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
        unset( $columnas['author'] );

        // Agregar columnas personalizadas
        $nuevas_columnas = [
            'cb'           => $columnas['cb'],
            'title'        => $columnas['title'],
            'tipo_servicio' => __( 'Tipo de Servicio', 'globalapi' ),
            'estado'       => __( 'Estado', 'globalapi' ),
            'url_base'     => __( 'URL Base', 'globalapi' ),
            'ultima_verificacion' => __( 'Última Verificación', 'globalapi' ),
            'date'         => __( 'Fecha Creación', 'globalapi' ),
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
            case 'tipo_servicio':
                $tipo = get_post_meta( $post_id, '_globalapi_tipo_servicio', true );
                $nombre_tipo = isset( self::TIPOS_SERVICIO[ $tipo ] ) ? self::TIPOS_SERVICIO[ $tipo ] : $tipo;
                echo esc_html( $nombre_tipo );
                break;

            case 'estado':
                $estado = get_post_meta( $post_id, '_globalapi_estado', true );
                $nombre_estado = isset( self::ESTADOS[ $estado ] ) ? self::ESTADOS[ $estado ] : $estado;
                $clase_estado = 'globalapi-estado-' . sanitize_html_class( $estado );
                echo '<span class="' . esc_attr( $clase_estado ) . '">' . esc_html( $nombre_estado ) . '</span>';
                break;

            case 'url_base':
                $url = get_post_meta( $post_id, '_globalapi_url_base', true );
                if ( $url ) {
                    echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
                } else {
                    echo '<span class="description">' . __( 'No configurada', 'globalapi' ) . '</span>';
                }
                break;

            case 'ultima_verificacion':
                $fecha = get_post_meta( $post_id, '_globalapi_ultima_verificacion', true );
                if ( $fecha ) {
                    $timestamp = strtotime( $fecha );
                    echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
                } else {
                    echo '<span class="description">' . __( 'Nunca verificada', 'globalapi' ) . '</span>';
                }
                break;
        }
    }

    /**
     * Obtiene una credencial por ID con valores descifrados
     *
     * @since 2.0.0
     * @param int $credencial_id ID de la credencial
     * @return array|false Datos de la credencial o false si no existe
     */
    public static function obtener_credencial( $credencial_id ) {
        $post = get_post( $credencial_id );
        
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        $instancia = new self();
        
        return [
            'id'                    => $post->ID,
            'nombre'                => $post->post_title,
            'descripcion'           => $post->post_content,
            'tipo_servicio'         => get_post_meta( $post->ID, '_globalapi_tipo_servicio', true ),
            'estado'                => get_post_meta( $post->ID, '_globalapi_estado', true ),
            'url_base'              => get_post_meta( $post->ID, '_globalapi_url_base', true ),
            'api_key'               => $instancia->descifrar_valor( get_post_meta( $post->ID, '_globalapi_api_key', true ) ),
            'api_secret'            => $instancia->descifrar_valor( get_post_meta( $post->ID, '_globalapi_api_secret', true ) ),
            'token_acceso'          => $instancia->descifrar_valor( get_post_meta( $post->ID, '_globalapi_token_acceso', true ) ),
            'refresh_token'         => $instancia->descifrar_valor( get_post_meta( $post->ID, '_globalapi_refresh_token', true ) ),
            'client_id'             => get_post_meta( $post->ID, '_globalapi_client_id', true ),
            'redirect_uri'          => get_post_meta( $post->ID, '_globalapi_redirect_uri', true ),
            'scopes'                => get_post_meta( $post->ID, '_globalapi_scopes', true ),
            'fecha_creacion'        => get_post_meta( $post->ID, '_globalapi_fecha_creacion', true ),
            'ultima_verificacion'   => get_post_meta( $post->ID, '_globalapi_ultima_verificacion', true ),
            'fecha_expiracion'      => get_post_meta( $post->ID, '_globalapi_fecha_expiracion', true ),
            'configuracion_extra'   => get_post_meta( $post->ID, '_globalapi_configuracion_extra', true ),
        ];
    }

    /**
     * Obtiene credenciales por tipo de servicio
     *
     * @since 2.0.0
     * @param string $tipo_servicio Tipo de servicio a buscar
     * @param string $estado Estado de la credencial (opcional)
     * @return array Lista de credenciales
     */
    public static function obtener_por_tipo_servicio( $tipo_servicio, $estado = 'activa' ) {
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_globalapi_tipo_servicio',
                    'value'   => $tipo_servicio,
                    'compare' => '='
                ]
            ]
        ];

        if ( $estado ) {
            $args['meta_query'][] = [
                'key'     => '_globalapi_estado',
                'value'   => $estado,
                'compare' => '='
            ];
        }

        $query = new WP_Query( $args );
        $credenciales = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $credenciales[] = self::obtener_credencial( get_the_ID() );
            }
            wp_reset_postdata();
        }

        return $credenciales;
    }

    /**
     * Actualiza el estado de una credencial
     *
     * @since 2.0.0
     * @param int $credencial_id ID de la credencial
     * @param string $nuevo_estado Nuevo estado
     * @return bool True si se actualizó correctamente
     */
    public static function actualizar_estado( $credencial_id, $nuevo_estado ) {
        if ( ! isset( self::ESTADOS[ $nuevo_estado ] ) ) {
            return false;
        }

        $resultado = update_post_meta( $credencial_id, '_globalapi_estado', $nuevo_estado );
        
        // Registrar en logs si la actualización fue exitosa
        if ( $resultado ) {
            update_post_meta( $credencial_id, '_globalapi_ultima_verificacion', current_time( 'mysql' ) );
        }

        return $resultado !== false;
    }

    /**
     * Valida si una credencial está activa y no expirada
     *
     * @since 2.0.0
     * @param int $credencial_id ID de la credencial
     * @return bool True si la credencial es válida
     */
    public static function es_valida( $credencial_id ) {
        $estado = get_post_meta( $credencial_id, '_globalapi_estado', true );
        $fecha_expiracion = get_post_meta( $credencial_id, '_globalapi_fecha_expiracion', true );

        // Verificar estado activo
        if ( $estado !== 'activa' ) {
            return false;
        }

        // Verificar expiración si está configurada
        if ( $fecha_expiracion ) {
            $timestamp_expiracion = strtotime( $fecha_expiracion );
            if ( $timestamp_expiracion < time() ) {
                // Actualizar estado a expirada automáticamente
                self::actualizar_estado( $credencial_id, 'expirada' );
                return false;
            }
        }

        return true;
    }
}

// Inicializar la clase
new Credencial();