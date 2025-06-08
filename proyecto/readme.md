# Líderes App - Sistema de Gestión de Contactos CRM

Aplicación Flutter para gestión de contactos CRM con backend OctoberCMS e integración con Groundhogg WordPress CRM.

## 📋 Descripción

Sistema modular de gestión de contactos que integra:
- **Frontend Flutter**: Aplicación cross-platform (Android, iOS, Web)
- **Backend OctoberCMS**: Infraestructura segura con plugins modulares
- **Integración Groundhogg**: CRM principal de WordPress
- **Autenticación OAuth**: Sistema seguro con InvisionCommunity v5

## 🏗️ Arquitectura

### Componentes Principales

#### 1. Plugin GlobalAPI (Infraestructura)
```
plugins/autor/globalapi/
├── controllers/          # OAuth & Proxy controllers
├── classes/             # Groundhogg & Invision services  
├── middleware/          # Authentication middleware
└── routes.php           # /api/auth/*, /api/groundhogg/*
```

#### 2. Aplicación Flutter
```
lideres_app/
├── lib/features/auth/   # OAuth integration with GlobalAPI
├── lib/services/        # HTTP client for GlobalAPI
└── lib/features/contacts/ # UI consuming secure endpoints
```

#### 3. Plugin LideresApp (Específico)
```
plugins/autor/lideresapp/
├── models/              # Audit & Config models
├── controllers/         # Audit endpoints
└── routes.php           # /api/audit/*, /api/config/*
```

## 🚀 Estado del Desarrollo

**📊 PROGRESO GENERAL: 0% - Proyecto iniciado**

### Fases de Desarrollo:

- **FASE 1**: Plugin GlobalAPI (Infraestructura) - 1-2 semanas ⏳
- **FASE 2**: Aplicación Flutter - 2-3 semanas ⏳  
- **FASE 3**: Plugin LideresApp (Auditoría) - 1-2 semanas ⏳

### Próximos Pasos:
1. ✅ Repositorio Git inicializado
2. 📋 Plugin GlobalAPI - Estructura base
3. 📋 OAuth InvisionCommunity
4. 📋 Proxy API Groundhogg

## 🔧 Tecnologías

- **Flutter 3.16+** - Framework principal
- **OctoberCMS 3.x** - Backend CMS
- **Laravel/Eloquent** - ORM y framework base
- **MySQL 8.0+** - Base de datos
- **Groundhogg API** - CRM de WordPress
- **InvisionCommunity OAuth** - Autenticación

## 📚 Documentación

- [`roadmap.md`](./roadmap.md) - Plan detallado de desarrollo
- Documentación API: *En desarrollo*
- Guías de instalación: *En desarrollo*

## 🔒 Seguridad

- **Sin exposición de credenciales** en frontend
- **OAuth robusto** manejado por backend
- **Middleware de autenticación** obligatorio
- **Rate limiting** y protección anti-abuso

## 📄 Licencia

*Por definir*

---

**Nota**: Este proyecto está en desarrollo inicial. Para más detalles consultar el [roadmap completo](./roadmap.md). 