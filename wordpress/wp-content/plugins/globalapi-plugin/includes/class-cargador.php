<?php
/**
 * Clase Cargador de Hooks del Plugin GlobalAPI
 *
 * Esta clase registra todas las acciones y filtros de WordPress que
 * utiliza el plugin. Mantiene listas de todos los hooks registrados
 * y ejecuta la configuración de todos los hooks cuando WordPress
 * se inicializa.
 *
 * @package     GlobalAPI
 * @subpackage  GlobalAPI/includes
 * @since       2.0.0
 * @author      Colombia Humana - Desarrollo Tecnológico
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase GlobalAPI_Cargador
 *
 * Registra todas las acciones y filtros del plugin con WordPress.
 * Mantiene una lista de todas las acciones y filtros a ser registrados
 * y los ejecuta todos cuando WordPress se inicializa.
 *
 * @since 2.0.0
 */
class GlobalAPI_Cargador {

    /**
     * Array de acciones registradas con WordPress
     *
     * @since  2.0.0
     * @access protected
     * @var    array $acciones Las acciones registradas con WordPress para activar el plugin
     */
    protected $acciones;

    /**
     * Array de filtros registrados con WordPress
     *
     * @since  2.0.0
     * @access protected
     * @var    array $filtros Los filtros registrados con WordPress para activar el plugin
     */
    protected $filtros;

    /**
     * Inicializar las colecciones utilizadas para mantener las acciones y filtros
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->acciones = [];
        $this->filtros = [];
    }

    /**
     * Agregar una nueva acción al array de acciones a registrar con WordPress
     *
     * @since  2.0.0
     * @param  string $hook El nombre de la acción de WordPress a la que se registra el callback
     * @param  object $componente Una referencia a la instancia del objeto en la que está definido el callback
     * @param  string $callback El nombre de la función definida en el $componente
     * @param  int    $prioridad Opcional. La prioridad en la que debe ejecutarse la función. Predeterminado 10
     * @param  int    $argumentos_aceptados Opcional. El número de argumentos que debe aceptar el callback. Predeterminado 1
     */
    public function agregar_accion($hook, $componente, $callback, $prioridad = 10, $argumentos_aceptados = 1) {
        $this->acciones = $this->agregar($this->acciones, $hook, $componente, $callback, $prioridad, $argumentos_aceptados);
    }

    /**
     * Agregar un nuevo filtro al array de filtros a registrar con WordPress
     *
     * @since  2.0.0
     * @param  string $hook El nombre del filtro de WordPress al que se registra el callback
     * @param  object $componente Una referencia a la instancia del objeto en la que está definido el callback
     * @param  string $callback El nombre de la función definida en el $componente
     * @param  int    $prioridad Opcional. La prioridad en la que debe ejecutarse la función. Predeterminado 10
     * @param  int    $argumentos_aceptados Opcional. El número de argumentos que debe aceptar el callback. Predeterminado 1
     */
    public function agregar_filter($hook, $componente, $callback, $prioridad = 10, $argumentos_aceptados = 1) {
        $this->filtros = $this->agregar($this->filtros, $hook, $componente, $callback, $prioridad, $argumentos_aceptados);
    }

    /**
     * Método utilitario para agregar hooks al array de acciones o filtros
     *
     * @since  2.0.0
     * @access private
     * @param  array  $hooks El array de hooks al que agregar el hook
     * @param  string $hook El nombre del hook de WordPress
     * @param  object $componente Una referencia a la instancia del objeto en la que está definido el callback
     * @param  string $callback El nombre de la función definida en el $componente
     * @param  int    $prioridad La prioridad en la que debe ejecutarse la función
     * @param  int    $argumentos_aceptados El número de argumentos que debe aceptar el callback
     * @return array El array de hooks modificado
     */
    private function agregar($hooks, $hook, $componente, $callback, $prioridad, $argumentos_aceptados) {
        $hooks[] = [
            'hook'                => $hook,
            'componente'          => $componente,
            'callback'            => $callback,
            'prioridad'           => $prioridad,
            'argumentos_aceptados' => $argumentos_aceptados
        ];

        return $hooks;
    }

    /**
     * Registrar los filtros y acciones con WordPress
     *
     * @since 2.0.0
     */
    public function ejecutar() {
        // Registrar todas las acciones
        foreach ($this->acciones as $hook) {
            add_action(
                $hook['hook'],
                [$hook['componente'], $hook['callback']],
                $hook['prioridad'],
                $hook['argumentos_aceptados']
            );
        }

        // Registrar todos los filtros
        foreach ($this->filtros as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['componente'], $hook['callback']],
                $hook['prioridad'],
                $hook['argumentos_aceptados']
            );
        }

        // Log de hooks registrados
        error_log(sprintf(
            'GlobalAPI Cargador: %d acciones y %d filtros registrados',
            count($this->acciones),
            count($this->filtros)
        ));
    }

    /**
     * Obtener todas las acciones registradas
     *
     * @since 2.0.0
     * @return array Array de todas las acciones registradas
     */
    public function obtener_acciones() {
        return $this->acciones;
    }

    /**
     * Obtener todos los filtros registrados
     *
     * @since 2.0.0
     * @return array Array de todos los filtros registrados
     */
    public function obtener_filtros() {
        return $this->filtros;
    }

    /**
     * Obtener el número total de hooks registrados
     *
     * @since 2.0.0
     * @return int Número total de hooks (acciones + filtros)
     */
    public function obtener_total_hooks() {
        return count($this->acciones) + count($this->filtros);
    }

    /**
     * Verificar si un hook específico está registrado
     *
     * @since 2.0.0
     * @param string $hook_nombre Nombre del hook a verificar
     * @param string $tipo Tipo de hook ('accion' o 'filtro'). Si es null, busca en ambos
     * @return bool True si el hook está registrado, false si no
     */
    public function hook_esta_registrado($hook_nombre, $tipo = null) {
        $buscar_en = [];
        
        if ($tipo === 'accion' || $tipo === null) {
            $buscar_en = array_merge($buscar_en, $this->acciones);
        }
        
        if ($tipo === 'filtro' || $tipo === null) {
            $buscar_en = array_merge($buscar_en, $this->filtros);
        }

        foreach ($buscar_en as $hook) {
            if ($hook['hook'] === $hook_nombre) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener información detallada sobre un hook específico
     *
     * @since 2.0.0
     * @param string $hook_nombre Nombre del hook
     * @param string $tipo Tipo de hook ('accion' o 'filtro'). Si es null, busca en ambos
     * @return array|null Array con información del hook o null si no se encuentra
     */
    public function obtener_info_hook($hook_nombre, $tipo = null) {
        $buscar_en = [];
        
        if ($tipo === 'accion' || $tipo === null) {
            foreach ($this->acciones as $hook) {
                if ($hook['hook'] === $hook_nombre) {
                    return array_merge($hook, ['tipo' => 'accion']);
                }
            }
        }
        
        if ($tipo === 'filtro' || $tipo === null) {
            foreach ($this->filtros as $hook) {
                if ($hook['hook'] === $hook_nombre) {
                    return array_merge($hook, ['tipo' => 'filtro']);
                }
            }
        }

        return null;
    }

    /**
     * Obtener lista de todos los hooks únicos registrados
     *
     * @since 2.0.0
     * @return array Array con nombres únicos de hooks
     */
    public function obtener_hooks_unicos() {
        $hooks_unicos = [];

        foreach ($this->acciones as $hook) {
            if (!in_array($hook['hook'], $hooks_unicos)) {
                $hooks_unicos[] = $hook['hook'];
            }
        }

        foreach ($this->filtros as $hook) {
            if (!in_array($hook['hook'], $hooks_unicos)) {
                $hooks_unicos[] = $hook['hook'];
            }
        }

        sort($hooks_unicos);
        return $hooks_unicos;
    }

    /**
     * Eliminar una acción específica de la lista
     *
     * @since 2.0.0
     * @param string $hook_nombre Nombre del hook
     * @param string $callback Nombre del callback (opcional)
     * @return bool True si se eliminó, false si no se encontró
     */
    public function eliminar_accion($hook_nombre, $callback = null) {
        $encontrado = false;
        
        foreach ($this->acciones as $index => $hook) {
            if ($hook['hook'] === $hook_nombre && ($callback === null || $hook['callback'] === $callback)) {
                unset($this->acciones[$index]);
                $encontrado = true;
            }
        }

        // Reindexar el array
        $this->acciones = array_values($this->acciones);
        
        return $encontrado;
    }

    /**
     * Eliminar un filtro específico de la lista
     *
     * @since 2.0.0
     * @param string $hook_nombre Nombre del hook
     * @param string $callback Nombre del callback (opcional)
     * @return bool True si se eliminó, false si no se encontró
     */
    public function eliminar_filtro($hook_nombre, $callback = null) {
        $encontrado = false;
        
        foreach ($this->filtros as $index => $hook) {
            if ($hook['hook'] === $hook_nombre && ($callback === null || $hook['callback'] === $callback)) {
                unset($this->filtros[$index]);
                $encontrado = true;
            }
        }

        // Reindexar el array
        $this->filtros = array_values($this->filtros);
        
        return $encontrado;
    }

    /**
     * Limpiar todos los hooks registrados
     *
     * @since 2.0.0
     */
    public function limpiar_hooks() {
        $total_anterior = $this->obtener_total_hooks();
        
        $this->acciones = [];
        $this->filtros = [];
        
        error_log("GlobalAPI Cargador: {$total_anterior} hooks limpiados");
    }

    /**
     * Obtener estadísticas de hooks registrados
     *
     * @since 2.0.0
     * @return array Estadísticas detalladas
     */
    public function obtener_estadisticas() {
        $hooks_por_tipo = [];
        $componentes_unicos = [];

        // Analizar acciones
        foreach ($this->acciones as $hook) {
            $nombre_hook = $hook['hook'];
            $componente = get_class($hook['componente']);
            
            if (!isset($hooks_por_tipo[$nombre_hook])) {
                $hooks_por_tipo[$nombre_hook] = ['acciones' => 0, 'filtros' => 0];
            }
            $hooks_por_tipo[$nombre_hook]['acciones']++;
            
            if (!in_array($componente, $componentes_unicos)) {
                $componentes_unicos[] = $componente;
            }
        }

        // Analizar filtros
        foreach ($this->filtros as $hook) {
            $nombre_hook = $hook['hook'];
            $componente = get_class($hook['componente']);
            
            if (!isset($hooks_por_tipo[$nombre_hook])) {
                $hooks_por_tipo[$nombre_hook] = ['acciones' => 0, 'filtros' => 0];
            }
            $hooks_por_tipo[$nombre_hook]['filtros']++;
            
            if (!in_array($componente, $componentes_unicos)) {
                $componentes_unicos[] = $componente;
            }
        }

        return [
            'total_acciones' => count($this->acciones),
            'total_filtros' => count($this->filtros),
            'total_hooks' => $this->obtener_total_hooks(),
            'hooks_unicos' => count($this->obtener_hooks_unicos()),
            'componentes_unicos' => count($componentes_unicos),
            'hooks_por_tipo' => $hooks_por_tipo,
            'componentes' => $componentes_unicos
        ];
    }
} 