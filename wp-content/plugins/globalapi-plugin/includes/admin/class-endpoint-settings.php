<?php
/**
 * Configuración de Endpoints y Permisos - Versión de Test
 */

if (!defined('ABSPATH')) {
    exit;
}

class GlobalAPI_Endpoint_Settings {
    
    public function __construct() {
        // Solo registrar un log para confirmar que se carga
        error_log('GlobalAPI_Endpoint_Settings: Clase cargada correctamente');
    }
} 