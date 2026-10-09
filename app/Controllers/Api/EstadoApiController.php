<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Carga\Application\ActualizarEstadoCarga\ActualizarEstadoCargaCommand;
use Courier\Carga\Application\ActualizarEstadoCarga\ActualizarEstadoCargaHandler;
use Throwable;

final class EstadoApiController extends BaseApiController
{
    public function update(string $id): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $command = new ActualizarEstadoCargaCommand(
                $id,
                (string) ($body['nuevo_estado'] ?? ''),
                (string) $this->usuarioIdJwt(),
                isset($body['comentario']) ? (string) $body['comentario'] : null,
            );

            $this->container()->get(ActualizarEstadoCargaHandler::class)->handle($command);

            return $this->success(['mensaje' => 'Estado actualizado correctamente.']);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }
}
