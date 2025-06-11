<?php
/**
 * Sistema de Capacidades y Roles para WordPress
 *
 * Gestiona roles personalizados y capacidades granulares para el plugin
 * GlobalAPI. Proporciona control detallado de permisos por módulo y
 * funcionalidad específica del sistema.
 *
 * @package GlobalAPI
 * @subpackage Security
 * @since 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Capabilities
 * 
 * Maneja la creación y gestión de roles y capacidades:
 * - Roles personalizados para diferentes niveles de acceso
 * - Capacidades granulares por módulo del plugin
 * - Integración con roles nativos de WordPress
 * - Sistema de permisos jerárquico
 * - Auditoría de cambios de permisos
 */
class Capabilities {

    /**
     * Versión del sistema de capacidades
     */
    const VERSION = '2.0.0';

    /**
     * Prefijo para capacidades personalizadas
     */
    const CAPABILITY_PREFIX = 'globalapi_';

    /**
     * Prefijo para roles personalizados
     */
    const ROLE_PREFIX = 'globalapi_';

    /**
     * Roles personalizados del sistema
     */
    const CUSTOM_ROLES = array(
        'admin' => 'GlobalAPI Administrator',
        'manager' => 'GlobalAPI Manager', 
        'user' => 'GlobalAPI User',
        'reader' => 'GlobalAPI Reader',
        'auditor' => 'GlobalAPI Auditor'
    );

    /**
     * Módulos del sistema
     */
    const MODULES = array(
        'contacts' => 'Contactos',
        'auth' => 'Autenticación',
        'api' => 'API REST',
        'cache' => 'Sistema de Cache',
        'logs' => 'Registros y Auditoría',
        'config' => 'Configuración',
        'security' => 'Seguridad',
        'health' => 'Monitoreo de Salud',
        'oauth' => 'OAuth e InvisionCommunity',
        'groundhogg' => 'Integración Groundhogg'
    );

    /**
     * Acciones disponibles por módulo
     */
    const ACTIONS = array(
        'view' => 'Ver',
        'create' => 'Crear',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'manage' => 'Gestionar',
        'configure' => 'Configurar',
        'audit' => 'Auditar',
        'export' => 'Exportar',
        'import' => 'Importar',
        'test' => 'Probar/Testing'
    );

    /**
     * Cache de capacidades del usuario actual
     */
    private static $user_capabilities_cache = array();

    /**
     * Estadísticas de uso de capacidades
     */
    private static $usage_stats = array();

    /**
     * Inicializar sistema de capacidades
     * 
     * @since 2.0.0
     */
    public static function init() {
        // Hooks de WordPress para gestión de roles
        add_action('init', array(__CLASS__, 'registrar_hooks'));
        
        // Hook de activación del plugin
        add_action('globalapi_plugin_activated', array(__CLASS__, 'crear_roles_capabilities'));
        
        // Hook de desactivación del plugin
        add_action('globalapi_plugin_deactivated', array(__CLASS__, 'limpiar_roles_capabilities'));
        
        // Cargar estadísticas
        self::cargar_estadisticas();
        
        // Verificación periódica de integridad
        add_action('globalapi_security_check', array(__CLASS__, 'verificar_integridad_roles'));
        
        // Auditoría de cambios de roles
        add_action('set_user_role', array(__CLASS__, 'auditar_cambio_rol'), 10, 3);
        add_action('add_user_role', array(__CLASS__, 'auditar_asignacion_rol'), 10, 2);
        add_action('remove_user_role', array(__CLASS__, 'auditar_eliminacion_rol'), 10, 2);
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Hook para verificación de capacidades en cada request
        add_action('wp_loaded', array(__CLASS__, 'verificar_capacidades_usuario'));
        
        // Filtros para modificar capacidades dinámicamente
        add_filter('user_has_cap', array(__CLASS__, 'filtrar_capacidades_usuario'), 10, 4);
        
        // Hook para limpiar cache al cambiar usuario
        add_action('wp_login', array(__CLASS__, 'limpiar_cache_usuario'));
        add_action('wp_logout', array(__CLASS__, 'limpiar_cache_usuario'));
        
        // Debug en footer para administradores
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array(__CLASS__, 'mostrar_debug_capacidades'));
            add_action('admin_footer', array(__CLASS__, 'mostrar_debug_capacidades'));
        }
    }

    /**
     * Crear roles y capacidades personalizados
     * 
     * @since 2.0.0
     */
    public static function crear_roles_capabilities() {
        // Crear capacidades base
        $capabilities = self::generar_capacidades();
        
        // Crear roles personalizados
        foreach (self::CUSTOM_ROLES as $role_key => $role_name) {
            $role_slug = self::ROLE_PREFIX . $role_key;
            
            // Eliminar rol si existe para recrearlo
            if (get_role($role_slug)) {
                remove_role($role_slug);
            }
            
            // Obtener capacidades para este rol
            $role_capabilities = self::obtener_capacidades_rol($role_key, $capabilities);
            
            // Crear el rol
            add_role($role_slug, $role_name, $role_capabilities);
            
            self::registrar_log('role_created', "Rol {$role_name} creado", array(
                'role_slug' => $role_slug,
                'capabilities_count' => count($role_capabilities)
            ));
        }

        // Agregar capacidades a roles existentes de WordPress
        self::agregar_capacidades_roles_wp();

        self::registrar_log('capabilities_system_initialized', 'Sistema de capacidades inicializado');
    }

    /**
     * Generar todas las capacidades del sistema
     * 
     * @return array Capacidades generadas
     * @since 2.0.0
     */
    private static function generar_capacidades() {
        $capabilities = array();

        // Generar capacidades por módulo y acción
        foreach (self::MODULES as $module_key => $module_name) {
            foreach (self::ACTIONS as $action_key => $action_name) {
                $capability = self::CAPABILITY_PREFIX . $module_key . '_' . $action_key;
                $capabilities[$capability] = array(
                    'module' => $module_key,
                    'action' => $action_key,
                    'description' => "{$action_name} {$module_name}"
                );
            }
        }

        // Capacidades especiales del sistema
        $special_capabilities = array(
            'globalapi_full_access' => array(
                'module' => 'system',
                'action' => 'full_access',
                'description' => 'Acceso completo al sistema GlobalAPI'
            ),
            'globalapi_view_dashboard' => array(
                'module' => 'system',
                'action' => 'view_dashboard',
                'description' => 'Ver dashboard principal'
            ),
            'globalapi_manage_system' => array(
                'module' => 'system',
                'action' => 'manage_system',
                'description' => 'Gestionar configuración del sistema'
            ),
            'globalapi_audit_system' => array(
                'module' => 'system',
                'action' => 'audit_system',
                'description' => 'Auditar actividad del sistema'
            ),
            'globalapi_emergency_access' => array(
                'module' => 'system',
                'action' => 'emergency_access',
                'description' => 'Acceso de emergencia al sistema'
            )
        );

        return array_merge($capabilities, $special_capabilities);
    }

    /**
     * Obtener capacidades para un rol específico
     * 
     * @param string $role_key Clave del rol
     * @param array $all_capabilities Todas las capacidades disponibles
     * @return array Capacidades para el rol
     * @since 2.0.0
     */
    private static function obtener_capacidades_rol($role_key, $all_capabilities) {
        $role_capabilities = array();

        switch ($role_key) {
            case 'admin':
                // Administrador: Acceso completo
                foreach ($all_capabilities as $cap => $info) {
                    $role_capabilities[$cap] = true;
                }
                // Capacidades básicas de WordPress
                $role_capabilities['read'] = true;
                $role_capabilities['edit_posts'] = true;
                $role_capabilities['edit_pages'] = true;
                break;

            case 'manager':
                // Manager: Gestión sin configuración crítica
                foreach ($all_capabilities as $cap => $info) {
                    if ($info['action'] !== 'delete' && 
                        $info['module'] !== 'security' &&
                        $cap !== 'globalapi_full_access' &&
                        $cap !== 'globalapi_emergency_access') {
                        $role_capabilities[$cap] = true;
                    }
                }
                $role_capabilities['read'] = true;
                break;

            case 'user':
                // Usuario: Operaciones básicas
                $allowed_modules = array('contacts', 'auth', 'api', 'cache');
                $allowed_actions = array('view', 'create', 'edit');
                
                foreach ($all_capabilities as $cap => $info) {
                    if (in_array($info['module'], $allowed_modules) && 
                        in_array($info['action'], $allowed_actions)) {
                        $role_capabilities[$cap] = true;
                    }
                }
                $role_capabilities['globalapi_view_dashboard'] = true;
                $role_capabilities['read'] = true;
                break;

            case 'reader':
                // Lector: Solo visualización
                foreach ($all_capabilities as $cap => $info) {
                    if ($info['action'] === 'view') {
                        $role_capabilities[$cap] = true;
                    }
                }
                $role_capabilities['globalapi_view_dashboard'] = true;
                $role_capabilities['read'] = true;
                break;

            case 'auditor':
                // Auditor: Acceso a logs y auditoría
                $audit_modules = array('logs', 'auth', 'security', 'health');
                $audit_actions = array('view', 'audit', 'export');
                
                foreach ($all_capabilities as $cap => $info) {
                    if (in_array($info['module'], $audit_modules) && 
                        in_array($info['action'], $audit_actions)) {
                        $role_capabilities[$cap] = true;
                    }
                }
                $role_capabilities['globalapi_view_dashboard'] = true;
                $role_capabilities['globalapi_audit_system'] = true;
                $role_capabilities['read'] = true;
                break;
        }

        return $role_capabilities;
    }

    /**
     * Agregar capacidades a roles existentes de WordPress
     * 
     * @since 2.0.0
     */
    private static function agregar_capacidades_roles_wp() {
        // Administrator: Acceso completo
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('globalapi_full_access');
            $admin_role->add_cap('globalapi_manage_system');
            $admin_role->add_cap('globalapi_emergency_access');
        }

        // Editor: Acceso limitado
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('globalapi_view_dashboard');
            $editor_role->add_cap('globalapi_contacts_view');
            $editor_role->add_cap('globalapi_contacts_edit');
        }

        // Author: Solo lectura de contactos
        $author_role = get_role('author');
        if ($author_role) {
            $author_role->add_cap('globalapi_view_dashboard');
            $author_role->add_cap('globalapi_contacts_view');
        }

        self::registrar_log('wp_roles_updated', 'Capacidades agregadas a roles de WordPress');
    }

    /**
     * Verificar si el usuario actual tiene una capacidad específica
     * 
     * @param string $capability Capacidad a verificar
     * @param int|null $user_id ID del usuario (null para usuario actual)
     * @return bool True si tiene la capacidad
     * @since 2.0.0
     */
    public static function usuario_puede($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Verificar cache
        $cache_key = $user_id . '_' . $capability;
        if (isset(self::$user_capabilities_cache[$cache_key])) {
            return self::$user_capabilities_cache[$cache_key];
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        // Verificar capacidad
        $can_access = false;

        // Super administrador siempre puede
        if (is_super_admin($user_id)) {
            $can_access = true;
        }
        // Verificar capacidad específica
        elseif ($user->has_cap($capability)) {
            $can_access = true;
        }
        // Verificar acceso completo
        elseif ($user->has_cap('globalapi_full_access')) {
            $can_access = true;
        }
        // Verificar acceso de emergencia
        elseif ($user->has_cap('globalapi_emergency_access')) {
            $can_access = true;
        }

        // Cache el resultado
        self::$user_capabilities_cache[$cache_key] = $can_access;

        // Registrar estadística
        self::registrar_uso_capacidad($capability, $user_id, $can_access);

        return $can_access;
    }

    /**
     * Verificar múltiples capacidades (AND)
     * 
     * @param array $capabilities Capacidades a verificar
     * @param int|null $user_id ID del usuario
     * @return bool True si tiene todas las capacidades
     * @since 2.0.0
     */
    public static function usuario_puede_todas($capabilities, $user_id = null) {
        foreach ($capabilities as $capability) {
            if (!self::usuario_puede($capability, $user_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verificar capacidades (OR)
     * 
     * @param array $capabilities Capacidades a verificar
     * @param int|null $user_id ID del usuario
     * @return bool True si tiene al menos una capacidad
     * @since 2.0.0
     */
    public static function usuario_puede_alguna($capabilities, $user_id = null) {
        foreach ($capabilities as $capability) {
            if (self::usuario_puede($capability, $user_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar acceso a módulo específico
     * 
     * @param string $module Módulo a verificar
     * @param string $action Acción requerida
     * @param int|null $user_id ID del usuario
     * @return bool True si tiene acceso
     * @since 2.0.0
     */
    public static function usuario_puede_modulo($module, $action = 'view', $user_id = null) {
        $capability = self::CAPABILITY_PREFIX . $module . '_' . $action;
        return self::usuario_puede($capability, $user_id);
    }

    /**
     * Obtener capacidades del usuario
     * 
     * @param int|null $user_id ID del usuario
     * @return array Capacidades del usuario
     * @since 2.0.0
     */
    public static function obtener_capacidades_usuario($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return array();
        }

        $capabilities = array();
        $all_caps = self::generar_capacidades();

        foreach ($all_caps as $cap => $info) {
            if (self::usuario_puede($cap, $user_id)) {
                $capabilities[$cap] = $info;
            }
        }

        return $capabilities;
    }

    /**
     * Obtener roles del usuario en GlobalAPI
     * 
     * @param int|null $user_id ID del usuario
     * @return array Roles de GlobalAPI del usuario
     * @since 2.0.0
     */
    public static function obtener_roles_globalapi_usuario($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return array();
        }

        $globalapi_roles = array();
        foreach ($user->roles as $role) {
            if (strpos($role, self::ROLE_PREFIX) === 0) {
                $role_key = substr($role, strlen(self::ROLE_PREFIX));
                if (isset(self::CUSTOM_ROLES[$role_key])) {
                    $globalapi_roles[$role_key] = self::CUSTOM_ROLES[$role_key];
                }
            }
        }

        return $globalapi_roles;
    }

    /**
     * Asignar rol de GlobalAPI a usuario
     * 
     * @param int $user_id ID del usuario
     * @param string $role_key Clave del rol
     * @return bool True si se asignó exitosamente
     * @since 2.0.0
     */
    public static function asignar_rol_usuario($user_id, $role_key) {
        if (!isset(self::CUSTOM_ROLES[$role_key])) {
            return false;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $role_slug = self::ROLE_PREFIX . $role_key;
        $user->add_role($role_slug);

        self::registrar_log('role_assigned', 'Rol asignado a usuario', array(
            'user_id' => $user_id,
            'role' => $role_key,
            'assigned_by' => get_current_user_id()
        ));

        return true;
    }

    /**
     * Eliminar rol de GlobalAPI de usuario
     * 
     * @param int $user_id ID del usuario
     * @param string $role_key Clave del rol
     * @return bool True si se eliminó exitosamente
     * @since 2.0.0
     */
    public static function eliminar_rol_usuario($user_id, $role_key) {
        if (!isset(self::CUSTOM_ROLES[$role_key])) {
            return false;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $role_slug = self::ROLE_PREFIX . $role_key;
        $user->remove_role($role_slug);

        self::registrar_log('role_removed', 'Rol eliminado de usuario', array(
            'user_id' => $user_id,
            'role' => $role_key,
            'removed_by' => get_current_user_id()
        ));

        return true;
    }

    /**
     * Filtrar capacidades de usuario dinámicamente
     * 
     * @param array $allcaps Todas las capacidades del usuario
     * @param array $caps Capacidades requeridas
     * @param array $args Argumentos adicionales
     * @param WP_User $user Usuario
     * @return array Capacidades filtradas
     * @since 2.0.0
     */
    public static function filtrar_capacidades_usuario($allcaps, $caps, $args, $user) {
        // Verificar si es una capacidad de GlobalAPI
        foreach ($caps as $cap) {
            if (strpos($cap, self::CAPABILITY_PREFIX) === 0) {
                // Lógica personalizada para capacidades dinámicas
                if (isset($allcaps['globalapi_full_access']) && $allcaps['globalapi_full_access']) {
                    $allcaps[$cap] = true;
                }
            }
        }

        return $allcaps;
    }

    /**
     * Verificar integridad de roles y capacidades
     * 
     * @return array Resultado de la verificación
     * @since 2.0.0
     */
    public static function verificar_integridad_roles() {
        $resultado = array(
            'roles_ok' => 0,
            'roles_missing' => 0,
            'capabilities_ok' => 0,
            'capabilities_missing' => 0,
            'errors' => array()
        );

        // Verificar roles personalizados
        foreach (self::CUSTOM_ROLES as $role_key => $role_name) {
            $role_slug = self::ROLE_PREFIX . $role_key;
            if (get_role($role_slug)) {
                $resultado['roles_ok']++;
            } else {
                $resultado['roles_missing']++;
                $resultado['errors'][] = "Rol faltante: {$role_name}";
            }
        }

        // Verificar capacidades en rol administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            if ($admin_role->has_cap('globalapi_full_access')) {
                $resultado['capabilities_ok']++;
            } else {
                $resultado['capabilities_missing']++;
                $resultado['errors'][] = "Capacidad faltante en administrator: globalapi_full_access";
            }
        }

        self::registrar_log('integrity_check', 'Verificación de integridad de roles', $resultado);

        return $resultado;
    }

    /**
     * Limpiar roles y capacidades del plugin
     * 
     * @since 2.0.0
     */
    public static function limpiar_roles_capabilities() {
        // Eliminar roles personalizados
        foreach (self::CUSTOM_ROLES as $role_key => $role_name) {
            $role_slug = self::ROLE_PREFIX . $role_key;
            remove_role($role_slug);
        }

        // Eliminar capacidades de roles de WordPress
        $wp_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
        $capabilities = array_keys(self::generar_capacidades());

        foreach ($wp_roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }

        self::registrar_log('capabilities_system_cleaned', 'Sistema de capacidades limpiado');
    }

    /**
     * Limpiar cache de capacidades
     * 
     * @since 2.0.0
     */
    public static function limpiar_cache_usuario() {
        self::$user_capabilities_cache = array();
    }

    /**
     * Registrar uso de capacidad para estadísticas
     * 
     * @param string $capability Capacidad utilizada
     * @param int $user_id ID del usuario
     * @param bool $granted Si se otorgó acceso
     * @since 2.0.0
     */
    private static function registrar_uso_capacidad($capability, $user_id, $granted) {
        $date = date('Y-m-d');
        
        if (!isset(self::$usage_stats[$date])) {
            self::$usage_stats[$date] = array();
        }
        
        if (!isset(self::$usage_stats[$date][$capability])) {
            self::$usage_stats[$date][$capability] = array(
                'total' => 0,
                'granted' => 0,
                'denied' => 0
            );
        }
        
        self::$usage_stats[$date][$capability]['total']++;
        
        if ($granted) {
            self::$usage_stats[$date][$capability]['granted']++;
        } else {
            self::$usage_stats[$date][$capability]['denied']++;
        }
        
        // Guardar estadísticas cada 10 usos
        if (self::$usage_stats[$date][$capability]['total'] % 10 === 0) {
            self::guardar_estadisticas();
        }
    }

    /**
     * Obtener estadísticas de uso de capacidades
     * 
     * @param string|null $date Fecha específica (null para todas)
     * @return array Estadísticas de uso
     * @since 2.0.0
     */
    public static function obtener_estadisticas_uso($date = null) {
        if ($date) {
            return self::$usage_stats[$date] ?? array();
        }
        
        return self::$usage_stats;
    }

    /**
     * Auditar cambio de rol
     * 
     * @param int $user_id ID del usuario
     * @param string $role Nuevo rol
     * @param array $old_roles Roles anteriores
     * @since 2.0.0
     */
    public static function auditar_cambio_rol($user_id, $role, $old_roles) {
        self::registrar_log('role_changed', 'Rol de usuario modificado', array(
            'user_id' => $user_id,
            'new_role' => $role,
            'old_roles' => $old_roles,
            'changed_by' => get_current_user_id()
        ));
    }

    /**
     * Auditar asignación de rol
     * 
     * @param int $user_id ID del usuario
     * @param string $role Rol asignado
     * @since 2.0.0
     */
    public static function auditar_asignacion_rol($user_id, $role) {
        if (strpos($role, self::ROLE_PREFIX) === 0) {
            self::registrar_log('globalapi_role_assigned', 'Rol GlobalAPI asignado', array(
                'user_id' => $user_id,
                'role' => $role,
                'assigned_by' => get_current_user_id()
            ));
        }
    }

    /**
     * Auditar eliminación de rol
     * 
     * @param int $user_id ID del usuario
     * @param string $role Rol eliminado
     * @since 2.0.0
     */
    public static function auditar_eliminacion_rol($user_id, $role) {
        if (strpos($role, self::ROLE_PREFIX) === 0) {
            self::registrar_log('globalapi_role_removed', 'Rol GlobalAPI eliminado', array(
                'user_id' => $user_id,
                'role' => $role,
                'removed_by' => get_current_user_id()
            ));
        }
    }

    /**
     * Verificar capacidades del usuario actual
     * 
     * @since 2.0.0
     */
    public static function verificar_capacidades_usuario() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Verificar si el usuario tiene roles de GlobalAPI
        $roles_globalapi = self::obtener_roles_globalapi_usuario($user_id);
        
        if (empty($roles_globalapi) && !current_user_can('administrator')) {
            // Usuario sin roles de GlobalAPI, verificar si necesita uno
            $user = get_user_by('ID', $user_id);
            
            // Asignar rol básico si es necesario
            if ($user && in_array('editor', $user->roles)) {
                self::asignar_rol_usuario($user_id, 'user');
            } elseif ($user && in_array('author', $user->roles)) {
                self::asignar_rol_usuario($user_id, 'reader');
            }
        }
    }

    /**
     * Mostrar debug de capacidades en footer
     * 
     * @since 2.0.0
     */
    public static function mostrar_debug_capacidades() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $roles = self::obtener_roles_globalapi_usuario($user_id);
        $stats = self::obtener_estadisticas_uso(date('Y-m-d'));
        
        echo "<!-- GlobalAPI Capabilities Debug:\n";
        echo "User ID: {$user_id}\n";
        echo "GlobalAPI Roles: " . implode(', ', array_keys($roles)) . "\n";
        echo "Today's Capability Checks: " . count($stats) . "\n";
        echo "Cache Size: " . count(self::$user_capabilities_cache) . "\n";
        echo "-->";
    }

    /**
     * Cargar estadísticas
     * 
     * @since 2.0.0
     */
    private static function cargar_estadisticas() {
        self::$usage_stats = get_option('globalapi_capabilities_stats', array());
    }

    /**
     * Guardar estadísticas
     * 
     * @since 2.0.0
     */
    private static function guardar_estadisticas() {
        update_option('globalapi_capabilities_stats', self::$usage_stats, false);
    }

    /**
     * Registrar evento en logs
     * 
     * @param string $accion Acción realizada
     * @param string $descripcion Descripción del evento
     * @param array $datos Datos adicionales
     * @since 2.0.0
     */
    private static function registrar_log($accion, $descripcion, $datos = array()) {
        if (class_exists('LogAuditoriaGlobalAPI')) {
            LogAuditoriaGlobalAPI::registrar_log(array(
                'accion' => 'capabilities_' . $accion,
                'descripcion' => $descripcion,
                'datos' => $datos
            ));
        }
    }

    /**
     * Obtener información de configuración
     * 
     * @return array Información de configuración
     * @since 2.0.0
     */
    public static function obtener_info_config() {
        return array(
            'version' => self::VERSION,
            'capability_prefix' => self::CAPABILITY_PREFIX,
            'role_prefix' => self::ROLE_PREFIX,
            'custom_roles' => self::CUSTOM_ROLES,
            'modules' => self::MODULES,
            'actions' => self::ACTIONS,
            'roles_created' => count(self::CUSTOM_ROLES),
            'capabilities_generated' => count(self::generar_capacidades()),
            'cache_size' => count(self::$user_capabilities_cache)
        );
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    Capabilities::init();
} 