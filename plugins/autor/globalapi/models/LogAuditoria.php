<?php namespace Autor\GlobalAPI\Models;

use Model;

/**
 * Modelo LogAuditoria
 * 
 * Registra todas las operaciones del sistema para auditoría y seguridad
 * 
 * @package Autor\GlobalAPI\Models
 */
class LogAuditoria extends Model
{
    /**
     * @var string Tabla de la base de datos
     */
    public $table = 'autor_globalapi_logs_auditoria';

    /**
     * @var bool Deshabilitar timestamps automáticos (solo created_at)
     */
    public $timestamps = false;

    /**
     * @var array Campos que se pueden asignar masivamente
     */
    public $fillable = [
        'evento',
        'accion',
        'resultado',
        'usuario_id',
        'usuario_email',
        'session_id',
        'ip_address',
        'user_agent',
        'metodo_http',
        'url',
        'servicio',
        'credencial_id',
        'endpoint',
        'parametros_request',
        'headers_request',
        'request_body',
        'codigo_respuesta',
        'headers_respuesta',
        'response_body',
        'mensaje_error',
        'tiempo_respuesta_ms',
        'tamaño_response_bytes',
        'contexto_adicional',
        'stack_trace',
        'datos_sensibles',
        'requiere_retencion',
        'expira_en'
    ];

    /**
     * @var array Campos que se castean a tipos específicos
     */
    public $casts = [
        'parametros_request' => 'array',
        'headers_request' => 'array',
        'headers_respuesta' => 'array',
        'contexto_adicional' => 'array',
        'datos_sensibles' => 'boolean',
        'requiere_retencion' => 'boolean',
        'expira_en' => 'datetime',
        'created_at' => 'datetime'
    ];

    /**
     * Constantes para tipos de evento
     */
    const EVENTO_OAUTH_LOGIN = 'oauth_login';
    const EVENTO_OAUTH_REFRESH = 'oauth_refresh';
    const EVENTO_API_CALL = 'api_call';
    const EVENTO_CREDENCIAL_CREATED = 'credencial_created';
    const EVENTO_CREDENCIAL_UPDATED = 'credencial_updated';
    const EVENTO_CREDENCIAL_DELETED = 'credencial_deleted';
    const EVENTO_VALIDATION = 'validation';
    const EVENTO_ERROR = 'error';
    const EVENTO_SECURITY = 'security';

    /**
     * Constantes para resultados
     */
    const RESULTADO_SUCCESS = 'success';
    const RESULTADO_ERROR = 'error';
    const RESULTADO_WARNING = 'warning';

    /**
     * Registrar evento de auditoría
     * 
     * @param string $evento
     * @param string $accion
     * @param string $resultado
     * @param array $datos
     * @return self
     */
    public static function registrar($evento, $accion, $resultado = self::RESULTADO_SUCCESS, $datos = [])
    {
        $request = request();
        $usuario = auth()->user();

        $log = new static([
            'evento' => $evento,
            'accion' => $accion,
            'resultado' => $resultado,
            'usuario_id' => $usuario ? $usuario->id : null,
            'usuario_email' => $usuario ? $usuario->email : null,
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metodo_http' => $request->method(),
            'url' => $request->fullUrl(),
            'created_at' => now()
        ]);

        // Agregar datos adicionales
        foreach ($datos as $key => $value) {
            if (in_array($key, $log->fillable)) {
                $log->$key = $value;
            }
        }

        // Configurar expiración por defecto (30 días para logs normales, 1 año para seguridad)
        if (!$log->expira_en) {
            $diasRetencion = in_array($evento, [self::EVENTO_SECURITY, self::EVENTO_ERROR]) ? 365 : 30;
            $log->expira_en = now()->addDays($diasRetencion);
        }

        $log->save();

        return $log;
    }

    /**
     * Registrar llamada a API
     * 
     * @param string $servicio
     * @param string $endpoint
     * @param array $request
     * @param array $response
     * @param int $tiempoMs
     * @return self
     */
    public static function registrarAPI($servicio, $endpoint, $request, $response, $tiempoMs)
    {
        $resultado = ($response['codigo'] >= 200 && $response['codigo'] < 300) 
            ? self::RESULTADO_SUCCESS 
            : self::RESULTADO_ERROR;

        return self::registrar(
            self::EVENTO_API_CALL,
            "Llamada a API {$servicio}",
            $resultado,
            [
                'servicio' => $servicio,
                'endpoint' => $endpoint,
                'parametros_request' => $request['parametros'] ?? [],
                'headers_request' => self::filtrarHeadersSensibles($request['headers'] ?? []),
                'request_body' => self::limitarTexto($request['body'] ?? ''),
                'codigo_respuesta' => $response['codigo'],
                'headers_respuesta' => self::filtrarHeadersSensibles($response['headers'] ?? []),
                'response_body' => self::limitarTexto($response['body'] ?? ''),
                'mensaje_error' => $response['error'] ?? null,
                'tiempo_respuesta_ms' => $tiempoMs,
                'tamaño_response_bytes' => strlen($response['body'] ?? ''),
                'credencial_id' => $request['credencial_id'] ?? null
            ]
        );
    }

    /**
     * Registrar evento OAuth
     * 
     * @param string $accion
     * @param string $resultado
     * @param array $datos
     * @return self
     */
    public static function registrarOAuth($accion, $resultado, $datos = [])
    {
        $evento = strpos($accion, 'refresh') !== false 
            ? self::EVENTO_OAUTH_REFRESH 
            : self::EVENTO_OAUTH_LOGIN;

        return self::registrar($evento, $accion, $resultado, $datos);
    }

    /**
     * Registrar evento de seguridad
     * 
     * @param string $accion
     * @param string $descripcion
     * @param array $contexto
     * @return self
     */
    public static function registrarSeguridad($accion, $descripcion, $contexto = [])
    {
        return self::registrar(
            self::EVENTO_SECURITY,
            $accion,
            self::RESULTADO_WARNING,
            [
                'mensaje_error' => $descripcion,
                'contexto_adicional' => $contexto,
                'datos_sensibles' => true,
                'requiere_retencion' => true,
                'expira_en' => now()->addYear() // Retener 1 año
            ]
        );
    }

    /**
     * Filtrar headers sensibles
     * 
     * @param array $headers
     * @return array
     */
    private static function filtrarHeadersSensibles($headers)
    {
        $sensibles = ['authorization', 'cookie', 'x-api-key'];
        
        foreach ($sensibles as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '***FILTRADO***';
            }
        }
        
        return $headers;
    }

    /**
     * Limitar texto largo para response body
     * 
     * @param string $texto
     * @param int $limite
     * @return string
     */
    private static function limitarTexto($texto, $limite = 5000)
    {
        if (strlen($texto) > $limite) {
            return substr($texto, 0, $limite) . '... [TRUNCADO]';
        }
        
        return $texto;
    }

    /**
     * Scope para eventos específicos
     */
    public function scopePorEvento($query, $evento)
    {
        return $query->where('evento', $evento);
    }

    /**
     * Scope para resultado específico
     */
    public function scopePorResultado($query, $resultado)
    {
        return $query->where('resultado', $resultado);
    }

    /**
     * Scope para servicio específico
     */
    public function scopePorServicio($query, $servicio)
    {
        return $query->where('servicio', $servicio);
    }

    /**
     * Scope para logs de un usuario
     */
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * Scope para logs en rango de fechas
     */
    public function scopeEntreFechas($query, $inicio, $fin)
    {
        return $query->whereBetween('created_at', [$inicio, $fin]);
    }

    /**
     * Scope para logs que requieren limpieza
     */
    public function scopeParaLimpiar($query)
    {
        return $query->where('expira_en', '<', now());
    }

    /**
     * Relación con credencial
     */
    public function credencial()
    {
        return $this->belongsTo(Credencial::class, 'credencial_id');
    }

    /**
     * Obtener eventos recientes para dashboard
     * 
     * @param int $limite
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function eventosRecientes($limite = 10)
    {
        return static::orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtener estadísticas de eventos
     * 
     * @param int $dias
     * @return array
     */
    public static function estadisticas($dias = 7)
    {
        $desde = now()->subDays($dias);
        
        return [
            'total' => static::where('created_at', '>=', $desde)->count(),
            'exitosos' => static::where('created_at', '>=', $desde)
                ->where('resultado', self::RESULTADO_SUCCESS)->count(),
            'errores' => static::where('created_at', '>=', $desde)
                ->where('resultado', self::RESULTADO_ERROR)->count(),
            'advertencias' => static::where('created_at', '>=', $desde)
                ->where('resultado', self::RESULTADO_WARNING)->count(),
        ];
    }
} 