/**
 * JavaScript para el panel de administración de GlobalAPI
 *
 * @package GlobalAPI
 * @since   2.0.0
 */

(function($) {
    'use strict';

    /**
     * Objeto principal de GlobalAPI Admin
     */
    var GlobalAPIAdmin = {
        
        /**
         * Inicializar funcionalidades
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initAccordions();
            this.initFormValidation();
        },

        /**
         * Enlazar eventos
         */
        bindEvents: function() {
            // Toggle de passwords
            $(document).on('click', '.toggle-password', this.togglePassword);
            
            // Probar credenciales
            $(document).on('click', '.test-credencial', this.testCredencial);
            
            // Exportar credencial
            $(document).on('click', '.export-credencial', this.exportCredencial);
            
            // Limpiar cache
            $(document).on('click', '#clear-cache', this.clearCache);
            
            // Formulario de configuración
            $(document).on('submit', '#globalapi-config-form', this.saveConfig);
            
            // Formulario de credencial
            $(document).on('submit', '#form-credencial', this.saveCredencial);
            
            // Filtros de tabla
            $(document).on('change', '.globalapi-filters select', this.filterTable);
            
            // Confirmación de eliminación
            $(document).on('click', 'a[href*="action=delete"]', this.confirmDelete);
        },

        /**
         * Toggle visibilidad de contraseñas
         */
        togglePassword: function(e) {
            e.preventDefault();
            
            var target = $(this).data('target');
            var input = $('[name="' + target + '"]');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).text(globalapi_admin.strings.ocultar);
            } else {
                input.attr('type', 'password');
                $(this).text(globalapi_admin.strings.mostrar);
            }
        },

        /**
         * Probar conexión de credencial
         */
        testCredencial: function(e) {
            e.preventDefault();
            
            var button = $(this);
            var credencialId = button.data('id');
            var originalText = button.text();
            
            // Cambiar estado del botón
            button.addClass('loading').prop('disabled', true);
            
            var data = {
                action: 'globalapi_test_credencial',
                credencial_id: credencialId,
                nonce: globalapi_admin.nonce
            };

            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        GlobalAPIAdmin.showNotice('success', response.data.message || globalapi_admin.strings.conexion_exitosa);
                    } else {
                        GlobalAPIAdmin.showNotice('error', response.data || globalapi_admin.strings.error_conexion);
                    }
                },
                error: function() {
                    GlobalAPIAdmin.showNotice('error', globalapi_admin.strings.error_generico);
                },
                complete: function() {
                    button.removeClass('loading').prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Exportar configuración de credencial
         */
        exportCredencial: function(e) {
            e.preventDefault();
            
            var credencialId = $(this).data('id');
            
            var data = {
                action: 'globalapi_export_credencial',
                credencial_id: credencialId,
                nonce: globalapi_admin.nonce
            };

            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        GlobalAPIAdmin.downloadJSON(response.data, 'credencial-' + credencialId + '.json');
                        GlobalAPIAdmin.showNotice('success', globalapi_admin.strings.exportacion_exitosa);
                    } else {
                        GlobalAPIAdmin.showNotice('error', response.data || globalapi_admin.strings.error_exportacion);
                    }
                },
                error: function() {
                    GlobalAPIAdmin.showNotice('error', globalapi_admin.strings.error_generico);
                }
            });
        },

        /**
         * Limpiar cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            if (!confirm(globalapi_admin.strings.confirmar_limpiar_cache)) {
                return;
            }

            var button = $(this);
            var originalText = button.text();
            
            button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'globalapi_clear_cache',
                    nonce: globalapi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        GlobalAPIAdmin.showNotice('success', response.data || globalapi_admin.strings.cache_limpiado);
                    } else {
                        GlobalAPIAdmin.showNotice('error', response.data || globalapi_admin.strings.error_generico);
                    }
                },
                error: function() {
                    GlobalAPIAdmin.showNotice('error', globalapi_admin.strings.error_generico);
                },
                complete: function() {
                    button.removeClass('loading').prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Guardar configuración
         */
        saveConfig: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            var originalValue = submitButton.val();
            
            submitButton.addClass('loading').prop('disabled', true);

            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=globalapi_save_config',
                success: function(response) {
                    if (response.success) {
                        GlobalAPIAdmin.showNotice('success', response.data.message || globalapi_admin.strings.configuracion_guardada);
                    } else {
                        GlobalAPIAdmin.showNotice('error', response.data || globalapi_admin.strings.error_guardar);
                    }
                },
                error: function() {
                    GlobalAPIAdmin.showNotice('error', globalapi_admin.strings.error_generico);
                },
                complete: function() {
                    submitButton.removeClass('loading').prop('disabled', false).val(originalValue);
                }
            });
        },

        /**
         * Guardar credencial
         */
        saveCredencial: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            var originalValue = submitButton.val();
            
            // Validar formulario
            if (!GlobalAPIAdmin.validateCredencialForm(form)) {
                return;
            }
            
            submitButton.addClass('loading').prop('disabled', true);

            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        GlobalAPIAdmin.showNotice('success', response.data.message || globalapi_admin.strings.credencial_guardada);
                        
                        // Redirigir si es una nueva credencial
                        if (response.data.credencial_id && !form.find('input[name="credencial_id"]').val()) {
                            setTimeout(function() {
                                window.location.href = globalapi_admin.edit_credencial_url + '&id=' + response.data.credencial_id;
                            }, 1500);
                        }
                    } else {
                        GlobalAPIAdmin.showNotice('error', response.data || globalapi_admin.strings.error_guardar);
                    }
                },
                error: function() {
                    GlobalAPIAdmin.showNotice('error', globalapi_admin.strings.error_generico);
                },
                complete: function() {
                    submitButton.removeClass('loading').prop('disabled', false).val(originalValue);
                }
            });
        },

        /**
         * Filtrar tabla
         */
        filterTable: function() {
            var form = $(this).closest('form');
            form.submit();
        },

        /**
         * Confirmar eliminación
         */
        confirmDelete: function(e) {
            if (!confirm(globalapi_admin.strings.confirmar_eliminacion)) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Validar formulario de credencial
         */
        validateCredencialForm: function(form) {
            var isValid = true;
            var errors = [];

            // Nombre requerido
            var nombre = form.find('[name="nombre"]').val().trim();
            if (!nombre) {
                errors.push(globalapi_admin.strings.nombre_requerido);
                isValid = false;
            }

            // Tipo de servicio requerido
            var tipoServicio = form.find('[name="tipo_servicio"]').val();
            if (!tipoServicio) {
                errors.push(globalapi_admin.strings.tipo_servicio_requerido);
                isValid = false;
            }

            // URL base requerida y válida
            var urlBase = form.find('[name="url_base"]').val().trim();
            if (!urlBase) {
                errors.push(globalapi_admin.strings.url_base_requerida);
                isValid = false;
            } else if (!GlobalAPIAdmin.isValidUrl(urlBase)) {
                errors.push(globalapi_admin.strings.url_base_invalida);
                isValid = false;
            }

            if (!isValid) {
                GlobalAPIAdmin.showNotice('error', errors.join('<br>'));
            }

            return isValid;
        },

        /**
         * Validar URL
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Mostrar notificación
         */
        showNotice: function(type, message) {
            // Remover notificaciones existentes
            $('.globalapi-notice').remove();
            
            var notice = $('<div class="globalapi-notice notice notice-' + type + ' is-dismissible">')
                .html('<p>' + message + '</p>');
            
            // Agregar botón de cerrar
            notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Insertar después del título de la página
            if ($('.wrap h1').length) {
                $('.wrap h1').after(notice);
            } else {
                $('.wrap').prepend(notice);
            }
            
            // Auto-hide después de 5 segundos para success
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut();
                }, 5000);
            }
            
            // Manejar el botón de cerrar
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut();
            });
        },

        /**
         * Descargar JSON
         */
        downloadJSON: function(data, filename) {
            var element = document.createElement('a');
            element.setAttribute('href', 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(data, null, 2)));
            element.setAttribute('download', filename);
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        },

        /**
         * Inicializar tooltips
         */
        initTooltips: function() {
            $(document).on('mouseenter', '.globalapi-tooltip', function() {
                var tooltip = $(this).data('tooltip');
                if (tooltip) {
                    $(this).attr('title', tooltip);
                }
            });
        },

        /**
         * Inicializar accordions
         */
        initAccordions: function() {
            $(document).on('click', '.globalapi-accordion-header', function() {
                var accordion = $(this).closest('.globalapi-accordion');
                accordion.toggleClass('active');
                
                if (accordion.hasClass('active')) {
                    accordion.find('.globalapi-accordion-content').slideDown();
                } else {
                    accordion.find('.globalapi-accordion-content').slideUp();
                }
            });
        },

        /**
         * Inicializar validación de formularios
         */
        initFormValidation: function() {
            // Validación en tiempo real
            $(document).on('blur', 'input[required]', function() {
                var input = $(this);
                var value = input.val().trim();
                
                if (!value) {
                    input.addClass('error');
                } else {
                    input.removeClass('error');
                }
            });

            // Validación de URLs
            $(document).on('blur', 'input[type="url"]', function() {
                var input = $(this);
                var value = input.val().trim();
                
                if (value && !GlobalAPIAdmin.isValidUrl(value)) {
                    input.addClass('error');
                } else {
                    input.removeClass('error');
                }
            });
        },

        /**
         * Mostrar/ocultar secciones condicionales
         */
        toggleConditionalSections: function() {
            // Mostrar sección OAuth según tipo de servicio
            var tipoServicio = $('#tipo_servicio').val();
            var seccionOAuth = $('#seccion-oauth');
            
            if (tipoServicio === 'invision_community' || tipoServicio === 'custom_api') {
                seccionOAuth.slideDown();
            } else {
                seccionOAuth.slideUp();
            }
        },

        /**
         * Refresh de widgets del dashboard
         */
        refreshDashboardWidget: function(widgetType) {
            var widget = $('#globalapi-' + widgetType);
            var content = widget.find('.inside');
            
            content.html('<div class="loading-spinner"></div>');
            
            $.ajax({
                url: globalapi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'globalapi_widget_refresh',
                    widget: widgetType,
                    nonce: globalapi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        content.html(response.data.content);
                    } else {
                        content.html('<p class="error">' + globalapi_admin.strings.error_cargar_widget + '</p>');
                    }
                },
                error: function() {
                    content.html('<p class="error">' + globalapi_admin.strings.error_generico + '</p>');
                }
            });
        }
    };

    /**
     * Utilidades adicionales
     */
    var Utils = {
        
        /**
         * Formatear fecha
         */
        formatDate: function(date) {
            return new Date(date).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        /**
         * Debounce función
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var later = function() {
                    clearTimeout(timeout);
                    func.apply(this, arguments);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Copiar al portapapeles
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    GlobalAPIAdmin.showNotice('success', globalapi_admin.strings.copiado_portapapeles);
                });
            } else {
                // Fallback para navegadores más antiguos
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                GlobalAPIAdmin.showNotice('success', globalapi_admin.strings.copiado_portapapeles);
            }
        }
    };

    /**
     * Inicializar cuando el documento esté listo
     */
    $(document).ready(function() {
        GlobalAPIAdmin.init();
        
        // Eventos específicos para la página de credenciales
        if ($('#tipo_servicio').length) {
            $('#tipo_servicio').on('change', GlobalAPIAdmin.toggleConditionalSections);
            GlobalAPIAdmin.toggleConditionalSections();
        }

        // Auto-refresh de widgets cada 5 minutos
        if ($('.globalapi-widget').length) {
            setInterval(function() {
                $('.globalapi-widget').each(function() {
                    var widgetType = $(this).attr('id').replace('globalapi-', '');
                    GlobalAPIAdmin.refreshDashboardWidget(widgetType);
                });
            }, 300000); // 5 minutos
        }
    });

    // Hacer disponible globalmente
    window.GlobalAPIAdmin = GlobalAPIAdmin;
    window.GlobalAPIUtils = Utils;

})(jQuery); 