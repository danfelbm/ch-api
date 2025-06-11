<?php
/**
 * Página de estado del sistema
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$titulo = isset($titulo) ? $titulo : 'Estado del Sistema';
$estado_apis = isset($estado_apis) ? $estado_apis : array();
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <div class="globalapi-notice info">
        <p><strong>Diagnóstico del Sistema:</strong> Estado actual del plugin y sus componentes.</p>
    </div>

    <!-- Estado General -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-admin-tools"></span> Estado General</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>Plugin Activo</th>
                    <td>
                        <span class="status-indicator success">●</span>
                        <strong>Activo</strong> - Versión <?php echo esc_html(GLOBALAPI_VERSION); ?>
                    </td>
                </tr>
                <tr>
                    <th>WordPress</th>
                    <td>
                        <span class="status-indicator success">●</span>
                        Versión <?php echo esc_html(get_bloginfo('version')); ?>
                    </td>
                </tr>
                <tr>
                    <th>PHP</th>
                    <td>
                        <?php $php_version = phpversion(); ?>
                        <span class="status-indicator <?php echo version_compare($php_version, '7.4', '>=') ? 'success' : 'warning'; ?>">●</span>
                        Versión <?php echo esc_html($php_version); ?>
                        <?php if (version_compare($php_version, '7.4', '<')): ?>
                            <small style="color: #d63638;"> - Se recomienda PHP 7.4+</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Base de Datos</th>
                    <td>
                        <?php global $wpdb; ?>
                        <span class="status-indicator success">●</span>
                        <?php echo esc_html($wpdb->db_version()); ?> 
                        <small>(<?php echo esc_html(DB_HOST); ?>)</small>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Extensiones PHP -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-admin-plugins"></span> Extensiones PHP</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>cURL</th>
                    <td>
                        <?php $curl_available = function_exists('curl_version'); ?>
                        <span class="status-indicator <?php echo $curl_available ? 'success' : 'error'; ?>">●</span>
                        <?php echo $curl_available ? 'Disponible' : 'No disponible'; ?>
                        <?php if ($curl_available): ?>
                            <small> - <?php echo esc_html(curl_version()['version']); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>OpenSSL</th>
                    <td>
                        <?php $openssl_available = extension_loaded('openssl'); ?>
                        <span class="status-indicator <?php echo $openssl_available ? 'success' : 'warning'; ?>">●</span>
                        <?php echo $openssl_available ? 'Disponible' : 'No disponible'; ?>
                        <?php if ($openssl_available): ?>
                            <small> - <?php echo esc_html(OPENSSL_VERSION_TEXT); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>JSON</th>
                    <td>
                        <?php $json_available = extension_loaded('json'); ?>
                        <span class="status-indicator <?php echo $json_available ? 'success' : 'error'; ?>">●</span>
                        <?php echo $json_available ? 'Disponible' : 'No disponible'; ?>
                    </td>
                </tr>
                <tr>
                    <th>mbstring</th>
                    <td>
                        <?php $mbstring_available = extension_loaded('mbstring'); ?>
                        <span class="status-indicator <?php echo $mbstring_available ? 'success' : 'warning'; ?>">●</span>
                        <?php echo $mbstring_available ? 'Disponible' : 'No disponible'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Custom Post Types -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-admin-post"></span> Custom Post Types</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>globalapi_credencial</th>
                    <td>
                        <?php $cpt_credencial = post_type_exists('globalapi_credencial'); ?>
                        <span class="status-indicator <?php echo $cpt_credencial ? 'success' : 'error'; ?>">●</span>
                        <?php echo $cpt_credencial ? 'Registrado' : 'No registrado'; ?>
                        <?php if ($cpt_credencial): ?>
                            <?php 
                            $count_credenciales = wp_count_posts('globalapi_credencial');
                            $total_credenciales = $count_credenciales->publish + $count_credenciales->private;
                            ?>
                            <small> - <?php echo esc_html($total_credenciales); ?> credenciales</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>globalapi_log</th>
                    <td>
                        <?php $cpt_log = post_type_exists('globalapi_log'); ?>
                        <span class="status-indicator <?php echo $cpt_log ? 'success' : 'error'; ?>">●</span>
                        <?php echo $cpt_log ? 'Registrado' : 'No registrado'; ?>
                        <?php if ($cpt_log): ?>
                            <?php 
                            $count_logs = wp_count_posts('globalapi_log');
                            $total_logs = $count_logs->publish;
                            ?>
                            <small> - <?php echo esc_html($total_logs); ?> logs</small>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- API REST -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-rest-api"></span> API REST</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>REST API WordPress</th>
                    <td>
                        <span class="status-indicator success">●</span>
                        Funcionando
                        <small> - <?php echo esc_html(rest_url('wp/v2/')); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>GlobalAPI Endpoints</th>
                    <td>
                        <span class="status-indicator success">●</span>
                        Registrados
                        <small> - <?php echo esc_html(rest_url('globalapi/v1/')); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>Prueba de Conectividad</th>
                    <td>
                        <button type="button" class="button" onclick="probarAPI()">
                            <span class="dashicons dashicons-update"></span>
                            Probar API
                        </button>
                        <span id="api-test-result"></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Permisos y Seguridad -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-lock"></span> Permisos y Seguridad</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>Directorio del Plugin</th>
                    <td>
                        <?php $plugin_writable = is_writable(GLOBALAPI_PLUGIN_PATH); ?>
                        <span class="status-indicator <?php echo $plugin_writable ? 'warning' : 'success'; ?>">●</span>
                        <?php echo $plugin_writable ? 'Escribible (revisar permisos)' : 'Solo lectura (correcto)'; ?>
                        <small> - <?php echo esc_html(GLOBALAPI_PLUGIN_PATH); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>wp-content/uploads</th>
                    <td>
                        <?php $uploads_dir = wp_upload_dir(); ?>
                        <?php $uploads_writable = is_writable($uploads_dir['basedir']); ?>
                        <span class="status-indicator <?php echo $uploads_writable ? 'success' : 'error'; ?>">●</span>
                        <?php echo $uploads_writable ? 'Escribible' : 'No escribible'; ?>
                        <small> - <?php echo esc_html($uploads_dir['basedir']); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>SSL/HTTPS</th>
                    <td>
                        <?php $is_ssl = is_ssl(); ?>
                        <span class="status-indicator <?php echo $is_ssl ? 'success' : 'warning'; ?>">●</span>
                        <?php echo $is_ssl ? 'Activo' : 'No activo'; ?>
                        <?php if (!$is_ssl): ?>
                            <small style="color: #d63638;"> - Se recomienda HTTPS para seguridad</small>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Información del Servidor -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-admin-generic"></span> Información del Servidor</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>Servidor Web</th>
                    <td>
                        <?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'); ?>
                    </td>
                </tr>
                <tr>
                    <th>Memoria PHP</th>
                    <td>
                        <?php 
                        $memory_limit = ini_get('memory_limit');
                        $memory_usage = memory_get_usage(true);
                        $memory_peak = memory_get_peak_usage(true);
                        ?>
                        <strong>Límite:</strong> <?php echo esc_html($memory_limit); ?> |
                        <strong>Uso actual:</strong> <?php echo esc_html(size_format($memory_usage)); ?> |
                        <strong>Pico:</strong> <?php echo esc_html(size_format($memory_peak)); ?>
                    </td>
                </tr>
                <tr>
                    <th>Tiempo de Ejecución</th>
                    <td>
                        <?php echo esc_html(ini_get('max_execution_time')); ?> segundos
                    </td>
                </tr>
                <tr>
                    <th>Upload Max Size</th>
                    <td>
                        <?php echo esc_html(ini_get('upload_max_filesize')); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Acciones de Diagnóstico -->
    <div class="status-section">
        <h3><span class="dashicons dashicons-admin-tools"></span> Acciones de Diagnóstico</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>Información Completa</th>
                    <td>
                        <button type="button" class="button" onclick="descargarDiagnostico()">
                            <span class="dashicons dashicons-download"></span>
                            Descargar Reporte
                        </button>
                        <p class="description">Descarga un reporte completo del sistema para soporte técnico.</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function probarAPI() {
    const resultSpan = document.getElementById('api-test-result');
    resultSpan.innerHTML = '<span style="color: #666;"> Probando...</span>';
    
    fetch('<?php echo esc_url(rest_url('globalapi/v1/status')); ?>')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'active') {
                resultSpan.innerHTML = '<span style="color: #46b450;"> ✓ API funcionando correctamente</span>';
            } else {
                resultSpan.innerHTML = '<span style="color: #d63638;"> ✗ API con problemas</span>';
            }
        })
        .catch(error => {
            resultSpan.innerHTML = '<span style="color: #d63638;"> ✗ Error de conectividad</span>';
        });
}

function descargarDiagnostico() {
    alert('Funcionalidad en desarrollo - Reporte de diagnóstico');
}
</script>

<style>
.status-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 0;
}

.status-section h3 {
    background: #f8f9fa;
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    font-size: 16px;
    color: #333;
}

.status-section h3 .dashicons {
    margin-right: 8px;
    color: #2271b1;
}

.status-section .form-table {
    margin-bottom: 0;
}

.status-indicator {
    font-size: 12px;
    margin-right: 8px;
}

.status-indicator.success {
    color: #46b450;
}

.status-indicator.warning {
    color: #ffb900;
}

.status-indicator.error {
    color: #dc3232;
}

.form-table th {
    width: 200px;
    font-weight: 600;
}

.form-table td small {
    color: #666;
    font-size: 12px;
}

.button .dashicons {
    font-size: 14px;
    margin-right: 4px;
    vertical-align: middle;
}
</style> 