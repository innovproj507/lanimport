<?php

declare(strict_types=1);

namespace Courier\Shared\Domain;

abstract class AggregateRoot
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    abstract public function id(): string;

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return array<int, object> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
