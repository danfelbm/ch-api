# Changelog - CH-API
## Registro de Cambios del Proyecto

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere al [Versionado Semántico](https://semver.org/lang/es/).

## [Sin liberar]

### Agregado
- **OctoberCMS**: Instalación completa en raíz del proyecto
- **Base de datos**: Configuración y migración exitosa (lideresapp)
- **Estructura de plugins**: Directorio preparado para desarrollo de plugins
- **Documentación**: Movida a carpeta `proyecto/` (changelog.md, readme.md, roadmap.md)
- **Playground OctoberCMS**: Documentado ruta del plugin de prueba para facilitar desarrollo

### Reorganizado
- **Estructura**: OctoberCMS movido de `/globalapi/` a la raíz del proyecto
- **URLs**: Cambiadas de `lideresapp.test/globalapi` a `lideresapp.test`
- **ASSET_URL**: Actualizado para la raíz (`lideresapp.test`)
- **RewriteBase**: Eliminado del .htaccess (ya no es subdirectorio)
- **Caché**: Limpiada para aplicar cambios

### Corregido
- **.gitignore**: Restaurado configuración específica para Flutter + OctoberCMS + archivos sensibles
- **Protección proyecto/roadmap.md**: Re-agregado al .gitignore por seguridad
- **Configuración OctoberCMS**: Restauradas exclusiones de storage, cache y vendor
- **Configuración Flutter**: Agregadas exclusiones específicas para desarrollo móvil

### Completado
- **Tarea 1.0.1**: Estructura Plugin GlobalAPI ✅

## [0.0.1] - 2024-06-08

### Agregado
- **Repositorio Git**: Inicialización del proyecto ch-api
- **README.md**: Documentación base del proyecto
- **roadmap.md**: Plan detallado de desarrollo con arquitectura modular
- **.gitignore**: Configuración para Flutter, OctoberCMS y archivos sensibles
- **Configuración GitHub**: Repositorio privado configurado
- **Credenciales APIs**: Configuración completa de Groundhogg e InvisionCommunity
- **Instrucciones de contexto**: Reglas para mantener roadmap y changelog

### Configurado
- **Git**: Usuario danfelbm, email danfelbm@gmail.com
- **GitHub CLI**: Autenticación y configuración completa
- **Arquitectura**: Definición de 3 fases de desarrollo modular

### Seguridad
- **roadmap.md**: Movido a .gitignore para proteger información confidencial
- **Credenciales**: APIs de Colombia Humana configuradas localmente

---

## Tipos de Cambios
- `Agregado` para funcionalidades nuevas
- `Cambiado` para cambios en funcionalidades existentes
- `Depreciado` para funcionalidades que serán removidas
- `Removido` para funcionalidades removidas
- `Corregido` para corrección de errores
- `Seguridad` para cambios relacionados con vulnerabilidades

## Estructura de Entradas
```
## [Versión] - YYYY-MM-DD
### Tipo de Cambio
- Descripción del cambio
- **Archivo afectado**: Detalles específicos
```

## Enlaces
- [Repositorio](https://github.com/danfelbm/ch-api)
- [Roadmap](./roadmap.md)
- [README](./README.md) 