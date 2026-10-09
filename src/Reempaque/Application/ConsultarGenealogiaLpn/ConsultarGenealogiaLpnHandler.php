<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\ConsultarGenealogiaLpn;

use Courier\Reempaque\Domain\GenealogiaLpnRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ConsultarGenealogiaLpnHandler implements QueryHandlerInterface
{
    public function __construct(private readonly GenealogiaLpnRepositoryInterface $genealogia)
    {
    }

    /** @return array{ancestros: array<int, array{lpnId: string, codigo: string}>, descendientes: array<int, array{lpnId: string, codigo: string}>} */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ConsultarGenealogiaLpnQuery);

        return $this->genealogia->buscarPorLpn($query->lpnId);
    }
}
