# Roadmap - Aplicación de Gestión de Contactos CRM
## Aplicación Flutter para Integración con Groundhogg WordPress CRM

## 🎯 INSTRUCCIONES PARA MANTENER CONTEXTO

### **Propósito de este Archivo**
- **roadmap.md**: Plan de desarrollo, progreso de tareas, arquitectura del proyecto
- **changelog.md**: Registro detallado de cada modificación realizada (crear separadamente)

### **Reglas de Modificación**
1. **roadmap.md**: Solo actualizar progreso de tareas, cambiar estados (📋 → 🔄 → ✅), modificar arquitectura si es necesario
2. **changelog.md**: Documentar CADA cambio realizado con fecha, descripción detallada y archivos afectados
3. **NO usar roadmap.md como changelog**: El roadmap es el estado actual, no historial de cambios

### **Estados de Tareas**
- 📋 Por definir
- 🔄 En desarrollo  
- ✅ Completado
- ❌ Bloqueado
- ⚠️ Problema

### **Al Retomar Conversación**
1. Revisar estado actual en roadmap.md
2. Consultar changelog.md para entender últimos cambios
3. Verificar tareas en progreso (🔄)
4. Continuar con próxima tarea planificada

### **Información Confidencial**
- ⚠️ **IMPORTANTE**: Este archivo contiene credenciales de APIs (protegido por .gitignore)
- Solo usar localmente, NO sincronizar con repositorio público

### Instrucciones Generales de Desarrollo

#### Convenciones de Idioma
- **Nombres de Variables y Funciones**: Usar español cuando sea posible y legible
  - ✅ `gestorContactos`, `buscarContacto()`, `actualizarDatos()`
  - ❌ `contactManager`, `searchContact()`, `updateData()`
- **Comentarios de Código**: Todos los comentarios en español
  - Documentación de funciones y clases en español
  - Comentarios explicativos en línea en español
  - Headers de archivos en español
- **Nombres de Clases**: Usar español con convención PascalCase
  - ✅ `GestorContactos`, `ServicioAuth`, `RepositorioContactos`
  - ❌ `ContactManager`, `AuthService`, `ContactRepository`
- **Nombres de Archivos**: Usar guiones bajos y español
  - ✅ `gestor_contactos.dart`, `servicio_auth.dart`
  - ❌ `contact_manager.dart`, `auth_service.dart`

#### Convenciones de Flutter y Dart
- **Widgets**: Usar español para widgets personalizados
  - ✅ `PantallaContactos`, `WidgetBusqueda`, `VistaDetalleContacto`
  - ❌ `ContactScreen`, `SearchWidget`, `ContactDetailView`
- **Estados**: Nomenclatura clara en español
  - ✅ `EstadoCargando`, `EstadoAutenticado`, `EstadoSincronizando`
- **Servicios**: Sufijo Service en español
  - ✅ `ServicioAuth`, `ServicioAPI`, `ServicioAuditoria`
- **Modelos**: Nombres descriptivos en español
  - ✅ `ModeloContacto`, `SesionUsuario`, `LogAuditoria`

#### Convenciones de Backend (WordPress Plugin)
- **Funciones**: snake_case en español
  - ✅ `obtener_contactos()`, `autenticar_usuario()`, `registrar_accion()`
- **Clases**: PascalCase en español (siguiendo convenciones WordPress)
  - ✅ `ConectorGroundhogg`, `GestorAuth`, `RegistroAuditoria`
- **Modelos**: WordPress Custom Post Types con nombres en español
  - ✅ `Contacto`, `Usuario`, `LogAuditoria`, `SesionUsuario`
- **APIs**: WordPress REST API endpoints en español
  - ✅ `/wp-json/globalapi/v1/contactos`, `/wp-json/globalapi/v1/auth/login`
- **Database**: Nombres de tablas WordPress y campos en español
  - ✅ `wp_usuarios`, `wp_contactos`, `wp_auditoria`, `wp_sesiones`
- **Plugins**: Estructura estándar de WordPress Plugin
  - ✅ `globalapi-plugin`, includes, admin, public, languages

#### Estándares de Código
- **Flutter**: Seguir guías oficiales de Flutter
- **Material Design 3**: Para coherencia visual
- **Clean Architecture**: Separación clara de capas
- **Dart Documentation**: DartDoc en español
- **Accesibilidad**: Cumplir con estándares de accesibilidad
- **Responsive Design**: Adaptable a diferentes tamaños de pantalla
- **WordPress Standards**: Seguir WordPress Plugin Development Standards
- **PSR Standards**: Para código PHP (PSR-1, PSR-4, PSR-12)

#### Estructura de Comentarios
```dart
/**
 * Gestiona la conexión y operaciones con contactos de Groundhogg
 * 
 * Esta clase maneja toda la comunicación con el backend PHP
 * que a su vez se conecta con la API REST de Groundhogg.
 * Incluye autenticación OAuth y registro de auditoría.
 *
 * @since 1.0.0
 * @package LideresApp
 * @subpackage Core
 */
class GestorContactos {
    
    /**
     * Obtiene lista de contactos con filtros aplicados
     *
     * @since 1.0.0
     * @param filtros Criterios de búsqueda y filtrado
     * @return Future<List<Contacto>> Lista de contactos
     */
    Future<List<Contacto>> obtenerContactos(FiltrosBusqueda filtros) async {
        // Implementación aquí
    }
}
```

#### Mensajes de Usuario
- **Interfaz**: Todos los textos en español
- **Mensajes de Error**: Claros y específicos en español
- **Documentación**: README y ayuda en español
- **Retroalimentación**: Mensajes informativos durante operaciones

### Sub-Roadmaps Detallados
- **[roadmap-v1.md](roadmap-v1.md)**: Plugin GlobalAPI (Infraestructura) - Plan detallado técnico para WordPress Plugin v2.x
- **roadmap-v2.md**: Aplicación Flutter (Cliente móvil) - UI/UX y lógica de negocio *(pendiente)*
- **roadmap-v3.md**: Plugin LideresApp (Auditoría) - Sistema de auditoría y reportes *(pendiente)*
- **roadmap-v4.md**: Integración y Testing - Pruebas de integración completa *(pendiente)*

### Tabla de Características y Progreso

| Versión | Característica | Descripción | Estado | Tiempo Est. | Notas |
|---------|---------------|-------------|--------|-------------|-------|
| **1.0** | **PLUGIN GLOBALAPI (INFRAESTRUCTURA)** | | | **1-2 semanas** | |
| 1.0.1 | Estructura Plugin GlobalAPI | Setup plugin WordPress, configuración base | ✅ | 1 día | Plugin de infraestructura |
| 1.0.2 | Configuración Credenciales | Sistema seguro para API keys Groundhogg/Invision | ✅ | 1 día | Credential management |
| 1.0.3 | Controladores Backend | Sistema completo de controladores (Config, Credenciales, Logs, Estado) | ✅ | 2 días | Backend controllers |
| 1.0.4 | Vistas Backend | Templates completos para listados, formularios, dashboards y partials | ✅ | 1.5 días | Backend views |
| 1.0.5 | Middleware Autenticación | Validación de sesiones y tokens de usuario | ✅ | 2 días | Security middleware |
| 1.0.6 | API REST WordPress | Endpoints seguros para operaciones CRUD | ✅ | 2 días | REST API layer |
| 1.0.7 | Gestión Sesiones JWT | Manejo seguro de sesiones con JWT | ✅ | 1 día | JWT Session management |
| 1.0.8 | Rate Limiting | Protección contra abuso de API | ✅ | 1 día | API protection |
| 1.0.9 | Conectores y Servicios | Integración con Groundhogg y OAuth | 🔄 | 3 días | External integrations |
| **2.0** | **APLICACIÓN FLUTTER** | | | **2-3 semanas** | |
| 2.0.1 | Estructura Proyecto Flutter | Setup Flutter, arquitectura, dependencias | 📋 | 1 día | App foundation |
| 2.0.2 | Interfaz Principal | Pantalla principal con navegación y diseño | 📋 | 2 días | Main UI/UX |
| 2.0.3 | Sistema de Rutas | Navegación entre pantallas, routing | 📋 | 1 día | Navigation system |
| 2.0.4 | Integración GlobalAPI Auth | Conectar con sistema OAuth del plugin | 📋 | 2 días | Secure authentication |
| 2.0.5 | Servicios API Flutter | Cliente HTTP para consumir GlobalAPI | 📋 | 2 días | API integration layer |
| 2.0.6 | Listado Contactos | Vista principal de contactos vía GlobalAPI | 📋 | 2 días | Contact listing |
| 2.0.7 | Vista Detalle Contacto | Pantalla individual de contacto | 📋 | 3 días | Detail view |
| 2.0.8 | Búsqueda y Filtros | Funcionalidad de búsqueda avanzada | 📋 | 2 días | Search functionality |
| 2.0.9 | CRUD Contactos | Crear, editar, eliminar contactos | 📋 | 3 días | Full CRUD operations |
| 2.0.10 | Testing App Flutter | Pruebas UI, integración con API | 📋 | 1 día | App testing |
| **3.0** | **PLUGIN LIDERESAPP (ESPECÍFICO)** | | | **1-2 semanas** | |
| 3.0.1 | Estructura Plugin LideresApp | Setup plugin específico para auditoría | 📋 | 1 día | Specific plugin base |
| 3.0.2 | Modelos Específicos | Usuario, Auditoría, Configuraciones | 📋 | 2 días | App-specific models |
| 3.0.3 | Sistema Auditoría | Logging automático de acciones de usuario | 📋 | 3 días | Comprehensive audit system |
| 3.0.4 | API Auditoría | Endpoints para registrar y consultar logs | 📋 | 2 días | Audit API endpoints |
| 3.0.5 | Dashboard Auditoría | Interfaz admin para visualizar auditoría | 📋 | 2 días | Admin audit interface |
| 3.0.6 | Integración Flutter-LideresApp | Conectar app con sistema de auditoría | 📋 | 1 día | Flutter audit integration |
| 3.0.7 | Testing Completo | Pruebas integrales del sistema completo | 📋 | 1 día | End-to-end testing |
| **4.0** | **FUNCIONES AVANZADAS** | | | **2-3 semanas** | |
| 4.0.1 | Paginación Inteligente | Carga progresiva de grandes listas | 📋 | 2 días | Performance |
| 4.0.2 | Cache Inteligente | Sistema de cache local avanzado | 📋 | 2 días | Performance optimization |
| 4.0.3 | Modo Offline | Funcionalidad básica sin conexión | 📋 | 3 días | Offline capability |
| 4.0.4 | Sincronización | Sync bidireccional inteligente | 📋 | 3 días | Data consistency |
| 4.0.5 | Notificaciones Push | Sistema de alertas y notificaciones | 📋 | 2 días | User engagement |
| 4.0.6 | Exportación Datos | Export CSV, Excel de contactos | 📋 | 2 días | Data export |
| **5.0** | **MEJORAS DE EXPERIENCIA** | | | **2 semanas** | |
| 5.0.1 | Filtros Avanzados | Filtros por etiquetas, fechas, campos custom | 📋 | 3 días | Advanced filtering |
| 5.0.2 | Tema Personalizable | Dark mode, colores corporativos | 📋 | 2 días | UI customization |
| 5.0.3 | Configuraciones Avanzadas | Panel de preferencias usuario | 📋 | 2 días | User preferences |
| 5.0.4 | Widgets Personalizables | Dashboard personalizable | 📋 | 3 días | Custom dashboard |
| 5.0.5 | Accesibilidad Completa | Support completo para discapacidades | 📋 | 2 días | Accessibility |
| **6.0** | **FUNCIONES AVANZADAS CRM** | | | **3-4 semanas** | |
| 6.0.1 | Gestión Campañas | Visualización y gestión de campañas | 📋 | 4 días | Campaign management |
| 6.0.2 | Seguimiento Actividades | Timeline de interacciones | 📋 | 4 días | Activity tracking |
| 6.0.3 | Automatizaciones | Vista y gestión de workflows | 📋 | 5 días | Automation management |
| 6.0.4 | Etiquetas Dinámicas | Gestión avanzada de tags | 📋 | 3 días | Tag management |
| 6.0.5 | Integración Email | Visualización de emails enviados | 📋 | 4 días | Email integration |
| **7.0** | **ANÁLISIS Y REPORTES** | | | **2-3 semanas** | |
| 7.0.1 | Dashboard Analytics | Panel con métricas principales | 📋 | 4 días | Analytics dashboard |
| 7.0.2 | Reportes Avanzados | Generación de reportes personalizados | 📋 | 4 días | Custom reporting |
| 7.0.3 | Gráficos Interactivos | Visualizaciones dinámicas | 📋 | 3 días | Data visualization |
| 7.0.4 | Exportación Reportes | PDF, Excel de reportes | 📋 | 2 días | Report export |
| 7.0.5 | Alertas Automáticas | Notificaciones basadas en métricas | 📋 | 3 días | Automated alerts |
| **8.0** | **FUNCIONES ADMINISTRATIVAS** | | | **2 semanas** | |
| 8.0.1 | Gestión Usuarios | CRUD de usuarios de la aplicación | 📋 | 3 días | User management |
| 8.0.2 | Configuraciones Admin | Panel de configuración avanzada | 📋 | 2 días | Admin configuration |
| 8.0.3 | Reportes Auditoría | Estadísticas de uso y actividad | 📋 | 3 días | Analytics |
| 8.0.4 | Backup/Restore | Respaldo y restauración de datos | 📋 | 2 días | Data protection |
| 8.0.5 | Roles y Permisos | Sistema granular de permisos | 📋 | 3 días | Permission system |
| **9.0** | **FUNCIONES EMPRESARIALES** | | | **4-5 semanas** | |
| 9.0.1 | Multi-tenant | Soporte múltiples organizaciones | 📋 | 6 días | Enterprise architecture |
| 9.0.2 | API Pública | Endpoints para integraciones terceros | 📋 | 4 días | API development |
| 9.0.3 | Integración Calendarios | Sync con Google Calendar, Outlook | 📋 | 4 días | Calendar integration |
| 9.0.4 | Módulo de Facturación | Gestión de suscripciones y pagos | 📋 | 6 días | Billing system |
| 9.0.5 | Escalabilidad Avanzada | Optimizaciones para grandes volúmenes | 📋 | 5 días | Performance scaling |

### Características Técnicas Transversales

| Área | Característica | Descripción | Estado | Prioridad |
|------|---------------|-------------|--------|-----------|
| **Arquitectura** | Clean Architecture | Separación clara de capas (domain, data, presentation) | 📋 | Alta |
| **Arquitectura** | Patrón BLoC | Gestión de estado reactiva | 📋 | Alta |
| **Seguridad** | OAuth 2.0 | Autenticación segura con InvisionCommunity | 📋 | Crítica |
| **Seguridad** | JWT Tokens | Gestión segura de sesiones | 📋 | Alta |
| **Seguridad** | Cifrado API Keys | Protección de credenciales Groundhogg | 📋 | Crítica |
| **Rendimiento** | Cache Inteligente | Cache de consultas frecuentes | 📋 | Alta |
| **Rendimiento** | Paginación Lazy | Carga bajo demanda de datos | 📋 | Alta |
| **Auditoría** | Logging Completo | Registro detallado de todas las acciones | 📋 | Crítica |
| **Auditoría** | Trazabilidad | Seguimiento completo de cambios | 📋 | Alta |
| **Compatibilidad** | Cross-Platform | Android, iOS, Web consistency | 📋 | Alta |
| **Compatibilidad** | Versiones Android | API 21+ (Android 5.0+) | 📋 | Alta |
| **Compatibilidad** | Versiones iOS | iOS 12+ | 📋 | Alta |
| **Accesibilidad** | Screen Readers | Soporte completo para lectores pantalla | 📋 | Media |
| **Accesibilidad** | Alto Contraste | Temas accesibles | 📋 | Media |
| **i18n** | Español Principal | Interfaz completa en español | 📋 | Alta |
| **i18n** | Múltiples Idiomas | Preparado para internacionalización | 📋 | Baja |

### Estructura de Archivos del Proyecto

| Archivo/Carpeta | Propósito | Estado | Notas |
|-----------------|-----------|--------|-------|
| **Frontend Flutter (lideres_app/)** | | | |
| `lib/main.dart` | Entry point de la aplicación | 📋 | Configuración inicial + auth |
| `lib/core/` | Configuración, constantes, utilidades | 📋 | Foundation |
| `lib/features/auth/` | Módulo de autenticación OAuth | 📋 | Authentication |
| `lib/features/contactos/` | Módulo principal de contactos | 📋 | Core functionality |
| `lib/features/busqueda/` | Búsqueda y filtros avanzados | 📋 | Search functionality |
| `lib/features/auditoria/` | Pantallas de auditoría | 📋 | Admin functionality |
| `lib/shared/widgets/` | Widgets reutilizables | 📋 | UI components |
| `lib/shared/services/` | Servicios compartidos | 📋 | Business logic |
| `lib/data/models/` | Modelos de datos | 📋 | Data structures |
| `lib/data/repositories/` | Repositorios de datos | 📋 | Data access |
| `lib/data/datasources/` | Fuentes de datos (API, local) | 📋 | Data sources |
| **Backend WordPress Plugin (wp-content/plugins/)** | | | |
| `globalapi-plugin/` | Plugin de infraestructura API | 📋 | Infrastructure plugin |
| `globalapi-plugin/includes/` | Clases OAuth y proxy | 📋 | Auth & proxy endpoints |
| `globalapi-plugin/admin/` | Servicios Groundhogg e Invision | 📋 | External API services |
| `globalapi-plugin/includes/middleware/` | Middleware de autenticación | 📋 | Security middleware |
| `globalapi-plugin/includes/api/` | REST API de infraestructura | 📋 | Infrastructure routing |
| `lideresapp-plugin/` | Plugin específico de auditoría | 📋 | App-specific plugin |
| `lideresapp-plugin/includes/models/` | Modelos auditoría y configuración | 📋 | Audit models |
| `lideresapp-plugin/includes/api/` | Endpoints de auditoría | 📋 | Audit endpoints |
| `wp-config.php` | Configuración de WordPress | 📋 | CMS configuration |
| **Base de Datos** | | | |
| `database/migrations/` | Migraciones de esquema | 📋 | Database schema |
| `database/seeds/` | Datos iniciales | 📋 | Initial data |
| **Configuración** | | | |
| `pubspec.yaml` | Dependencias Flutter | 📋 | Flutter dependencies |
| `globalapi-plugin/globalapi.php` | Archivo principal plugin infraestructura | 📋 | Infrastructure config |
| `lideresapp-plugin/lideresapp.php` | Archivo principal plugin específico | 📋 | App-specific config |
| `.env` | Variables de entorno (API keys, etc.) | 📋 | Environment configuration |
| `docs/` | Documentación del proyecto | 📋 | Documentation |


### Estados de Progreso

| Símbolo | Significado | Descripción |
|---------|-------------|-------------|
| ✅ | Completado | Característica implementada y probada |
| 🔄 | En desarrollo | Trabajando actualmente en esta característica |
| ⏳ | Planificado | Próxima en la cola de desarrollo |
| 📋 | Por definir | Requiere más planificación/especificación |
| ❌ | Bloqueado | Dependencias o problemas que resolver |
| ⚠️ | Problema | Requiere atención o revisión |

### Notas de Desarrollo

#### Prioridades Actuales
1. **Versión 1.0**: Establecer MVP funcional con autenticación y listado básico
2. **Seguridad**: Implementación robusta de OAuth y protección de API keys
3. **Integración Groundhogg**: Conexión sólida con API REST de WordPress
4. **Auditoría**: Sistema completo de logging y trazabilidad

#### Consideraciones Técnicas Críticas
1. **Seguridad**: Todas las comunicaciones deben ser autenticadas
2. **Performance**: Optimizar consultas a Groundhogg para grandes datasets
3. **Reliability**: Manejo robusto de errores de API externa
4. **Scalability**: Arquitectura preparada para crecimiento

#### Desafíos Principales
1. **Integración OAuth**: Configuración correcta con InvisionCommunity v5
2. **API Rate Limits**: Manejo inteligente de límites de Groundhogg
3. **Sincronización**: Mantener datos consistentes entre sistemas
4. **Error Handling**: Experiencia de usuario fluida ante fallos de red

#### Tecnologías Clave
- **Flutter**: Desarrollo cross-platform eficiente
- **WordPress**: CMS robusto con sistema de plugins flexible
- **WordPress REST API**: API nativa para comunicación con la aplicación
- **MySQL**: Base de datos confiable para auditoría
- **Groundhogg**: CRM principal para WordPress
- **InvisionCommunity**: Sistema de autenticación OAuth

#### Consideraciones de Seguridad
1. **API Keys**: Nunca exponer credenciales en el frontend
2. **OAuth Tokens**: Manejo seguro con refresh automático
3. **HTTPS Only**: Todas las comunicaciones cifradas
4. **Input Validation**: Validación estricta en backend
5. **Rate Limiting**: Protección contra ataques de fuerza bruta

#### Consideraciones de Auditoría
1. **Logging Completo**: Registrar todas las acciones de usuario
2. **Trazabilidad**: Seguimiento de cambios en datos
3. **Retención**: Políticas claras de retención de logs
4. **Compliance**: Cumplimiento de normativas de privacidad

#### Integración con Groundhogg
1. **Endpoints Utilizados**: `/contacts`, `/campaigns`, `/funnels`, `/tags`
2. **Autenticación**: API Key + Secret en backend PHP
3. **Rate Limits**: Respeto a límites de consultas por minuto
4. **Webhooks**: Configuración para sincronización en tiempo real

## **📊 Estado General del Proyecto**

- **🎯 Progreso General**: **100%** (30 de 30 sub-tareas completadas)
- **📅 Fecha Objetivo**: Marzo 2025
- **⏱️ Tiempo Estimado Total**: 8-10 semanas
- **👥 Equipo**: 1 desarrollador senior full-stack

---

### **🏆 Hitos Principales**

| **Fase** | **Estado** | **Progreso** | **Tiempo** |
|----------|------------|--------------|------------|
| **1.0 - Plugin Infraestructura** | ✅ COMPLETADO | 100% | 2-3 semanas |
| **2.0 - App Flutter Cliente** | 📋 PENDIENTE | 0% | 3-4 semanas |
| **3.0 - Plugin Auditoría** | 📋 PENDIENTE | 0% | 1-2 semanas |  
| **4.0 - Testing Integral** | 📋 PENDIENTE | 0% | 1-2 semanas |

---

## **📈 Sub-Roadmaps Detallados**

### **1.0: WordPress Plugin GlobalAPI** 🔌
- **Estado**: ✅ **COMPLETADO**
- **Progreso**: **100%** (30 de 30 sub-tareas completadas)
- **Descripción**: Plugin WordPress para gestión de contactos con API REST
- **Detalle**: [Ver roadmap-v1.md](roadmap-v1.md) 
- **Fase actual**: ✅ **PROYECTO TERMINADO** 
- **Prioridad**: ✅ **FINALIZADA**

### **2.0: Aplicación Flutter Cliente** ⏳
- **Estado**: 📋 PENDIENTE
- **Progreso**: 0%
- **Descripción**: Aplicación Flutter para integración con Groundhogg WordPress CRM
- **Detalle**: UI/UX y lógica de negocio
- **Tiempo**: 3-4 semanas

### **3.0: Plugin LideresApp (Auditoría)** ⏳
- **Estado**: 📋 PENDIENTE
- **Progreso**: 0%
- **Descripción**: Sistema de auditoría y reportes
- **Detalle**: Plugin específico para auditoría
- **Tiempo**: 1-2 semanas

### **4.0: Testing Integral** 📋
- **Estado**: 📋 PENDIENTE
- **Progreso**: 0%
- **Descripción**: Pruebas de integración completa
- **Detalle**: Testing end-to-end del sistema completo
- **Tiempo**: 1-2 semanas

## Información del Proyecto
- **Nombre**: Aplicación de Gestión de Contactos CRM (CH-API)
- **Tipo**: Aplicación Flutter con backend WordPress Plugin + integración Groundhogg
- **Versión Objetivo**: 2.0 WordPress Plugin MVP
- **Progreso**: 100% - Plugin GlobalAPI Fase 2.6 EN PROGRESO
- **Arquitectura**: Flutter + WordPress Plugin + MySQL + Groundhogg API

## 📦 Información del Repositorio GitHub

### **Configuración del Repositorio**
- **URL**: https://github.com/danfelbm/ch-api
- **Nombre**: `ch-api`
- **Descripción**: "api y servicios para colombia humana"
- **Visibilidad**: Privado 🔒
- **Propietario**: danfelbm
- **Rama principal**: `main`

### **Configuración Git Local**
- **Usuario**: `danfelbm`
- **Email**: `danfelbm@gmail.com`
- **Remote origin**: `https://github.com/danfelbm/ch-api.git`
- **Directorio local**: `/Users/testuser/Herd/lideresapp`

### **Estado Actual del Repositorio**
- ✅ Repositorio inicializado y configurado
- ✅ Commit inicial realizado en español
- ✅ GitHub CLI configurado y autenticado
- ✅ Remote origin configurado correctamente
- ✅ Archivos base subidos (README.md, roadmap.md, .gitignore)

## Resources y Referencias

### Documentación Técnica
- [Groundhogg REST API](https://docs.groundhogg.io/developer/rest-api/) - API principal del CRM
- [InvisionCommunity OAuth](https://invisioncommunity.com/developers/rest-api/oauth/) - Sistema de autenticación
- [Flutter HTTP Package](https://pub.dev/packages/http) - Cliente HTTP
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/) - Documentación oficial para plugins
- [WordPress REST API](https://developer.wordpress.org/rest-api/) - API nativa de WordPress

### Herramientas de Desarrollo WordPress

#### **🛠️ Referencias para WordPress Plugin Development**
- **Documentación**: [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- **Estándares**: [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- **Plugin Boilerplate**: [WordPress Plugin Boilerplate](https://github.com/DevinVinson/WordPress-Plugin-Boilerplate)
- **REST API**: [WordPress REST API](https://developer.wordpress.org/rest-api/)

#### **📋 Comandos útiles para WordPress Plugin Development**
```bash
# Estructura básica de plugin WordPress
mkdir wp-content/plugins/globalapi-plugin
cd wp-content/plugins/globalapi-plugin

# Activar plugin desde WP-CLI
wp plugin activate globalapi-plugin

# Verificar plugins instalados
wp plugin list

# Generar endpoints REST API personalizada
wp eval "var_dump(rest_get_server()->get_routes());"
```

### Configuración APIs - Colombia Humana

#### **Groundhogg CRM Configuration**
- **Base URL v3**: `https://crm.colombiahumana.co/wp-json/gh/v3`
- **Base URL v4**: `https://crm.colombiahumana.co/wp-json/gh/v4`
- **Clave Pública**: `272df11587153a9d6945c57dd18ed0fe`
- **Token**: `85743d02efbde665a7f81241a4d6dc5e`
- **Llave Secreta**: `6120fabab6e880eb9ae744a320ed6905`
- **Rate Limits**: 60 requests por minuto
- **Endpoints Principales**: `/contacts`, `/tags`, `/notes`, `/campaigns`

#### **InvisionCommunity OAuth Configuration**
- **Authorization URL**: `https://www.colombiahumana.co/oauth/authorize/`
- **Token URL**: `https://www.colombiahumana.co/oauth/token/`
- **Client Identifier**: `864edc8128e535df0e2b8382074b5f74`
- **Client Secret**: `3d75e6e4a613fed11ae85dacc72e09e16b80ed0d389ac5d0`
- **REST API Key**: `5ad68fff23b4ef111cf387b4e924ce2c`
- **Scopes Requeridos**: `read`, `profile`
- **Grant Type**: `authorization_code`

### Consideraciones Legales y Éticas
- Política de privacidad para datos de contactos
- Consentimiento para procesamiento de datos
- Cumplimiento GDPR/LOPD
- Auditoría de acceso a datos sensibles

### Comandos de Desarrollo

#### Setup Inicial:
```bash
# Plugin GlobalAPI (Infraestructura)
wp plugin activate globalapi-plugin

# Plugin LideresApp (Específico)  
wp plugin activate lideresapp-plugin

# Frontend Flutter  
cd lideres_app && flutter pub get

# Verificar estructura WordPress
wp core version
wp theme list
```

#### Desarrollo:
```bash
# WordPress (configurado en servidor)
# GlobalAPI endpoints: /wp-json/globalapi/v1/*
# LideresApp endpoints: /wp-json/lideresapp/v1/*

# Flutter desarrollo
cd lideres_app && flutter run
```

#### Testing:
```bash
# Tests Plugin GlobalAPI
wp eval-file globalapi-plugin/tests/test-auth.php

# Tests Plugin LideresApp
wp eval-file lideresapp-plugin/tests/test-audit.php

# Tests Flutter
cd lideres_app && flutter test
``` 