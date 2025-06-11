<?php
/**
 * Formulario de credencial (Nueva/Editar)
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Determinar si es edición o creación
$is_edit = isset($credencial) && $credencial instanceof WP_Post;
$form_title = $is_edit ? 'Editar Credencial' : 'Nueva Credencial';

// Valores por defecto
$nombre = $is_edit ? $credencial->post_title : '';
$descripcion = $is_edit ? $credencial->post_excerpt : '';
$tipo_servicio = $is_edit ? get_post_meta($credencial->ID, '_globalapi_tipo_servicio', true) : '';
$url_base = $is_edit ? get_post_meta($credencial->ID, '_globalapi_url_base', true) : '';
$api_key = $is_edit ? get_post_meta($credencial->ID, '_globalapi_api_key', true) : '';
$api_secret = $is_edit ? get_post_meta($credencial->ID, '_globalapi_api_secret', true) : '';
$estado = $is_edit ? get_post_meta($credencial->ID, '_globalapi_estado', true) : 'activa';

// Procesar formulario si se envió (ANTES de cualquier output HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['globalapi_save_credencial'])) {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'globalapi_credencial_form')) {
        wp_die('Error de seguridad. Inténtalo de nuevo.');
    }

    // Sanitizar datos
    $post_data = array(
        'post_title' => sanitize_text_field($_POST['nombre']),
        'post_excerpt' => sanitize_textarea_field($_POST['descripcion']),
        'post_type' => 'globalapi_credencial',
        'post_status' => 'publish'
    );

    if ($is_edit) {
        $post_data['ID'] = $credencial->ID;
        $result = wp_update_post($post_data);
    } else {
        $result = wp_insert_post($post_data);
    }

    if (!is_wp_error($result)) {
        // Guardar metadatos comunes
        update_post_meta($result, '_globalapi_tipo_servicio', sanitize_text_field($_POST['tipo_servicio']));
        update_post_meta($result, '_globalapi_estado', sanitize_text_field($_POST['estado']));
        update_post_meta($result, '_globalapi_fecha_creacion', current_time('mysql'));
        
        // Guardar metadatos específicos según el tipo de servicio
        $tipo_servicio = sanitize_text_field($_POST['tipo_servicio']);
        
        if ($tipo_servicio === 'groundhogg') {
            // Campos de Groundhogg CRM
            if (!empty($_POST['gh_base_url_v3'])) update_post_meta($result, '_globalapi_gh_base_url_v3', esc_url_raw($_POST['gh_base_url_v3']));
            if (!empty($_POST['gh_base_url_v4'])) update_post_meta($result, '_globalapi_gh_base_url_v4', esc_url_raw($_POST['gh_base_url_v4']));
            if (!empty($_POST['gh_clave_publica'])) update_post_meta($result, '_globalapi_gh_clave_publica', sanitize_text_field($_POST['gh_clave_publica']));
            if (!empty($_POST['gh_token'])) update_post_meta($result, '_globalapi_gh_token', sanitize_text_field($_POST['gh_token']));
            if (!empty($_POST['gh_llave_secreta'])) update_post_meta($result, '_globalapi_gh_llave_secreta', sanitize_text_field($_POST['gh_llave_secreta']));
        } elseif ($tipo_servicio === 'invisioncommunity') {
            // Campos de InvisionCommunity OAuth
            if (!empty($_POST['ic_authorization_url'])) update_post_meta($result, '_globalapi_ic_authorization_url', esc_url_raw($_POST['ic_authorization_url']));
            if (!empty($_POST['ic_token_url'])) update_post_meta($result, '_globalapi_ic_token_url', esc_url_raw($_POST['ic_token_url']));
            if (!empty($_POST['ic_client_identifier'])) update_post_meta($result, '_globalapi_ic_client_identifier', sanitize_text_field($_POST['ic_client_identifier']));
            if (!empty($_POST['ic_client_secret'])) update_post_meta($result, '_globalapi_ic_client_secret', sanitize_text_field($_POST['ic_client_secret']));
            if (!empty($_POST['ic_rest_api_key'])) update_post_meta($result, '_globalapi_ic_rest_api_key', sanitize_text_field($_POST['ic_rest_api_key']));
            if (!empty($_POST['ic_scopes'])) update_post_meta($result, '_globalapi_ic_scopes', sanitize_text_field($_POST['ic_scopes']));
            if (!empty($_POST['ic_grant_type'])) update_post_meta($result, '_globalapi_ic_grant_type', sanitize_text_field($_POST['ic_grant_type']));
        }

        // Registrar en logs
        if (class_exists('LogAuditoria')) {
            LogAuditoria::registrar_log(array(
                'tipo_evento' => 'credencial_' . ($is_edit ? 'updated' : 'created'),
                'descripcion' => ($is_edit ? 'Credencial actualizada: ' : 'Nueva credencial creada: ') . sanitize_text_field($_POST['nombre']),
                'severidad' => 'info',
                'datos_adicionales' => array(
                    'credencial_id' => $result,
                    'tipo_servicio' => sanitize_text_field($_POST['tipo_servicio'])
                )
            ));
        }

        // Redirigir con mensaje de éxito - AHORA SIN OUTPUT PREVIO
        $redirect_url = admin_url('admin.php?page=globalapi-credenciales&mensaje=' . ($is_edit ? 'updated' : 'created'));
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        // En caso de error, mostrar mensaje
        $error_message = 'Error al guardar la credencial: ' . $result->get_error_message();
    }
}
?>

<div class="globalapi-content">
    <div class="form-header">
        <h2><?php echo esc_html($form_title); ?></h2>
        <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales'); ?>" class="button">
            ← Volver a Credenciales
        </a>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="notice notice-error">
            <p><strong>Error:</strong> <?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="globalapi-form">
        <?php wp_nonce_field('globalapi_credencial_form'); ?>
        
        <div class="form-section">
            <h3>Información General</h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="nombre">Nombre de la Credencial *</label>
                    </th>
                    <td>
                        <input type="text" id="nombre" name="nombre" value="<?php echo esc_attr($nombre); ?>" 
                               class="regular-text" required>
                        <p class="description">Nombre descriptivo para identificar esta credencial.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="descripcion">Descripción</label>
                    </th>
                    <td>
                        <textarea id="descripcion" name="descripcion" rows="3" class="large-text"><?php echo esc_textarea($descripcion); ?></textarea>
                        <p class="description">Descripción opcional de la credencial y su uso.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tipo_servicio">Tipo de Servicio *</label>
                    </th>
                    <td>
                        <select id="tipo_servicio" name="tipo_servicio" required onchange="mostrarCamposEspecificos()">
                            <option value="">Selecciona un tipo</option>
                            <option value="groundhogg" <?php selected($tipo_servicio, 'groundhogg'); ?>>Groundhogg CRM</option>
                            <option value="invisioncommunity" <?php selected($tipo_servicio, 'invisioncommunity'); ?>>InvisionCommunity OAuth</option>
                        </select>
                        <p class="description">Tipo de API o servicio para esta credencial.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="form-section">
            <h3>Configuración de API</h3>
            
            <!-- Campos Comunes -->
            <table class="form-table" id="campos-comunes">
                <tr>
                    <th scope="row">
                        <label for="estado">Estado</label>
                    </th>
                    <td>
                        <select id="estado" name="estado">
                            <option value="activa" <?php selected($estado, 'activa'); ?>>Activa</option>
                            <option value="inactiva" <?php selected($estado, 'inactiva'); ?>>Inactiva</option>
                            <option value="expirada" <?php selected($estado, 'expirada'); ?>>Expirada</option>
                        </select>
                        <p class="description">Estado actual de la credencial.</p>
                    </td>
                </tr>
            </table>
            
            <!-- Campos específicos para Groundhogg CRM -->
            <table class="form-table campos-especificos" id="campos-groundhogg" style="display: none;">
                <tr><td colspan="2"><h4>🔌 Configuración Groundhogg CRM</h4></td></tr>
                <tr>
                    <th scope="row">
                        <label for="gh_base_url_v3">Base URL v3 *</label>
                    </th>
                    <td>
                        <input type="url" id="gh_base_url_v3" name="gh_base_url_v3" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_gh_base_url_v3', true)); ?>" 
                               class="regular-text" placeholder="https://crm.colombiahumana.co/wp-json/gh/v3">
                        <p class="description">URL base para API v3 de Groundhogg</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="gh_base_url_v4">Base URL v4 *</label>
                    </th>
                    <td>
                        <input type="url" id="gh_base_url_v4" name="gh_base_url_v4" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_gh_base_url_v4', true)); ?>" 
                               class="regular-text" placeholder="https://crm.colombiahumana.co/wp-json/gh/v4">
                        <p class="description">URL base para API v4 de Groundhogg</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="gh_clave_publica">Clave Pública *</label>
                    </th>
                    <td>
                        <input type="text" id="gh_clave_publica" name="gh_clave_publica" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_gh_clave_publica', true)); ?>" 
                               class="regular-text" placeholder="272df11587153a9d6945c57dd18ed0fe">
                        <p class="description">Clave pública de Groundhogg</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="gh_token">Token *</label>
                    </th>
                    <td>
                        <input type="text" id="gh_token" name="gh_token" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_gh_token', true)); ?>" 
                               class="regular-text" placeholder="85743d02efbde665a7f81241a4d6dc5e">
                        <p class="description">Token de autenticación de Groundhogg</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="gh_llave_secreta">Llave Secreta *</label>
                    </th>
                    <td>
                        <input type="password" id="gh_llave_secreta" name="gh_llave_secreta" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_gh_llave_secreta', true)); ?>" 
                               class="regular-text" placeholder="6120fabab6e880eb9ae744a320ed6905">
                        <p class="description">Llave secreta de Groundhogg</p>
                        <label><input type="checkbox" class="show-password" data-target="gh_llave_secreta"> Mostrar llave secreta</label>
                    </td>
                </tr>
            </table>
            
            <!-- Campos específicos para InvisionCommunity OAuth -->
            <table class="form-table campos-especificos" id="campos-invisioncommunity" style="display: none;">
                <tr><td colspan="2"><h4>🔐 Configuración InvisionCommunity OAuth</h4></td></tr>
                <tr>
                    <th scope="row">
                        <label for="ic_authorization_url">Authorization URL *</label>
                    </th>
                    <td>
                        <input type="url" id="ic_authorization_url" name="ic_authorization_url" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_authorization_url', true)); ?>" 
                               class="regular-text" placeholder="https://www.colombiahumana.co/oauth/authorize/">
                        <p class="description">URL de autorización OAuth</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_token_url">Token URL *</label>
                    </th>
                    <td>
                        <input type="url" id="ic_token_url" name="ic_token_url" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_token_url', true)); ?>" 
                               class="regular-text" placeholder="https://www.colombiahumana.co/oauth/token/">
                        <p class="description">URL para obtener tokens OAuth</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_client_identifier">Client Identifier *</label>
                    </th>
                    <td>
                        <input type="text" id="ic_client_identifier" name="ic_client_identifier" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_client_identifier', true)); ?>" 
                               class="regular-text" placeholder="864edc8128e535df0e2b8382074b5f74">
                        <p class="description">Identificador del cliente OAuth</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_client_secret">Client Secret *</label>
                    </th>
                    <td>
                        <input type="password" id="ic_client_secret" name="ic_client_secret" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_client_secret', true)); ?>" 
                               class="regular-text" placeholder="3d75e6e4a613fed11ae85dacc72e09e16b80ed0d389ac5d0">
                        <p class="description">Secret del cliente OAuth</p>
                        <label><input type="checkbox" class="show-password" data-target="ic_client_secret"> Mostrar client secret</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_rest_api_key">REST API Key *</label>
                    </th>
                    <td>
                        <input type="text" id="ic_rest_api_key" name="ic_rest_api_key" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_rest_api_key', true)); ?>" 
                               class="regular-text" placeholder="5ad68fff23b4ef111cf387b4e924ce2c">
                        <p class="description">Clave de API REST de InvisionCommunity</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_scopes">Scopes Requeridos</label>
                    </th>
                    <td>
                        <input type="text" id="ic_scopes" name="ic_scopes" 
                               value="<?php echo esc_attr(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_scopes', true) ?: 'read,profile'); ?>" 
                               class="regular-text" placeholder="read,profile">
                        <p class="description">Scopes separados por comas (ej: read,profile)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ic_grant_type">Grant Type</label>
                    </th>
                    <td>
                        <select id="ic_grant_type" name="ic_grant_type">
                            <option value="authorization_code" <?php selected(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_grant_type', true), 'authorization_code'); ?>>Authorization Code</option>
                            <option value="client_credentials" <?php selected(get_post_meta($is_edit ? $credencial->ID : 0, '_globalapi_ic_grant_type', true), 'client_credentials'); ?>>Client Credentials</option>
                        </select>
                        <p class="description">Tipo de concesión OAuth 2.0</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="form-actions">
            <input type="submit" name="globalapi_save_credencial" class="button button-primary" 
                   value="<?php echo $is_edit ? 'Actualizar Credencial' : 'Crear Credencial'; ?>">
            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales'); ?>" class="button button-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

<style>
.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.globalapi-form {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 8px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f1;
}

.form-actions .button {
    margin-right: 10px;
}

.show-password {
    margin-top: 8px;
}

.campos-especificos h4 {
    color: #2271b1;
    margin: 0;
    padding: 10px 0;
    border-bottom: 2px solid #f0f0f1;
    font-size: 14px;
    font-weight: 600;
}

.campos-especificos {
    border: 1px solid #e0e5eb;
    border-radius: 4px;
    margin-top: 15px;
    background: #fafafa;
}

.campos-especificos td, .campos-especificos th {
    padding: 15px !important;
}

.campos-especificos input[type="text"],
.campos-especificos input[type="url"],
.campos-especificos input[type="password"],
.campos-especificos select {
    background: #fff;
    border: 1px solid #8c8f94;
}

.campos-especificos input[placeholder] {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

#campos-comunes {
    margin-bottom: 20px;
}
</style>

<script>
function mostrarCamposEspecificos() {
    const tipoServicio = document.getElementById('tipo_servicio').value;
    
    // Ocultar todas las tablas específicas
    document.querySelectorAll('.campos-especificos').forEach(tabla => {
        tabla.style.display = 'none';
    });
    
    // Mostrar la tabla correspondiente
    if (tipoServicio === 'groundhogg') {
        document.getElementById('campos-groundhogg').style.display = 'table';
    } else if (tipoServicio === 'invisioncommunity') {
        document.getElementById('campos-invisioncommunity').style.display = 'table';
    }
}

jQuery(document).ready(function($) {
    // Mostrar campos específicos al cargar la página
    mostrarCamposEspecificos();
    
    // Mostrar/ocultar passwords
    $('.show-password').change(function() {
        const targetId = $(this).data('target');
        const targetField = $('#' + targetId);
        if (this.checked) {
            targetField.attr('type', 'text');
        } else {
            targetField.attr('type', 'password');
        }
    });
});
</script> 