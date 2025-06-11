<?php
/**
 * Gestión de taxonomías para credenciales
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
 * Clase para gestionar taxonomías del plugin
 */
class TaxonomiasGlobalAPI {

    /**
     * Constructor - registra hooks
     */
    public function __construct() {
        add_action( 'init', [ $this, 'registrar_taxonomias' ] );
        add_action( 'init', [ $this, 'crear_terminos_predeterminados' ] );
    }

    /**
     * Registra las taxonomías personalizadas
     */
    public function registrar_taxonomias() {
        // Taxonomía para tipos de servicio de credenciales
        register_taxonomy( 'globalapi_tipo_servicio_cred', [ Credencial::POST_TYPE ], [
            'labels' => [
                'name' => __( 'Tipos de Servicio', 'globalapi' ),
                'singular_name' => __( 'Tipo de Servicio', 'globalapi' ),
                'menu_name' => __( 'Tipos de Servicio', 'globalapi' ),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => false,
            'rewrite' => false,
            'show_in_rest' => false,
            'capabilities' => [
                'manage_terms' => 'manage_options',
                'edit_terms' => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
        ] );

        // Taxonomía para estados de credenciales
        register_taxonomy( 'globalapi_estado_cred', [ Credencial::POST_TYPE ], [
            'labels' => [
                'name' => __( 'Estados de Credencial', 'globalapi' ),
                'singular_name' => __( 'Estado de Credencial', 'globalapi' ),
                'menu_name' => __( 'Estados', 'globalapi' ),
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => false,
            'rewrite' => false,
            'show_in_rest' => false,
            'capabilities' => [
                'manage_terms' => 'manage_options',
                'edit_terms' => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
        ] );
    }

    /**
     * Crea términos predeterminados para las taxonomías
     */
    public function crear_terminos_predeterminados() {
        // Crear términos para tipos de servicio
        $tipos_servicio = [
            'groundhogg' => 'Groundhogg CRM',
            'invision_community' => 'InvisionCommunity OAuth',
            'wordpress_api' => 'WordPress REST API',
            'oauth_generico' => 'OAuth Genérico',
            'api_rest' => 'API REST Genérica'
        ];

        foreach ( $tipos_servicio as $slug => $nombre ) {
            if ( ! term_exists( $slug, 'globalapi_tipo_servicio_cred' ) ) {
                wp_insert_term( $nombre, 'globalapi_tipo_servicio_cred', [
                    'slug' => $slug,
                    'description' => sprintf( __( 'Credenciales para %s', 'globalapi' ), $nombre )
                ] );
            }
        }

        // Crear términos para estados
        $estados = [
            'activa' => 'Activa',
            'inactiva' => 'Inactiva',
            'expirada' => 'Expirada',
            'bloqueada' => 'Bloqueada',
            'testing' => 'En Pruebas'
        ];

        foreach ( $estados as $slug => $nombre ) {
            if ( ! term_exists( $slug, 'globalapi_estado_cred' ) ) {
                wp_insert_term( $nombre, 'globalapi_estado_cred', [
                    'slug' => $slug,
                    'description' => sprintf( __( 'Credencial en estado %s', 'globalapi' ), strtolower( $nombre ) )
                ] );
            }
        }
    }
}

// Inicializar la clase
new TaxonomiasGlobalAPI(); 