<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

interface GenealogiaLpnRepositoryInterface
{
    public function registrar(string $reempaqueOrdenId, string $origenLpnId, string $destinoLpnId): void;

    /** @return array{ancestros: array<int, array{lpnId: string, codigo: string}>, descendientes: array<int, array{lpnId: string, codigo: string}>} */
    public function buscarPorLpn(string $lpnId): array;
}
