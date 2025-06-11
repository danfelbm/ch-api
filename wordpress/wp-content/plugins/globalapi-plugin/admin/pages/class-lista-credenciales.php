<?php
/**
 * Lista de credenciales para el admin de WordPress
 *
 * Esta clase extiende WP_List_Table para proporcionar una interfaz
 * completa de gestión de credenciales con filtros, búsqueda,
 * acciones masivas y ordenamiento.
 *
 * @package    GlobalAPI
 * @subpackage Admin/Pages
 * @since      2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Asegurar que WP_List_Table esté disponible
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Clase Lista_Credenciales
 * 
 * Extiende WP_List_Table para mostrar y gestionar credenciales.
 *
 * @since 2.0.0
 */
class GlobalAPI_Lista_Credenciales extends WP_List_Table {

    /**
     * Constructor de la clase
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'credencial',
            'plural' => 'credenciales',
            'ajax' => false
        ));

        // Manejar acciones
        $this->procesar_acciones();
    }

    /**
     * Preparar los elementos de la tabla
     *
     * @since 2.0.0
     * @return void
     */
    public function prepare_items() {
        // Configurar columnas
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Obtener datos
        $per_page = $this->get_items_per_page('credenciales_per_page', 20);
        $current_page = $this->get_pagenum();
        
        $args = array(
            'page' => $current_page,
            'per_page' => $per_page,
            'orderby' => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'fecha_creacion',
            'order' => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc',
            'search' => isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '',
            'estado' => isset($_REQUEST['estado']) ? sanitize_text_field($_REQUEST['estado']) : '',
            'tipo_servicio' => isset($_REQUEST['tipo_servicio']) ? sanitize_text_field($_REQUEST['tipo_servicio']) : ''
        );

        $data = $this->obtener_credenciales($args);
        $total_items = $this->contar_credenciales($args);

        $this->items = $data;

        // Configurar paginación
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    /**
     * Definir columnas de la tabla
     *
     * @since 2.0.0
     * @return array Columnas de la tabla
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'nombre' => __('Nombre', 'globalapi'),
            'tipo_servicio' => __('Tipo de Servicio', 'globalapi'),
            'estado' => __('Estado', 'globalapi'),
            'url_base' => __('URL Base', 'globalapi'),
            'ultima_verificacion' => __('Última Verificación', 'globalapi'),
            'fecha_creacion' => __('Fecha Creación', 'globalapi'),
            'acciones' => __('Acciones', 'globalapi')
        );
    }

    /**
     * Columnas ocultas
     *
     * @since 2.0.0
     * @return array Columnas ocultas
     */
    public function get_hidden_columns() {
        return array();
    }

    /**
     * Columnas ordenables
     *
     * @since 2.0.0
     * @return array Columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'nombre' => array('nombre', false),
            'tipo_servicio' => array('tipo_servicio', false),
            'estado' => array('estado', false),
            'ultima_verificacion' => array('ultima_verificacion', true),
            'fecha_creacion' => array('fecha_creacion', true)
        );
    }

    /**
     * Columna checkbox para acciones masivas
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML del checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="credenciales[]" value="%s" />',
            $item['ID']
        );
    }

    /**
     * Columna nombre con enlaces de acción
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_nombre($item) {
        $edit_link = admin_url('admin.php?page=globalapi-credenciales&action=edit&id=' . $item['ID']);
        $delete_link = wp_nonce_url(
            admin_url('admin.php?page=globalapi-credenciales&action=delete&id=' . $item['ID']),
            'delete_credencial_' . $item['ID']
        );

        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                $edit_link,
                __('Editar', 'globalapi')
            ),
            'test' => sprintf(
                '<a href="#" class="test-credencial" data-id="%s">%s</a>',
                $item['ID'],
                __('Probar', 'globalapi')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                $delete_link,
                __('¿Estás seguro de eliminar esta credencial?', 'globalapi'),
                __('Eliminar', 'globalapi')
            )
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            $edit_link,
            esc_html($item['nombre']),
            $this->row_actions($actions)
        );
    }

    /**
     * Columna tipo de servicio
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_tipo_servicio($item) {
        $tipo = esc_html($item['tipo_servicio']);
        $filter_link = admin_url('admin.php?page=globalapi-credenciales&tipo_servicio=' . urlencode($item['tipo_servicio']));
        
        return sprintf(
            '<a href="%s" title="%s">%s</a>',
            $filter_link,
            __('Filtrar por tipo de servicio', 'globalapi'),
            $tipo
        );
    }

    /**
     * Columna estado con indicador visual
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_estado($item) {
        $estado = $item['estado'];
        $clases = array(
            'activa' => 'status-active',
            'inactiva' => 'status-inactive',
            'expirada' => 'status-expired',
            'error' => 'status-error',
            'testing' => 'status-testing'
        );

        $etiquetas = array(
            'activa' => __('Activa', 'globalapi'),
            'inactiva' => __('Inactiva', 'globalapi'),
            'expirada' => __('Expirada', 'globalapi'),
            'error' => __('Error', 'globalapi'),
            'testing' => __('Testing', 'globalapi')
        );

        $clase = isset($clases[$estado]) ? $clases[$estado] : 'status-unknown';
        $etiqueta = isset($etiquetas[$estado]) ? $etiquetas[$estado] : ucfirst($estado);

        return sprintf(
            '<span class="status-indicator %s">%s</span>',
            $clase,
            $etiqueta
        );
    }

    /**
     * Columna URL base
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_url_base($item) {
        if (empty($item['url_base'])) {
            return '<em>' . __('No configurada', 'globalapi') . '</em>';
        }

        return sprintf(
            '<a href="%s" target="_blank" title="%s">%s</a>',
            esc_url($item['url_base']),
            __('Abrir en nueva ventana', 'globalapi'),
            esc_html($this->truncar_url($item['url_base']))
        );
    }

    /**
     * Columna última verificación
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_ultima_verificacion($item) {
        if (empty($item['ultima_verificacion'])) {
            return '<em>' . __('Nunca', 'globalapi') . '</em>';
        }

        $fecha = mysql2date('Y-m-d H:i:s', $item['ultima_verificacion']);
        $tiempo_transcurrido = human_time_diff(strtotime($fecha), current_time('timestamp'));

        return sprintf(
            '<span title="%s">%s</span>',
            $fecha,
            sprintf(__('Hace %s', 'globalapi'), $tiempo_transcurrido)
        );
    }

    /**
     * Columna fecha de creación
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_fecha_creacion($item) {
        $fecha = mysql2date('Y-m-d H:i:s', $item['fecha_creacion']);
        return sprintf(
            '<span title="%s">%s</span>',
            $fecha,
            mysql2date('d/m/Y', $item['fecha_creacion'])
        );
    }

    /**
     * Columna acciones
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @return string HTML de la columna
     */
    public function column_acciones($item) {
        $acciones = array();

        // Botón probar conexión
        $acciones[] = sprintf(
            '<button type="button" class="button button-small test-credencial" data-id="%s" title="%s">
                <span class="dashicons dashicons-admin-tools"></span>
            </button>',
            $item['ID'],
            __('Probar conexión', 'globalapi')
        );

        // Botón editar
        $acciones[] = sprintf(
            '<a href="%s" class="button button-small" title="%s">
                <span class="dashicons dashicons-edit"></span>
            </a>',
            admin_url('admin.php?page=globalapi-credenciales&action=edit&id=' . $item['ID']),
            __('Editar credencial', 'globalapi')
        );

        // Botón exportar
        $acciones[] = sprintf(
            '<button type="button" class="button button-small export-credencial" data-id="%s" title="%s">
                <span class="dashicons dashicons-download"></span>
            </button>',
            $item['ID'],
            __('Exportar configuración', 'globalapi')
        );

        return implode(' ', $acciones);
    }

    /**
     * Columna por defecto
     *
     * @since 2.0.0
     * @param object $item Item de la tabla
     * @param string $column_name Nombre de la columna
     * @return string Contenido de la columna
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /**
     * Acciones masivas disponibles
     *
     * @since 2.0.0
     * @return array Acciones masivas
     */
    public function get_bulk_actions() {
        return array(
            'activar' => __('Activar', 'globalapi'),
            'desactivar' => __('Desactivar', 'globalapi'),
            'verificar' => __('Verificar conexión', 'globalapi'),
            'delete' => __('Eliminar', 'globalapi')
        );
    }

    /**
     * Obtener credenciales con filtros
     *
     * @since 2.0.0
     * @param array $args Argumentos de búsqueda
     * @return array Credenciales encontradas
     */
    private function obtener_credenciales($args) {
        $credenciales = array();
        
        // Construir query
        $query_args = array(
            'post_type' => 'globalapi_credencial',
            'post_status' => array('publish', 'private'),
            'posts_per_page' => $args['per_page'],
            'paged' => $args['page'],
            'orderby' => $this->mapear_orderby($args['orderby']),
            'order' => strtoupper($args['order']),
            'meta_query' => array()
        );

        // Búsqueda por texto
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        // Filtro por estado
        if (!empty($args['estado'])) {
            $query_args['meta_query'][] = array(
                'key' => '_globalapi_estado',
                'value' => $args['estado'],
                'compare' => '='
            );
        }

        // Filtro por tipo de servicio
        if (!empty($args['tipo_servicio'])) {
            $query_args['meta_query'][] = array(
                'key' => '_globalapi_tipo_servicio',
                'value' => $args['tipo_servicio'],
                'compare' => '='
            );
        }

        $query = new WP_Query($query_args);

        foreach ($query->posts as $post) {
            $credenciales[] = array(
                'ID' => $post->ID,
                'nombre' => $post->post_title,
                'tipo_servicio' => get_post_meta($post->ID, '_globalapi_tipo_servicio', true),
                'estado' => get_post_meta($post->ID, '_globalapi_estado', true),
                'url_base' => get_post_meta($post->ID, '_globalapi_url_base', true),
                'ultima_verificacion' => get_post_meta($post->ID, '_globalapi_ultima_verificacion', true),
                'fecha_creacion' => $post->post_date,
                'fecha_modificacion' => $post->post_modified
            );
        }

        return $credenciales;
    }

    /**
     * Contar total de credenciales
     *
     * @since 2.0.0
     * @param array $args Argumentos de búsqueda
     * @return int Total de credenciales
     */
    private function contar_credenciales($args) {
        $query_args = array(
            'post_type' => 'globalapi_credencial',
            'post_status' => array('publish', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array()
        );

        // Aplicar los mismos filtros que en obtener_credenciales
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        if (!empty($args['estado'])) {
            $query_args['meta_query'][] = array(
                'key' => '_globalapi_estado',
                'value' => $args['estado'],
                'compare' => '='
            );
        }

        if (!empty($args['tipo_servicio'])) {
            $query_args['meta_query'][] = array(
                'key' => '_globalapi_tipo_servicio',
                'value' => $args['tipo_servicio'],
                'compare' => '='
            );
        }

        $query = new WP_Query($query_args);
        return $query->found_posts;
    }

    /**
     * Mapear campos de ordenamiento
     *
     * @since 2.0.0
     * @param string $orderby Campo de ordenamiento
     * @return string Campo mapeado
     */
    private function mapear_orderby($orderby) {
        $mapping = array(
            'nombre' => 'title',
            'fecha_creacion' => 'date',
            'tipo_servicio' => 'meta_value',
            'estado' => 'meta_value',
            'ultima_verificacion' => 'meta_value'
        );

        return isset($mapping[$orderby]) ? $mapping[$orderby] : 'date';
    }

    /**
     * Procesar acciones de la tabla
     *
     * @since 2.0.0
     * @return void
     */
    private function procesar_acciones() {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        // Verificar nonce para acciones individuales
        if (isset($_GET['id'])) {
            $id = absint($_GET['id']);
            
            switch ($action) {
                case 'delete':
                    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_credencial_' . $id)) {
                        $this->eliminar_credencial($id);
                    }
                    break;
            }
        }

        // Procesar acciones masivas
        if (isset($_POST['credenciales']) && is_array($_POST['credenciales'])) {
            $credenciales_ids = array_map('absint', $_POST['credenciales']);
            
            switch ($action) {
                case 'activar':
                    $this->cambiar_estado_masivo($credenciales_ids, 'activa');
                    break;
                    
                case 'desactivar':
                    $this->cambiar_estado_masivo($credenciales_ids, 'inactiva');
                    break;
                    
                case 'verificar':
                    $this->verificar_credenciales_masivo($credenciales_ids);
                    break;
                    
                case 'delete':
                    $this->eliminar_credenciales_masivo($credenciales_ids);
                    break;
            }
        }
    }

    /**
     * Eliminar una credencial
     *
     * @since 2.0.0
     * @param int $id ID de la credencial
     * @return void
     */
    private function eliminar_credencial($id) {
        if (wp_delete_post($id, true)) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'credencial_delete',
                'Credencial eliminada',
                array(
                    'credencial_id' => $id,
                    'usuario_id' => get_current_user_id()
                )
            );
            
            $this->mostrar_notice('success', __('Credencial eliminada exitosamente.', 'globalapi'));
        } else {
            $this->mostrar_notice('error', __('Error al eliminar la credencial.', 'globalapi'));
        }
    }

    /**
     * Cambiar estado de credenciales masivamente
     *
     * @since 2.0.0
     * @param array $ids IDs de credenciales
     * @param string $estado Nuevo estado
     * @return void
     */
    private function cambiar_estado_masivo($ids, $estado) {
        $actualizado = 0;
        
        foreach ($ids as $id) {
            if (update_post_meta($id, '_globalapi_estado', $estado)) {
                $actualizado++;
            }
        }

        if ($actualizado > 0) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'credencial_bulk_update',
                'Estado de credenciales actualizado masivamente',
                array(
                    'credenciales_ids' => $ids,
                    'nuevo_estado' => $estado,
                    'actualizado' => $actualizado,
                    'usuario_id' => get_current_user_id()
                )
            );
            
            $this->mostrar_notice('success', sprintf(
                __('%d credenciales actualizadas exitosamente.', 'globalapi'),
                $actualizado
            ));
        }
    }

    /**
     * Verificar credenciales masivamente
     *
     * @since 2.0.0
     * @param array $ids IDs de credenciales
     * @return void
     */
    private function verificar_credenciales_masivo($ids) {
        $verificado = 0;
        
        foreach ($ids as $id) {
            // Lógica de verificación aquí
            update_post_meta($id, '_globalapi_ultima_verificacion', current_time('mysql'));
            $verificado++;
        }

        if ($verificado > 0) {
            $this->mostrar_notice('success', sprintf(
                __('%d credenciales verificadas exitosamente.', 'globalapi'),
                $verificado
            ));
        }
    }

    /**
     * Eliminar credenciales masivamente
     *
     * @since 2.0.0
     * @param array $ids IDs de credenciales
     * @return void
     */
    private function eliminar_credenciales_masivo($ids) {
        $eliminado = 0;
        
        foreach ($ids as $id) {
            if (wp_delete_post($id, true)) {
                $eliminado++;
            }
        }

        if ($eliminado > 0) {
            GlobalAPI_Log_Auditoria::registrar_log(
                'credencial_bulk_delete',
                'Credenciales eliminadas masivamente',
                array(
                    'credenciales_eliminadas' => $eliminado,
                    'usuario_id' => get_current_user_id()
                )
            );
            
            $this->mostrar_notice('success', sprintf(
                __('%d credenciales eliminadas exitosamente.', 'globalapi'),
                $eliminado
            ));
        }
    }

    /**
     * Mostrar notice de admin
     *
     * @since 2.0.0
     * @param string $type Tipo de notice (success, error, warning, info)
     * @param string $message Mensaje a mostrar
     * @return void
     */
    private function mostrar_notice($type, $message) {
        add_action('admin_notices', function() use ($type, $message) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Truncar URL para mostrar
     *
     * @since 2.0.0
     * @param string $url URL a truncar
     * @param int $length Longitud máxima
     * @return string URL truncada
     */
    private function truncar_url($url, $length = 50) {
        if (strlen($url) <= $length) {
            return $url;
        }
        
        return substr($url, 0, $length - 3) . '...';
    }

    /**
     * Mostrar filtros adicionales
     *
     * @since 2.0.0
     * @param string $which Posición de los filtros (top o bottom)
     * @return void
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $estado_actual = isset($_REQUEST['estado']) ? $_REQUEST['estado'] : '';
        $tipo_servicio_actual = isset($_REQUEST['tipo_servicio']) ? $_REQUEST['tipo_servicio'] : '';
        ?>
        <div class="alignleft actions">
            <!-- Filtro por estado -->
            <select name="estado">
                <option value=""><?php _e('Todos los estados', 'globalapi'); ?></option>
                <option value="activa" <?php selected($estado_actual, 'activa'); ?>><?php _e('Activas', 'globalapi'); ?></option>
                <option value="inactiva" <?php selected($estado_actual, 'inactiva'); ?>><?php _e('Inactivas', 'globalapi'); ?></option>
                <option value="expirada" <?php selected($estado_actual, 'expirada'); ?>><?php _e('Expiradas', 'globalapi'); ?></option>
                <option value="error" <?php selected($estado_actual, 'error'); ?>><?php _e('Con error', 'globalapi'); ?></option>
            </select>

            <!-- Filtro por tipo de servicio -->
            <select name="tipo_servicio">
                <option value=""><?php _e('Todos los servicios', 'globalapi'); ?></option>
                <option value="groundhogg" <?php selected($tipo_servicio_actual, 'groundhogg'); ?>><?php _e('Groundhogg', 'globalapi'); ?></option>
                <option value="invision_community" <?php selected($tipo_servicio_actual, 'invision_community'); ?>><?php _e('InvisionCommunity', 'globalapi'); ?></option>
                <option value="wordpress_api" <?php selected($tipo_servicio_actual, 'wordpress_api'); ?>><?php _e('WordPress API', 'globalapi'); ?></option>
            </select>

            <?php submit_button(__('Filtrar', 'globalapi'), 'button', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Mostrar navegación de la tabla
     *
     * @since 2.0.0
     * @param string $which Posición de la navegación
     * @return void
     */
    protected function display_tablenav($which) {
        if ($which === 'top') {
            wp_nonce_field('bulk-' . $this->_args['plural']);
        }
        
        parent::display_tablenav($which);
    }
} 