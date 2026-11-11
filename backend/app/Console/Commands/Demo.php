<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use App\Services\DemandaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Carrega dados de demonstração. Permitido apenas com APP_ENV=local.
 * Não limpa nem altera dados existentes: usuários, setores e categorias são
 * criados apenas se ainda não existirem, e cada demanda de exemplo é criada
 * uma única vez.
 */
class Demo extends Command
{
    protected $signature = 'comando:demo {--senha-env=COMANDO_DEMO_PASSWORD : Variável de ambiente com a senha dos usuários de demonstração}';

    protected $description = 'Carrega dados de demonstração (somente ambiente local)';

    private const DOMINIO = 'comando.local';

    public function handle(DemandaService $servico): int
    {
        if (! app()->environment('local')) {
            $this->error('A demonstração só pode ser carregada com APP_ENV=local.');

            return self::FAILURE;
        }

        $senha = $this->senha();
        if ($senha === null) {
            return self::FAILURE;
        }

        $setores = [];
        foreach ([
            'Administração' => 'Setor administrativo.',
            'Tecnologia' => 'Infraestrutura, sistemas e suporte.',
            'Financeiro' => 'Contas a pagar, receber e reembolsos.',
            'Recursos Humanos' => 'Pessoas, folha e benefícios.',
        ] as $nome => $descricao) {
            $setores[$nome] = Setor::query()->firstOrCreate(['nome' => $nome], ['descricao' => $descricao]);
        }

        $categorias = [];
        foreach (['Acesso a sistemas', 'Equipamentos', 'Pagamentos', 'Documentos', 'Geral'] as $nome) {
            $categorias[$nome] = Categoria::query()->firstOrCreate(['nome' => $nome]);
        }

        $u = [];
        foreach ([
            'admin' => ['Administradora Demo', 'administrador', 'Administração'],
            'gestora.ti' => ['Gabriela Torres', 'gestor', 'Tecnologia'],
            'gestor.financeiro' => ['Fernando Lopes', 'gestor', 'Financeiro'],
            'ana' => ['Ana Silva', 'colaborador', 'Tecnologia'],
            'bruno' => ['Bruno Costa', 'colaborador', 'Tecnologia'],
            'carla' => ['Carla Santos', 'colaborador', 'Financeiro'],
            'diego' => ['Diego Lima', 'colaborador', 'Financeiro'],
            'elisa' => ['Elisa Rocha', 'colaborador', 'Recursos Humanos'],
        ] as $login => [$nome, $perfil, $setor]) {
            $u[$login] = User::query()->where('email', $login.'@'.self::DOMINIO)->first()
                ?? tap(new User, fn (User $novo) => $novo->forceFill([
                    'name' => $nome,
                    'email' => $login.'@'.self::DOMINIO,
                    'password' => $senha,
                    'perfil' => $perfil,
                    'setor_id' => $setores[$setor]->id,
                    'ativo' => true,
                ])->save());
        }

        $criar = function (User $solicitante, string $titulo, string $destino, string $categoria, string $prioridade, ?User $responsavel = null) use ($servico, $setores, $categorias): ?Demanda {
            if (Demanda::query()->where('solicitante_id', $solicitante->id)->where('titulo', $titulo)->exists()) {
                return null; // já carregada anteriormente
            }

            return $servico->criar($solicitante, [
                'titulo' => $titulo,
                'descricao' => "Demanda de demonstração: {$titulo}.\nUse-a para explorar comentários, anexos, histórico e notificações.",
                'categoria_id' => $categorias[$categoria]->id,
                'setor_destino_id' => $setores[$destino]->id,
                'responsavel_id' => $responsavel?->id,
                'prioridade' => $prioridade,
                'prazo_desejado' => now()->addDays(7)->toDateString(),
            ]);
        };

        // Aberta, sem responsável, entre setores.
        $criar($u['ana'], 'Reembolso de despesas de viagem', 'Financeiro', 'Pagamentos', 'normal');
        $criar($u['diego'], 'Liberar acesso à VPN', 'Tecnologia', 'Acesso a sistemas', 'alta');

        // Em andamento, entre setores, com prazo.
        if ($d = $criar($u['carla'], 'Acesso ao módulo de compras do ERP', 'Tecnologia', 'Acesso a sistemas', 'alta', $u['bruno'])) {
            $servico->alterarStatus($u['bruno'], $d->id, Demanda::STATUS_EM_ANDAMENTO);
            $servico->definirPrazo($u['bruno'], $d->id, now()->addDays(2)->setTime(18, 0)->toIso8601String());
            $servico->comentar($u['bruno'], $d->id, 'Perfil de acesso solicitado ao fornecedor.');
        }

        // Aguardando solicitante.
        if ($d = $criar($u['diego'], 'Notebook com bateria descarregando rápido', 'Tecnologia', 'Equipamentos', 'normal', $u['ana'])) {
            $servico->alterarStatus($u['ana'], $d->id, Demanda::STATUS_EM_ANDAMENTO);
            $servico->alterarStatus($u['ana'], $d->id, Demanda::STATUS_AGUARDANDO, 'Informe o número de patrimônio do equipamento.');
        }

        // Resolvida, aguardando confirmação do solicitante.
        if ($d = $criar($u['elisa'], 'Conferência dos descontos da folha', 'Financeiro', 'Documentos', 'urgente', $u['carla'])) {
            $servico->alterarStatus($u['carla'], $d->id, Demanda::STATUS_EM_ANDAMENTO);
            $servico->alterarStatus($u['carla'], $d->id, Demanda::STATUS_RESOLVIDA, 'Descontos conferidos e corrigidos na competência atual.');
        }

        // Concluída, dentro do mesmo setor.
        if ($d = $criar($u['ana'], 'Instalar certificado digital', 'Tecnologia', 'Equipamentos', 'baixa', $u['bruno'])) {
            $servico->alterarStatus($u['bruno'], $d->id, Demanda::STATUS_EM_ANDAMENTO);
            $servico->alterarStatus($u['bruno'], $d->id, Demanda::STATUS_RESOLVIDA, 'Certificado instalado e testado.');
            $servico->alterarStatus($u['ana'], $d->id, Demanda::STATUS_CONCLUIDA);
        }

        // Cancelada.
        if ($d = $criar($u['bruno'], 'Atualizar cadastro de dependentes', 'Recursos Humanos', 'Documentos', 'normal')) {
            $servico->alterarStatus($u['bruno'], $d->id, Demanda::STATUS_CANCELADA, 'Atualização feita pelo portal do colaborador.');
        }

        // Em andamento e atrasada, dentro do mesmo setor.
        if ($d = $criar($u['carla'], 'Revisar lançamentos de contas a pagar', 'Financeiro', 'Pagamentos', 'alta', $u['diego'])) {
            $servico->alterarStatus($u['diego'], $d->id, Demanda::STATUS_EM_ANDAMENTO);
            // Prazo vencido gravado diretamente apenas para ilustrar o indicador de atraso.
            DB::table('demandas')->where('id', $d->id)->update(['prazo_atendimento' => now()->subDay()]);
        }

        $this->info('Demonstração carregada sem remover dados existentes.');
        $nomesSetores = Setor::query()->pluck('nome', 'id');
        $this->table(['E-mail', 'Perfil', 'Setor'], collect($u)->map(fn (User $user) => [
            $user->email, $user->perfil, $nomesSetores[$user->setor_id] ?? '-',
        ])->values()->all());

        return self::SUCCESS;
    }

    private function senha(): ?string
    {
        $variavel = (string) $this->option('senha-env');
        $senha = $variavel !== '' ? getenv($variavel) : false;

        if ($senha === false || $senha === '') {
            if ($this->input->isInteractive()) {
                $senha = (string) $this->secret('Senha para os NOVOS usuários de demonstração (mín. 10 caracteres, letras e números)');
            } else {
                $senha = 'Demo'.Str::password(10, symbols: false);
                $this->warn("Senha gerada para os novos usuários de demonstração: {$senha}");
            }
        }

        $validacao = Validator::make(['password' => $senha], ['password' => ['required', Password::min(10)->letters()->numbers()]]);
        if ($validacao->fails()) {
            $this->error($validacao->errors()->first());

            return null;
        }

        return $senha;
    }
}
