=== GlobalAPI - Infraestructura CRM ===
Contributors: colombiahumana
Donate link: https://colombiahumana.co/donaciones
Tags: crm, api, groundhogg, oauth, infraestructura
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin de infraestructura para gestión segura de APIs y credenciales. Diseñado para la aplicación móvil Flutter de Colombia Humana.

== Descripción ==

GlobalAPI es un plugin de infraestructura especializado que gestiona de forma segura las conexiones API y credenciales para la integración con sistemas CRM (Groundhogg) y OAuth (InvisionCommunity). 

Diseñado específicamente para soportar la aplicación móvil Flutter de Colombia Humana, proporcionando endpoints seguros, gestión de sesiones y auditoría completa de todas las operaciones.

= Características Principales =

* **Gestión Segura de Credenciales**: Sistema robusto para almacenar y gestionar API keys y tokens
* **Proxy API Seguro**: Endpoints protegidos para operaciones con Groundhogg CRM
* **Autenticación OAuth**: Integración completa con InvisionCommunity OAuth 2.0
* **Sistema de Auditoría**: Logging detallado de todas las operaciones para cumplimiento
* **Gestión de Sesiones**: Manejo seguro de sesiones JWT para aplicaciones móviles
* **Rate Limiting**: Protección contra abuso y ataques de fuerza bruta
* **Arquitectura Modular**: Diseño escalable siguiendo estándares WordPress

= Casos de Uso =

* Aplicaciones móviles que necesitan conexión segura con WordPress/Groundhogg
* Sistemas que requieren proxy API para proteger credenciales sensibles
* Organizaciones que necesitan auditoría completa de operaciones CRM
* Desarrolladores que buscan infraestructura robusta para aplicaciones Flutter

= Tecnologías Compatibles =

* Groundhogg CRM (WordPress)
* InvisionCommunity OAuth 2.0
* Flutter/Dart (aplicaciones móviles)
* JWT (JSON Web Tokens)
* WordPress REST API

= Seguridad =

* Encriptación AES-256 para credenciales sensibles
* Validación y sanitización de todas las entradas
* Rate limiting automático
* Logs de auditoría con timestamps
* Verificación de integridad de datos

== Instalación ==

1. Descarga el plugin desde el repositorio oficial
2. Descomprime el archivo en `/wp-content/plugins/globalapi-plugin/`
3. Activa el plugin desde el panel de administración de WordPress
4. Ve a GlobalAPI > Configuración para configurar las credenciales API
5. Configura los endpoints y permisos según tus necesidades

= Requisitos del Servidor =

* WordPress 6.4 o superior
* PHP 8.1 o superior
* Extensiones PHP: curl, json, mbstring, openssl
* MySQL 5.7+ o MariaDB 10.3+
* HTTPS habilitado (recomendado para producción)

= Configuración Inicial =

1. **Credenciales Groundhogg**: Configura tu API key de Groundhogg
2. **OAuth InvisionCommunity**: Configura Client ID y Secret
3. **Configuración de Seguridad**: Establece límites de rate limiting
4. **Configuración de Logs**: Define el nivel de auditoría requerido

== Preguntas Frecuentes ==

= ¿Este plugin es compatible con otros CRM? =

Actualmente está diseñado específicamente para Groundhogg CRM. El soporte para otros CRM está en evaluación para futuras versiones.

= ¿Puedo usar este plugin sin la aplicación móvil? =

Sí, el plugin puede funcionar como infraestructura API independiente, aunque está optimizado para uso con aplicaciones móviles.

= ¿Cómo configuro las credenciales de forma segura? =

El plugin incluye un sistema de configuración segura en el admin panel. Todas las credenciales se encriptan antes del almacenamiento.

= ¿Qué sucede si desactivo el plugin? =

Los datos se preservan. Solo se limpian cachés y sesiones temporales. Para eliminación completa, usar la opción de desinstalación.

= ¿Es compatible con WordPress Multisite? =

Actualmente está diseñado para instalaciones single-site. El soporte multisite está planificado para v2.1.

== Capturas de Pantalla ==

1. Panel principal de GlobalAPI
2. Configuración de credenciales API
3. Dashboard de auditoría y logs
4. Configuración de seguridad y rate limiting
5. Gestión de sesiones activas

== Registro de Cambios ==

= 2.0.0 - 2024-12-09 =
* Versión inicial del plugin GlobalAPI
* Sistema completo de gestión de credenciales
* Proxy API para Groundhogg CRM
* Integración OAuth con InvisionCommunity
* Sistema de auditoría y logging
* Gestión segura de sesiones JWT
* Rate limiting y protecciones de seguridad
* Soporte completo para aplicaciones Flutter

= Roadmap Futuro =
* v2.1: Soporte WordPress Multisite
* v2.2: Integración con más sistemas CRM
* v2.3: Dashboard de analíticas avanzadas
* v2.4: API pública para desarrolladores terceros

== Notas de Desarrollo ==

Este plugin sigue los estándares de desarrollo de WordPress y utiliza:

* PSR-4 autoloading para organización de clases
* WordPress Coding Standards para calidad de código
* Arquitectura modular para escalabilidad
* Documentación completa en español
* Testing automatizado con PHPUnit

Para desarrolladores que deseen contribuir o extender el plugin, consultar la documentación técnica en el repositorio oficial.

== Soporte ==

Para soporte técnico, reportar bugs o solicitar características:
- GitHub: https://github.com/colombiahumana/globalapi-plugin
- Email: desarrollo@colombiahumana.co
- Documentación: https://docs.colombiahumana.co/globalapi

== Licencia ==

Este plugin está licenciado bajo GPL v2 o posterior. Ver LICENSE.txt para detalles completos. 