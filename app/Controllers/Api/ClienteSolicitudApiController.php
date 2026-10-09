<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Cliente\Application\SolicitarRegistroCliente\SolicitarRegistroClienteCommand;
use Courier\Cliente\Application\SolicitarRegistroCliente\SolicitarRegistroClienteHandler;
use Courier\Cliente\Infrastructure\Storage\ClienteDocumentoStorage;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;

final class ClienteSolicitudApiController extends BaseApiController
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    public function store(): ResponseInterface
    {
        $nombre = trim((string) $this->request->getPost('nombre'));
        $apellido = trim((string) $this->request->getPost('apellido')) ?: null;
        $email = trim((string) $this->request->getPost('email'));
        $telefono = trim((string) $this->request->getPost('telefono')) ?: null;
        $direccion = trim((string) $this->request->getPost('direccion')) ?: null;
        $empresa = trim((string) $this->request->getPost('empresa')) ?: null;
        $pais = trim((string) $this->request->getPost('pais')) ?: null;

        if ($nombre === '' || $email === '') {
            return $this->error('Nombre y correo electronico son obligatorios.', 422);
        }

        try {
            $clienteId = (string) Uuid::generate();
            $archivos = $this->procesarArchivos($clienteId);

            $command = new SolicitarRegistroClienteCommand(
                $clienteId,
                $nombre,
                $apellido,
                $email,
                $telefono,
                $direccion,
                $empresa,
                $pais,
                $archivos,
            );

            $result = $this->container()->get(SolicitarRegistroClienteHandler::class)->handle($command);

            return $this->success(['cliente_id' => $result->clienteId, 'codigo' => $result->codigo], 201);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** @return array<int, array{nombre_archivo: string, ruta_archivo: string, tipo: string}> */
    private function procesarArchivos(string $clienteId): array
    {
        $storage = $this->container()->get(ClienteDocumentoStorage::class);
        $resultado = [];

        foreach ($_FILES['documentos']['name'] ?? [] as $i => $nombreArchivo) {
            if ($_FILES['documentos']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $size = (int) $_FILES['documentos']['size'][$i];
            $type = (string) $_FILES['documentos']['type'][$i];

            if ($size > self::MAX_FILE_SIZE || !in_array($type, self::ALLOWED_MIME_TYPES, true)) {
                throw new ValidationException("Archivo invalido: {$nombreArchivo}");
            }

            $resultado[] = $storage->store($clienteId, [
                'name' => $nombreArchivo,
                'type' => $type,
                'tmp_name' => $_FILES['documentos']['tmp_name'][$i],
                'error' => $_FILES['documentos']['error'][$i],
                'size' => $size,
            ]);
        }

        return $resultado;
    }
}
