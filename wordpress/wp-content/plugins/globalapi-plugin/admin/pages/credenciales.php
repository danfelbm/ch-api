<?php
/**
 * Página de gestión de credenciales
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$titulo = isset($titulo) ? $titulo : 'Gestión de Credenciales';

// Obtener acción actual
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$credencial_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Procesar acciones
switch ($action) {
    case 'new':
        include_once GLOBALAPI_PLUGIN_PATH . 'admin/pages/credencial-form.php';
        return;
    case 'edit':
        if ($credencial_id) {
            $credencial = get_post($credencial_id);
            if ($credencial && $credencial->post_type === 'globalapi_credencial') {
                include_once GLOBALAPI_PLUGIN_PATH . 'admin/pages/credencial-form.php';
                return;
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=globalapi-credenciales&error=not_found'));
        exit;
    case 'test':
        if ($credencial_id) {
            include_once GLOBALAPI_PLUGIN_PATH . 'admin/pages/credencial-test.php';
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=globalapi-credenciales&error=not_found'));
        exit;
    case 'delete':
        if ($credencial_id && wp_verify_nonce($_GET['_wpnonce'], 'delete_credencial_' . $credencial_id)) {
            $credencial = get_post($credencial_id);
            if ($credencial && $credencial->post_type === 'globalapi_credencial') {
                wp_delete_post($credencial_id, true);
                wp_safe_redirect(admin_url('admin.php?page=globalapi-credenciales&mensaje=deleted'));
                exit;
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=globalapi-credenciales&error=delete_failed'));
        exit;
}
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <?php
    // Mostrar mensajes
    if (isset($_GET['mensaje'])) {
        $mensaje = sanitize_text_field($_GET['mensaje']);
        switch ($mensaje) {
            case 'created':
                echo '<div class="notice notice-success is-dismissible"><p><strong>¡Éxito!</strong> Credencial creada correctamente.</p></div>';
                break;
            case 'updated':
                echo '<div class="notice notice-success is-dismissible"><p><strong>¡Éxito!</strong> Credencial actualizada correctamente.</p></div>';
                break;
            case 'deleted':
                echo '<div class="notice notice-success is-dismissible"><p><strong>¡Éxito!</strong> Credencial eliminada correctamente.</p></div>';
                break;
        }
    }
    
    if (isset($_GET['error'])) {
        $error = sanitize_text_field($_GET['error']);
        switch ($error) {
            case 'not_found':
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Credencial no encontrada.</p></div>';
                break;
            case 'permission_denied':
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No tienes permisos para realizar esta acción.</p></div>';
                break;
            case 'delete_failed':
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No se pudo eliminar la credencial.</p></div>';
                break;
        }
    }
    ?>
    
    <div class="globalapi-notice info">
        <p><strong>Gestión de Credenciales API:</strong> Administra de forma segura las credenciales para APIs externas.</p>
    </div>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=new'); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                Nueva Credencial
            </a>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo de Servicio</th>
                <th>Estado</th>
                <th>Última Actualización</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Obtener credenciales
            $credenciales = get_posts(array(
                'post_type' => 'globalapi_credencial',
                'post_status' => array('publish', 'private'),
                'numberposts' => -1
            ));

            if (empty($credenciales)):
            ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">
                        <div class="no-items">
                            <span class="dashicons dashicons-lock" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></span>
                            <br>
                            <strong>No hay credenciales configuradas</strong>
                            <br>
                            <p>Crea tu primera credencial para comenzar a gestionar APIs externas.</p>
                            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=new'); ?>" class="button button-primary">
                                Nueva Credencial
                            </a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($credenciales as $credencial): 
                    $tipo_servicio = get_post_meta($credencial->ID, '_globalapi_tipo_servicio', true);
                    $estado = get_post_meta($credencial->ID, '_globalapi_estado', true);
                    $estado_clase = '';
                    
                    switch ($estado) {
                        case 'activa':
                            $estado_clase = 'success';
                            $estado_texto = 'Activa';
                            break;
                        case 'inactiva':
                            $estado_clase = 'warning';
                            $estado_texto = 'Inactiva';
                            break;
                        case 'expirada':
                            $estado_clase = 'error';
                            $estado_texto = 'Expirada';
                            break;
                        default:
                            $estado_clase = 'info';
                            $estado_texto = 'Sin definir';
                    }
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($credencial->post_title); ?></strong>
                        <br>
                        <small><?php echo esc_html($credencial->post_excerpt); ?></small>
                    </td>
                    <td>
                        <span class="service-type-badge">
                            <?php echo esc_html(ucfirst($tipo_servicio ?: 'No especificado')); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($estado_clase); ?>">
                            <?php echo esc_html($estado_texto); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html(get_the_modified_date('d/m/Y H:i', $credencial->ID)); ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=edit&id=' . $credencial->ID); ?>" class="button button-small">
                                ✏️ Editar
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=test&id=' . $credencial->ID); ?>" class="button button-small">
                                🧪 Probar
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=globalapi-credenciales&action=delete&id=' . $credencial->ID), 'delete_credencial_' . $credencial->ID); ?>" 
                               class="button button-small button-link-delete"
                               onclick="return confirm('¿Estás seguro de eliminar esta credencial? Esta acción no se puede deshacer.');">
                                🗑️ Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.service-type-badge {
    background: #f0f0f1;
    color: #2271b1;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.no-items {
    text-align: center;
    color: #666;
}

.no-items .dashicons {
    display: block;
    margin-bottom: 15px;
}

.row-actions {
    display: flex;
    gap: 5px;
}

.tablenav {
    margin: 15px 0;
}

.button-link-delete {
    color: #b32d2e !important;
    border-color: #b32d2e !important;
}

.button-link-delete:hover {
    background: #b32d2e !important;
    color: #fff !important;
    border-color: #b32d2e !important;
}
</style> 