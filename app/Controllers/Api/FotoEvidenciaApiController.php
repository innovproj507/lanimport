<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Evidencia\Application\SubirFotoEvidencia\SubirFotoEvidenciaCommand;
use Courier\Evidencia\Application\SubirFotoEvidencia\SubirFotoEvidenciaHandler;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Evidencia\Domain\TipoEvidencia;
use Courier\Shared\Domain\Exception\ValidationException;
use Throwable;

/**
 * Fotos de evidencia por carga del cliente (bodega/api/{cargas|salidas}/{id}/fotos): las toma
 * la app de bodega y tambien se suben desde la web (misma sesion).
 * cargas/{cargaId} = fotos de recepcion; salidas/{ordenId}?carga={cargaId} = fotos de esa carga
 * al subirla al contenedor (sin ?carga, el GET lista todas las de la salida).
 */
final class FotoEvidenciaApiController extends BaseApiController
{
    public function listar(string $recurso, string $id): ResponseInterface
    {
        try {
            $fotos = $this->container()->get(FotoEvidenciaRepositoryInterface::class)->listar(self::tipo($recurso), $id, $this->carga());

            return $this->success(array_map(self::json(...), $fotos));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Recibe una foto (campo "foto", multipart) y devuelve la lista actualizada de esa carga. */
    public function subir(string $recurso, string $id): ResponseInterface
    {
        try {
            $archivo = $_FILES['foto'] ?? null;

            if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new ValidationException(match ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La foto es demasiado grande.',
                    default => 'No llego la foto. Intente de nuevo.',
                });
            }

            $tipo = self::tipo($recurso);
            $this->container()->get(SubirFotoEvidenciaHandler::class)->handle(new SubirFotoEvidenciaCommand(
                $tipo->value,
                $id,
                (string) $archivo['tmp_name'],
                (int) $archivo['size'],
                $this->container()->get(SessionManager::class)->currentUsuarioId(),
                $this->carga(),
            ));

            $fotos = $this->container()->get(FotoEvidenciaRepositoryInterface::class)->listar($tipo, $id, $this->carga());

            return $this->success(array_map(self::json(...), $fotos), 201);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    private static function tipo(string $recurso): TipoEvidencia
    {
        return $recurso === 'salidas' ? TipoEvidencia::Salida : TipoEvidencia::Recepcion;
    }

    private function carga(): ?string
    {
        $carga = trim((string) $this->request->getGet('carga'));

        return $carga !== '' ? $carga : null;
    }

    /**
     * @param array{id: string, ruta: string, usuario: ?string, fecha: string, cargaId: ?string} $foto
     * @return array<string, mixed>
     */
    private static function json(array $foto): array
    {
        return [
            'id' => $foto['id'],
            'url' => '/' . $foto['ruta'],
            'cargaId' => $foto['cargaId'],
            'usuario' => $foto['usuario'],
            'fecha' => (new \DateTimeImmutable($foto['fecha']))->format('d/m/Y H:i'),
        ];
    }
}
