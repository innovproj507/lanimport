<?php

declare(strict_types=1);

namespace Courier\Evidencia\Application\SubirFotoEvidencia;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Evidencia\Domain\AlmacenFotosInterface;
use Courier\Evidencia\Domain\FotoEvidencia;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Evidencia\Domain\TipoEvidencia;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;

/**
 * Guarda una foto de evidencia de la recepcion de una carga o de una salida.
 * La app la reduce antes de subirla; aqui solo se acepta si es una imagen real.
 */
final class SubirFotoEvidenciaHandler implements CommandHandlerInterface
{
    public const TAMANO_MAXIMO = 10 * 1024 * 1024;

    private const EXTENSIONES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public function __construct(
        private readonly FotoEvidenciaRepositoryInterface $fotos,
        private readonly AlmacenFotosInterface $almacen,
        private readonly CargaRepositoryInterface $cargas,
        private readonly SalidaOrdenRepositoryInterface $salidas,
    ) {
    }

    /** @return FotoEvidencia la foto guardada */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SubirFotoEvidenciaCommand);

        $tipo = TipoEvidencia::tryFrom($command->tipo) ?? throw new ValidationException('Tipo de evidencia no valido.');

        if ($tipo === TipoEvidencia::Recepcion && $this->cargas->findById($command->referenciaId) === null) {
            throw new NotFoundException('Carga no encontrada.');
        }

        if ($tipo === TipoEvidencia::Salida && $this->salidas->findById($command->referenciaId) === null) {
            throw new NotFoundException('Orden de salida no encontrada.');
        }

        if ($command->tamano <= 0 || !is_file($command->archivoTemporal)) {
            throw new ValidationException('No llego la foto. Intente de nuevo.');
        }

        if ($command->tamano > self::TAMANO_MAXIMO) {
            throw new ValidationException('La foto no puede pesar mas de 10 MB.');
        }

        $info = @getimagesize($command->archivoTemporal);
        $extension = $info !== false ? (self::EXTENSIONES[$info[2]] ?? null) : null;

        if ($extension === null) {
            throw new ValidationException('El archivo no es una foto (JPG, PNG o WEBP).');
        }

        $id = Uuid::generate();
        $ruta = $this->almacen->guardar($command->archivoTemporal, (string) $id, $extension);

        $foto = $tipo === TipoEvidencia::Recepcion
            ? FotoEvidencia::deRecepcion($id, $command->referenciaId, $ruta, $command->usuarioId)
            : FotoEvidencia::deSalida($id, $command->referenciaId, $ruta, $command->usuarioId);

        try {
            $this->fotos->save($foto);
        } catch (\Throwable $e) {
            $this->almacen->eliminar($ruta);

            throw $e;
        }

        return $foto;
    }
}
