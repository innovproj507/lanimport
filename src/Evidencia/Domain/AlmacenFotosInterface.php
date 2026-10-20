<?php

declare(strict_types=1);

namespace Courier\Evidencia\Domain;

/** Donde quedan los archivos de las fotos de evidencia. */
interface AlmacenFotosInterface
{
    /**
     * Mueve la imagen subida a su lugar definitivo.
     *
     * @return string ruta relativa a public/ (p.ej. uploads/evidencias/2026/10/<id>.jpg)
     */
    public function guardar(string $archivoTemporal, string $id, string $extension): string;

    public function eliminar(string $rutaArchivo): void;
}
