<?php namespace Autor\GlobalAPI\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Crear tabla de logs de auditoría para GlobalAPI
 * 
 * Esta tabla registra todas las operaciones y eventos
 * del sistema para fines de auditoría y seguridad
 */
class CreateLogsAuditoriaTable extends Migration
{
    /**
     * Ejecutar la migración
     */
    public function up()
    {
        Schema::create('autor_globalapi_logs_auditoria', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            
            // Identificación de la acción
            $table->string('evento', 100)->comment('Tipo de evento (oauth_login, api_call, etc.)');
            $table->string('accion', 100)->comment('Acción específica realizada');
            $table->string('resultado', 20)->comment('Resultado: success, error, warning');
            
            // Información del usuario
            $table->integer('usuario_id')->nullable()->comment('ID del usuario si aplica');
            $table->string('usuario_email', 255)->nullable()->comment('Email del usuario');
            $table->string('session_id', 100)->nullable()->comment('ID de sesión');
            
            // Información de la solicitud
            $table->string('ip_address', 45)->nullable()->comment('IP del cliente');
            $table->string('user_agent', 500)->nullable()->comment('User agent del navegador');
            $table->string('metodo_http', 10)->nullable()->comment('Método HTTP (GET, POST, etc.)');
            $table->string('url', 500)->nullable()->comment('URL de la solicitud');
            
            // Información del servicio
            $table->string('servicio', 50)->nullable()->comment('Servicio afectado (groundhogg, invision)');
            $table->integer('credencial_id')->nullable()->comment('ID de credencial utilizada');
            $table->string('endpoint', 255)->nullable()->comment('Endpoint de API llamado');
            
            // Detalles de la operación
            $table->json('parametros_request')->nullable()->comment('Parámetros de la solicitud');
            $table->json('headers_request')->nullable()->comment('Headers de la solicitud');
            $table->text('request_body')->nullable()->comment('Cuerpo de la solicitud');
            
            // Respuesta
            $table->integer('codigo_respuesta')->nullable()->comment('Código HTTP de respuesta');
            $table->json('headers_respuesta')->nullable()->comment('Headers de respuesta');
            $table->text('response_body')->nullable()->comment('Cuerpo de respuesta (limitado)');
            $table->text('mensaje_error')->nullable()->comment('Mensaje de error si aplica');
            
            // Métricas de rendimiento
            $table->integer('tiempo_respuesta_ms')->nullable()->comment('Tiempo de respuesta en milisegundos');
            $table->integer('tamaño_response_bytes')->nullable()->comment('Tamaño de respuesta en bytes');
            
            // Información de contexto
            $table->json('contexto_adicional')->nullable()->comment('Información adicional de contexto');
            $table->text('stack_trace')->nullable()->comment('Stack trace si hay error');
            
            // Control de datos
            $table->boolean('datos_sensibles')->default(false)->comment('Si contiene datos sensibles');
            $table->boolean('requiere_retencion')->default(true)->comment('Si debe retenerse para auditoría');
            $table->timestamp('expira_en')->nullable()->comment('Cuando expira este log');
            
            // Auditoría
            $table->timestamp('created_at')->comment('Momento exacto del evento');
            $table->index('created_at', 'idx_created_at');
            
            // Índices para consultas frecuentes
            $table->index(['evento', 'resultado'], 'idx_evento_resultado');
            $table->index(['servicio', 'created_at'], 'idx_servicio_fecha');
            $table->index(['usuario_id', 'created_at'], 'idx_usuario_fecha');
            $table->index(['ip_address', 'created_at'], 'idx_ip_fecha');
            $table->index(['credencial_id', 'created_at'], 'idx_credencial_fecha');
            $table->index('expira_en', 'idx_expira_en');
        });
    }

    /**
     * Revertir la migración
     */
    public function down()
    {
        Schema::dropIfExists('autor_globalapi_logs_auditoria');
    }
} 