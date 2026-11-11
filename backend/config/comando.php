<?php

return [
    /*
     * Fuso usado para exibição e para interpretar filtros de data enviados
     * pelo frontend. Os horários são sempre armazenados em UTC.
     */
    'fuso_exibicao' => env('DISPLAY_TIMEZONE', 'America/Sao_Paulo'),

    /*
     * Disco (config/filesystems.php) onde os anexos são gravados. O disco
     * "local" é privado (storage/app/private) e fica em volume persistente.
     * Uma futura migração para Amazon S3 exige apenas configurar o disco "s3"
     * e alterar ANEXOS_DISK.
     */
    'anexos' => [
        'disco' => env('ANEXOS_DISK', 'local'),
        'diretorio' => 'anexos',
        'max_arquivos' => 5,
        'max_kb' => 10240,
        'extensoes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'docx', 'xlsx'],
    ],

    'paginacao' => [
        'padrao' => 15,
        'maximo' => 100,
    ],
];
