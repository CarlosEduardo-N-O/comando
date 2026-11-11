<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura principal do Comando. Todas as datas são gravadas em UTC
 * (timestamp with time zone) e convertidas para o fuso de exibição no frontend.
 * Não exige superusuário nem extensões do PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('setor_id')->nullable()->after('password')->constrained('setores')->restrictOnDelete();
            $table->string('perfil', 20)->default('colaborador')->after('setor_id');
            $table->boolean('ativo')->default(true)->after('perfil');
            $table->index(['setor_id', 'perfil', 'ativo']);
            $table->index(['perfil', 'ativo']);
        });

        // Sequência dedicada: nextval() é atômico e nunca devolve o mesmo
        // número para transações concorrentes, evitando protocolos duplicados.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS demanda_protocolo_seq START 1');

        Schema::create('demandas', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo', 20)->unique();
            $table->string('titulo', 200);
            $table->text('descricao');
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('setor_origem_id')->constrained('setores')->restrictOnDelete();
            $table->foreignId('setor_destino_id')->constrained('setores')->restrictOnDelete();
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('prioridade', 10)->default('normal');
            $table->string('status', 30)->default('aberta');
            $table->date('prazo_desejado')->nullable();
            $table->timestampTz('prazo_atendimento')->nullable();
            $table->timestampTz('resolvida_em')->nullable();
            $table->timestampTz('concluida_em')->nullable();
            $table->timestampTz('cancelada_em')->nullable();
            $table->timestampsTz();

            $table->index(['solicitante_id', 'status']);
            $table->index(['responsavel_id', 'status']);
            $table->index(['setor_destino_id', 'status']);
            $table->index(['setor_origem_id']);
            $table->index(['categoria_id']);
            $table->index(['status', 'prazo_atendimento']);
            $table->index(['prioridade']);
            $table->index(['created_at']);
        });

        DB::statement("ALTER TABLE demandas ADD CONSTRAINT demandas_status_check CHECK (status IN ('aberta','em_andamento','aguardando_solicitante','resolvida','concluida','cancelada'))");
        DB::statement("ALTER TABLE demandas ADD CONSTRAINT demandas_prioridade_check CHECK (prioridade IN ('baixa','normal','alta','urgente'))");
        DB::statement('ALTER TABLE demandas ADD CONSTRAINT demandas_responsavel_check CHECK (responsavel_id IS NULL OR responsavel_id <> solicitante_id)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_check CHECK (perfil IN ('colaborador','gestor','administrador'))");

        Schema::create('demanda_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demanda_id')->constrained('demandas')->restrictOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->text('mensagem');
            $table->timestampsTz();
            $table->index(['demanda_id', 'id']);
        });

        Schema::create('demanda_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demanda_id')->constrained('demandas')->restrictOnDelete();
            $table->foreignId('comentario_id')->nullable()->constrained('demanda_comentarios')->restrictOnDelete();
            $table->foreignId('enviado_por_id')->constrained('users')->restrictOnDelete();
            $table->string('nome_original', 255);
            $table->string('disco', 40);
            $table->string('caminho', 500)->unique();
            $table->string('mime', 150);
            $table->unsignedBigInteger('tamanho');
            $table->timestampsTz();
            $table->index(['demanda_id']);
            $table->index(['comentario_id']);
        });

        Schema::create('demanda_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demanda_id')->constrained('demandas')->restrictOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->string('acao', 40);
            $table->text('justificativa')->nullable();
            $table->jsonb('antes')->nullable();
            $table->jsonb('depois')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['demanda_id', 'id']);
            $table->index(['acao']);
        });

        // Tabela no formato das notificações de banco do Laravel, com a
        // coluna "evento" para impedir duplicidade por pessoa e evento.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->string('evento', 80);
            $table->foreignId('demanda_id')->nullable()->constrained('demandas')->restrictOnDelete();
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->unique(['notifiable_type', 'notifiable_id', 'evento']);
            $table->index(['notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('demanda_historicos');
        Schema::dropIfExists('demanda_anexos');
        Schema::dropIfExists('demanda_comentarios');
        Schema::dropIfExists('demandas');
        DB::statement('DROP SEQUENCE IF EXISTS demanda_protocolo_seq');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_perfil_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['setor_id', 'perfil', 'ativo']);
            $table->dropIndex(['perfil', 'ativo']);
            $table->dropConstrainedForeignId('setor_id');
            $table->dropColumn(['perfil', 'ativo']);
        });
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('setores');
    }
};
