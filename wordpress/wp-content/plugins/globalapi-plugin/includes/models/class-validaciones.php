<?php
/**
 * Validaciones usando WordPress sanitize/validate
 *
 * @since 2.0.0
 * @package GlobalAPI
 * @subpackage Models
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase para validaciones del plugin
 */
class ValidacionesGlobalAPI {

    /**
     * Valida datos de credencial
     *
     * @param array $datos Datos a validar
     * @return array|WP_Error Datos validados o error
     */
    public static function validar_credencial( $datos ) {
        $errores = [];
        $datos_limpios = [];

        // Validar tipo de servicio
        if ( empty( $datos['tipo_servicio'] ) ) {
            $errores[] = __( 'El tipo de servicio es obligatorio.', 'globalapi' );
        } else {
            $tipo_servicio = sanitize_text_field( $datos['tipo_servicio'] );
            if ( ! array_key_exists( $tipo_servicio, Credencial::TIPOS_SERVICIO ) ) {
                $errores[] = __( 'Tipo de servicio no válido.', 'globalapi' );
            } else {
                $datos_limpios['tipo_servicio'] = $tipo_servicio;
            }
        }

        // Validar URL base
        if ( ! empty( $datos['url_base'] ) ) {
            $url_base = esc_url_raw( $datos['url_base'] );
            if ( ! filter_var( $url_base, FILTER_VALIDATE_URL ) ) {
                $errores[] = __( 'La URL base no es válida.', 'globalapi' );
            } else {
                $datos_limpios['url_base'] = $url_base;
            }
        }

        // Validar estado
        if ( ! empty( $datos['estado'] ) ) {
            $estado = sanitize_text_field( $datos['estado'] );
            if ( ! array_key_exists( $estado, Credencial::ESTADOS ) ) {
                $errores[] = __( 'Estado no válido.', 'globalapi' );
            } else {
                $datos_limpios['estado'] = $estado;
            }
        }

        // Validar API Key
        if ( ! empty( $datos['api_key'] ) ) {
            $api_key = sanitize_text_field( $datos['api_key'] );
            if ( strlen( $api_key ) < 10 ) {
                $errores[] = __( 'La API Key debe tener al menos 10 caracteres.', 'globalapi' );
            } else {
                $datos_limpios['api_key'] = $api_key;
            }
        }

        // Validar configuración JSON
        if ( ! empty( $datos['configuracion_extra'] ) ) {
            $json = trim( $datos['configuracion_extra'] );
            $decoded = json_decode( $json, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $errores[] = __( 'La configuración extra debe ser un JSON válido.', 'globalapi' );
            } else {
                $datos_limpios['configuracion_extra'] = wp_json_encode( $decoded );
            }
        }

        // Validar fecha de expiración
        if ( ! empty( $datos['fecha_expiracion'] ) ) {
            $fecha = sanitize_text_field( $datos['fecha_expiracion'] );
            $timestamp = strtotime( $fecha );
            if ( $timestamp === false ) {
                $errores[] = __( 'Formato de fecha de expiración no válido.', 'globalapi' );
            } elseif ( $timestamp < time() ) {
                $errores[] = __( 'La fecha de expiración no puede ser en el pasado.', 'globalapi' );
            } else {
                $datos_limpios['fecha_expiracion'] = date( 'Y-m-d H:i:s', $timestamp );
            }
        }

        if ( ! empty( $errores ) ) {
            return new WP_Error( 'validacion_error', __( 'Errores de validación', 'globalapi' ), $errores );
        }

        return $datos_limpios;
    }

    /**
     * Valida datos de log de auditoría
     *
     * @param array $datos Datos a validar
     * @return array|WP_Error Datos validados o error
     */
    public static function validar_log_auditoria( $datos ) {
        $errores = [];
        $datos_limpios = [];

        // Validar título
        if ( empty( $datos['titulo'] ) ) {
            $datos_limpios['titulo'] = 'Log - ' . date( 'Y-m-d H:i:s' );
        } else {
            $datos_limpios['titulo'] = sanitize_text_field( $datos['titulo'] );
        }

        // Validar descripción
        if ( ! empty( $datos['descripcion'] ) ) {
            $datos_limpios['descripcion'] = sanitize_textarea_field( $datos['descripcion'] );
        }

        // Validar tipo de evento
        if ( ! empty( $datos['tipo_evento'] ) ) {
            $tipo_evento = sanitize_text_field( $datos['tipo_evento'] );
            if ( ! array_key_exists( $tipo_evento, LogAuditoria::TIPOS_EVENTO ) ) {
                $errores[] = __( 'Tipo de evento no válido.', 'globalapi' );
            } else {
                $datos_limpios['tipo_evento'] = $tipo_evento;
            }
        }

        // Validar severidad
        if ( ! empty( $datos['severidad'] ) ) {
            $severidad = sanitize_text_field( $datos['severidad'] );
            if ( ! array_key_exists( $severidad, LogAuditoria::NIVELES_SEVERIDAD ) ) {
                $errores[] = __( 'Nivel de severidad no válido.', 'globalapi' );
            } else {
                $datos_limpios['severidad'] = $severidad;
            }
        }

        // Validar usuario ID
        if ( ! empty( $datos['usuario_id'] ) ) {
            $usuario_id = absint( $datos['usuario_id'] );
            if ( $usuario_id && ! get_user_by( 'id', $usuario_id ) ) {
                $errores[] = __( 'Usuario no válido.', 'globalapi' );
            } else {
                $datos_limpios['usuario_id'] = $usuario_id;
            }
        }

        // Validar IP
        if ( ! empty( $datos['ip_address'] ) ) {
            $ip = sanitize_text_field( $datos['ip_address'] );
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $errores[] = __( 'Dirección IP no válida.', 'globalapi' );
            } else {
                $datos_limpios['ip_address'] = $ip;
            }
        }

        // Validar código de respuesta
        if ( ! empty( $datos['response_code'] ) ) {
            $code = absint( $datos['response_code'] );
            if ( $code < 100 || $code > 599 ) {
                $errores[] = __( 'Código de respuesta HTTP no válido.', 'globalapi' );
            } else {
                $datos_limpios['response_code'] = $code;
            }
        }

        if ( ! empty( $errores ) ) {
            return new WP_Error( 'validacion_error', __( 'Errores de validación de log', 'globalapi' ), $errores );
        }

        return $datos_limpios;
    }

    /**
     * Sanitiza parámetros de API
     *
     * @param array $parametros Parámetros a sanitizar
     * @return array Parámetros sanitizados
     */
    public static function sanitizar_parametros_api( $parametros ) {
        if ( ! is_array( $parametros ) ) {
            return [];
        }

        $sanitizados = [];
        
        foreach ( $parametros as $clave => $valor ) {
            $clave_limpia = sanitize_key( $clave );
            
            if ( is_array( $valor ) ) {
                $sanitizados[ $clave_limpia ] = self::sanitizar_parametros_api( $valor );
            } elseif ( is_string( $valor ) ) {
                $sanitizados[ $clave_limpia ] = sanitize_text_field( $valor );
            } elseif ( is_numeric( $valor ) ) {
                $sanitizados[ $clave_limpia ] = $valor;
            } elseif ( is_bool( $valor ) ) {
                $sanitizados[ $clave_limpia ] = $valor;
            }
        }

        return $sanitizados;
    }

    /**
     * Valida estructura de respuesta de API
     *
     * @param mixed $respuesta Respuesta a validar
     * @return bool True si la respuesta es válida
     */
    public static function validar_respuesta_api( $respuesta ) {
        if ( is_wp_error( $respuesta ) ) {
            return false;
        }

        $codigo = wp_remote_retrieve_response_code( $respuesta );
        return $codigo >= 200 && $codigo < 400;
    }

    /**
     * Sanitiza datos de configuración
     *
     * @param array $configuracion Configuración a sanitizar
     * @return array Configuración sanitizada
     */
    public static function sanitizar_configuracion( $configuracion ) {
        $campos_permitidos = [
            'timeout' => 'absint',
            'retries' => 'absint',
            'headers' => 'array',
            'verify_ssl' => 'bool',
            'user_agent' => 'sanitize_text_field'
        ];

        $config_limpia = [];

        foreach ( $configuracion as $campo => $valor ) {
            if ( array_key_exists( $campo, $campos_permitidos ) ) {
                $funcion = $campos_permitidos[ $campo ];
                
                switch ( $funcion ) {
                    case 'absint':
                        $config_limpia[ $campo ] = absint( $valor );
                        break;
                    case 'bool':
                        $config_limpia[ $campo ] = (bool) $valor;
                        break;
                    case 'array':
                        $config_limpia[ $campo ] = is_array( $valor ) ? $valor : [];
                        break;
                    case 'sanitize_text_field':
                        $config_limpia[ $campo ] = sanitize_text_field( $valor );
                        break;
                }
            }
        }

        return $config_limpia;
    }
}

// Funciones de ayuda
if ( ! function_exists( 'globalapi_validar_nonce' ) ) {
    /**
     * Valida nonce de seguridad
     *
     * @param string $nonce Valor del nonce
     * @param string $action Acción del nonce
     * @return bool True si es válido
     */
    function globalapi_validar_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, $action );
    }
}

if ( ! function_exists( 'globalapi_verificar_permisos' ) ) {
    /**
     * Verifica permisos del usuario
     *
     * @param string $capability Capacidad requerida
     * @return bool True si tiene permisos
     */
    function globalapi_verificar_permisos( $capability = 'manage_options' ) {
        return current_user_can( $capability );
    }
} 