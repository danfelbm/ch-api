<?php
/**
 * Dashboard principal del plugin GlobalAPI
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Variables pasadas desde el controlador
$titulo = isset($titulo) ? $titulo : 'Dashboard Principal';
$estadisticas = isset($estadisticas) ? $estadisticas : array();
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <div class="globalapi-notice info">
        <p><strong>¡Bienvenido al sistema GlobalAPI!</strong> Plugin de infraestructura para gestión segura de APIs y credenciales.</p>
    </div>

    <div class="globalapi-dashboard-widgets">
        <div class="widget-row">
            <!-- Widget de Credenciales -->
            <div class="widget-box">
                <h3><span class="dashicons dashicons-lock"></span> Credenciales</h3>
                <div class="widget-content">
                    <?php if (isset($estadisticas['credenciales'])): ?>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo esc_html($estadisticas['credenciales']['total']); ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                        <div class="stat-item success">
                            <span class="stat-number"><?php echo esc_html($estadisticas['credenciales']['activas']); ?></span>
                            <span class="stat-label">Activas</span>
                        </div>
                        <div class="stat-item warning">
                            <span class="stat-number"><?php echo esc_html($estadisticas['credenciales']['inactivas']); ?></span>
                            <span class="stat-label">Inactivas</span>
                        </div>
                        <div class="stat-item error">
                            <span class="stat-number"><?php echo esc_html($estadisticas['credenciales']['expiradas']); ?></span>
                            <span class="stat-label">Expiradas</span>
                        </div>
                    <?php else: ?>
                        <p>Sin datos de credenciales disponibles.</p>
                    <?php endif; ?>
                </div>
                <div class="widget-actions">
                    <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales'); ?>" class="button button-primary">
                        Gestionar Credenciales
                    </a>
                </div>
            </div>

            <!-- Widget de Logs -->
            <div class="widget-box">
                <h3><span class="dashicons dashicons-visibility"></span> Auditoría</h3>
                <div class="widget-content">
                    <?php if (isset($estadisticas['logs'])): ?>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo esc_html($estadisticas['logs']['total_24h']); ?></span>
                            <span class="stat-label">Últimas 24h</span>
                        </div>
                        <div class="stat-item info">
                            <span class="stat-number"><?php echo esc_html($estadisticas['logs']['info_24h']); ?></span>
                            <span class="stat-label">Info</span>
                        </div>
                        <div class="stat-item warning">
                            <span class="stat-number"><?php echo esc_html($estadisticas['logs']['warnings_24h']); ?></span>
                            <span class="stat-label">Avisos</span>
                        </div>
                        <div class="stat-item error">
                            <span class="stat-number"><?php echo esc_html($estadisticas['logs']['errores_24h']); ?></span>
                            <span class="stat-label">Errores</span>
                        </div>
                    <?php else: ?>
                        <p>Sin datos de logs disponibles.</p>
                    <?php endif; ?>
                </div>
                <div class="widget-actions">
                    <a href="<?php echo admin_url('admin.php?page=globalapi-logs'); ?>" class="button">
                        Ver Logs
                    </a>
                </div>
            </div>

            <!-- Widget de Estado del Sistema -->
            <div class="widget-box">
                <h3><span class="dashicons dashicons-admin-tools"></span> Estado del Sistema</h3>
                <div class="widget-content">
                    <div class="system-status">
                        <div class="status-item">
                            <span class="status-icon success">●</span>
                            <span class="status-label">Plugin Activo</span>
                        </div>
                        <div class="status-item">
                            <span class="status-icon <?php echo (function_exists('curl_version')) ? 'success' : 'error'; ?>">●</span>
                            <span class="status-label">cURL <?php echo (function_exists('curl_version')) ? 'Disponible' : 'No disponible'; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-icon <?php echo (extension_loaded('openssl')) ? 'success' : 'warning'; ?>">●</span>
                            <span class="status-label">OpenSSL <?php echo (extension_loaded('openssl')) ? 'Disponible' : 'No disponible'; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-icon success">●</span>
                            <span class="status-label">API REST Funcionando</span>
                        </div>
                    </div>
                </div>
                <div class="widget-actions">
                    <a href="<?php echo admin_url('admin.php?page=globalapi-estado'); ?>" class="button">
                        Ver Estado Completo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="globalapi-quick-actions">
        <h3>Acciones Rápidas</h3>
        <div class="quick-actions-row">
            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=new'); ?>" class="quick-action">
                <span class="dashicons dashicons-plus-alt"></span>
                Nueva Credencial
            </a>
            <a href="<?php echo admin_url('admin.php?page=globalapi-configuracion'); ?>" class="quick-action">
                <span class="dashicons dashicons-admin-settings"></span>
                Configuración
            </a>
            <a href="<?php echo admin_url('admin.php?page=globalapi-logs'); ?>" class="quick-action">
                <span class="dashicons dashicons-list-view"></span>
                Ver Logs
            </a>
            <a href="<?php echo admin_url('admin.php?page=globalapi-documentacion'); ?>" class="quick-action">
                <span class="dashicons dashicons-book"></span>
                Documentación
            </a>
        </div>
    </div>
</div>

<style>
.globalapi-dashboard-widgets {
    margin: 20px 0;
}

.widget-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.widget-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.widget-box h3 {
    background: #f8f9fa;
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    font-size: 16px;
    color: #333;
}

.widget-box h3 .dashicons {
    margin-right: 8px;
    color: #2271b1;
}

.widget-content {
    padding: 20px;
}

.stat-item {
    display: inline-block;
    text-align: center;
    margin-right: 20px;
    margin-bottom: 10px;
    min-width: 60px;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-item.success .stat-number { color: #46b450; }
.stat-item.warning .stat-number { color: #ffb900; }
.stat-item.error .stat-number { color: #dc3232; }
.stat-item.info .stat-number { color: #00a0d2; }

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

.widget-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #ddd;
}

.system-status .status-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.status-icon {
    font-size: 12px;
    margin-right: 8px;
    width: 12px;
}

.status-icon.success { color: #46b450; }
.status-icon.warning { color: #ffb900; }
.status-icon.error { color: #dc3232; }

.globalapi-quick-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.quick-actions-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.quick-action {
    display: inline-flex;
    align-items: center;
    padding: 10px 15px;
    background: #f0f0f1;
    color: #2271b1;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: all 0.2s;
}

.quick-action:hover {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.quick-action .dashicons {
    margin-right: 6px;
    font-size: 16px;
}
</style> 