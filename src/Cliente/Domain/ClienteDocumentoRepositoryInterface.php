<?php

declare(strict_types=1);

namespace Courier\Cliente\Domain;

interface ClienteDocumentoRepositoryInterface
{
    public function save(ClienteDocumento $documento): void;

    /** @return array<int, ClienteDocumento> */
    public function findByClienteId(string $clienteId): array;
}
