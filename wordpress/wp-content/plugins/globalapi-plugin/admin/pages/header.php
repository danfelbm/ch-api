<?php
/**
 * Header común para páginas admin del plugin GlobalAPI
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

<div class="wrap globalapi-admin-page">
    <div class="globalapi-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-admin-settings"></span>
            GlobalAPI
        </h1>
        
        <div class="globalapi-version">
            Versión <?php echo esc_html(GLOBALAPI_VERSION); ?>
        </div>
    </div>

    <style>
    .globalapi-admin-page {
        max-width: 1200px;
    }
    
    .globalapi-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #ddd;
    }
    
    .globalapi-header h1 {
        color: #2271b1;
        font-size: 28px;
        margin: 0;
    }
    
    .globalapi-header .dashicons {
        margin-right: 10px;
        font-size: 32px;
        vertical-align: middle;
    }
    
    .globalapi-version {
        background: #f0f0f1;
        padding: 5px 12px;
        border-radius: 4px;
        font-size: 12px;
        color: #666;
    }
    
    .globalapi-content {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .globalapi-notice {
        padding: 12px;
        margin: 15px 0;
        border-left: 4px solid;
        background: #fff;
    }
    
    .globalapi-notice.info {
        border-left-color: #00a0d2;
        background-color: #f0f8ff;
    }
    
    .globalapi-notice.success {
        border-left-color: #46b450;
        background-color: #f0fff4;
    }
    
    .globalapi-notice.warning {
        border-left-color: #ffb900;
        background-color: #fffbf0;
    }
    
    .globalapi-notice.error {
        border-left-color: #dc3232;
        background-color: #fff0f0;
    }
    </style> 