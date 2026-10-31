<?php

declare(strict_types=1);

namespace Courier\Lpn\Infrastructure\Barcode;

use Courier\Carga\Domain\ImagenesEtiquetasCargaInterface;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;

final class LiberarImagenesEtiquetasCarga implements ImagenesEtiquetasCargaInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
    ) {
    }

    public function liberar(string $cargaId): int
    {
        $borradas = 0;

        foreach ($this->lpns->findByCargaId($cargaId) as $lpn) {
            $codigo = (string) $lpn->codigo();

            if ($codigo !== '' && $this->barcodeGenerator->existe($codigo)) {
                $this->barcodeGenerator->eliminar($codigo);
                $borradas++;
            }
        }

        return $borradas;
    }
}
