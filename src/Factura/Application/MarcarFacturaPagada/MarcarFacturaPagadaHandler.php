<?php

declare(strict_types=1);

namespace Courier\Factura\Application\MarcarFacturaPagada;

use Courier\Factura\Domain\Exception\FacturaNotFoundException;
use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class MarcarFacturaPagadaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly FacturaRepositoryInterface $facturas,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarcarFacturaPagadaCommand);

        $factura = $this->facturas->findById($command->facturaId);

        if ($factura === null) {
            throw new FacturaNotFoundException("No se encontro la factura con id {$command->facturaId}.");
        }

        $factura->marcarPagada();

        $this->unitOfWork->run(function () use ($factura) {
            $this->facturas->save($factura);
        });

        return null;
    }
}
