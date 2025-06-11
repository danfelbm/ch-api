<?php
/**
 * Footer común para páginas admin del plugin GlobalAPI
 *
 * @package GlobalAPI
 * @subpackage Admin/Pages
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

    <div class="globalapi-footer">
        <hr>
        <p>
            <strong>GlobalAPI v<?php echo esc_html(GLOBALAPI_VERSION); ?></strong> 
            &bull; 
            Plugin desarrollado por <strong>Colombia Humana - Desarrollo Tecnológico</strong>
            &bull;
            <a href="<?php echo admin_url('admin.php?page=globalapi-documentacion'); ?>">Documentación</a>
            &bull;
            <a href="<?php echo admin_url('admin.php?page=globalapi-estado'); ?>">Estado del Sistema</a>
        </p>
    </div>

</div> <!-- .wrap --> 