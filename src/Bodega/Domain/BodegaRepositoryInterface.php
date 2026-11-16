<?php

declare(strict_types=1);

namespace Courier\Bodega\Domain;

interface BodegaRepositoryInterface
{
    public function save(Bodega $bodega): void;

    public function findById(string $id): ?Bodega;

    /** @return array<int, Bodega> todas, activas primero y por nombre */
    public function findAll(): array;

    /** @return array<int, Bodega> */
    public function findAllActivas(): array;

    public function existsCodigo(string $codigo, ?string $exceptoId = null): bool;

    /** Bodega fija de un usuario (null = ve todas). */
    public function bodegaDeUsuario(string $usuarioId): ?string;

    public function asignarAUsuario(string $usuarioId, ?string $bodegaId): void;

    /** @return array<string, string> usuarioId => bodegaId, de los usuarios con bodega fija */
    public function bodegasDeUsuarios(): array;
}
