<?php

declare(strict_types=1);

namespace Courier\Auth\Application\BuscarUsuarios;

use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarUsuariosHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios)
    {
    }

    public function handle(QueryInterface $query): BuscarUsuariosResult
    {
        assert($query instanceof BuscarUsuariosQuery);

        $resultado = $this->usuarios->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarUsuariosResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
