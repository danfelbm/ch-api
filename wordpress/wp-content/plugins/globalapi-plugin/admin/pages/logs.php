<?php
/**
 * Página de logs de auditoría
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$titulo = isset($titulo) ? $titulo : 'Logs de Auditoría';
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <div class="globalapi-notice info">
        <p><strong>Auditoría del Sistema:</strong> Registro completo de actividades y eventos del plugin.</p>
    </div>

    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="filtro-severidad">
                <option value="">Todas las severidades</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="critical">Crítico</option>
            </select>
            
            <select id="filtro-tipo">
                <option value="">Todos los tipos</option>
                <option value="admin_access">Acceso Admin</option>
                <option value="api_call">Llamada API</option>
                <option value="login">Login</option>
                <option value="error">Error</option>
            </select>
            
            <button type="button" class="button">Filtrar</button>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" onclick="exportarLogs()">
                <span class="dashicons dashicons-download"></span>
                Exportar CSV
            </button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 150px;">Fecha/Hora</th>
                <th style="width: 100px;">Severidad</th>
                <th style="width: 120px;">Tipo</th>
                <th>Descripción</th>
                <th style="width: 100px;">Usuario</th>
                <th style="width: 80px;">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Obtener logs
            $logs = get_posts(array(
                'post_type' => 'globalapi_log',
                'post_status' => 'publish',
                'numberposts' => 50,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            if (empty($logs)):
            ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <div class="no-items">
                            <span class="dashicons dashicons-visibility" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></span>
                            <br>
                            <strong>No hay logs registrados</strong>
                            <br>
                            <p>Los logs de auditoría aparecerán aquí conforme se use el sistema.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $severidad = get_post_meta($log->ID, '_globalapi_severidad', true);
                    $tipo_evento = get_post_meta($log->ID, '_globalapi_tipo_evento', true);
                    $ip_cliente = get_post_meta($log->ID, '_globalapi_ip_cliente', true);
                    $usuario_id = get_post_meta($log->ID, '_globalapi_usuario_id', true);
                    
                    $severidad_clase = '';
                    switch ($severidad) {
                        case 'info':
                            $severidad_clase = 'info';
                            break;
                        case 'warning':
                            $severidad_clase = 'warning';
                            break;
                        case 'error':
                        case 'critical':
                            $severidad_clase = 'error';
                            break;
                        default:
                            $severidad_clase = 'info';
                    }
                    
                    $usuario_nombre = 'Sistema';
                    if ($usuario_id) {
                        $usuario = get_userdata($usuario_id);
                        if ($usuario) {
                            $usuario_nombre = $usuario->display_name;
                        }
                    }
                ?>
                <tr>
                    <td>
                        <?php echo esc_html(get_the_date('d/m/Y H:i:s', $log->ID)); ?>
                    </td>
                    <td>
                        <span class="severity-badge severity-<?php echo esc_attr($severidad_clase); ?>">
                            <?php echo esc_html(ucfirst($severidad ?: 'info')); ?>
                        </span>
                    </td>
                    <td>
                        <span class="event-type-badge">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $tipo_evento ?: 'sistema'))); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo esc_html($log->post_title); ?></strong>
                        <?php if ($log->post_content): ?>
                            <br>
                            <small><?php echo esc_html(wp_trim_words($log->post_content, 20)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($usuario_nombre); ?>
                    </td>
                    <td>
                        <code><?php echo esc_html($ip_cliente ?: 'N/A'); ?></code>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($logs)): ?>
    <div class="tablenav bottom">
        <div class="alignleft actions">
            <span class="displaying-num"><?php echo count($logs); ?> elementos</span>
        </div>
        <div class="alignright">
            <a href="#" class="button">Cargar más</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportarLogs() {
    // Implementar exportación CSV
    alert('Funcionalidad en desarrollo - Exportar logs a CSV');
}
</script>

<style>
.severity-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.severity-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.severity-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.severity-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.event-type-badge {
    background: #f0f0f1;
    color: #2271b1;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 500;
}

.no-items {
    text-align: center;
    color: #666;
}

.no-items .dashicons {
    display: block;
    margin-bottom: 15px;
}

.tablenav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
    padding: 10px 0;
}

.tablenav select {
    margin-right: 10px;
}

code {
    font-size: 11px;
    color: #666;
}
</style> 