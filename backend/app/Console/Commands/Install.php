<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AguardaBanco;
use App\Models\Categoria;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Instalação idempotente: verifica a conexão, executa migrations pendentes,
 * cria cadastros básicos ausentes e o primeiro administrador (somente se
 * ainda não houver administrador ativo). Pode ser executado novamente sem
 * duplicar nem apagar dados. Nunca executa migrate:fresh.
 */
class Install extends Command
{
    use AguardaBanco;

    protected $signature = 'comando:install
        {--nome= : Nome do primeiro administrador}
        {--email= : E-mail do primeiro administrador}
        {--senha-env=COMANDO_ADMIN_PASSWORD : Variável de ambiente com a senha (uso não interativo)}';

    protected $description = 'Prepara o banco do Comando e cria o primeiro administrador';

    public function handle(): int
    {
        if (empty(config('app.key'))) {
            $this->error('APP_KEY não configurada. Gere a chave conforme o README antes da instalação.');

            return self::FAILURE;
        }

        $this->info('1/4 Verificando conexão com o banco...');
        if (! $this->aguardarBanco(30)) {
            $this->error('Não foi possível conectar ao banco. Verifique DB_HOST, credenciais, rede e TLS.');

            return self::FAILURE;
        }

        $this->info('2/4 Executando migrations pendentes...');
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('3/4 Conferindo cadastros básicos...');
        $setor = Setor::query()->firstOrCreate(['nome' => 'Administração'], ['descricao' => 'Setor administrativo criado na instalação.']);
        Categoria::query()->firstOrCreate(['nome' => 'Geral'], ['descricao' => 'Categoria padrão criada na instalação.']);

        $this->info('4/4 Conferindo administrador...');
        if (User::query()->ativos()->where('perfil', User::PERFIL_ADMINISTRADOR)->exists()) {
            $this->info('Já existe administrador ativo. Nenhum usuário foi alterado.');
            $this->info('Instalação concluída.');

            return self::SUCCESS;
        }

        $dados = $this->coletarAdministrador();
        if ($dados === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($dados, $setor) {
            DB::select('SELECT pg_advisory_xact_lock(12026001)');
            if (User::query()->ativos()->where('perfil', User::PERFIL_ADMINISTRADOR)->exists()) {
                return;
            }
            $usuario = new User;
            $usuario->forceFill([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => $dados['password'],
                'setor_id' => $setor->id,
                'perfil' => User::PERFIL_ADMINISTRADOR,
                'ativo' => true,
            ])->save();
        });

        $this->info("Administrador {$dados['email']} criado. Instalação concluída.");

        return self::SUCCESS;
    }

    /** @return array{name: string, email: string, password: string}|null */
    private function coletarAdministrador(): ?array
    {
        $senhaEnv = (string) $this->option('senha-env');
        $senha = $senhaEnv !== '' ? getenv($senhaEnv) : false;

        $dados = [
            'name' => $this->option('nome') ?: $this->ask('Nome do primeiro administrador'),
            'email' => mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail do administrador')))),
        ];

        if ($senha === false || $senha === '') {
            $senha = (string) $this->secret('Senha (mínimo 10 caracteres, com letras e números)');
            if ($senha !== (string) $this->secret('Confirme a senha')) {
                $this->error('As senhas não conferem.');

                return null;
            }
        }
        $dados['password'] = $senha;

        $validacao = Validator::make($dados, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ], ['email.unique' => 'Já existe um usuário com este e-mail. Use outro e-mail ou promova o usuário pelo banco com cautela.']);

        if ($validacao->fails()) {
            foreach ($validacao->errors()->all() as $erro) {
                $this->error($erro);
            }

            return null;
        }

        return $validacao->validated();
    }
}
