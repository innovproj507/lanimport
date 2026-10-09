<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure\Persistence;

use PDO;
use Throwable;

final class UnitOfWork
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function run(callable $work): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $work();
            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $e;
        }
    }
}
