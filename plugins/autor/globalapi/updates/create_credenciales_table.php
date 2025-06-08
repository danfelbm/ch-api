<?php namespace Autor\GlobalAPI\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Crear tabla de credenciales para GlobalAPI
 * 
 * Esta tabla almacena de forma segura las credenciales
 * de APIs externas como Groundhogg e InvisionCommunity
 */
class CreateCredencialesTable extends Migration
{
    /**
     * Ejecutar la migración
     */
    public function up()
    {
        Schema::create('autor_globalapi_credenciales', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            
            // Identificación del servicio
            $table->string('servicio', 50)->comment('Nombre del servicio (groundhogg, invision)');
            $table->string('nombre', 100)->comment('Nombre descriptivo de la credencial');
            $table->text('descripcion')->nullable()->comment('Descripción de la credencial');
            
            // Credenciales principales (cifradas)
            $table->text('cliente_id')->nullable()->comment('ID del cliente OAuth (cifrado)');
            $table->text('cliente_secreto')->nullable()->comment('Secreto del cliente OAuth (cifrado)');
            $table->text('api_key')->nullable()->comment('Clave de API (cifrada)');
            $table->text('api_secret')->nullable()->comment('Secreto de API (cifrado)');
            $table->text('token')->nullable()->comment('Token de acceso (cifrado)');
            
            // URLs y configuración
            $table->string('base_url', 255)->nullable()->comment('URL base del servicio');
            $table->string('auth_url', 255)->nullable()->comment('URL de autorización OAuth');
            $table->string('token_url', 255)->nullable()->comment('URL de obtención de token');
            $table->json('scopes')->nullable()->comment('Scopes OAuth requeridos');
            $table->json('configuracion_extra')->nullable()->comment('Configuración adicional');
            
            // Gestión de tokens OAuth
            $table->text('refresh_token')->nullable()->comment('Token de actualización (cifrado)');
            $table->timestamp('token_expira_en')->nullable()->comment('Cuando expira el token');
            $table->timestamp('ultimo_refresh')->nullable()->comment('Última actualización de token');
            
            // Control de estado
            $table->boolean('activo')->default(true)->comment('Si las credenciales están activas');
            $table->boolean('validado')->default(false)->comment('Si las credenciales han sido validadas');
            $table->timestamp('ultima_validacion')->nullable()->comment('Última validación exitosa');
            $table->text('ultimo_error')->nullable()->comment('Último error de validación');
            
            // Rate limiting
            $table->integer('limite_requests_minuto')->default(60)->comment('Límite de requests por minuto');
            $table->integer('requests_realizados')->default(0)->comment('Requests realizados en ventana actual');
            $table->timestamp('ventana_rate_limit')->nullable()->comment('Inicio de ventana de rate limit');
            
            // Auditoría
            $table->timestamps();
            $table->timestamp('eliminado_en')->nullable();
            $table->string('creado_por', 100)->nullable()->comment('Usuario que creó la credencial');
            $table->string('modificado_por', 100)->nullable()->comment('Usuario que modificó la credencial');
            
            // Índices
            $table->index(['servicio', 'activo'], 'idx_servicio_activo');
            $table->index(['validado', 'activo'], 'idx_validado_activo');
            $table->index('token_expira_en', 'idx_token_expira');
            $table->unique(['servicio', 'nombre'], 'unique_servicio_nombre');
        });
    }

    /**
     * Revertir la migración
     */
    public function down()
    {
        Schema::dropIfExists('autor_globalapi_credenciales');
    }
} 