<?php
/**
 * Widgets para WordPress Dashboard
 *
 * @package    GlobalAPI
 * @subpackage Admin/Widgets
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GlobalAPI_Dashboard_Widgets {

    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'agregar_widgets'));
        add_action('wp_ajax_globalapi_widget_refresh', array($this, 'ajax_refresh_widget'));
    }

    /**
     * Agregar widgets al dashboard
     */
    public function agregar_widgets() {
        // Solo para usuarios con capacidad de gestionar opciones
        if (!current_user_can('manage_options')) {
            return;
        }

        // Widget principal de estado
        wp_add_dashboard_widget(
            'globalapi_estado',
            __('GlobalAPI - Estado del Sistema', 'globalapi'),
            array($this, 'widget_estado_sistema'),
            array($this, 'widget_estado_sistema_config')
        );

        // Widget de estadísticas
        wp_add_dashboard_widget(
            'globalapi_estadisticas',
            __('GlobalAPI - Estadísticas', 'globalapi'),
            array($this, 'widget_estadisticas')
        );

        // Widget de logs recientes
        wp_add_dashboard_widget(
            'globalapi_logs_recientes',
            __('GlobalAPI - Logs Recientes', 'globalapi'),
            array($this, 'widget_logs_recientes')
        );
    }

    /**
     * Widget de estado del sistema
     */
    public function widget_estado_sistema() {
        $config = get_option('globalapi_configuracion', array());
        $plugin_habilitado = isset($config['plugin_habilitado']) && $config['plugin_habilitado'];
        
        // Obtener credenciales activas
        $credenciales_activas = get_posts(array(
            'post_type' => 'globalapi_credencial',
            'post_status' => 'private',
            'meta_query' => array(
                array(
                    'key' => '_globalapi_estado',
                    'value' => 'activa',
                    'compare' => '='
                )
            ),
            'numberposts' => -1
        ));

        // Estado general
        $estado_general = 'ok';
        if (!$plugin_habilitado) {
            $estado_general = 'disabled';
        } elseif (empty($credenciales_activas)) {
            $estado_general = 'warning';
        }

        ?>
        <div class="globalapi-widget-estado">
            <div class="estado-general estado-<?php echo $estado_general; ?>">
                <span class="dashicons dashicons-<?php echo $this->obtener_icono_estado($estado_general); ?>"></span>
                <strong><?php echo $this->obtener_texto_estado($estado_general); ?></strong>
            </div>

            <div class="estadisticas-rapidas">
                <div class="stat-item">
                    <span class="numero"><?php echo count($credenciales_activas); ?></span>
                    <span class="etiqueta"><?php _e('APIs Activas', 'globalapi'); ?></span>
                </div>
                
                <div class="stat-item">
                    <?php
                    $logs_hoy = get_posts(array(
                        'post_type' => 'globalapi_log',
                        'date_query' => array(
                            array(
                                'after' => '1 day ago'
                            )
                        ),
                        'numberposts' => -1
                    ));
                    ?>
                    <span class="numero"><?php echo count($logs_hoy); ?></span>
                    <span class="etiqueta"><?php _e('Logs Hoy', 'globalapi'); ?></span>
                </div>
            </div>

            <div class="acciones-rapidas">
                <a href="<?php echo admin_url('admin.php?page=globalapi'); ?>" class="button button-primary button-small">
                    <?php _e('Ver Dashboard', 'globalapi'); ?>
                </a>
                <button class="button button-secondary button-small refresh-widget" data-widget="estado">
                    <?php _e('Actualizar', 'globalapi'); ?>
                </button>
            </div>
        </div>

        <style>
        .globalapi-widget-estado .estado-general {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .estado-ok { background: #d4edda; color: #155724; }
        .estado-warning { background: #fff3cd; color: #856404; }
        .estado-disabled { background: #f8d7da; color: #721c24; }
        .estadisticas-rapidas {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-item .numero {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .stat-item .etiqueta {
            font-size: 11px;
            color: #666;
        }
        .acciones-rapidas {
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        </style>
        <?php
    }

    /**
     * Widget de estadísticas
     */
    public function widget_estadisticas() {
        // Estadísticas de credenciales
        $total_credenciales = wp_count_posts('globalapi_credencial');
        
        // Estadísticas de logs por severidad
        $logs_error = get_posts(array(
            'post_type' => 'globalapi_log',
            'tax_query' => array(
                array(
                    'taxonomy' => 'globalapi_severidad',
                    'field' => 'slug',
                    'terms' => array('error', 'critical')
                )
            ),
            'date_query' => array(
                array('after' => '7 days ago')
            ),
            'numberposts' => -1
        ));

        ?>
        <div class="globalapi-estadisticas">
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Métrica', 'globalapi'); ?></th>
                        <th><?php _e('Valor', 'globalapi'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Credenciales Totales', 'globalapi'); ?></td>
                        <td><strong><?php echo $total_credenciales->private + $total_credenciales->publish; ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Errores (7 días)', 'globalapi'); ?></td>
                        <td>
                            <span class="<?php echo count($logs_error) > 0 ? 'error-text' : 'ok-text'; ?>">
                                <?php echo count($logs_error); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Última Actividad', 'globalapi'); ?></td>
                        <td>
                            <?php
                            $ultimo_log = get_posts(array(
                                'post_type' => 'globalapi_log',
                                'numberposts' => 1,
                                'orderby' => 'date',
                                'order' => 'DESC'
                            ));
                            
                            if ($ultimo_log) {
                                echo human_time_diff(strtotime($ultimo_log[0]->post_date), current_time('timestamp')) . ' ' . __('atrás', 'globalapi');
                            } else {
                                _e('Sin actividad', 'globalapi');
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="acciones-widget">
                <a href="<?php echo admin_url('admin.php?page=globalapi-logs'); ?>" class="button button-small">
                    <?php _e('Ver Todos los Logs', 'globalapi'); ?>
                </a>
            </div>
        </div>

        <style>
        .globalapi-estadisticas .widefat th,
        .globalapi-estadisticas .widefat td {
            padding: 8px 10px;
        }
        .error-text { color: #dc3545; }
        .ok-text { color: #28a745; }
        .acciones-widget {
            margin-top: 10px;
            text-align: center;
        }
        </style>
        <?php
    }

    /**
     * Widget de logs recientes
     */
    public function widget_logs_recientes() {
        $logs_recientes = get_posts(array(
            'post_type' => 'globalapi_log',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ?>
        <div class="globalapi-logs-recientes">
            <?php if (empty($logs_recientes)): ?>
                <p class="no-logs"><?php _e('No hay logs recientes', 'globalapi'); ?></p>
            <?php else: ?>
                <ul class="logs-list">
                    <?php foreach ($logs_recientes as $log): ?>
                        <?php
                        $severidad = wp_get_post_terms($log->ID, 'globalapi_severidad', array('fields' => 'slugs'));
                        $severidad_slug = !empty($severidad) ? $severidad[0] : 'info';
                        $tiempo = human_time_diff(strtotime($log->post_date), current_time('timestamp'));
                        ?>
                        <li class="log-item severidad-<?php echo $severidad_slug; ?>">
                            <span class="log-icon dashicons dashicons-<?php echo $this->obtener_icono_severidad($severidad_slug); ?>"></span>
                            <div class="log-content">
                                <div class="log-mensaje"><?php echo esc_html(wp_trim_words($log->post_content, 10)); ?></div>
                                <div class="log-meta">
                                    <span class="log-tiempo"><?php printf(__('Hace %s', 'globalapi'), $tiempo); ?></span>
                                    <span class="log-severidad"><?php echo ucfirst($severidad_slug); ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="acciones-widget">
                <a href="<?php echo admin_url('admin.php?page=globalapi-logs'); ?>" class="button button-small">
                    <?php _e('Ver Todos', 'globalapi'); ?>
                </a>
                <button class="button button-small refresh-widget" data-widget="logs">
                    <?php _e('Actualizar', 'globalapi'); ?>
                </button>
            </div>
        </div>

        <style>
        .globalapi-logs-recientes .logs-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .log-item {
            display: flex;
            align-items: flex-start;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-icon {
            margin-right: 8px;
            margin-top: 2px;
        }
        .severidad-error .log-icon { color: #dc3545; }
        .severidad-warning .log-icon { color: #ffc107; }
        .severidad-info .log-icon { color: #17a2b8; }
        .severidad-debug .log-icon { color: #6c757d; }
        .log-content {
            flex: 1;
        }
        .log-mensaje {
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 2px;
        }
        .log-meta {
            font-size: 11px;
            color: #666;
        }
        .log-severidad {
            margin-left: 8px;
            padding: 2px 6px;
            border-radius: 3px;
            background: #f0f0f0;
        }
        .no-logs {
            text-align: center;
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }

    /**
     * Configuración del widget de estado
     */
    public function widget_estado_sistema_config() {
        if (isset($_POST['globalapi_widget_config'])) {
            $config = get_option('globalapi_widget_config', array());
            $config['mostrar_detalles'] = isset($_POST['mostrar_detalles']) ? 1 : 0;
            update_option('globalapi_widget_config', $config);
        }

        $config = get_option('globalapi_widget_config', array());
        $mostrar_detalles = isset($config['mostrar_detalles']) ? $config['mostrar_detalles'] : 1;
        ?>
        <p>
            <label>
                <input type="checkbox" name="mostrar_detalles" value="1" <?php checked($mostrar_detalles, 1); ?>>
                <?php _e('Mostrar detalles adicionales', 'globalapi'); ?>
            </label>
        </p>
        <input type="hidden" name="globalapi_widget_config" value="1">
        <?php
    }

    /**
     * AJAX para refrescar widgets
     */
    public function ajax_refresh_widget() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sin permisos', 'globalapi'));
        }

        $widget = sanitize_text_field($_POST['widget']);
        
        ob_start();
        switch ($widget) {
            case 'estado':
                $this->widget_estado_sistema();
                break;
            case 'estadisticas':
                $this->widget_estadisticas();
                break;
            case 'logs':
                $this->widget_logs_recientes();
                break;
        }
        $content = ob_get_clean();

        wp_send_json_success(array('content' => $content));
    }

    /**
     * Obtener icono según estado
     */
    private function obtener_icono_estado($estado) {
        $iconos = array(
            'ok' => 'yes-alt',
            'warning' => 'warning',
            'disabled' => 'dismiss',
            'error' => 'no-alt'
        );
        
        return isset($iconos[$estado]) ? $iconos[$estado] : 'info';
    }

    /**
     * Obtener texto según estado
     */
    private function obtener_texto_estado($estado) {
        $textos = array(
            'ok' => __('Sistema Operativo', 'globalapi'),
            'warning' => __('Atención Requerida', 'globalapi'),
            'disabled' => __('Plugin Deshabilitado', 'globalapi'),
            'error' => __('Error del Sistema', 'globalapi')
        );
        
        return isset($textos[$estado]) ? $textos[$estado] : __('Estado Desconocido', 'globalapi');
    }

    /**
     * Obtener icono según severidad de log
     */
    private function obtener_icono_severidad($severidad) {
        $iconos = array(
            'error' => 'no-alt',
            'critical' => 'warning',
            'warning' => 'flag',
            'info' => 'info',
            'debug' => 'admin-tools'
        );
        
        return isset($iconos[$severidad]) ? $iconos[$severidad] : 'marker';
    }
}

// Inicializar widgets
new GlobalAPI_Dashboard_Widgets();

// Script para refrescar widgets
add_action('admin_footer', function() {
    if (get_current_screen()->id !== 'dashboard') {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.refresh-widget').click(function() {
            var widget = $(this).data('widget');
            var container = $(this).closest('.inside');
            
            container.html('<div style="text-align:center;padding:20px;"><?php _e('Actualizando...', 'globalapi'); ?></div>');
            
            $.post(ajaxurl, {
                action: 'globalapi_widget_refresh',
                widget: widget
            }, function(response) {
                if (response.success) {
                    container.html(response.data.content);
                }
            });
        });
    });
    </script>
    <?php
}); 