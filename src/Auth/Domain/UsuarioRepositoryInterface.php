<?php

declare(strict_types=1);

namespace Courier\Auth\Domain;

interface UsuarioRepositoryInterface
{
    public function findByEmail(string $email): ?Usuario;

    public function findById(string $id): ?Usuario;

    public function save(Usuario $usuario): void;

    /** @return array<int, Usuario> */
    public function findAll(): array;

    /** @return array{items: array<int, Usuario>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsEmail(string $email): bool;
}
