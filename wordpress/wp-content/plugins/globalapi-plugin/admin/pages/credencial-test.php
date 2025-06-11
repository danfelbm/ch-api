<?php
/**
 * Página de prueba de credencial
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener credencial
$credencial = get_post($credencial_id);
if (!$credencial || $credencial->post_type !== 'globalapi_credencial') {
    wp_safe_redirect(admin_url('admin.php?page=globalapi-credenciales&error=not_found'));
    exit;
}

// Obtener datos de la credencial
$tipo_servicio = get_post_meta($credencial->ID, '_globalapi_tipo_servicio', true);
$estado = get_post_meta($credencial->ID, '_globalapi_estado', true);

// Procesar prueba si se solicitó
$test_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['globalapi_test_credencial'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'globalapi_test_credencial_' . $credencial->ID)) {
        wp_die('Error de seguridad. Inténtalo de nuevo.');
    }
    
    $test_results = test_credencial_connection($credencial->ID, $tipo_servicio);
}

/**
 * Función para probar conexión de credencial
 */
function test_credencial_connection($credencial_id, $tipo_servicio) {
    $results = array(
        'success' => false,
        'message' => '',
        'details' => array(),
        'response_time' => 0,
        'status_code' => 0
    );
    
    $start_time = microtime(true);
    
    try {
        switch ($tipo_servicio) {
            case 'groundhogg':
                $results = test_groundhogg_connection($credencial_id);
                break;
            case 'invisioncommunity':
                $results = test_invisioncommunity_connection($credencial_id);
                break;
            default:
                $results['message'] = 'Tipo de servicio no soportado para pruebas.';
        }
    } catch (Exception $e) {
        $results['message'] = 'Error durante la prueba: ' . $e->getMessage();
    }
    
    $results['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
    
    // Registrar en logs
    if (class_exists('LogAuditoria')) {
        LogAuditoria::registrar_log(array(
            'tipo_evento' => 'credencial_test',
            'descripcion' => 'Prueba de credencial: ' . get_the_title($credencial_id),
            'severidad' => $results['success'] ? 'info' : 'warning',
            'datos_adicionales' => array(
                'credencial_id' => $credencial_id,
                'tipo_servicio' => $tipo_servicio,
                'resultado' => $results['success'] ? 'exitoso' : 'fallido',
                'tiempo_respuesta' => $results['response_time'] . 'ms'
            )
        ));
    }
    
    return $results;
}

/**
 * Probar conexión Groundhogg
 */
function test_groundhogg_connection($credencial_id) {
    $results = array('success' => false, 'message' => '', 'details' => array(), 'raw_response' => '');
    
    // Obtener credenciales
    $base_url_v3 = get_post_meta($credencial_id, '_globalapi_gh_base_url_v3', true);
    $base_url_v4 = get_post_meta($credencial_id, '_globalapi_gh_base_url_v4', true);
    $public_key = get_post_meta($credencial_id, '_globalapi_gh_clave_publica', true);
    $token = get_post_meta($credencial_id, '_globalapi_gh_token', true);
    $secret_key = get_post_meta($credencial_id, '_globalapi_gh_llave_secreta', true);
    
    // Verificar credenciales mínimas
    if (empty($base_url_v3) || empty($public_key) || empty($token)) {
        $results['message'] = '❌ Credenciales incompletas para Groundhogg CRM.';
        $results['details'] = array(
            'base_url_v3' => !empty($base_url_v3) ? '✅ Configurada' : '❌ Falta',
            'public_key' => !empty($public_key) ? '✅ Configurada' : '❌ Falta',
            'token' => !empty($token) ? '✅ Configurado' : '❌ Falta',
            'secret_key' => !empty($secret_key) ? '✅ Configurada' : '❌ Falta'
        );
        return $results;
    }
    
    // Probar endpoint específico: buscar contactos con query "Daniel"
    $test_url = rtrim($base_url_v3, '/') . '/contacts?q=Daniel';
    
    // Preparar headers de autenticación
    $headers = array(
        'Gh-Public-Key' => $public_key,
        'Gh-Token' => $token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    );
    
    // Si hay secret key, agregar autenticación adicional
    if (!empty($secret_key)) {
        $headers['Gh-Secret-Key'] = $secret_key;
    }
    
    // Log para debugging
    error_log('GlobalAPI Test - URL: ' . $test_url);
    error_log('GlobalAPI Test - Headers: ' . json_encode(array_merge($headers, array('Gh-Token' => substr($token, 0, 5) . '...', 'Gh-Secret-Key' => '...'))));
    
    // Hacer petición
    $response = wp_remote_get($test_url, array(
        'headers' => $headers,
        'timeout' => 20,
        'sslverify' => false // Desactivar verificación SSL para pruebas locales
    ));
    
    if (is_wp_error($response)) {
        $results['message'] = '❌ Error de conexión: ' . $response->get_error_message();
        $results['details'] = array(
            'endpoint' => $test_url,
            'error_code' => $response->get_error_code(),
            'error_message' => $response->get_error_message()
        );
        return $results;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $response_headers = wp_remote_retrieve_headers($response);
    $results['status_code'] = $status_code;
    $results['raw_response'] = $body;
    
    // Intentar decodificar la respuesta JSON
    $data = json_decode($body, true);
    
    if ($status_code === 200) {
        $results['success'] = true;
        $results['message'] = '✅ Conexión exitosa con Groundhogg CRM v3';
        
        // Determinar el tipo de respuesta
        if (is_array($data)) {
            if (isset($data['status']) && $data['status'] === 'active') {
                // Es una respuesta del plugin GlobalAPI (proxy)
                $results['details'] = array(
                    'tipo_respuesta' => 'GlobalAPI Proxy',
                    'endpoint_probado' => $test_url,
                    'status' => $status_code,
                    'plugin_version' => $data['version'] ?? 'N/A',
                    'timestamp' => $data['timestamp'] ?? 'N/A'
                );
            } elseif (isset($data['contacts']) || (is_array($data) && isset($data[0]['ID']))) {
                // Es una respuesta directa de Groundhogg
                $contact_count = isset($data['contacts']) ? count($data['contacts']) : count($data);
                $results['details'] = array(
                    'tipo_respuesta' => 'Groundhogg Directo',
                    'endpoint_probado' => $test_url,
                    'status' => $status_code,
                    'contactos_encontrados' => $contact_count,
                    'response_size' => strlen($body) . ' bytes'
                );
            } else {
                // Respuesta exitosa pero formato desconocido
                $results['details'] = array(
                    'tipo_respuesta' => 'Formato Desconocido',
                    'endpoint_probado' => $test_url,
                    'status' => $status_code,
                    'response_size' => strlen($body) . ' bytes',
                    'primeros_datos' => substr($body, 0, 100) . '...'
                );
            }
        }
        
        // Probar también el proxy de GlobalAPI
        $proxy_url = home_url('/index.php?rest_route=/globalapi/v1/proxy/groundhogg/contacts&q=Daniel&credencial_id=' . $credencial_id);
        $results['details']['proxy_test'] = '🔄 Probando proxy...';
        
        $proxy_response = wp_remote_get($proxy_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (!is_wp_error($proxy_response)) {
            $proxy_status = wp_remote_retrieve_response_code($proxy_response);
            if ($proxy_status === 200) {
                $results['details']['proxy_status'] = '✅ Proxy funcionando';
                $results['details']['proxy_url'] = '/globalapi/v1/proxy/groundhogg/{endpoint}';
            } else {
                $results['details']['proxy_status'] = '❌ Proxy error (HTTP ' . $proxy_status . ')';
            }
        } else {
            $results['details']['proxy_status'] = '❌ Proxy no disponible';
        }
    } elseif ($status_code === 401) {
        $results['message'] = '❌ Error de autenticación (HTTP 401)';
        $error_message = '';
        
        if (is_array($data) && isset($data['message'])) {
            $error_message = $data['message'];
        } elseif (is_array($data) && isset($data['code'])) {
            $error_message = $data['code'];
        }
        
        $results['details'] = array(
            'endpoint' => $test_url,
            'status' => $status_code,
            'error' => $error_message ?: 'No autorizado',
            'headers_enviados' => array(
                'Gh-Public-Key' => substr($public_key, 0, 8) . '...',
                'Gh-Token' => substr($token, 0, 8) . '...',
                'Gh-Secret-Key' => !empty($secret_key) ? 'Enviada' : 'No enviada'
            )
        );
    } else {
        $results['message'] = '❌ Error en la respuesta (HTTP ' . $status_code . ')';
        $results['details'] = array(
            'endpoint' => $test_url,
            'status' => $status_code,
            'response' => substr($body, 0, 200) . '...',
            'content_type' => $response_headers['content-type'] ?? 'Unknown'
        );
    }
    
    return $results;
}

/**
 * Probar conexión InvisionCommunity
 */
function test_invisioncommunity_connection($credencial_id) {
    $results = array('success' => false, 'message' => '', 'details' => array());
    
    // Obtener credenciales
    $auth_url = get_post_meta($credencial_id, '_globalapi_ic_authorization_url', true);
    $token_url = get_post_meta($credencial_id, '_globalapi_ic_token_url', true);
    $client_id = get_post_meta($credencial_id, '_globalapi_ic_client_identifier', true);
    $api_key = get_post_meta($credencial_id, '_globalapi_ic_rest_api_key', true);
    
    if (empty($auth_url) || empty($client_id)) {
        $results['message'] = 'Credenciales incompletas para InvisionCommunity.';
        return $results;
    }
    
    // Para OAuth, simplemente verificar que las URLs son accesibles
    $test_url = $auth_url;
    
    $response = wp_remote_head($test_url, array(
        'timeout' => 10,
        'redirection' => 0
    ));
    
    if (is_wp_error($response)) {
        $results['message'] = 'Error de conexión: ' . $response->get_error_message();
        return $results;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $results['status_code'] = $status_code;
    
    if ($status_code === 200 || $status_code === 302) {
        $results['success'] = true;
        $results['message'] = '✅ Servidor InvisionCommunity accesible';
        $results['details'] = array(
            'auth_endpoint' => $auth_url,
            'token_endpoint' => $token_url,
            'client_id' => substr($client_id, 0, 8) . '...',
            'status' => $status_code,
            'oauth_ready' => !empty($token_url) ? 'Sí' : 'No'
        );
    } else {
        $results['message'] = '❌ Servidor no accesible (HTTP ' . $status_code . ')';
        $results['details'] = array(
            'endpoint' => $test_url,
            'status' => $status_code
        );
    }
    
    return $results;
}
?>

<div class="globalapi-content">
    <div class="test-header">
        <h2>🧪 Probar Credencial: <?php echo esc_html($credencial->post_title); ?></h2>
        <div class="header-actions">
            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales&action=edit&id=' . $credencial->ID); ?>" class="button">
                ✏️ Editar
            </a>
            <a href="<?php echo admin_url('admin.php?page=globalapi-credenciales'); ?>" class="button">
                ← Volver a Credenciales
            </a>
        </div>
    </div>

    <!-- Información de la credencial -->
    <div class="credencial-info">
        <h3>📋 Información de la Credencial</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Tipo de Servicio:</strong>
                <span class="service-badge service-<?php echo esc_attr($tipo_servicio); ?>">
                    <?php echo esc_html(ucfirst($tipo_servicio)); ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Estado:</strong>
                <span class="status-badge status-<?php echo esc_attr($estado); ?>">
                    <?php echo esc_html(ucfirst($estado)); ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Última Actualización:</strong>
                <?php echo esc_html(get_the_modified_date('d/m/Y H:i', $credencial->ID)); ?>
            </div>
            <div class="info-item">
                <strong>Descripción:</strong>
                <?php echo esc_html($credencial->post_excerpt ?: 'Sin descripción'); ?>
            </div>
        </div>
    </div>

    <!-- Detalles técnicos -->
    <div class="credencial-details">
        <h3>🔧 Detalles de Configuración</h3>
        <?php if ($tipo_servicio === 'groundhogg'): ?>
            <div class="config-details">
                <h4>Groundhogg CRM</h4>
                <ul>
                    <li><strong>URL Base v3:</strong> <?php echo esc_html(get_post_meta($credencial->ID, '_globalapi_gh_base_url_v3', true) ?: 'No configurada'); ?></li>
                    <li><strong>URL Base v4:</strong> <?php echo esc_html(get_post_meta($credencial->ID, '_globalapi_gh_base_url_v4', true) ?: 'No configurada'); ?></li>
                    <li><strong>Clave Pública:</strong> <?php echo esc_html(substr(get_post_meta($credencial->ID, '_globalapi_gh_clave_publica', true), 0, 8) . '...'); ?></li>
                    <li><strong>Token:</strong> <?php echo esc_html(substr(get_post_meta($credencial->ID, '_globalapi_gh_token', true), 0, 8) . '...'); ?></li>
                    <li><strong>Llave Secreta:</strong> <?php echo get_post_meta($credencial->ID, '_globalapi_gh_llave_secreta', true) ? '✅ Configurada' : '❌ No configurada'; ?></li>
                </ul>
            </div>
        <?php elseif ($tipo_servicio === 'invisioncommunity'): ?>
            <div class="config-details">
                <h4>InvisionCommunity OAuth</h4>
                <ul>
                    <li><strong>Authorization URL:</strong> <?php echo esc_html(get_post_meta($credencial->ID, '_globalapi_ic_authorization_url', true) ?: 'No configurada'); ?></li>
                    <li><strong>Token URL:</strong> <?php echo esc_html(get_post_meta($credencial->ID, '_globalapi_ic_token_url', true) ?: 'No configurada'); ?></li>
                    <li><strong>Client ID:</strong> <?php echo esc_html(substr(get_post_meta($credencial->ID, '_globalapi_ic_client_identifier', true), 0, 8) . '...'); ?></li>
                    <li><strong>Client Secret:</strong> <?php echo get_post_meta($credencial->ID, '_globalapi_ic_client_secret', true) ? '✅ Configurado' : '❌ No configurado'; ?></li>
                    <li><strong>REST API Key:</strong> <?php echo get_post_meta($credencial->ID, '_globalapi_ic_rest_api_key', true) ? '✅ Configurada' : '❌ No configurada'; ?></li>
                    <li><strong>Scopes:</strong> <?php echo esc_html(get_post_meta($credencial->ID, '_globalapi_ic_scopes', true) ?: 'read,profile'); ?></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- Botón de prueba -->
    <div class="test-action">
        <form method="post" id="test-form">
            <?php wp_nonce_field('globalapi_test_credencial_' . $credencial->ID); ?>
            <input type="submit" name="globalapi_test_credencial" class="button button-primary button-large test-button" value="🚀 Probar Conexión">
            <div class="test-info">
                <p>Esta prueba verificará la conectividad y autenticación con el servicio configurado.</p>
                <?php if ($tipo_servicio === 'groundhogg'): ?>
                    <p class="test-note">📌 Se probará el endpoint: <code>/contacts?q=Daniel</code> en la API v3</p>
                <?php elseif ($tipo_servicio === 'invisioncommunity'): ?>
                    <p class="test-note">📌 Se verificará la accesibilidad del servidor OAuth</p>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Resultados de la prueba -->
    <?php if ($test_results): ?>
        <div class="test-results <?php echo $test_results['success'] ? 'success' : 'error'; ?>">
            <h3>📊 Resultados de la Prueba</h3>
            
            <div class="result-message">
                <?php echo esc_html($test_results['message']); ?>
            </div>
            
            <div class="result-details">
                <div class="result-meta">
                    <span><strong>Tiempo de respuesta:</strong> <?php echo esc_html($test_results['response_time']); ?>ms</span>
                    <?php if ($test_results['status_code']): ?>
                        <span><strong>Código HTTP:</strong> <?php echo esc_html($test_results['status_code']); ?></span>
                    <?php endif; ?>
                    <span><strong>Timestamp:</strong> <?php echo esc_html(current_time('d/m/Y H:i:s')); ?></span>
                </div>
                
                <?php if (!empty($test_results['details'])): ?>
                    <div class="result-data">
                        <h4>Detalles Técnicos:</h4>
                        <ul>
                            <?php foreach ($test_results['details'] as $key => $value): ?>
                                <?php if (is_array($value)): ?>
                                    <li>
                                        <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                        <ul style="margin-top: 5px;">
                                            <?php foreach ($value as $subkey => $subvalue): ?>
                                                <li style="margin-left: 20px;">
                                                    <strong><?php echo esc_html($subkey); ?>:</strong> <?php echo esc_html($subvalue); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php else: ?>
                                    <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo esc_html($value); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($test_results['raw_response']) && ($test_results['success'] || $test_results['status_code'] !== 200)): ?>
                    <div class="result-raw">
                        <h4>Respuesta Raw (Debug):</h4>
                        <details>
                            <summary>Clic para ver respuesta completa</summary>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 300px; overflow-y: auto;"><?php 
                                $raw = $test_results['raw_response'];
                                $decoded = json_decode($raw, true);
                                if ($decoded) {
                                    echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                } else {
                                    echo esc_html($raw);
                                }
                            ?></pre>
                        </details>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.test-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.credencial-info, .credencial-details {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.service-badge, .status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.service-groundhogg { background: #e3f2fd; color: #1976d2; }
.service-invisioncommunity { background: #f3e5f5; color: #7b1fa2; }

.status-activa { background: #e8f5e8; color: #2e7d32; }
.status-inactiva { background: #fff3e0; color: #f57c00; }
.status-expirada { background: #ffebee; color: #c62828; }

.config-details h4 {
    margin-top: 0;
    color: #2271b1;
}

.config-details ul {
    list-style: none;
    padding: 0;
}

.config-details li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.test-action {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 30px;
    text-align: center;
    margin-bottom: 20px;
}

.test-button {
    font-size: 16px !important;
    padding: 10px 30px !important;
    height: auto !important;
}

.test-info {
    margin-top: 15px;
    color: #666;
}

.test-results {
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.test-results.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.test-results.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.result-message {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.result-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    font-size: 14px;
}

.result-data h4 {
    margin-bottom: 10px;
}

.result-data ul {
    list-style: none;
    padding: 0;
}

.result-data li {
    padding: 5px 0;
}

#test-form {
    position: relative;
}

.test-button:disabled {
    opacity: 0.6;
}

.test-note {
    margin-top: 10px;
    color: #2271b1;
    font-style: italic;
}

.test-note code {
    background: #e0e5eb;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

.result-raw {
    margin-top: 20px;
}

.result-raw summary {
    cursor: pointer;
    color: #2271b1;
    font-weight: 600;
    padding: 5px 0;
}

.result-raw details[open] summary {
    margin-bottom: 10px;
}

.result-data ul ul {
    list-style: none;
    padding: 0;
}

.result-data ul ul li {
    font-size: 13px;
    color: #555;
}

.test-results.error .result-raw pre {
    background: #fff5f5 !important;
}

.test-results.success .result-raw pre {
    background: #f0f8f0 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#test-form').on('submit', function() {
        const button = $('.test-button');
        button.prop('disabled', true).val('🔄 Probando...');
        
        // Re-habilitar después de 10 segundos por seguridad
        setTimeout(function() {
            button.prop('disabled', false).val('🚀 Probar Conexión');
        }, 10000);
    });
});
</script> 