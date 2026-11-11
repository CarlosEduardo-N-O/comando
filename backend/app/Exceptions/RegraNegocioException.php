<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violação de regra de negócio (transição inválida, justificativa ausente,
 * responsável inválido...). Responde 422 no mesmo formato das validações.
 */
class RegraNegocioException extends RuntimeException
{
    public function __construct(string $message, private readonly string $campo = 'demanda', private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function conflito(string $message, string $campo = 'demanda'): self
    {
        return new self($message, $campo, 409);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->campo => [$this->getMessage()]],
        ], $this->status);
    }
}
