<?php
/**
 * Meta fields personalizados para credenciales API
 *
 * Define los meta boxes y campos personalizados para el
 * formulario de edición de credenciales en el admin de WordPress,
 * adaptándose dinámicamente según el tipo de servicio.
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
 * Clase para gestionar meta fields de credenciales
 *
 * Maneja la creación y renderizado de meta boxes personalizados
 * para el formulario de edición de credenciales, incluyendo
 * validación y sanitización de datos sensibles.
 *
 * @since 2.0.0
 */
class MetaFieldsCredencial {

    /**
     * Constructor - registra hooks
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'agregar_meta_boxes' ] );
        add_action( 'save_post_' . Credencial::POST_TYPE, [ $this, 'guardar_meta_fields' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_globalapi_verificar_credencial', [ $this, 'ajax_verificar_credencial' ] );
    }

    /**
     * Agrega meta boxes al formulario de edición
     *
     * @since 2.0.0
     * @return void
     */
    public function agregar_meta_boxes() {
        // Meta box principal de configuración
        add_meta_box(
            'globalapi_credencial_config',
            __( 'Configuración de Credencial', 'globalapi' ),
            [ $this, 'render_meta_box_configuracion' ],
            Credencial::POST_TYPE,
            'normal',
            'high'
        );

        // Meta box de credenciales sensibles
        add_meta_box(
            'globalapi_credencial_auth',
            __( 'Datos de Autenticación', 'globalapi' ),
            [ $this, 'render_meta_box_autenticacion' ],
            Credencial::POST_TYPE,
            'normal',
            'high'
        );

        // Meta box de configuración OAuth
        add_meta_box(
            'globalapi_credencial_oauth',
            __( 'Configuración OAuth', 'globalapi' ),
            [ $this, 'render_meta_box_oauth' ],
            Credencial::POST_TYPE,
            'normal',
            'default'
        );

        // Meta box de configuración avanzada
        add_meta_box(
            'globalapi_credencial_avanzada',
            __( 'Configuración Avanzada', 'globalapi' ),
            [ $this, 'render_meta_box_avanzada' ],
            Credencial::POST_TYPE,
            'side',
            'default'
        );

        // Meta box de estado y estadísticas
        add_meta_box(
            'globalapi_credencial_estado',
            __( 'Estado y Estadísticas', 'globalapi' ),
            [ $this, 'render_meta_box_estado' ],
            Credencial::POST_TYPE,
            'side',
            'high'
        );

        // Meta box de acciones rápidas
        add_meta_box(
            'globalapi_credencial_acciones',
            __( 'Acciones', 'globalapi' ),
            [ $this, 'render_meta_box_acciones' ],
            Credencial::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render del meta box de configuración principal
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_configuracion( $post ) {
        // Nonce de seguridad
        wp_nonce_field( 'globalapi_guardar_credencial', 'globalapi_credencial_nonce' );

        // Obtener valores actuales
        $tipo_servicio = get_post_meta( $post->ID, '_globalapi_tipo_servicio', true );
        $estado = get_post_meta( $post->ID, '_globalapi_estado', true );
        $url_base = get_post_meta( $post->ID, '_globalapi_url_base', true );

        ?>
        <table class="form-table globalapi-form-table">
            <tr>
                <th scope="row">
                    <label for="globalapi_tipo_servicio"><?php _e( 'Tipo de Servicio', 'globalapi' ); ?></label>
                </th>
                <td>
                    <select name="_globalapi_tipo_servicio" id="globalapi_tipo_servicio" class="regular-text" required>
                        <option value=""><?php _e( 'Seleccionar tipo de servicio...', 'globalapi' ); ?></option>
                        <?php foreach ( Credencial::TIPOS_SERVICIO as $valor => $etiqueta ) : ?>
                            <option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $tipo_servicio, $valor ); ?>>
                                <?php echo esc_html( $etiqueta ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e( 'Selecciona el tipo de servicio externo para esta credencial.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="globalapi_estado"><?php _e( 'Estado', 'globalapi' ); ?></label>
                </th>
                <td>
                    <select name="_globalapi_estado" id="globalapi_estado" class="regular-text">
                        <?php foreach ( Credencial::ESTADOS as $valor => $etiqueta ) : ?>
                            <option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $estado, $valor ); ?>>
                                <?php echo esc_html( $etiqueta ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e( 'Estado actual de la credencial.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="globalapi_url_base"><?php _e( 'URL Base', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="url" 
                           name="_globalapi_url_base" 
                           id="globalapi_url_base" 
                           value="<?php echo esc_attr( $url_base ); ?>" 
                           class="regular-text" 
                           placeholder="https://ejemplo.com/api/v1" />
                    <p class="description">
                        <?php _e( 'URL base del servicio API (sin trailing slash).', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div id="globalapi_configuracion_especifica">
            <!-- Contenido dinámico según tipo de servicio -->
        </div>
        <?php
    }

    /**
     * Render del meta box de datos de autenticación
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_autenticacion( $post ) {
        // Obtener valores actuales (valores cifrados no se muestran por seguridad)
        $api_key = get_post_meta( $post->ID, '_globalapi_api_key', true );
        $api_secret = get_post_meta( $post->ID, '_globalapi_api_secret', true );
        $token_acceso = get_post_meta( $post->ID, '_globalapi_token_acceso', true );
        $refresh_token = get_post_meta( $post->ID, '_globalapi_refresh_token', true );

        ?>
        <div class="globalapi-auth-notice">
            <p>
                <strong><?php _e( 'Nota de Seguridad:', 'globalapi' ); ?></strong>
                <?php _e( 'Los datos de autenticación se cifran automáticamente al guardar. Por seguridad, los campos aparecerán vacíos al editar credenciales existentes.', 'globalapi' ); ?>
            </p>
        </div>

        <table class="form-table globalapi-form-table">
            <tr class="globalapi-field-api-key">
                <th scope="row">
                    <label for="globalapi_api_key"><?php _e( 'API Key', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="password" 
                           name="_globalapi_api_key" 
                           id="globalapi_api_key" 
                           value="" 
                           class="regular-text" 
                           placeholder="<?php echo $api_key ? __( '••••••••••••••••', 'globalapi' ) : __( 'Ingresa la API Key', 'globalapi' ); ?>" 
                           autocomplete="new-password" />
                    <button type="button" class="button globalapi-toggle-password" data-target="globalapi_api_key">
                        <?php _e( 'Mostrar', 'globalapi' ); ?>
                    </button>
                    <p class="description">
                        <?php _e( 'Clave API proporcionada por el servicio externo.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="globalapi-field-api-secret">
                <th scope="row">
                    <label for="globalapi_api_secret"><?php _e( 'API Secret', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="password" 
                           name="_globalapi_api_secret" 
                           id="globalapi_api_secret" 
                           value="" 
                           class="regular-text" 
                           placeholder="<?php echo $api_secret ? __( '••••••••••••••••', 'globalapi' ) : __( 'Ingresa el API Secret', 'globalapi' ); ?>" 
                           autocomplete="new-password" />
                    <button type="button" class="button globalapi-toggle-password" data-target="globalapi_api_secret">
                        <?php _e( 'Mostrar', 'globalapi' ); ?>
                    </button>
                    <p class="description">
                        <?php _e( 'Clave secreta API proporcionada por el servicio externo.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="globalapi-field-token-acceso">
                <th scope="row">
                    <label for="globalapi_token_acceso"><?php _e( 'Token de Acceso', 'globalapi' ); ?></label>
                </th>
                <td>
                    <textarea name="_globalapi_token_acceso" 
                              id="globalapi_token_acceso" 
                              rows="3" 
                              class="large-text" 
                              placeholder="<?php echo $token_acceso ? __( '••••••••••••••••', 'globalapi' ) : __( 'Token de acceso (para OAuth)', 'globalapi' ); ?>"></textarea>
                    <p class="description">
                        <?php _e( 'Token de acceso OAuth (se puede generar automáticamente).', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="globalapi-field-refresh-token">
                <th scope="row">
                    <label for="globalapi_refresh_token"><?php _e( 'Refresh Token', 'globalapi' ); ?></label>
                </th>
                <td>
                    <textarea name="_globalapi_refresh_token" 
                              id="globalapi_refresh_token" 
                              rows="3" 
                              class="large-text" 
                              placeholder="<?php echo $refresh_token ? __( '••••••••••••••••', 'globalapi' ) : __( 'Refresh token (para OAuth)', 'globalapi' ); ?>"></textarea>
                    <p class="description">
                        <?php _e( 'Token para renovar el acceso automáticamente.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render del meta box de configuración OAuth
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_oauth( $post ) {
        // Obtener valores actuales
        $client_id = get_post_meta( $post->ID, '_globalapi_client_id', true );
        $redirect_uri = get_post_meta( $post->ID, '_globalapi_redirect_uri', true );
        $scopes = get_post_meta( $post->ID, '_globalapi_scopes', true );

        ?>
        <table class="form-table globalapi-form-table">
            <tr class="globalapi-field-client-id">
                <th scope="row">
                    <label for="globalapi_client_id"><?php _e( 'Client ID', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="_globalapi_client_id" 
                           id="globalapi_client_id" 
                           value="<?php echo esc_attr( $client_id ); ?>" 
                           class="regular-text" 
                           placeholder="<?php _e( 'ID del cliente OAuth', 'globalapi' ); ?>" />
                    <p class="description">
                        <?php _e( 'Identificador del cliente para autenticación OAuth.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="globalapi-field-redirect-uri">
                <th scope="row">
                    <label for="globalapi_redirect_uri"><?php _e( 'URI de Redirección', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="url" 
                           name="_globalapi_redirect_uri" 
                           id="globalapi_redirect_uri" 
                           value="<?php echo esc_attr( $redirect_uri ); ?>" 
                           class="regular-text" 
                           placeholder="<?php echo esc_attr( home_url( '/wp-json/globalapi/v1/oauth/callback' ) ); ?>" />
                    <p class="description">
                        <?php _e( 'URL de callback para el flujo OAuth.', 'globalapi' ); ?>
                        <br>
                        <strong><?php _e( 'Sugerido:', 'globalapi' ); ?></strong> 
                        <code><?php echo esc_html( home_url( '/wp-json/globalapi/v1/oauth/callback' ) ); ?></code>
                    </p>
                </td>
            </tr>
            <tr class="globalapi-field-scopes">
                <th scope="row">
                    <label for="globalapi_scopes"><?php _e( 'Scopes OAuth', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="_globalapi_scopes" 
                           id="globalapi_scopes" 
                           value="<?php echo esc_attr( $scopes ); ?>" 
                           class="regular-text" 
                           placeholder="read,write,profile" />
                    <p class="description">
                        <?php _e( 'Permisos solicitados, separados por comas.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div class="globalapi-oauth-actions" style="margin-top: 20px;">
            <button type="button" id="globalapi_generar_oauth_url" class="button button-secondary">
                <?php _e( 'Generar URL de Autorización', 'globalapi' ); ?>
            </button>
            <button type="button" id="globalapi_renovar_token" class="button button-secondary">
                <?php _e( 'Renovar Token de Acceso', 'globalapi' ); ?>
            </button>
        </div>

        <div id="globalapi_oauth_url_result" style="margin-top: 15px; display: none;">
            <p><strong><?php _e( 'URL de Autorización:', 'globalapi' ); ?></strong></p>
            <textarea readonly class="large-text" rows="3" id="globalapi_oauth_url_display"></textarea>
            <p class="description">
                <?php _e( 'Copia esta URL y ábrela en tu navegador para autorizar la aplicación.', 'globalapi' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render del meta box de configuración avanzada
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_avanzada( $post ) {
        // Obtener valores actuales
        $fecha_expiracion = get_post_meta( $post->ID, '_globalapi_fecha_expiracion', true );
        $configuracion_extra = get_post_meta( $post->ID, '_globalapi_configuracion_extra', true );

        ?>
        <table class="form-table globalapi-form-table">
            <tr>
                <th scope="row">
                    <label for="globalapi_fecha_expiracion"><?php _e( 'Fecha de Expiración', 'globalapi' ); ?></label>
                </th>
                <td>
                    <input type="datetime-local" 
                           name="_globalapi_fecha_expiracion" 
                           id="globalapi_fecha_expiracion" 
                           value="<?php echo esc_attr( $fecha_expiracion ? date( 'Y-m-d\TH:i', strtotime( $fecha_expiracion ) ) : '' ); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e( 'Fecha de expiración del token (opcional).', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="globalapi_configuracion_extra"><?php _e( 'Configuración Extra', 'globalapi' ); ?></label>
                </th>
                <td>
                    <textarea name="_globalapi_configuracion_extra" 
                              id="globalapi_configuracion_extra" 
                              rows="8" 
                              class="large-text code" 
                              placeholder='{"timeout": 30, "retries": 3}'><?php echo esc_textarea( $configuracion_extra ); ?></textarea>
                    <p class="description">
                        <?php _e( 'Configuración adicional en formato JSON.', 'globalapi' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render del meta box de estado y estadísticas
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_estado( $post ) {
        // Obtener datos de auditoría
        $fecha_creacion = get_post_meta( $post->ID, '_globalapi_fecha_creacion', true );
        $ultima_verificacion = get_post_meta( $post->ID, '_globalapi_ultima_verificacion', true );

        ?>
        <div class="globalapi-status-widget">
            <h4><?php _e( 'Información de Estado', 'globalapi' ); ?></h4>
            
            <p>
                <strong><?php _e( 'Fecha de Creación:', 'globalapi' ); ?></strong><br>
                <?php echo $fecha_creacion ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $fecha_creacion ) ) ) : __( 'No disponible', 'globalapi' ); ?>
            </p>

            <p>
                <strong><?php _e( 'Última Verificación:', 'globalapi' ); ?></strong><br>
                <?php echo $ultima_verificacion ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ultima_verificacion ) ) ) : __( 'Nunca verificada', 'globalapi' ); ?>
            </p>

            <div id="globalapi_status_indicator" class="globalapi-status-indicator">
                <span class="status-light"></span>
                <span class="status-text"><?php _e( 'Estado desconocido', 'globalapi' ); ?></span>
            </div>
        </div>

        <?php if ( $post->ID ) : ?>
        <div class="globalapi-stats-widget" style="margin-top: 20px;">
            <h4><?php _e( 'Estadísticas de Uso', 'globalapi' ); ?></h4>
            <div id="globalapi_usage_stats">
                <p><?php _e( 'Cargando estadísticas...', 'globalapi' ); ?></p>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render del meta box de acciones rápidas
     *
     * @since 2.0.0
     * @param WP_Post $post Objeto del post actual
     * @return void
     */
    public function render_meta_box_acciones( $post ) {
        ?>
        <div class="globalapi-actions-widget">
            <?php if ( $post->ID ) : ?>
            <p>
                <button type="button" id="globalapi_verificar_conexion" class="button button-secondary button-large" style="width: 100%;">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e( 'Verificar Conexión', 'globalapi' ); ?>
                </button>
            </p>

            <p>
                <button type="button" id="globalapi_probar_endpoint" class="button button-secondary button-large" style="width: 100%;">
                    <span class="dashicons dashicons-rest-api"></span>
                    <?php _e( 'Probar Endpoint', 'globalapi' ); ?>
                </button>
            </p>

            <p>
                <button type="button" id="globalapi_exportar_config" class="button button-secondary button-large" style="width: 100%;">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e( 'Exportar Configuración', 'globalapi' ); ?>
                </button>
            </p>

            <hr>

            <p>
                <button type="button" id="globalapi_ver_logs" class="button button-secondary button-large" style="width: 100%;">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e( 'Ver Logs Relacionados', 'globalapi' ); ?>
                </button>
            </p>
            <?php else : ?>
            <p class="description">
                <?php _e( 'Guarda la credencial para acceder a las acciones.', 'globalapi' ); ?>
            </p>
            <?php endif; ?>
        </div>

        <div id="globalapi_action_results" style="margin-top: 20px; display: none;">
            <div class="notice notice-info">
                <p id="globalapi_action_message"></p>
            </div>
        </div>
        <?php
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

        // Validar campos requeridos
        $errores = [];

        if ( empty( $_POST['_globalapi_tipo_servicio'] ) ) {
            $errores[] = __( 'El tipo de servicio es obligatorio.', 'globalapi' );
        }

        if ( ! empty( $_POST['_globalapi_configuracion_extra'] ) ) {
            $json_test = json_decode( $_POST['_globalapi_configuracion_extra'], true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $errores[] = __( 'La configuración extra debe ser un JSON válido.', 'globalapi' );
            }
        }

        // Si hay errores, mostrar mensaje y salir
        if ( ! empty( $errores ) ) {
            set_transient( 'globalapi_credencial_errors_' . $post_id, $errores, 60 );
            return;
        }

        // Procesar fecha de expiración
        if ( ! empty( $_POST['_globalapi_fecha_expiracion'] ) ) {
            $fecha_expiracion = sanitize_text_field( $_POST['_globalapi_fecha_expiracion'] );
            $timestamp = strtotime( $fecha_expiracion );
            if ( $timestamp !== false ) {
                update_post_meta( $post_id, '_globalapi_fecha_expiracion', date( 'Y-m-d H:i:s', $timestamp ) );
            }
        } else {
            delete_post_meta( $post_id, '_globalapi_fecha_expiracion' );
        }

        // Registrar en logs la modificación de credencial
        if ( class_exists( 'LogAuditoria' ) ) {
            LogAuditoria::registrar_log( [
                'titulo'               => sprintf( __( 'Credencial modificada: %s', 'globalapi' ), $post->post_title ),
                'descripcion'          => sprintf( __( 'La credencial "%s" ha sido modificada por el usuario.', 'globalapi' ), $post->post_title ),
                'tipo_evento'          => 'credencial_update',
                'severidad'            => 'info',
                'datos_adicionales'    => [
                    'credencial_id'   => $post_id,
                    'tipo_servicio'   => sanitize_text_field( $_POST['_globalapi_tipo_servicio'] ),
                    'estado_anterior' => get_post_meta( $post_id, '_globalapi_estado', true ),
                    'estado_nuevo'    => sanitize_text_field( $_POST['_globalapi_estado'] ),
                ]
            ] );
        }

        // Mensaje de éxito
        set_transient( 'globalapi_credencial_success_' . $post_id, __( 'Credencial guardada correctamente.', 'globalapi' ), 60 );
    }

    /**
     * Encola scripts y estilos para el admin
     *
     * @since 2.0.0
     * @param string $hook_suffix Sufijo del hook actual
     * @return void
     */
    public function enqueue_scripts( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'post-new.php', 'post.php' ] ) ) {
            return;
        }

        $pantalla = get_current_screen();
        if ( $pantalla->post_type !== Credencial::POST_TYPE ) {
            return;
        }

        // Script principal de meta fields
        wp_enqueue_script(
            'globalapi-meta-fields',
            plugin_dir_url( __FILE__ ) . '../../admin/js/meta-fields-credencial.js',
            [ 'jquery', 'wp-api' ],
            '2.0.0',
            true
        );

        // Estilos específicos para meta fields
        wp_enqueue_style(
            'globalapi-meta-fields',
            plugin_dir_url( __FILE__ ) . '../../admin/css/meta-fields-credencial.css',
            [],
            '2.0.0'
        );

        // Editor de código para JSON
        wp_enqueue_code_editor( [ 'type' => 'application/json' ] );

        // Localizar script con datos
        wp_localize_script( 'globalapi-meta-fields', 'globalapi_meta', [
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'globalapi_meta_action' ),
            'post_id'           => get_the_ID(),
            'tipos_servicio'    => Credencial::TIPOS_SERVICIO,
            'estados'           => Credencial::ESTADOS,
            'textos'            => [
                'verificando'       => __( 'Verificando conexión...', 'globalapi' ),
                'verificado_ok'     => __( 'Conexión verificada correctamente', 'globalapi' ),
                'verificado_error'  => __( 'Error en la verificación', 'globalapi' ),
                'probando_endpoint' => __( 'Probando endpoint...', 'globalapi' ),
                'endpoint_ok'       => __( 'Endpoint responde correctamente', 'globalapi' ),
                'endpoint_error'    => __( 'Error en el endpoint', 'globalapi' ),
                'exportando'        => __( 'Generando exportación...', 'globalapi' ),
                'exportado_ok'      => __( 'Configuración exportada', 'globalapi' ),
                'error_general'     => __( 'Ha ocurrido un error', 'globalapi' ),
            ],
            'configuraciones_tipo' => [
                'groundhogg' => [
                    'campos_requeridos' => [ 'api_key', 'api_secret' ],
                    'campos_oauth' => false,
                    'url_ejemplo' => 'https://tudominio.com/wp-json/gh/v4',
                ],
                'invision_community' => [
                    'campos_requeridos' => [ 'client_id' ],
                    'campos_oauth' => true,
                    'url_ejemplo' => 'https://tudominio.com/api',
                ],
                'wordpress_api' => [
                    'campos_requeridos' => [ 'api_key' ],
                    'campos_oauth' => false,
                    'url_ejemplo' => 'https://tudominio.com/wp-json/wp/v2',
                ],
            ]
        ] );

        // Mostrar errores/mensajes si existen
        $post_id = get_the_ID();
        if ( $post_id ) {
            $errores = get_transient( 'globalapi_credencial_errors_' . $post_id );
            $exito = get_transient( 'globalapi_credencial_success_' . $post_id );

            if ( $errores ) {
                add_action( 'admin_notices', function() use ( $errores ) {
                    echo '<div class="notice notice-error"><ul>';
                    foreach ( $errores as $error ) {
                        echo '<li>' . esc_html( $error ) . '</li>';
                    }
                    echo '</ul></div>';
                } );
                delete_transient( 'globalapi_credencial_errors_' . $post_id );
            }

            if ( $exito ) {
                add_action( 'admin_notices', function() use ( $exito ) {
                    echo '<div class="notice notice-success"><p>' . esc_html( $exito ) . '</p></div>';
                } );
                delete_transient( 'globalapi_credencial_success_' . $post_id );
            }
        }
    }

    /**
     * Maneja AJAX para verificar credencial
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_verificar_credencial() {
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'globalapi_meta_action' ) ) {
            wp_die( __( 'Error de seguridad', 'globalapi' ) );
        }

        // Verificar capacidades
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permisos insuficientes', 'globalapi' ) );
        }

        $post_id = intval( $_POST['post_id'] );
        $accion = sanitize_text_field( $_POST['accion'] );

        $respuesta = [ 'success' => false, 'message' => '' ];

        switch ( $accion ) {
            case 'verificar_conexion':
                $respuesta = $this->verificar_conexion_credencial( $post_id );
                break;

            case 'probar_endpoint':
                $respuesta = $this->probar_endpoint_credencial( $post_id );
                break;

            case 'exportar_config':
                $respuesta = $this->exportar_configuracion_credencial( $post_id );
                break;

            case 'obtener_stats':
                $respuesta = $this->obtener_estadisticas_credencial( $post_id );
                break;

            default:
                $respuesta['message'] = __( 'Acción no válida', 'globalapi' );
                break;
        }

        wp_send_json( $respuesta );
    }

    /**
     * Verifica la conexión de una credencial
     *
     * @since 2.0.0
     * @param int $post_id ID de la credencial
     * @return array Resultado de la verificación
     */
    private function verificar_conexion_credencial( $post_id ) {
        $credencial = Credencial::obtener_credencial( $post_id );
        
        if ( ! $credencial ) {
            return [
                'success' => false,
                'message' => __( 'Credencial no encontrada', 'globalapi' )
            ];
        }

        // Simular verificación (implementar lógica real según tipo de servicio)
        $tipo_servicio = $credencial['tipo_servicio'];
        $url_base = $credencial['url_base'];

        if ( empty( $url_base ) ) {
            return [
                'success' => false,
                'message' => __( 'URL base no configurada', 'globalapi' )
            ];
        }

        // Realizar petición de prueba
        $response = wp_remote_get( $url_base, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'GlobalAPI/2.0 WordPress Plugin'
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => sprintf( __( 'Error de conexión: %s', 'globalapi' ), $response->get_error_message() )
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        
        // Actualizar última verificación
        update_post_meta( $post_id, '_globalapi_ultima_verificacion', current_time( 'mysql' ) );

        if ( $status_code >= 200 && $status_code < 400 ) {
            return [
                'success' => true,
                'message' => sprintf( __( 'Conexión exitosa (HTTP %d)', 'globalapi' ), $status_code ),
                'status_code' => $status_code
            ];
        } else {
            return [
                'success' => false,
                'message' => sprintf( __( 'Error HTTP: %d', 'globalapi' ), $status_code ),
                'status_code' => $status_code
            ];
        }
    }

    /**
     * Prueba un endpoint específico de la credencial
     *
     * @since 2.0.0
     * @param int $post_id ID de la credencial
     * @return array Resultado de la prueba
     */
    private function probar_endpoint_credencial( $post_id ) {
        $credencial = Credencial::obtener_credencial( $post_id );
        
        if ( ! $credencial ) {
            return [
                'success' => false,
                'message' => __( 'Credencial no encontrada', 'globalapi' )
            ];
        }

        // Definir endpoints de prueba según tipo de servicio
        $endpoints_prueba = [
            'groundhogg'         => '/contacts',
            'invision_community' => '/core/members',
            'wordpress_api'      => '/posts',
            'oauth_generico'     => '/me',
            'api_rest'           => '/'
        ];

        $tipo_servicio = $credencial['tipo_servicio'];
        $endpoint_prueba = isset( $endpoints_prueba[ $tipo_servicio ] ) ? $endpoints_prueba[ $tipo_servicio ] : '/';
        $url_completa = rtrim( $credencial['url_base'], '/' ) . $endpoint_prueba;

        // Preparar headers según tipo de servicio
        $headers = [ 'User-Agent' => 'GlobalAPI/2.0 WordPress Plugin' ];

        if ( $tipo_servicio === 'groundhogg' && $credencial['api_key'] ) {
            $headers['Authorization'] = 'Basic ' . base64_encode( $credencial['api_key'] . ':' . $credencial['api_secret'] );
        }

        $response = wp_remote_get( $url_completa, [
            'timeout' => 15,
            'headers' => $headers
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => sprintf( __( 'Error en endpoint: %s', 'globalapi' ), $response->get_error_message() ),
                'endpoint' => $endpoint_prueba
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        return [
            'success' => $status_code >= 200 && $status_code < 400,
            'message' => sprintf( __( 'Endpoint %s responde con HTTP %d', 'globalapi' ), $endpoint_prueba, $status_code ),
            'status_code' => $status_code,
            'endpoint' => $endpoint_prueba,
            'response_size' => strlen( $body )
        ];
    }

    /**
     * Exporta la configuración de una credencial
     *
     * @since 2.0.0
     * @param int $post_id ID de la credencial
     * @return array Configuración exportada
     */
    private function exportar_configuracion_credencial( $post_id ) {
        $credencial = Credencial::obtener_credencial( $post_id );
        
        if ( ! $credencial ) {
            return [
                'success' => false,
                'message' => __( 'Credencial no encontrada', 'globalapi' )
            ];
        }

        // Preparar configuración para exportar (sin datos sensibles)
        $config_exportacion = [
            'nombre' => $credencial['nombre'],
            'tipo_servicio' => $credencial['tipo_servicio'],
            'url_base' => $credencial['url_base'],
            'client_id' => $credencial['client_id'],
            'redirect_uri' => $credencial['redirect_uri'],
            'scopes' => $credencial['scopes'],
            'configuracion_extra' => $credencial['configuracion_extra'],
            'fecha_exportacion' => current_time( 'mysql' ),
            'exportado_por' => wp_get_current_user()->display_name
        ];

        return [
            'success' => true,
            'message' => __( 'Configuración exportada correctamente', 'globalapi' ),
            'config' => $config_exportacion,
            'filename' => sprintf( 'credencial-%s-%s.json', sanitize_title( $credencial['nombre'] ), date( 'Y-m-d' ) )
        ];
    }

    /**
     * Obtiene estadísticas de uso de una credencial
     *
     * @since 2.0.0
     * @param int $post_id ID de la credencial
     * @return array Estadísticas de uso
     */
    private function obtener_estadisticas_credencial( $post_id ) {
        // Esta funcionalidad se implementará cuando tengamos el sistema de logs completo
        return [
            'success' => true,
            'message' => __( 'Estadísticas cargadas', 'globalapi' ),
            'stats' => [
                'total_requests' => 0,
                'requests_hoy' => 0,
                'ultimo_uso' => null,
                'errores_recientes' => 0
            ]
        ];
    }
}

// Inicializar la clase
new MetaFieldsCredencial();