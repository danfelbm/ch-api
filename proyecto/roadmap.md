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

#### Convenciones de Backend (OctoberCMS)
- **Funciones**: snake_case en español
  - ✅ `obtener_contactos()`, `autenticar_usuario()`, `registrar_accion()`
- **Clases**: PascalCase en español (siguiendo convenciones Laravel)
  - ✅ `ConectorGroundhogg`, `GestorAuth`, `RegistroAuditoria`
- **Modelos**: Eloquent con nombres en español
  - ✅ `Contacto`, `Usuario`, `LogAuditoria`, `SesionUsuario`
- **APIs**: Endpoints en español
  - ✅ `/api/contactos`, `/api/auth/login`
- **Database**: Nombres de tablas y campos en español
  - ✅ `usuarios`, `contactos`, `auditoria`, `sesiones`
- **Plugins**: Estructura estándar de OctoberCMS
  - ✅ `autor/plugin`, controladores, modelos, componentes

#### Estándares de Código
- **Flutter**: Seguir guías oficiales de Flutter
- **Material Design 3**: Para coherencia visual
- **Clean Architecture**: Separación clara de capas
- **Dart Documentation**: DartDoc en español
- **Accesibilidad**: Cumplir con estándares de accesibilidad
- **Responsive Design**: Adaptable a diferentes tamaños de pantalla
- **OctoberCMS Standards**: Seguir convenciones de OctoberCMS y Laravel
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

### Tabla de Características y Progreso

| Versión | Característica | Descripción | Estado | Tiempo Est. | Notas |
|---------|---------------|-------------|--------|-------------|-------|
| **1.0** | **PLUGIN GLOBALAPI (INFRAESTRUCTURA)** | | | **1-2 semanas** | |
| 1.0.1 | Estructura Plugin GlobalAPI | Setup plugin OctoberCMS, configuración base | ✅ | 1 día | Plugin de infraestructura |
| 1.0.2 | Configuración Credenciales | Sistema seguro para API keys Groundhogg/Invision | 📋 | 1 día | Credential management |
| 1.0.3 | OAuth InvisionCommunity | Implementar flujo OAuth completo con Invision | 📋 | 3 días | Authentication system |
| 1.0.4 | Middleware Autenticación | Validación de sesiones y tokens de usuario | 📋 | 2 días | Security middleware |
| 1.0.5 | Proxy API Groundhogg | Endpoints seguros para operaciones Groundhogg | 📋 | 2 días | Secure proxy layer |
| 1.0.6 | Gestión Sesiones | Manejo seguro de sesiones de usuario | 📋 | 1 día | Session management |
| 1.0.7 | Rate Limiting | Protección contra abuso de API | 📋 | 1 día | API protection |
| 1.0.8 | Testing Plugin GlobalAPI | Pruebas de seguridad y endpoints | 📋 | 1 día | Infrastructure testing |
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
| **Backend OctoberCMS (backend/)** | | | |
| `plugins/autor/globalapi/` | Plugin de infraestructura API | 📋 | Infrastructure plugin |
| `plugins/autor/globalapi/controllers/` | Controladores OAuth y proxy | 📋 | Auth & proxy endpoints |
| `plugins/autor/globalapi/classes/` | Servicios Groundhogg e Invision | 📋 | External API services |
| `plugins/autor/globalapi/middleware/` | Middleware de autenticación | 📋 | Security middleware |
| `plugins/autor/globalapi/routes.php` | Rutas API de infraestructura | 📋 | Infrastructure routing |
| `plugins/autor/lideresapp/` | Plugin específico de auditoría | 📋 | App-specific plugin |
| `plugins/autor/lideresapp/models/` | Modelos auditoría y configuración | 📋 | Audit models |
| `plugins/autor/lideresapp/controllers/` | Controladores de auditoría | 📋 | Audit endpoints |
| `config/` | Configuración de OctoberCMS | 📋 | CMS configuration |
| **Base de Datos** | | | |
| `database/migrations/` | Migraciones de esquema | 📋 | Database schema |
| `database/seeds/` | Datos iniciales | 📋 | Initial data |
| **Configuración** | | | |
| `pubspec.yaml` | Dependencias Flutter | 📋 | Flutter dependencies |
| `plugins/autor/globalapi/plugin.yaml` | Configuración plugin infraestructura | 📋 | Infrastructure config |
| `plugins/autor/lideresapp/plugin.yaml` | Configuración plugin específico | 📋 | App-specific config |
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
- **OctoberCMS**: Framework CMS robusto basado en Laravel
- **Eloquent ORM**: Manejo avanzado de base de datos
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

### Estado Actual del Proyecto

**📊 PROGRESO GENERAL: 0% - Proyecto reorganizado por fases**

**🎯 NUEVA ARQUITECTURA MODULAR:**

**FASE 1 (1.0): PLUGIN GLOBALAPI (INFRAESTRUCTURA)** ⏳
- Plugin de infraestructura reutilizable
- OAuth seguro con InvisionCommunity
- Proxy API para Groundhogg
- Gestión segura de credenciales
- **Tiempo**: 1-2 semanas

**FASE 2 (2.0): APLICACIÓN FLUTTER** ⏳  
- App Flutter completa con UI/UX
- Integración con GlobalAPI (segura desde el inicio)
- Funcionalidad completa de contactos
- **Tiempo**: 2-3 semanas

**FASE 3 (3.0): PLUGIN LIDERESAPP (ESPECÍFICO)** ⏳
- Plugin específico para auditoría
- Modelos y endpoints de la app
- Dashboard administrativo
- Testing integral del sistema
- **Tiempo**: 1-2 semanas

**✅ VENTAJAS DE ESTA ARQUITECTURA:**
- **Seguridad desde el inicio**: Sin exposición de credenciales
- **Reutilizable**: GlobalAPI sirve para futuras aplicaciones  
- **Modular**: Cada plugin tiene responsabilidades específicas
- **Escalable**: Fácil agregar nuevas funcionalidades

#### Próximos Pasos Inmediatos (Arquitectura Modular):
1. **Tarea 1.0.1**: Crear estructura del plugin GlobalAPI
2. **Tarea 1.0.2**: Configurar gestión segura de credenciales
3. **Tarea 1.0.3**: Implementar OAuth completo con InvisionCommunity
4. **Tarea 1.0.4**: Crear middleware de autenticación

**🔧 PREREQUISITOS TÉCNICOS:**
- ✅ Servidor web con PHP (ya configurado)
- ✅ OctoberCMS instalado y funcionando
- ✅ Base de datos MySQL disponible
- 📋 Credenciales InvisionCommunity OAuth
- 📋 API Keys de Groundhogg WordPress
- 📋 Flutter SDK instalado

---

**📅 INFORMACIÓN DEL PROYECTO:**
- **Fecha de Inicio**: Por definir
- **Progreso**: 0% - Proyecto nuevo
- **Estado**: Planificación inicial
- **Próximo Hito**: Setup básico del proyecto

## Información del Proyecto
- **Nombre**: Aplicación de Gestión de Contactos CRM (CH-API)
- **Tipo**: Aplicación Flutter con backend OctoberCMS + integración Groundhogg
- **Versión Objetivo**: 1.0 MVP
- **Progreso**: 0% - Iniciando desarrollo
- **Arquitectura**: Flutter + OctoberCMS + MySQL + Groundhogg API

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
- [OctoberCMS Documentation](https://docs.octobercms.com/3.x/) - Documentación oficial del CMS
- [Laravel Documentation](https://laravel.com/docs/10.x) - Framework base de OctoberCMS

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
cd backend && php artisan plugin:install Autor.GlobalAPI

# Plugin LideresApp (Específico)
cd backend && php artisan plugin:install Autor.LideresApp

# Frontend Flutter  
cd lideres_app && flutter pub get

# Migraciones
cd backend && php artisan october:migrate
```

#### Desarrollo:
```bash
# OctoberCMS (ya configurado en servidor)
# GlobalAPI endpoints: /api/auth/*, /api/groundhogg/*
# LideresApp endpoints: /api/audit/*, /api/config/*

# Flutter desarrollo
cd lideres_app && flutter run
```

#### Testing:
```bash
# Tests Plugin GlobalAPI
cd backend && php artisan test plugins/autor/globalapi

# Tests Plugin LideresApp
cd backend && php artisan test plugins/autor/lideresapp

# Tests Flutter
cd lideres_app && flutter test
``` 