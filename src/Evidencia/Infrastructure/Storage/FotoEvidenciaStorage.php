<?php

declare(strict_types=1);

namespace Courier\Evidencia\Infrastructure\Storage;

use Courier\Evidencia\Domain\AlmacenFotosInterface;
use RuntimeException;

/** Fotos en public/uploads/evidencias/AAAA/MM/<uuid>.<ext> (el nombre no se puede adivinar). */
final class FotoEvidenciaStorage implements AlmacenFotosInterface
{
    private const RELATIVE_DIR = 'uploads/evidencias';

    public function __construct(private readonly string $publicPath)
    {
    }

    public function guardar(string $archivoTemporal, string $id, string $extension): string
    {
        $relativo = self::RELATIVE_DIR . '/' . date('Y/m');
        $dir = $this->publicPath . '/' . $relativo;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de fotos: {$dir}");
        }

        $nombre = preg_replace('/[^a-f0-9-]/', '', strtolower($id)) . '.' . $extension;

        if (!move_uploaded_file($archivoTemporal, $dir . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar la foto.');
        }

        return $relativo . '/' . $nombre;
    }

    public function eliminar(string $rutaArchivo): void
    {
        // Solo dentro de la carpeta de evidencias.
        if (!str_starts_with($rutaArchivo, self::RELATIVE_DIR . '/') || str_contains($rutaArchivo, '..')) {
            return;
        }

        $ruta = $this->publicPath . '/' . $rutaArchivo;

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
