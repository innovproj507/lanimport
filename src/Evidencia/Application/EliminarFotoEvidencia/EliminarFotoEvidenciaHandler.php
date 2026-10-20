<?php

declare(strict_types=1);

namespace Courier\Evidencia\Application\EliminarFotoEvidencia;

use Courier\Evidencia\Domain\AlmacenFotosInterface;
use Courier\Evidencia\Domain\FotoEvidencia;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

/** Borra una foto de evidencia (registro y archivo). */
final class EliminarFotoEvidenciaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly FotoEvidenciaRepositoryInterface $fotos,
        private readonly AlmacenFotosInterface $almacen,
    ) {
    }

    /** @return FotoEvidencia la foto eliminada (para volver a su carga u orden) */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarFotoEvidenciaCommand);

        $foto = $this->fotos->findById($command->fotoId) ?? throw new NotFoundException('Foto no encontrada.');

        $this->fotos->delete($foto->id());
        $this->almacen->eliminar($foto->rutaArchivo());

        return $foto;
    }
}
