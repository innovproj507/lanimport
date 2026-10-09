<?php

declare(strict_types=1);

namespace Courier\Cliente\Domain;

interface ClienteRepositoryInterface
{
    public function save(Cliente $cliente): void;

    public function findById(string $id): ?Cliente;

    public function findByUsuarioId(string $usuarioId): ?Cliente;

    /** @return array<int, Cliente> */
    public function findAll(): array;

    /** @return array<int, Cliente> */
    public function findApproved(): array;

    /** @return array<int, Cliente> */
    public function findPending(): array;

    /** @return array<int, Cliente> */
    public function search(string $term): array;

    /** @return array{items: array<int, Cliente>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsEmail(string $email): bool;

    public function existsEmailExcluding(string $email, string $excludeId): bool;
}
