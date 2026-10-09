<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Courier\Auth\Domain\Exception\InvalidCredentialsException;
use Courier\Auth\Domain\Exception\TooManyAttemptsException;
use Courier\Carga\Domain\Exception\InvalidEstadoTransitionException;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Throwable;

/**
 * Equivalente a JsonResponse + ApiExceptionHandler (interfaces/Api/Support)
 * del sistema original, adaptado al request/response nativos de CI4.
 */
abstract class BaseApiController extends BaseController
{
    protected function usuarioIdJwt(): ?string
    {
        return Services::apiAuthContext()->usuarioId;
    }

    protected function success(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'data' => $data,
        ]);
    }

    /** @param array<string, mixed> $errors */
    protected function error(string $message, int $status = 400, array $errors = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ]);
    }

    protected function fromException(Throwable $e): ResponseInterface
    {
        return match (true) {
            $e instanceof ValidationException => $this->error($e->getMessage(), 422),
            $e instanceof InvalidCredentialsException => $this->error($e->getMessage(), 401),
            $e instanceof TooManyAttemptsException => $this->error($e->getMessage(), 429),
            $e instanceof NotFoundException => $this->error($e->getMessage(), 404),
            $e instanceof InvalidEstadoTransitionException => $this->error($e->getMessage(), 409),
            default => $this->unexpectedError($e),
        };
    }

    private function unexpectedError(Throwable $e): ResponseInterface
    {
        log_message('error', 'Error no controlado en la API: {message}', ['message' => $e->getMessage()]);
        log_message('error', $e->getTraceAsString());

        return $this->error('Error interno del servidor.', 500);
    }
}
