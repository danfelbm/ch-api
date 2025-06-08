<?php namespace Autor\GlobalAPI\Models;

use Model;
use October\Rain\Database\Traits\SoftDelete;
use Illuminate\Support\Facades\Crypt;

/**
 * Modelo Credencial
 * 
 * Gestiona las credenciales de APIs externas de forma segura
 * Todas las credenciales sensibles se almacenan cifradas
 * 
 * @package Autor\GlobalAPI\Models
 */
class Credencial extends Model
{
    use SoftDelete;

    /**
     * @var string Tabla de la base de datos
     */
    public $table = 'autor_globalapi_credenciales';

    /**
     * @var array Campos que se pueden asignar masivamente
     */
    public $fillable = [
        'servicio',
        'nombre',
        'descripcion',
        'base_url',
        'auth_url', 
        'token_url',
        'scopes',
        'configuracion_extra',
        'activo',
        'limite_requests_minuto',
        'creado_por',
        'modificado_por'
    ];

    /**
     * @var array Campos que se castean a tipos específicos
     */
    public $casts = [
        'scopes' => 'array',
        'configuracion_extra' => 'array',
        'activo' => 'boolean',
        'validado' => 'boolean',
        'token_expira_en' => 'datetime',
        'ultimo_refresh' => 'datetime',
        'ultima_validacion' => 'datetime',
        'ventana_rate_limit' => 'datetime',
        'eliminado_en' => 'datetime'
    ];

    /**
     * @var array Fechas para SoftDelete
     */
    protected $dates = ['eliminado_en'];

    /**
     * @var array Campos sensibles que se cifran automáticamente
     */
    protected $camposCifrados = [
        'cliente_id',
        'cliente_secreto', 
        'api_key',
        'api_secret',
        'token',
        'refresh_token'
    ];

    /**
     * Constantes para tipos de servicio
     */
    const SERVICIO_GROUNDHOGG = 'groundhogg';
    const SERVICIO_INVISION = 'invision';

    /**
     * Opciones de servicios disponibles
     * 
     * @return array
     */
    public function getServicioOptions()
    {
        return [
            self::SERVICIO_GROUNDHOGG => 'Groundhogg CRM',
            self::SERVICIO_INVISION => 'InvisionCommunity OAuth'
        ];
    }

    /**
     * Mutador para cifrar campos sensibles automáticamente
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->camposCifrados) && !empty($value)) {
            $value = Crypt::encryptString($value);
        }
        
        return parent::setAttribute($key, $value);
    }

    /**
     * Accessor para descifrar campos sensibles automáticamente
     * 
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        
        if (in_array($key, $this->camposCifrados) && !empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Si no se puede descifrar, retornar null
                return null;
            }
        }
        
        return $value;
    }

    /**
     * Accessor para mostrar valor cifrado en backend
     * 
     * @param string $key
     * @return string
     */
    public function getValorCifradoAttribute($key)
    {
        $value = parent::getAttribute($key);
        return !empty($value) ? '***CIFRADO***' : '';
    }

    /**
     * Validar credenciales contra el servicio externo
     * 
     * @return bool
     */
    public function validar()
    {
        try {
            $resultado = false;
            
            switch ($this->servicio) {
                case self::SERVICIO_GROUNDHOGG:
                    $resultado = $this->validarGroundhogg();
                    break;
                    
                case self::SERVICIO_INVISION:
                    $resultado = $this->validarInvision();
                    break;
            }
            
            $this->validado = $resultado;
            $this->ultima_validacion = $resultado ? now() : null;
            $this->ultimo_error = $resultado ? null : 'Error de validación';
            $this->save();
            
            return $resultado;
            
        } catch (\Exception $e) {
            $this->validado = false;
            $this->ultimo_error = $e->getMessage();
            $this->save();
            
            return false;
        }
    }

    /**
     * Validar credenciales de Groundhogg
     * 
     * @return bool
     */
    private function validarGroundhogg()
    {
        // TODO: Implementar validación real contra API de Groundhogg
        return !empty($this->api_key) && !empty($this->api_secret);
    }

    /**
     * Validar credenciales de InvisionCommunity
     * 
     * @return bool
     */
    private function validarInvision()
    {
        // TODO: Implementar validación real contra OAuth de Invision
        return !empty($this->cliente_id) && !empty($this->cliente_secreto);
    }

    /**
     * Verificar si el token OAuth ha expirado
     * 
     * @return bool
     */
    public function tokenExpirado()
    {
        return $this->token_expira_en && $this->token_expira_en->isPast();
    }

    /**
     * Verificar rate limiting
     * 
     * @return bool
     */
    public function puedeHacerRequest()
    {
        if (!$this->ventana_rate_limit || $this->ventana_rate_limit->diffInMinutes(now()) >= 1) {
            // Nueva ventana de rate limit
            $this->requests_realizados = 0;
            $this->ventana_rate_limit = now();
        }
        
        return $this->requests_realizados < $this->limite_requests_minuto;
    }

    /**
     * Incrementar contador de requests
     */
    public function incrementarRequest()
    {
        $this->requests_realizados++;
        $this->save();
    }

    /**
     * Scope para credenciales activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para credenciales validadas
     */
    public function scopeValidadas($query)
    {
        return $query->where('validado', true);
    }

    /**
     * Scope por servicio
     */
    public function scopePorServicio($query, $servicio)
    {
        return $query->where('servicio', $servicio);
    }

    /**
     * Relación con logs de auditoría
     */
    public function logsAuditoria()
    {
        return $this->hasMany(LogAuditoria::class, 'credencial_id');
    }
} 