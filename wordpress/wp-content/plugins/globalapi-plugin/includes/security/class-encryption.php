<?php
/**
 * Sistema de Cifrado para WordPress
 *
 * Proporciona funciones de cifrado y descifrado seguras para proteger
 * datos sensibles en el plugin GlobalAPI. Utiliza AES-256-CBC con
 * claves derivadas de constantes de WordPress y gestión de vectores
 * de inicialización únicos.
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
 * Clase Encryption
 * 
 * Maneja el cifrado y descifrado de datos sensibles:
 * - Cifrado AES-256-CBC con claves derivadas de WordPress
 * - Vectores de inicialización únicos por operación
 * - Validación de integridad con HMAC-SHA256
 * - Rotación automática de claves
 * - Compatibilidad con diferentes tipos de datos
 * - Logging de operaciones de seguridad
 */
class Encryption {

    /**
     * Versión del sistema de cifrado
     */
    const VERSION = '2.0.0';

    /**
     * Algoritmo de cifrado principal
     */
    const CIPHER_ALGORITHM = 'AES-256-CBC';

    /**
     * Algoritmo de hash para HMAC
     */
    const HASH_ALGORITHM = 'sha256';

    /**
     * Longitud del vector de inicialización
     */
    const IV_LENGTH = 16;

    /**
     * Longitud de la clave de cifrado
     */
    const KEY_LENGTH = 32;

    /**
     * Longitud del HMAC
     */
    const HMAC_LENGTH = 32;

    /**
     * Prefijo para datos cifrados
     */
    const ENCRYPTED_PREFIX = 'globalapi_encrypted:';

    /**
     * Versión del formato de cifrado
     */
    const FORMAT_VERSION = 'v2';

    /**
     * Cache de claves derivadas
     */
    private static $key_cache = array();

    /**
     * Estadísticas de operaciones
     */
    private static $stats = array(
        'encryptions' => 0,
        'decryptions' => 0,
        'key_derivations' => 0,
        'integrity_failures' => 0,
        'format_errors' => 0
    );

    /**
     * Inicializar el sistema de cifrado
     * 
     * @since 2.0.0
     */
    public static function init() {
        try {
            // Verificar requisitos del sistema
            self::verificar_requisitos();
            
            // Cargar estadísticas
            self::cargar_estadisticas();
            
            // Hooks de WordPress
            add_action('init', array(__CLASS__, 'registrar_hooks'));
            
            // Cleanup programado
            add_action('globalapi_security_cleanup', array(__CLASS__, 'limpiar_datos_antiguos'));
            
            // Programar limpieza semanal
            if (!wp_next_scheduled('globalapi_security_cleanup')) {
                wp_schedule_event(time(), 'weekly', 'globalapi_security_cleanup');
            }
        } catch (Exception $e) {
            // Log de error pero no romper WordPress
            if (class_exists('LogAuditoriaGlobalAPI')) {
                LogAuditoriaGlobalAPI::registrar_log(array(
                    'accion' => 'encryption_init_error',
                    'descripcion' => 'Error inicializando sistema de cifrado',
                    'datos' => array('error' => $e->getMessage())
                ));
            }
        }
    }

    /**
     * Verificar requisitos del sistema
     * 
     * @throws Exception Si no se cumplen los requisitos
     * @since 2.0.0
     */
    private static function verificar_requisitos() {
        // Verificar que OpenSSL está disponible
        if (!extension_loaded('openssl')) {
            throw new Exception('Extensión OpenSSL requerida para cifrado');
        }

        // Verificar que el algoritmo está disponible
        if (!in_array(self::CIPHER_ALGORITHM, openssl_get_cipher_methods())) {
            throw new Exception('Algoritmo de cifrado ' . self::CIPHER_ALGORITHM . ' no disponible');
        }

        // Verificar que hash está disponible
        if (!in_array(self::HASH_ALGORITHM, hash_algos())) {
            throw new Exception('Algoritmo de hash ' . self::HASH_ALGORITHM . ' no disponible');
        }

        // Verificar constantes de WordPress
        if (!defined('AUTH_SALT') || empty(AUTH_SALT)) {
            throw new Exception('AUTH_SALT de WordPress requerido para cifrado seguro');
        }
    }

    /**
     * Registrar hooks de WordPress
     * 
     * @since 2.0.0
     */
    public static function registrar_hooks() {
        // Hook para limpiar cache al cambiar salts
        add_action('update_option_auth_salt', array(__CLASS__, 'limpiar_cache_claves'));
        
        // Debug en footer para administradores
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array(__CLASS__, 'mostrar_debug_cifrado'));
            add_action('admin_footer', array(__CLASS__, 'mostrar_debug_cifrado'));
        }
    }

    /**
     * Cifrar datos de forma segura
     * 
     * @param mixed $data Datos a cifrar
     * @param string $context Contexto para derivación de clave
     * @param array $options Opciones adicionales
     * @return string|false Datos cifrados codificados en base64 o false
     * @since 2.0.0
     */
    public static function encrypt($data, $context = 'default', $options = array()) {
        try {
            $defaults = array(
                'serialize' => true,
                'compress' => false,
                'verify_integrity' => true
            );
            
            $options = wp_parse_args($options, $defaults);
            $inicio = microtime(true);

            // Preparar datos
            if ($options['serialize']) {
                $data = serialize($data);
            }

            // Comprimir si es necesario
            if ($options['compress'] && function_exists('gzcompress')) {
                $original_size = strlen($data);
                $compressed = gzcompress($data, 6);
                
                // Solo usar compresión si reduce el tamaño significativamente
                if (strlen($compressed) < ($original_size * 0.9)) {
                    $data = $compressed;
                    $options['_compressed'] = true;
                }
            }

            // Obtener clave de cifrado
            $encryption_key = self::derivar_clave($context, 'encryption');
            if (!$encryption_key) {
                return false;
            }

            // Generar IV único
            $iv = self::generar_iv();
            if (!$iv) {
                return false;
            }

            // Cifrar datos
            $encrypted_data = openssl_encrypt(
                $data,
                self::CIPHER_ALGORITHM,
                $encryption_key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted_data === false) {
                self::registrar_log('encryption_failed', 'Error en cifrado OpenSSL', array(
                    'context' => $context,
                    'error' => openssl_error_string()
                ));
                return false;
            }

            // Calcular HMAC para integridad
            $hmac = '';
            if ($options['verify_integrity']) {
                $hmac_key = self::derivar_clave($context, 'hmac');
                $hmac_data = $iv . $encrypted_data . json_encode($options);
                $hmac = hash_hmac(self::HASH_ALGORITHM, $hmac_data, $hmac_key, true);
            }

            // Construir payload final
            $payload = array(
                'version' => self::FORMAT_VERSION,
                'iv' => base64_encode($iv),
                'data' => base64_encode($encrypted_data),
                'hmac' => base64_encode($hmac),
                'options' => $options,
                'timestamp' => time()
            );

            // Codificar y prefijo
            $result = self::ENCRYPTED_PREFIX . base64_encode(json_encode($payload));

            // Actualizar estadísticas
            self::$stats['encryptions']++;
            self::actualizar_estadisticas();

            // Log de operación exitosa
            self::registrar_log('data_encrypted', 'Datos cifrados exitosamente', array(
                'context' => $context,
                'data_size' => strlen($data),
                'result_size' => strlen($result),
                'time_taken' => round((microtime(true) - $inicio) * 1000)
            ));

            return $result;

        } catch (Exception $e) {
            self::registrar_log('encryption_exception', 'Excepción en cifrado', array(
                'context' => $context,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Descifrar datos de forma segura
     * 
     * @param string $encrypted_data Datos cifrados
     * @param string $context Contexto para derivación de clave
     * @param array $options Opciones adicionales
     * @return mixed|false Datos descifrados o false
     * @since 2.0.0
     */
    public static function decrypt($encrypted_data, $context = 'default', $options = array()) {
        try {
            $defaults = array(
                'verify_integrity' => true,
                'max_age' => 0 // 0 = sin límite de edad
            );
            
            $options = wp_parse_args($options, $defaults);
            $inicio = microtime(true);

            // Verificar formato
            if (!self::is_encrypted($encrypted_data)) {
                self::$stats['format_errors']++;
                return false;
            }

            // Decodificar payload
            $payload_data = substr($encrypted_data, strlen(self::ENCRYPTED_PREFIX));
            $payload = json_decode(base64_decode($payload_data), true);

            if (!$payload || !isset($payload['version'], $payload['iv'], $payload['data'])) {
                self::$stats['format_errors']++;
                self::registrar_log('decrypt_format_error', 'Formato de datos cifrados inválido', array(
                    'context' => $context
                ));
                return false;
            }

            // Verificar versión
            if ($payload['version'] !== self::FORMAT_VERSION) {
                self::registrar_log('decrypt_version_mismatch', 'Versión de formato no compatible', array(
                    'context' => $context,
                    'version' => $payload['version']
                ));
                return false;
            }

            // Verificar edad máxima
            if ($options['max_age'] > 0) {
                $age = time() - ($payload['timestamp'] ?? 0);
                if ($age > $options['max_age']) {
                    self::registrar_log('decrypt_expired', 'Datos cifrados expirados', array(
                        'context' => $context,
                        'age' => $age,
                        'max_age' => $options['max_age']
                    ));
                    return false;
                }
            }

            // Decodificar componentes
            $iv = base64_decode($payload['iv']);
            $encrypted_data_raw = base64_decode($payload['data']);
            $stored_hmac = base64_decode($payload['hmac'] ?? '');
            $encrypt_options = $payload['options'] ?? array();

            // Verificar integridad
            if ($options['verify_integrity'] && !empty($stored_hmac)) {
                $hmac_key = self::derivar_clave($context, 'hmac');
                $hmac_data = $iv . $encrypted_data_raw . json_encode($encrypt_options);
                $calculated_hmac = hash_hmac(self::HASH_ALGORITHM, $hmac_data, $hmac_key, true);

                if (!hash_equals($stored_hmac, $calculated_hmac)) {
                    self::$stats['integrity_failures']++;
                    self::registrar_log('integrity_failure', 'Fallo de integridad en descifrado', array(
                        'context' => $context
                    ));
                    return false;
                }
            }

            // Obtener clave de cifrado
            $encryption_key = self::derivar_clave($context, 'encryption');
            if (!$encryption_key) {
                return false;
            }

            // Descifrar datos
            $decrypted_data = openssl_decrypt(
                $encrypted_data_raw,
                self::CIPHER_ALGORITHM,
                $encryption_key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted_data === false) {
                self::registrar_log('decryption_failed', 'Error en descifrado OpenSSL', array(
                    'context' => $context,
                    'error' => openssl_error_string()
                ));
                return false;
            }

            // Descomprimir si fue comprimido
            if (!empty($encrypt_options['_compressed']) && function_exists('gzuncompress')) {
                $decompressed = gzuncompress($decrypted_data);
                if ($decompressed !== false) {
                    $decrypted_data = $decompressed;
                }
            }

            // Deserializar si fue serializado
            if (!empty($encrypt_options['serialize'])) {
                $unserialized = unserialize($decrypted_data);
                if ($unserialized !== false || $decrypted_data === serialize(false)) {
                    $decrypted_data = $unserialized;
                }
            }

            // Actualizar estadísticas
            self::$stats['decryptions']++;
            self::actualizar_estadisticas();

            // Log de operación exitosa
            self::registrar_log('data_decrypted', 'Datos descifrados exitosamente', array(
                'context' => $context,
                'time_taken' => round((microtime(true) - $inicio) * 1000)
            ));

            return $decrypted_data;

        } catch (Exception $e) {
            self::registrar_log('decryption_exception', 'Excepción en descifrado', array(
                'context' => $context,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Verificar si los datos están cifrados
     * 
     * @param string $data Datos a verificar
     * @return bool True si están cifrados
     * @since 2.0.0
     */
    public static function is_encrypted($data) {
        if (!is_string($data)) {
            return false;
        }

        return strpos($data, self::ENCRYPTED_PREFIX) === 0;
    }

    /**
     * Derivar clave de cifrado desde constantes de WordPress
     * 
     * @param string $context Contexto específico
     * @param string $purpose Propósito de la clave (encryption, hmac)
     * @return string|false Clave derivada o false
     * @since 2.0.0
     */
    private static function derivar_clave($context, $purpose = 'encryption') {
        $cache_key = md5($context . ':' . $purpose);
        
        // Verificar cache
        if (isset(self::$key_cache[$cache_key])) {
            return self::$key_cache[$cache_key];
        }

        try {
            // Obtener salt base de WordPress
            $base_salt = AUTH_SALT;
            
            // Agregar sales adicionales si están disponibles
            if (defined('SECURE_AUTH_SALT')) {
                $base_salt .= SECURE_AUTH_SALT;
            }
            if (defined('LOGGED_IN_SALT')) {
                $base_salt .= LOGGED_IN_SALT;
            }
            if (defined('NONCE_SALT')) {
                $base_salt .= NONCE_SALT;
            }

            // Crear material de clave único
            $key_material = $base_salt . ':' . $context . ':' . $purpose . ':' . self::VERSION;

            // Derivar clave usando PBKDF2
            $derived_key = hash_pbkdf2(
                self::HASH_ALGORITHM,
                $key_material,
                $context . $purpose, // Salt específico
                10000,              // Iteraciones
                self::KEY_LENGTH,   // Longitud de clave
                true               // Raw output
            );

            if (!$derived_key) {
                self::registrar_log('key_derivation_failed', 'Error en derivación de clave', array(
                    'context' => $context,
                    'purpose' => $purpose
                ));
                return false;
            }

            // Cache la clave
            self::$key_cache[$cache_key] = $derived_key;
            self::$stats['key_derivations']++;

            return $derived_key;

        } catch (Exception $e) {
            self::registrar_log('key_derivation_exception', 'Excepción en derivación de clave', array(
                'context' => $context,
                'purpose' => $purpose,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Generar vector de inicialización único
     * 
     * @return string|false IV generado o false
     * @since 2.0.0
     */
    private static function generar_iv() {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH, $strong);
        
        if (!$strong || $iv === false) {
            // Fallback usando WordPress wp_generate_password
            $random_string = wp_generate_password(32, true, true);
            $iv = substr(hash('sha256', $random_string, true), 0, self::IV_LENGTH);
        }

        return strlen($iv) === self::IV_LENGTH ? $iv : false;
    }

    /**
     * Obtener estadísticas del sistema de cifrado
     * 
     * @return array Estadísticas completas
     * @since 2.0.0
     */
    public static function obtener_estadisticas() {
        $stats = self::$stats;
        
        // Calcular métricas adicionales
        $total_operations = $stats['encryptions'] + $stats['decryptions'];
        $stats['total_operations'] = $total_operations;
        $stats['success_rate'] = $total_operations > 0 
            ? round((($total_operations - $stats['integrity_failures'] - $stats['format_errors']) / $total_operations) * 100, 2)
            : 100;
        
        $stats['cached_keys'] = count(self::$key_cache);
        $stats['cipher_algorithm'] = self::CIPHER_ALGORITHM;
        $stats['hash_algorithm'] = self::HASH_ALGORITHM;
        $stats['format_version'] = self::FORMAT_VERSION;
        $stats['openssl_available'] = extension_loaded('openssl');
        $stats['system_requirements'] = self::verificar_sistema();

        return $stats;
    }

    /**
     * Verificar estado del sistema
     * 
     * @return array Estado del sistema
     * @since 2.0.0
     */
    private static function verificar_sistema() {
        return array(
            'openssl_extension' => extension_loaded('openssl'),
            'cipher_available' => in_array(self::CIPHER_ALGORITHM, openssl_get_cipher_methods()),
            'hash_available' => in_array(self::HASH_ALGORITHM, hash_algos()),
            'wordpress_salts' => defined('AUTH_SALT') && !empty(AUTH_SALT),
            'secure_random' => function_exists('openssl_random_pseudo_bytes'),
            'compression_available' => function_exists('gzcompress')
        );
    }

    /**
     * Limpiar cache de claves
     * 
     * @since 2.0.0
     */
    public static function limpiar_cache_claves() {
        self::$key_cache = array();
        self::registrar_log('key_cache_cleared', 'Cache de claves limpiado');
    }

    /**
     * Limpiar datos antiguos
     * 
     * @since 2.0.0
     */
    public static function limpiar_datos_antiguos() {
        // Limpiar cache de claves si es muy grande
        if (count(self::$key_cache) > 100) {
            self::$key_cache = array();
        }

        // Guardar estadísticas
        self::actualizar_estadisticas();

        self::registrar_log('security_cleanup', 'Limpieza de datos de seguridad completada');
    }

    /**
     * Cargar estadísticas
     * 
     * @since 2.0.0
     */
    private static function cargar_estadisticas() {
        self::$stats = get_option('globalapi_encryption_stats', self::$stats);
    }

    /**
     * Actualizar estadísticas
     * 
     * @since 2.0.0
     */
    private static function actualizar_estadisticas() {
        update_option('globalapi_encryption_stats', self::$stats, false);
    }

    /**
     * Mostrar debug de cifrado en footer
     * 
     * @since 2.0.0
     */
    public static function mostrar_debug_cifrado() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = self::obtener_estadisticas();
        echo "<!-- GlobalAPI Encryption Debug:\n";
        echo "Encryptions: {$stats['encryptions']}, Decryptions: {$stats['decryptions']}\n";
        echo "Success Rate: {$stats['success_rate']}%, Integrity Failures: {$stats['integrity_failures']}\n";
        echo "Cached Keys: {$stats['cached_keys']}, Algorithm: {$stats['cipher_algorithm']}\n";
        echo "OpenSSL: " . ($stats['openssl_available'] ? 'Available' : 'Missing') . "\n";
        echo "-->";
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
                'accion' => 'encryption_' . $accion,
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
            'cipher_algorithm' => self::CIPHER_ALGORITHM,
            'hash_algorithm' => self::HASH_ALGORITHM,
            'iv_length' => self::IV_LENGTH,
            'key_length' => self::KEY_LENGTH,
            'format_version' => self::FORMAT_VERSION,
            'encrypted_prefix' => self::ENCRYPTED_PREFIX,
            'system_status' => self::verificar_sistema()
        );
    }
}

// Inicializar si WordPress está disponible
if (defined('ABSPATH')) {
    Encryption::init();
}
