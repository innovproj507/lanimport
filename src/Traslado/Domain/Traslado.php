<?php

declare(strict_types=1);

namespace Courier\Traslado\Domain;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Traslado de bultos de una bodega a otra.
 *
 *   pendiente  -> la bodega origen escanea los bultos al cargarlos y "despacha" (los no
 *                 escaneados se quedan en origen).
 *   en_camino  -> la bodega destino escanea cada bulto al llegar: pasa a esa bodega al instante.
 *   recibido   -> cuando llegaron todos.
 *   cancelado  -> solo mientras esta pendiente; los bultos quedan libres en origen.
 */
final class Traslado
{
    /** @var array<string, array{lpnId: string, codigo: string, salida: ?DateTimeImmutable, llegada: ?DateTimeImmutable}> por lpnId */
    private array $lineas = [];

    private function __construct(
        private readonly string $id,
        private readonly string $origenBodegaId,
        private readonly string $destinoBodegaId,
        private EstadoTraslado $estado,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?string $codigo = null,
        private ?string $nota = null,
        private ?string $despachadoPorUsuarioId = null,
        private ?DateTimeImmutable $despachadoAt = null,
        private ?DateTimeImmutable $recibidoAt = null,
    ) {
    }

    /** @param array<string, string> $lpns lpnId => codigo */
    public static function crear(string $id, string $origen, string $destino, array $lpns, string $usuarioId, ?string $nota): self
    {
        if ($origen === $destino) {
            throw new ValidationException('La bodega destino debe ser distinta de la de origen.');
        }

        if ($lpns === []) {
            throw new ValidationException('Seleccione al menos una carga con bultos disponibles para trasladar.');
        }

        $t = new self($id, $origen, $destino, EstadoTraslado::PENDIENTE, $usuarioId, new DateTimeImmutable(), null,
            $nota !== null && trim($nota) !== '' ? mb_substr(trim($nota), 0, 255) : null);

        foreach ($lpns as $lpnId => $codigo) {
            $t->lineas[(string) $lpnId] = ['lpnId' => (string) $lpnId, 'codigo' => (string) $codigo, 'salida' => null, 'llegada' => null];
        }

        return $t;
    }

    /**
     * @param array<int, array{lpnId: string, codigo: string, salida: ?DateTimeImmutable, llegada: ?DateTimeImmutable}> $lineas
     */
    public static function reconstituir(
        string $id, string $origen, string $destino, EstadoTraslado $estado, string $creadoPor, DateTimeImmutable $createdAt,
        ?string $codigo, ?string $nota, ?string $despachadoPor, ?DateTimeImmutable $despachadoAt, ?DateTimeImmutable $recibidoAt, array $lineas,
    ): self {
        $t = new self($id, $origen, $destino, $estado, $creadoPor, $createdAt, $codigo, $nota, $despachadoPor, $despachadoAt, $recibidoAt);

        foreach ($lineas as $l) {
            $t->lineas[$l['lpnId']] = $l;
        }

        return $t;
    }

    /** Bodega origen escanea el bulto al cargarlo. Devuelve el lpnId. */
    public function escanearSalida(string $codigo): string
    {
        if ($this->estado !== EstadoTraslado::PENDIENTE) {
            throw new ValidationException("El traslado {$this->etiqueta()} ya salio: ahora se escanea en la bodega destino.");
        }

        $lpnId = $this->buscar($codigo);
        $this->lineas[$lpnId]['salida'] ??= new DateTimeImmutable();

        return $lpnId;
    }

    /**
     * Sale el camion. Los bultos no escaneados no van: se quitan del traslado (quedan libres en
     * origen). Devuelve los lpnId que se quitaron.
     *
     * @return array<int, string>
     */
    public function despachar(string $usuarioId): array
    {
        if ($this->estado !== EstadoTraslado::PENDIENTE) {
            throw new ValidationException("El traslado {$this->etiqueta()} ya fue despachado o cancelado.");
        }

        $quitados = [];
        foreach ($this->lineas as $lpnId => $l) {
            if ($l['salida'] === null) {
                $quitados[] = $lpnId;
                unset($this->lineas[$lpnId]);
            }
        }

        if ($this->lineas === []) {
            throw new ValidationException('Escanee los bultos que van en el traslado antes de despacharlo.');
        }

        $this->estado = EstadoTraslado::EN_CAMINO;
        $this->despachadoPorUsuarioId = $usuarioId;
        $this->despachadoAt = new DateTimeImmutable();

        return $quitados;
    }

    /** Bodega destino escanea el bulto al llegar. Devuelve el lpnId (null si ya se habia escaneado). */
    public function escanearLlegada(string $codigo): ?string
    {
        if ($this->estado !== EstadoTraslado::EN_CAMINO) {
            throw new ValidationException($this->estado === EstadoTraslado::PENDIENTE
                ? "El traslado {$this->etiqueta()} todavia no sale de la bodega origen."
                : "El traslado {$this->etiqueta()} ya esta {$this->estado->label()}.");
        }

        $lpnId = $this->buscar($codigo);

        if ($this->lineas[$lpnId]['llegada'] !== null) {
            return null;
        }

        $this->lineas[$lpnId]['llegada'] = new DateTimeImmutable();

        if ($this->faltanPorLlegar() === 0) {
            $this->estado = EstadoTraslado::RECIBIDO;
            $this->recibidoAt = new DateTimeImmutable();
        }

        return $lpnId;
    }

    /** @return array<int, string> lpnIds que se liberan */
    public function cancelar(): array
    {
        if ($this->estado !== EstadoTraslado::PENDIENTE) {
            throw new ValidationException('Solo se puede cancelar un traslado que todavia no sale.');
        }

        $this->estado = EstadoTraslado::CANCELADO;

        return array_keys($this->lineas);
    }

    private function buscar(string $codigo): string
    {
        foreach ($this->lineas as $lpnId => $l) {
            if (strcasecmp($l['codigo'], trim($codigo)) === 0) {
                return $lpnId;
            }
        }

        throw new ValidationException("El bulto {$codigo} no va en el traslado {$this->etiqueta()}.");
    }

    public function faltanPorLlegar(): int
    {
        return count(array_filter($this->lineas, static fn ($l) => $l['llegada'] === null));
    }

    /** @return array{salida: int, llegada: int, total: int} */
    public function progreso(): array
    {
        return [
            'salida' => count(array_filter($this->lineas, static fn ($l) => $l['salida'] !== null)),
            'llegada' => count(array_filter($this->lineas, static fn ($l) => $l['llegada'] !== null)),
            'total' => count($this->lineas),
        ];
    }

    public function etiqueta(): string
    {
        return $this->codigo ?? 'TR';
    }

    public function id(): string { return $this->id; }
    public function codigo(): ?string { return $this->codigo; }
    public function origenBodegaId(): string { return $this->origenBodegaId; }
    public function destinoBodegaId(): string { return $this->destinoBodegaId; }
    public function estado(): EstadoTraslado { return $this->estado; }
    public function nota(): ?string { return $this->nota; }
    public function creadoPorUsuarioId(): string { return $this->creadoPorUsuarioId; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function despachadoPorUsuarioId(): ?string { return $this->despachadoPorUsuarioId; }
    public function despachadoAt(): ?DateTimeImmutable { return $this->despachadoAt; }
    public function recibidoAt(): ?DateTimeImmutable { return $this->recibidoAt; }

    /** @return array<int, array{lpnId: string, codigo: string, salida: ?DateTimeImmutable, llegada: ?DateTimeImmutable}> */
    public function lineas(): array
    {
        return array_values($this->lineas);
    }

    /** @return array<int, string> */
    public function lpnIds(): array
    {
        return array_keys($this->lineas);
    }
}
