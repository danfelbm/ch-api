<?php
/**
 * Página de configuración del plugin
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$titulo = isset($titulo) ? $titulo : 'Configuración del Plugin';
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <div class="globalapi-notice info">
        <p><strong>Configuración General:</strong> Ajusta los parámetros de funcionamiento del plugin GlobalAPI.</p>
    </div>

    <form method="post" action="options.php">
        <?php
        settings_fields('globalapi_settings');
        do_settings_sections('globalapi_settings');
        ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="globalapi_timeout">Timeout de API (segundos)</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="globalapi_timeout" 
                            name="globalapi_timeout" 
                            value="<?php echo esc_attr(get_option('globalapi_timeout', 30)); ?>" 
                            min="5" 
                            max="300" 
                            class="regular-text"
                        >
                        <p class="description">Tiempo máximo de espera para las conexiones API (5-300 segundos).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_cache_duration">Duración del Cache (minutos)</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="globalapi_cache_duration" 
                            name="globalapi_cache_duration" 
                            value="<?php echo esc_attr(get_option('globalapi_cache_duration', 60)); ?>" 
                            min="1" 
                            max="1440" 
                            class="regular-text"
                        >
                        <p class="description">Tiempo que se guardan en cache las respuestas de API (1-1440 minutos).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_max_logs">Máximo de Logs</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="globalapi_max_logs" 
                            name="globalapi_max_logs" 
                            value="<?php echo esc_attr(get_option('globalapi_max_logs', 1000)); ?>" 
                            min="100" 
                            max="10000" 
                            class="regular-text"
                        >
                        <p class="description">Número máximo de logs de auditoría a mantener (100-10000).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_debug_mode">Modo Debug</label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input 
                                    type="checkbox" 
                                    id="globalapi_debug_mode" 
                                    name="globalapi_debug_mode" 
                                    value="1" 
                                    <?php checked(get_option('globalapi_debug_mode', 0), 1); ?>
                                >
                                Habilitar logging detallado para desarrollo
                            </label>
                            <p class="description">Activa logs detallados para debugging. <strong>No usar en producción.</strong></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_ssl_verify">Verificación SSL</label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input 
                                    type="checkbox" 
                                    id="globalapi_ssl_verify" 
                                    name="globalapi_ssl_verify" 
                                    value="1" 
                                    <?php checked(get_option('globalapi_ssl_verify', 1), 1); ?>
                                >
                                Verificar certificados SSL en conexiones
                            </label>
                            <p class="description">Recomendado mantener activo por seguridad.</p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_rate_limit">Rate Limiting</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="globalapi_rate_limit" 
                            name="globalapi_rate_limit" 
                            value="<?php echo esc_attr(get_option('globalapi_rate_limit', 100)); ?>" 
                            min="10" 
                            max="1000" 
                            class="regular-text"
                        >
                        <p class="description">Número máximo de peticiones API por hora (10-1000).</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>Configuración de Seguridad</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="globalapi_encryption_key">Clave de Encriptación</label>
                    </th>
                    <td>
                        <input 
                            type="password" 
                            id="globalapi_encryption_key" 
                            name="globalapi_encryption_key" 
                            value="<?php echo esc_attr(str_repeat('*', 32)); ?>" 
                            class="regular-text" 
                            readonly
                        >
                        <p class="description">
                            Clave para encriptar credenciales sensibles. 
                            <strong>Generada automáticamente.</strong>
                            <button type="button" class="button" onclick="alert('La regeneración de clave requiere re-encriptar todas las credenciales existentes.')">
                                Regenerar
                            </button>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_allowed_ips">IPs Permitidas</label>
                    </th>
                    <td>
                        <textarea 
                            id="globalapi_allowed_ips" 
                            name="globalapi_allowed_ips" 
                            rows="4" 
                            class="large-text"
                            placeholder="192.168.1.100&#10;10.0.0.0/8&#10;203.0.113.0/24"
                        ><?php echo esc_textarea(get_option('globalapi_allowed_ips', '')); ?></textarea>
                        <p class="description">IPs o rangos permitidos para acceder a la API. Una por línea. Vacío = todos permitidos.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>Configuración de Notificaciones</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="globalapi_email_alerts">Email de Alertas</label>
                    </th>
                    <td>
                        <input 
                            type="email" 
                            id="globalapi_email_alerts" 
                            name="globalapi_email_alerts" 
                            value="<?php echo esc_attr(get_option('globalapi_email_alerts', get_option('admin_email'))); ?>" 
                            class="regular-text"
                        >
                        <p class="description">Email donde enviar alertas de errores críticos.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="globalapi_webhook_url">Webhook URL</label>
                    </th>
                    <td>
                        <input 
                            type="url" 
                            id="globalapi_webhook_url" 
                            name="globalapi_webhook_url" 
                            value="<?php echo esc_attr(get_option('globalapi_webhook_url', '')); ?>" 
                            class="regular-text"
                            placeholder="https://hooks.slack.com/services/..."
                        >
                        <p class="description">URL webhook para notificaciones (Slack, Discord, etc).</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('Guardar Configuración'); ?>
    </form>

    <hr>

    <h3>Acciones del Sistema</h3>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">Limpiar Cache</th>
                <td>
                    <button type="button" class="button" onclick="limpiarCache()">
                        Limpiar Cache de API
                    </button>
                    <p class="description">Elimina todas las respuestas cacheadas de APIs.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Limpiar Logs Antiguos</th>
                <td>
                    <button type="button" class="button" onclick="limpiarLogs()">
                        Limpiar Logs > 30 días
                    </button>
                    <p class="description">Elimina logs de auditoría mayores a 30 días.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Verificar Sistema</th>
                <td>
                    <button type="button" class="button" onclick="verificarSistema()">
                        Ejecutar Verificación
                    </button>
                    <p class="description">Verifica el estado de todas las credenciales y conexiones.</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
function limpiarCache() {
    if (confirm('¿Estás seguro de que deseas limpiar todo el cache de API?')) {
        // Implementar AJAX para limpiar cache
        alert('Funcionalidad en desarrollo - Cache limpiado');
    }
}

function limpiarLogs() {
    if (confirm('¿Estás seguro de que deseas eliminar logs antiguos?')) {
        // Implementar AJAX para limpiar logs
        alert('Funcionalidad en desarrollo - Logs limpiados');
    }
}

function verificarSistema() {
    // Implementar AJAX para verificar sistema
    alert('Funcionalidad en desarrollo - Sistema verificado');
}
</script>

<style>
.form-table th {
    width: 200px;
}

.form-table input[type="number"],
.form-table input[type="email"],
.form-table input[type="url"],
.form-table input[type="password"] {
    width: 300px;
}

.form-table textarea {
    width: 500px;
}

hr {
    margin: 30px 0;
}

h3 {
    margin-top: 30px;
    color: #2271b1;
}
</style> 