<?php

namespace Tests\Feature;

use App\Models\Anexo;
use App\Models\Demanda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AnexoTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $nome = 'contrato.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_anexos_privados_na_abertura_e_download_autorizado(): void
    {
        $resposta = $this->actingAs($this->solicitante)->post('/api/v1/demandas', [
            ...$this->payload(['responsavel_id' => $this->atendenteRh->id]),
            'anexos' => [$this->pdf('../../etc/contrato.pdf'), UploadedFile::fake()->createWithContent('nota.txt', 'Conteúdo reservado')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $resposta->assertJsonCount(2, 'data.anexos')->assertJsonMissingPath('data.anexos.0.caminho');
        $demandaId = $resposta->json('data.id');
        $anexo = Anexo::query()->where('nome_original', 'contrato.pdf')->firstOrFail();

        // Nome interno aleatório, sem o nome enviado pelo usuário.
        $this->assertStringNotContainsString('contrato', $anexo->caminho);
        Storage::disk('local')->assertExists($anexo->caminho);

        $download = "/api/v1/demandas/{$demandaId}/anexos/{$anexo->id}/download";
        $this->get($download)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('contrato.pdf');
        $this->actingAs($this->atendenteRh)->get($download)->assertOk();
        $this->actingAs($this->colegaTi)->getJson($download)->assertForbidden();
        $this->actingAs($this->outroRh)->getJson($download)->assertForbidden();

        // Anexo de outra demanda pela URL desta: 404.
        $outra = $this->demanda($this->colegaTi, $this->rh);
        $this->actingAs($this->colegaTi)->getJson("/api/v1/demandas/{$outra->id}/anexos/{$anexo->id}/download")->assertNotFound();
    }

    public function test_anexos_em_comentario(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);

        $this->actingAs($this->atendenteRh)->post($this->url($demanda->id, 'comentarios'), [
            'mensagem' => '<script>alert(1)</script> segue planilha',
            'anexos' => [UploadedFile::fake()->createWithContent('imagem.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.mensagem', '<script>alert(1)</script> segue planilha') // armazenado como texto; o frontend não interpreta HTML
            ->assertJsonCount(1, 'data.anexos');

        $this->actingAs($this->solicitante)->getJson($this->url($demanda->id, 'comentarios'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_valida_tipo_tamanho_e_quantidade(): void
    {
        $this->actingAs($this->solicitante);
        $enviar = fn (array $arquivos) => $this->post('/api/v1/demandas', [...$this->payload(), 'anexos' => $arquivos], ['Accept' => 'application/json']);

        $enviar([UploadedFile::fake()->create('programa.exe', 10)])->assertUnprocessable()->assertJsonValidationErrors('anexos.0');
        // Extensão permitida, conteúdo não corresponde (executável renomeado).
        // Arquivo real (não "fake") para que o tipo seja detectado pelo conteúdo (finfo).
        $falso = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($falso, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00".str_repeat("\x00", 64).'This program cannot be run in DOS mode.');
        $enviar([new UploadedFile($falso, 'relatorio.pdf', null, null, true)])->assertUnprocessable()->assertJsonValidationErrors('anexos.0');
        $enviar([UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('anexos.0');
        $enviar(array_map(fn ($i) => UploadedFile::fake()->createWithContent("n{$i}.txt", 'texto'), range(1, 6)))
            ->assertUnprocessable()->assertJsonValidationErrors('anexos');

        $this->assertSame(0, Demanda::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_falha_apos_upload_nao_deixa_arquivos_orfaos(): void
    {
        // O responsável inválido é detectado depois da validação do arquivo: nada pode sobrar.
        $this->actingAs($this->solicitante)->post('/api/v1/demandas', [
            ...$this->payload(['responsavel_id' => $this->colegaTi->id]),
            'anexos' => [$this->pdf()],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(0, Anexo::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        // Falha durante a gravação dos registros após salvar o arquivo.
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        Anexo::creating(fn () => throw new \RuntimeException('falha simulada'));
        try {
            $this->actingAs($this->atendenteRh)->post($this->url($demanda->id, 'comentarios'), [
                'mensagem' => 'com anexo', 'anexos' => [$this->pdf()],
            ], ['Accept' => 'application/json'])->assertServerError();
        } finally {
            Anexo::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, $demanda->comentarios()->count());
    }
}
