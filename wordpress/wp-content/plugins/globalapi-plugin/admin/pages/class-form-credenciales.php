<?php
/**
 * Formularios para gestión de credenciales
 *
 * @package    GlobalAPI
 * @subpackage Admin/Pages
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GlobalAPI_Form_Credenciales {

    public function __construct() {
        add_action('wp_ajax_guardar_credencial', array($this, 'ajax_guardar_credencial'));
        add_action('wp_ajax_probar_credencial', array($this, 'ajax_probar_credencial'));
    }

    /**
     * Mostrar formulario de credencial
     */
    public function mostrar_formulario($credencial_id = 0) {
        $credencial = null;
        $es_edicion = false;

        if ($credencial_id > 0) {
            $credencial = get_post($credencial_id);
            $es_edicion = true;
        }

        $nonce_action = $es_edicion ? 'editar_credencial_' . $credencial_id : 'crear_credencial';
        ?>
        <div class="wrap">
            <h1><?php echo $es_edicion ? __('Editar Credencial', 'globalapi') : __('Nueva Credencial', 'globalapi'); ?></h1>

            <form method="post" id="form-credencial" class="globalapi-form">
                <?php wp_nonce_field($nonce_action, 'credencial_nonce'); ?>
                <input type="hidden" name="action" value="guardar_credencial">
                <?php if ($es_edicion): ?>
                    <input type="hidden" name="credencial_id" value="<?php echo $credencial_id; ?>">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <!-- Información básica -->
                    <tr>
                        <th scope="row">
                            <label for="nombre"><?php _e('Nombre de la Credencial', 'globalapi'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="nombre" name="nombre" 
                                   value="<?php echo $es_edicion ? esc_attr($credencial->post_title) : ''; ?>" 
                                   class="regular-text" required>
                            <p class="description"><?php _e('Nombre descriptivo para identificar esta credencial', 'globalapi'); ?></p>
                        </td>
                    </tr>

                    <!-- Tipo de servicio -->
                    <tr>
                        <th scope="row">
                            <label for="tipo_servicio"><?php _e('Tipo de Servicio', 'globalapi'); ?> *</label>
                        </th>
                        <td>
                            <?php
                            $tipo_actual = $es_edicion ? get_post_meta($credencial_id, '_globalapi_tipo_servicio', true) : '';
                            $tipos_servicio = array(
                                'groundhogg' => __('Groundhogg CRM', 'globalapi'),
                                'invision_community' => __('InvisionCommunity', 'globalapi'),
                                'wordpress_api' => __('WordPress API', 'globalapi'),
                                'custom_api' => __('API Personalizada', 'globalapi')
                            );
                            ?>
                            <select id="tipo_servicio" name="tipo_servicio" required class="regular-text">
                                <option value=""><?php _e('Seleccionar tipo...', 'globalapi'); ?></option>
                                <?php foreach ($tipos_servicio as $valor => $etiqueta): ?>
                                    <option value="<?php echo esc_attr($valor); ?>" <?php selected($tipo_actual, $valor); ?>>
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <!-- URL Base -->
                    <tr>
                        <th scope="row">
                            <label for="url_base"><?php _e('URL Base', 'globalapi'); ?> *</label>
                        </th>
                        <td>
                            <input type="url" id="url_base" name="url_base" 
                                   value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_url_base', true)) : ''; ?>" 
                                   class="regular-text" required>
                            <p class="description"><?php _e('URL base del API (ej: https://api.ejemplo.com/v1)', 'globalapi'); ?></p>
                        </td>
                    </tr>

                    <!-- API Key -->
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'globalapi'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_key" name="api_key" 
                                   value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_api_key', true)) : ''; ?>" 
                                   class="regular-text">
                            <button type="button" class="button toggle-password" data-target="api_key">
                                <?php _e('Mostrar/Ocultar', 'globalapi'); ?>
                            </button>
                        </td>
                    </tr>

                    <!-- API Secret -->
                    <tr>
                        <th scope="row">
                            <label for="api_secret"><?php _e('API Secret', 'globalapi'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_secret" name="api_secret" 
                                   value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_api_secret', true)) : ''; ?>" 
                                   class="regular-text">
                            <button type="button" class="button toggle-password" data-target="api_secret">
                                <?php _e('Mostrar/Ocultar', 'globalapi'); ?>
                            </button>
                        </td>
                    </tr>

                    <!-- Estado -->
                    <tr>
                        <th scope="row">
                            <label for="estado"><?php _e('Estado', 'globalapi'); ?></label>
                        </th>
                        <td>
                            <?php
                            $estado_actual = $es_edicion ? get_post_meta($credencial_id, '_globalapi_estado', true) : 'activa';
                            $estados = array(
                                'activa' => __('Activa', 'globalapi'),
                                'inactiva' => __('Inactiva', 'globalapi'),
                                'testing' => __('En pruebas', 'globalapi')
                            );
                            ?>
                            <select id="estado" name="estado" class="regular-text">
                                <?php foreach ($estados as $valor => $etiqueta): ?>
                                    <option value="<?php echo esc_attr($valor); ?>" <?php selected($estado_actual, $valor); ?>>
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Configuración OAuth (se muestra según tipo de servicio) -->
                <div id="seccion-oauth" style="display: none;">
                    <h2><?php _e('Configuración OAuth', 'globalapi'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="client_id"><?php _e('Client ID', 'globalapi'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="client_id" name="client_id" 
                                       value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_client_id', true)) : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="client_secret"><?php _e('Client Secret', 'globalapi'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="client_secret" name="client_secret" 
                                       value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_client_secret', true)) : ''; ?>" 
                                       class="regular-text">
                                <button type="button" class="button toggle-password" data-target="client_secret">
                                    <?php _e('Mostrar/Ocultar', 'globalapi'); ?>
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="redirect_uri"><?php _e('Redirect URI', 'globalapi'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="redirect_uri" name="redirect_uri" 
                                       value="<?php echo $es_edicion ? esc_attr(get_post_meta($credencial_id, '_globalapi_redirect_uri', true)) : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php echo $es_edicion ? __('Actualizar Credencial', 'globalapi') : __('Crear Credencial', 'globalapi'); ?>">
                    <button type="button" id="probar-conexion" class="button button-secondary">
                        <?php _e('Probar Conexión', 'globalapi'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales'); ?>" class="button button-secondary">
                        <?php _e('Cancelar', 'globalapi'); ?>
                    </a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Mostrar/ocultar sección OAuth según tipo de servicio
            $('#tipo_servicio').change(function() {
                var tipo = $(this).val();
                if (tipo === 'invision_community' || tipo === 'custom_api') {
                    $('#seccion-oauth').show();
                } else {
                    $('#seccion-oauth').hide();
                }
            }).trigger('change');

            // Toggle password visibility
            $('.toggle-password').click(function() {
                var target = $(this).data('target');
                var input = $('#' + target);
                var type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
            });

            // Probar conexión
            $('#probar-conexion').click(function() {
                var datos = $('#form-credencial').serialize();
                $.post(ajaxurl, datos + '&action=probar_credencial', function(response) {
                    if (response.success) {
                        alert('<?php _e('Conexión exitosa', 'globalapi'); ?>');
                    } else {
                        alert('<?php _e('Error en la conexión: ', 'globalapi'); ?>' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Guardar credencial via AJAX
     */
    public function ajax_guardar_credencial() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Sin permisos', 'globalapi'));
        }

        $credencial_id = isset($_POST['credencial_id']) ? absint($_POST['credencial_id']) : 0;
        $es_edicion = $credencial_id > 0;

        // Verificar nonce
        $nonce_action = $es_edicion ? 'editar_credencial_' . $credencial_id : 'crear_credencial';
        if (!wp_verify_nonce($_POST['credencial_nonce'], $nonce_action)) {
            wp_send_json_error(__('Nonce inválido', 'globalapi'));
        }

        // Validar datos
        $datos = $this->validar_datos_credencial($_POST);
        if (is_wp_error($datos)) {
            wp_send_json_error($datos->get_error_message());
        }

        // Guardar credencial
        $resultado = $this->guardar_credencial($datos, $credencial_id);
        if (is_wp_error($resultado)) {
            wp_send_json_error($resultado->get_error_message());
        }

        wp_send_json_success(array(
            'message' => $es_edicion ? __('Credencial actualizada', 'globalapi') : __('Credencial creada', 'globalapi'),
            'credencial_id' => $resultado
        ));
    }

    /**
     * Validar datos de credencial
     */
    private function validar_datos_credencial($datos) {
        $errores = new WP_Error();

        // Campos requeridos
        if (empty($datos['nombre'])) {
            $errores->add('nombre_vacio', __('El nombre es requerido', 'globalapi'));
        }

        if (empty($datos['tipo_servicio'])) {
            $errores->add('tipo_vacio', __('El tipo de servicio es requerido', 'globalapi'));
        }

        if (empty($datos['url_base']) || !filter_var($datos['url_base'], FILTER_VALIDATE_URL)) {
            $errores->add('url_invalida', __('URL base inválida', 'globalapi'));
        }

        if ($errores->has_errors()) {
            return $errores;
        }

        return array(
            'nombre' => sanitize_text_field($datos['nombre']),
            'tipo_servicio' => sanitize_text_field($datos['tipo_servicio']),
            'url_base' => esc_url_raw($datos['url_base']),
            'api_key' => sanitize_text_field($datos['api_key']),
            'api_secret' => sanitize_text_field($datos['api_secret']),
            'estado' => sanitize_text_field($datos['estado']),
            'client_id' => sanitize_text_field($datos['client_id']),
            'client_secret' => sanitize_text_field($datos['client_secret']),
            'redirect_uri' => esc_url_raw($datos['redirect_uri'])
        );
    }

    /**
     * Guardar credencial en la base de datos
     */
    private function guardar_credencial($datos, $credencial_id = 0) {
        $es_edicion = $credencial_id > 0;

        // Preparar datos del post
        $post_data = array(
            'post_title' => $datos['nombre'],
            'post_type' => 'globalapi_credencial',
            'post_status' => 'private',
            'meta_input' => array(
                '_globalapi_tipo_servicio' => $datos['tipo_servicio'],
                '_globalapi_url_base' => $datos['url_base'],
                '_globalapi_estado' => $datos['estado'],
                '_globalapi_fecha_modificacion' => current_time('mysql')
            )
        );

        if ($es_edicion) {
            $post_data['ID'] = $credencial_id;
            $resultado = wp_update_post($post_data);
        } else {
            $post_data['meta_input']['_globalapi_fecha_creacion'] = current_time('mysql');
            $resultado = wp_insert_post($post_data);
        }

        if (is_wp_error($resultado) || !$resultado) {
            return new WP_Error('save_error', __('Error al guardar la credencial', 'globalapi'));
        }

        $id = $es_edicion ? $credencial_id : $resultado;

        // Guardar datos sensibles (encriptados)
        if (!empty($datos['api_key'])) {
            update_post_meta($id, '_globalapi_api_key', base64_encode($datos['api_key']));
        }
        if (!empty($datos['api_secret'])) {
            update_post_meta($id, '_globalapi_api_secret', base64_encode($datos['api_secret']));
        }
        if (!empty($datos['client_id'])) {
            update_post_meta($id, '_globalapi_client_id', $datos['client_id']);
        }
        if (!empty($datos['client_secret'])) {
            update_post_meta($id, '_globalapi_client_secret', base64_encode($datos['client_secret']));
        }
        if (!empty($datos['redirect_uri'])) {
            update_post_meta($id, '_globalapi_redirect_uri', $datos['redirect_uri']);
        }

        // Registrar en logs
        GlobalAPI_Log_Auditoria::registrar_log(
            $es_edicion ? 'credencial_update' : 'credencial_create',
            $es_edicion ? 'Credencial actualizada' : 'Credencial creada',
            array(
                'credencial_id' => $id,
                'tipo_servicio' => $datos['tipo_servicio'],
                'usuario_id' => get_current_user_id()
            )
        );

        return $id;
    }

    /**
     * Probar conexión con credencial
     */
    public function ajax_probar_credencial() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Sin permisos', 'globalapi'));
        }

        $tipo_servicio = sanitize_text_field($_POST['tipo_servicio']);
        $url_base = esc_url_raw($_POST['url_base']);

        // Test básico de conexión
        $response = wp_remote_get($url_base, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 400) {
            wp_send_json_success(__('Conexión exitosa', 'globalapi'));
        } else {
            wp_send_json_error(__('Error de conexión: código ' . $code, 'globalapi'));
        }
    }
}

new GlobalAPI_Form_Credenciales(); 