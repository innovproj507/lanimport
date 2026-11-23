<?php

declare(strict_types=1);

namespace Courier\Devolucion\Application\AdjuntarDocumentoDevolucion;

use Courier\Carga\Infrastructure\Storage\CargaDocumentoStorage;
use Courier\Devolucion\Domain\OrdenDevolucionRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Adjunta documentos a una orden de devolucion (factura, nota de credito, guia, fotos...), abierta
 * o ya despachada. Solo tipos de documento conocidos, para que nada ejecutable quede en public/uploads.
 */
final class AdjuntarDocumentoDevolucionHandler
{
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly OrdenDevolucionRepositoryInterface $ordenes,
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
    public function handle(string $ordenId, array $archivos, ?string $usuarioId): int
    {
        if ($this->ordenes->findById($ordenId) === null) {
            throw new NotFoundException('Orden de devolucion no encontrada.');
        }

        if ($archivos === []) {
            throw new ValidationException('Elija al menos un documento.');
        }

        self::validar($archivos);

        foreach ($archivos as $archivo) {
            $guardado = $this->storage->store($archivo);
            $this->ordenes->agregarDocumento($ordenId, $guardado['nombre_archivo'], $guardado['ruta_archivo'], $usuarioId);
        }

        return count($archivos);
    }
}
