<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\EliminarAduanero;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\RegistroEnUsoException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Elimina un aduanero que nunca se uso en cargas ni salidas; si tiene historial
 * se debe desactivar. Su usuario de acceso (API de rastreo), si tenia, queda desactivado.
 */
final class EliminarAduaneroHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly AduaneroRepositoryInterface $aduaneros,
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return string nombre del aduanero eliminado */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarAduaneroCommand);

        $aduanero = $this->aduaneros->findById($command->aduaneroId);

        if ($aduanero === null) {
            throw new NotFoundException('Aduanero no encontrado.');
        }

        $referencias = $this->aduaneros->referencias($aduanero->id());

        if ($referencias !== []) {
            throw RegistroEnUsoException::para("al aduanero {$aduanero->nombre()}", $referencias);
        }

        $this->unitOfWork->run(function () use ($aduanero): void {
            $this->aduaneros->delete($aduanero->id());

            $usuario = $aduanero->usuarioId() !== null ? $this->usuarios->findById($aduanero->usuarioId()) : null;

            if ($usuario !== null && $usuario->activo()) {
                $usuario->desactivar();
                $this->usuarios->save($usuario);
            }
        });

        return $aduanero->nombre();
    }
}
