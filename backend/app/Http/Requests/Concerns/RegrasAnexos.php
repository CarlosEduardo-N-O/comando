<?php

namespace App\Http\Requests\Concerns;

trait RegrasAnexos
{
    /**
     * Valida quantidade, extensão declarada, tipo real (conteúdo do arquivo)
     * e tamanho de cada anexo.
     *
     * @return array<string, mixed>
     */
    protected function regrasAnexos(): array
    {
        $extensoes = implode(',', config('comando.anexos.extensoes'));

        return [
            'anexos' => ['nullable', 'array', 'max:'.config('comando.anexos.max_arquivos')],
            'anexos.*' => [
                'file',
                'max:'.config('comando.anexos.max_kb'),
                'extensions:'.$extensoes,
                'mimes:'.$extensoes,
            ],
        ];
    }

    /** @return array<string, string> */
    protected function mensagensAnexos(): array
    {
        return [
            'anexos.max' => 'Envie no máximo :max arquivos por vez.',
            'anexos.*.max' => 'Cada arquivo deve ter no máximo 10 MB.',
            'anexos.*.extensions' => 'Tipo de arquivo não permitido. Use PDF, JPG, JPEG, PNG, WEBP, TXT, DOCX ou XLSX.',
            'anexos.*.mimes' => 'O conteúdo do arquivo não corresponde a um tipo permitido.',
            'anexos.*.uploaded' => 'Falha no envio do arquivo. Verifique se ele tem no máximo 10 MB.',
        ];
    }
}
