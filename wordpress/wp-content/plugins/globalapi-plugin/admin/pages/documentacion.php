<?php
/**
 * Página de documentación del plugin
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$titulo = isset($titulo) ? $titulo : 'Documentación del Plugin';
?>

<div class="globalapi-content">
    <h2><?php echo esc_html($titulo); ?></h2>
    
    <div class="globalapi-notice info">
        <p><strong>Documentación GlobalAPI:</strong> Guías, ejemplos y referencias para usar el plugin.</p>
    </div>

    <div class="documentation-nav">
        <h3>Índice de Contenidos</h3>
        <ul>
            <li><a href="#introduccion">Introducción</a></li>
            <li><a href="#instalacion">Instalación y Configuración</a></li>
            <li><a href="#credenciales">Gestión de Credenciales</a></li>
            <li><a href="#api-rest">API REST</a></li>
            <li><a href="#seguridad">Seguridad</a></li>
            <li><a href="#ejemplos">Ejemplos de Uso</a></li>
            <li><a href="#troubleshooting">Solución de Problemas</a></li>
        </ul>
    </div>

    <div class="documentation-section" id="introduccion">
        <h3>📋 Introducción</h3>
        <p>GlobalAPI es un plugin de WordPress diseñado para la gestión centralizada y segura de credenciales de APIs externas. Desarrollado específicamente para Colombia Humana, facilita la integración con sistemas CRM Flutter y otras aplicaciones externas.</p>
        
        <h4>Características Principales:</h4>
        <ul>
            <li><strong>Gestión Segura de Credenciales:</strong> Almacenamiento encriptado de API keys y tokens</li>
            <li><strong>API REST Completa:</strong> Endpoints para autenticación y gestión de datos</li>
            <li><strong>Auditoría Completa:</strong> Registro detallado de todas las operaciones</li>
            <li><strong>Interfaz Administrativa:</strong> Panel de control intuitivo en WordPress</li>
            <li><strong>Seguridad Avanzada:</strong> Validación de nonces, rate limiting, y verificación SSL</li>
        </ul>
    </div>

    <div class="documentation-section" id="instalacion">
        <h3>⚙️ Instalación y Configuración</h3>
        
        <h4>Requisitos del Sistema:</h4>
        <ul>
            <li>WordPress 5.0 o superior</li>
            <li>PHP 7.4 o superior</li>
            <li>MySQL 5.6 o superior</li>
            <li>Extensiones PHP: cURL, OpenSSL, JSON, mbstring</li>
            <li>HTTPS recomendado para producción</li>
        </ul>

        <h4>Pasos de Instalación:</h4>
        <ol>
            <li>Descomprimir el plugin en <code>/wp-content/plugins/globalapi-plugin/</code></li>
            <li>Activar el plugin desde el panel de WordPress</li>
            <li>Configurar permalinks a "Nombre de la entrada"</li>
            <li>Verificar que los Custom Post Types se registren correctamente</li>
            <li>Configurar las opciones desde <em>GlobalAPI → Configuración</em></li>
        </ol>

        <h4>Configuración Inicial:</h4>
        <pre><code>// Configuración recomendada para producción
Timeout de API: 30 segundos
Cache Duration: 60 minutos
Máximo de Logs: 1000
SSL Verification: Habilitado
Rate Limit: 100 requests/hora</code></pre>
    </div>

    <div class="documentation-section" id="credenciales">
        <h3>🔐 Gestión de Credenciales</h3>
        
        <p>Las credenciales se almacenan como Custom Post Type <code>globalapi_credencial</code> con metadatos encriptados.</p>

        <h4>Crear Nueva Credencial:</h4>
        <ol>
            <li>Ir a <em>GlobalAPI → Credenciales</em></li>
            <li>Hacer clic en "Nueva Credencial"</li>
            <li>Completar los campos requeridos</li>
            <li>Seleccionar el tipo de servicio</li>
            <li>Guardar la credencial</li>
        </ol>

        <h4>Campos de Credencial:</h4>
        <ul>
            <li><strong>Nombre:</strong> Identificador descriptivo</li>
            <li><strong>Tipo de Servicio:</strong> oauth, bearer, basic, custom</li>
            <li><strong>API Key/Token:</strong> Credencial principal (encriptada)</li>
            <li><strong>Secret:</strong> Clave secreta si es necesaria (encriptada)</li>
            <li><strong>URL Base:</strong> Endpoint base de la API</li>
            <li><strong>Estado:</strong> activa, inactiva, expirada</li>
        </ul>

        <h4>Estados de Credencial:</h4>
        <div class="status-examples">
            <span class="status-badge status-success">Activa</span> - Funcionando correctamente<br>
            <span class="status-badge status-warning">Inactiva</span> - Deshabilitada temporalmente<br>
            <span class="status-badge status-error">Expirada</span> - Requiere renovación
        </div>
    </div>

    <div class="documentation-section" id="api-rest">
        <h3>🔗 API REST</h3>
        
        <p>GlobalAPI expone endpoints REST para integración con aplicaciones externas.</p>

        <h4>Endpoints Principales:</h4>
        <table class="api-endpoints">
            <thead>
                <tr>
                    <th>Método</th>
                    <th>Endpoint</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/wp-json/globalapi/v1/status</code></td>
                    <td>Estado del plugin</td>
                </tr>
                <tr>
                    <td><code>POST</code></td>
                    <td><code>/wp-json/globalapi/v1/auth/login</code></td>
                    <td>Autenticación de usuario</td>
                </tr>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/wp-json/globalapi/v1/credentials</code></td>
                    <td>Listar credenciales</td>
                </tr>
                <tr>
                    <td><code>POST</code></td>
                    <td><code>/wp-json/globalapi/v1/credentials</code></td>
                    <td>Crear credencial</td>
                </tr>
                <tr>
                    <td><code>PUT</code></td>
                    <td><code>/wp-json/globalapi/v1/credentials/{id}</code></td>
                    <td>Actualizar credencial</td>
                </tr>
            </tbody>
        </table>

        <h4>Ejemplo de Autenticación:</h4>
        <pre><code>POST /wp-json/globalapi/v1/auth/login
Content-Type: application/json

{
    "username": "usuario",
    "password": "contraseña"
}

Respuesta:
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user_id": 1,
    "expires": "2024-01-01T12:00:00Z"
}</code></pre>

        <h4>Ejemplo de Uso con cURL:</h4>
        <pre><code>curl -X GET "https://tudominio.com/wp-json/globalapi/v1/status" \
     -H "Authorization: Bearer TOKEN_AQUI" \
     -H "Content-Type: application/json"</code></pre>
    </div>

    <div class="documentation-section" id="seguridad">
        <h3>🛡️ Seguridad</h3>
        
        <h4>Medidas de Seguridad Implementadas:</h4>
        <ul>
            <li><strong>Encriptación:</strong> Todas las credenciales se almacenan encriptadas</li>
            <li><strong>WordPress Nonces:</strong> Validación CSRF en formularios</li>
            <li><strong>Rate Limiting:</strong> Límite de peticiones por IP/usuario</li>
            <li><strong>SSL/TLS:</strong> Verificación de certificados SSL</li>
            <li><strong>Auditoría:</strong> Registro completo de actividades</li>
            <li><strong>Permisos:</strong> Control de acceso basado en capabilities</li>
        </ul>

        <h4>Configuración de Seguridad:</h4>
        <pre><code>// IPs permitidas (opcional)
192.168.1.100
10.0.0.0/8
203.0.113.0/24

// Rate limiting
100 requests/hora por IP

// SSL
Verificación habilitada por defecto</code></pre>

        <h4>Headers de Seguridad Recomendados:</h4>
        <pre><code># Nginx
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";

# Apache (.htaccess)
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"</code></pre>
    </div>

    <div class="documentation-section" id="ejemplos">
        <h3>💡 Ejemplos de Uso</h3>
        
        <h4>Integración con Flutter (Dart):</h4>
        <pre><code>import 'package:http/http.dart' as http;
import 'dart:convert';

class GlobalAPIClient {
  final String baseUrl;
  String? _token;
  
  GlobalAPIClient(this.baseUrl);
  
  Future&lt;bool&gt; login(String username, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/wp-json/globalapi/v1/auth/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'username': username,
        'password': password,
      }),
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      _token = data['token'];
      return true;
    }
    return false;
  }
  
  Future&lt;List&gt; getCredentials() async {
    final response = await http.get(
      Uri.parse('$baseUrl/wp-json/globalapi/v1/credentials'),
      headers: {
        'Authorization': 'Bearer $_token',
        'Content-Type': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    }
    throw Exception('Error loading credentials');
  }
}</code></pre>

        <h4>Integración con JavaScript:</h4>
        <pre><code>class GlobalAPI {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.token = null;
  }
  
  async login(username, password) {
    const response = await fetch(`${this.baseUrl}/wp-json/globalapi/v1/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ username, password }),
    });
    
    if (response.ok) {
      const data = await response.json();
      this.token = data.token;
      return true;
    }
    return false;
  }
  
  async getCredentials() {
    const response = await fetch(`${this.baseUrl}/wp-json/globalapi/v1/credentials`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
    });
    
    return response.json();
  }
}</code></pre>
    </div>

    <div class="documentation-section" id="troubleshooting">
        <h3>🔧 Solución de Problemas</h3>
        
        <h4>Problemas Comunes:</h4>
        
        <div class="troubleshoot-item">
            <h5>❌ Error 404 en endpoints de API</h5>
            <p><strong>Causa:</strong> Permalinks no configurados correctamente</p>
            <p><strong>Solución:</strong> Ir a <em>Ajustes → Enlaces permanentes</em> y seleccionar "Nombre de la entrada"</p>
        </div>

        <div class="troubleshoot-item">
            <h5>❌ Custom Post Types no aparecen</h5>
            <p><strong>Causa:</strong> Los modelos no se están cargando</p>
            <p><strong>Solución:</strong> Verificar que `cargar_modelos()` se ejecute en el constructor de la clase principal</p>
        </div>

        <div class="troubleshoot-item">
            <h5>❌ Error de clases no encontradas</h5>
            <p><strong>Causa:</strong> Archivos de clases no incluidos correctamente</p>
            <p><strong>Solución:</strong> Verificar los `require_once` en el archivo principal del plugin</p>
        </div>

        <div class="troubleshoot-item">
            <h5>❌ Problemas de autenticación</h5>
            <p><strong>Causa:</strong> Headers de autorización no llegan al servidor</p>
            <p><strong>Solución:</strong> Configurar servidor web para pasar headers de autorización</p>
            <pre><code># Nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;

# Apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1</code></pre>
        </div>

        <h4>Logs de Debug:</h4>
        <p>Para habilitar logging detallado:</p>
        <ol>
            <li>Ir a <em>GlobalAPI → Configuración</em></li>
            <li>Habilitar "Modo Debug"</li>
            <li>Revisar logs en <em>GlobalAPI → Logs</em></li>
            <li>Consultar también <code>wp-content/debug.log</code></li>
        </ol>

        <h4>Comandos de Diagnóstico:</h4>
        <pre><code># Verificar estado del plugin
wp plugin status globalapi-plugin

# Verificar Custom Post Types
wp post-type list

# Probar API REST
curl -I https://tudominio.com/wp-json/globalapi/v1/status

# Ver logs recientes
wp globalapi logs --recent</code></pre>
    </div>

    <div class="support-section">
        <h3>📞 Soporte Técnico</h3>
        <p>Para soporte técnico y consultas:</p>
        <ul>
            <li><strong>Desarrollado por:</strong> Colombia Humana - Desarrollo Tecnológico</li>
            <li><strong>Versión:</strong> <?php echo esc_html(GLOBALAPI_VERSION); ?></li>
            <li><strong>Documentación:</strong> <a href="#" target="_blank">Wiki del Proyecto</a></li>
            <li><strong>Issues:</strong> <a href="#" target="_blank">Reportar Errores</a></li>
        </ul>
    </div>
</div>

<style>
.documentation-nav {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.documentation-nav h3 {
    margin-top: 0;
    color: #2271b1;
}

.documentation-nav ul {
    list-style-type: none;
    padding-left: 0;
    columns: 2;
    column-gap: 30px;
}

.documentation-nav li {
    margin-bottom: 8px;
    break-inside: avoid;
}

.documentation-nav a {
    text-decoration: none;
    color: #2271b1;
    font-weight: 500;
}

.documentation-nav a:hover {
    text-decoration: underline;
}

.documentation-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 25px;
    margin-bottom: 25px;
}

.documentation-section h3 {
    margin-top: 0;
    color: #2271b1;
    border-bottom: 2px solid #f0f0f1;
    padding-bottom: 10px;
}

.documentation-section h4 {
    color: #333;
    margin-top: 25px;
}

.documentation-section h5 {
    color: #d63638;
    margin-top: 20px;
}

.documentation-section pre {
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.4;
}

.documentation-section code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}

.documentation-section pre code {
    background: none;
    padding: 0;
}

.api-endpoints {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.api-endpoints th,
.api-endpoints td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}

.api-endpoints th {
    background: #f8f9fa;
    font-weight: 600;
}

.api-endpoints code {
    font-weight: 600;
}

.status-examples {
    margin: 15px 0;
    line-height: 2;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 10px;
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

.troubleshoot-item {
    border-left: 4px solid #ffb900;
    padding-left: 15px;
    margin: 20px 0;
}

.troubleshoot-item h5 {
    margin-top: 0;
}

.support-section {
    background: #f0f8ff;
    border: 1px solid #bee5eb;
    border-radius: 4px;
    padding: 20px;
    margin-top: 30px;
}

.support-section h3 {
    margin-top: 0;
    color: #0c5460;
}
</style> 