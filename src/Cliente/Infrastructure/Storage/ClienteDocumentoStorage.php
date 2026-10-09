<?php

declare(strict_types=1);

namespace Courier\Cliente\Infrastructure\Storage;

use RuntimeException;

final class ClienteDocumentoStorage
{
    private const RELATIVE_DIR = 'uploads/clientes';

    public function __construct(private readonly string $publicPath)
    {
    }

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function store(string $clienteId, array $uploadedFile): array
    {
        $dir = $this->publicPath . '/' . self::RELATIVE_DIR . '/' . $clienteId;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de documentos: {$dir}");
        }

        $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $uploadedFile['name']);
        $fullPath = $dir . '/' . $safeName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
            throw new RuntimeException("No se pudo guardar el documento: {$uploadedFile['name']}");
        }

        return [
            'nombre_archivo' => $uploadedFile['name'],
            'ruta_archivo' => self::RELATIVE_DIR . '/' . $clienteId . '/' . $safeName,
            'tipo' => $uploadedFile['type'],
        ];
    }
}
