<?php

namespace App\Services;

use App\Models\Anexo;
use App\Models\Comentario;
use App\Models\Demanda;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Armazena anexos de forma privada, com nomes internos aleatórios, usando a
 * abstração de discos do Laravel (local hoje, S3 no futuro).
 *
 * Uso: chame armazenar() dentro da transação e, se ela falhar, chame
 * descartar() com os caminhos gravados para não deixar arquivos órfãos.
 */
class AnexoService
{
    /** @var list<string> */
    private array $gravados = [];

    public function disco(): string
    {
        return config('comando.anexos.disco', 'local');
    }

    /**
     * @param  array<int, UploadedFile>  $arquivos
     */
    public function armazenar(Demanda $demanda, ?Comentario $comentario, User $autor, array $arquivos): void
    {
        $disco = $this->disco();
        $diretorio = config('comando.anexos.diretorio', 'anexos').'/'.now()->format('Y/m');

        foreach ($arquivos as $arquivo) {
            $extensao = strtolower($arquivo->getClientOriginalExtension());
            $nomeInterno = Str::uuid()->toString().'.'.$extensao;

            $caminho = Storage::disk($disco)->putFileAs($diretorio, $arquivo, $nomeInterno, ['visibility' => 'private']);
            if ($caminho === false) {
                throw new RuntimeException('Não foi possível armazenar o arquivo enviado.');
            }
            $this->gravados[] = $caminho;

            $anexo = new Anexo;
            $anexo->forceFill([
                'demanda_id' => $demanda->id,
                'comentario_id' => $comentario?->id,
                'enviado_por_id' => $autor->id,
                'nome_original' => $this->nomeSeguro($arquivo->getClientOriginalName(), $extensao),
                'disco' => $disco,
                'caminho' => $caminho,
                'mime' => $arquivo->getMimeType() ?: 'application/octet-stream',
                'tamanho' => $arquivo->getSize(),
            ])->save();
        }
    }

    /** Remove arquivos gravados nesta operação (chamado quando a transação falha). */
    public function descartar(): void
    {
        foreach ($this->gravados as $caminho) {
            try {
                Storage::disk($this->disco())->delete($caminho);
            } catch (Throwable) {
                // A limpeza agendada (LimparAnexosOrfaos) recolhe o que restar.
            }
        }
        $this->gravados = [];
    }

    public function confirmar(): void
    {
        $this->gravados = [];
    }

    /** Nome exibido no download: sem caminhos, caracteres de controle ou extensões trocadas. */
    private function nomeSeguro(string $original, string $extensao): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $original)), PATHINFO_FILENAME);
        $base = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '', $base) ?? '';
        $base = trim(Str::limit($base, 180, ''));

        return ($base !== '' ? $base : 'anexo').'.'.$extensao;
    }
}
