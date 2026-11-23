<?php

declare(strict_types=1);

namespace Courier\Carga\Application\AdjuntarDocumentoDevolucion;

use Courier\Carga\Domain\DevolucionProveedorRepositoryInterface;
use Courier\Carga\Infrastructure\Storage\CargaDocumentoStorage;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Adjunta documentos a una devolucion al proveedor (factura, nota de credito, guia, fotos...).
 * Solo tipos de documento conocidos, para que nada ejecutable quede en public/uploads.
 */
final class AdjuntarDocumentoDevolucionHandler
{
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly DevolucionProveedorRepositoryInterface $devoluciones,
        private readonly CargaDocumentoStorage $storage,
    ) {
    }

    /**
     * Valida todos antes de guardar ninguno.
     *
     * @param array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}> $archivos
     */
    public static function validar(array $archivos): void
    {
        foreach ($archivos as $archivo) {
            if ($archivo['error'] !== UPLOAD_ERR_OK) {
                throw new ValidationException("No se pudo subir {$archivo['name']}" . (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ': es muy grande.' : '.'));
            }

            if ($archivo['size'] > self::MAX_BYTES) {
                throw new ValidationException("{$archivo['name']} pesa mas de 10 MB.");
            }

            $extension = mb_strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, self::EXTENSIONES, true)) {
                throw new ValidationException("{$archivo['name']}: tipo de archivo no permitido. Use PDF, imagen (JPG, PNG, WEBP), Word, Excel, CSV o TXT.");
            }
        }
    }

    /**
     * @param array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}> $archivos
     * @return int documentos guardados
     */
    public function handle(string $devolucionId, array $archivos, ?string $usuarioId): int
    {
        if ($this->devoluciones->cargaIdDe($devolucionId) === null) {
            throw new NotFoundException('Devolucion no encontrada.');
        }

        self::validar($archivos);

        foreach ($archivos as $archivo) {
            $guardado = $this->storage->store($archivo);
            $this->devoluciones->agregarDocumento($devolucionId, $guardado['nombre_archivo'], $guardado['ruta_archivo'], $usuarioId);
        }

        return count($archivos);
    }
}
