<?php

declare(strict_types=1);

namespace Courier\Devolucion\Domain;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Orden de devolucion al proveedor: bultos de una o varias cargas (de varios clientes y
 * proveedores) que un camion viene a buscar.
 *
 *   pendiente  -> bodega escanea cada bulto al subirlo al camion y toma fotos por carga; se
 *                 llenan los datos del camion, la nota y los documentos.
 *   despachada -> el camion salio: exige todos los bultos escaneados.
 *   cancelada  -> solo mientras esta pendiente; los bultos vuelven a estar disponibles.
 */
final class OrdenDevolucion
{
    /** @var array<string, array{lpnId: string, codigo: string, cargaId: ?string, escaneado: ?DateTimeImmutable}> por lpnId */
    private array $lineas = [];

    /** @var array<int, string> lpnIds quitados en esta operacion (el repositorio borra solo esas lineas) */
    private array $quitados = [];

    private function __construct(
        private readonly string $id,
        private readonly ?string $bodegaId,
        private EstadoDevolucion $estado,
        private string $motivo,
        private ?string $nota,
        private DatosTransporte $transporte,
        private readonly ?string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?string $codigo = null,
        private ?string $confirmadoPorUsuarioId = null,
        private ?DateTimeImmutable $confirmedAt = null,
    ) {
    }

    /** @param array<string, array{codigo: string, cargaId: ?string}> $lpns lpnId => datos */
    public static function crear(string $id, string $bodegaId, array $lpns, string $motivo, ?string $nota, DatosTransporte $transporte, string $usuarioId): self
    {
        if ($lpns === []) {
            throw new ValidationException('Seleccione al menos una carga con bultos disponibles para devolver.');
        }

        $orden = new self($id, $bodegaId, EstadoDevolucion::PENDIENTE, self::motivoValido($motivo), self::notaLimpia($nota), $transporte, $usuarioId, new DateTimeImmutable());

        foreach ($lpns as $lpnId => $l) {
            $orden->lineas[(string) $lpnId] = ['lpnId' => (string) $lpnId, 'codigo' => (string) $l['codigo'], 'cargaId' => $l['cargaId'], 'escaneado' => null];
        }

        return $orden;
    }

    /** @param array<int, array{lpnId: string, codigo: string, cargaId: ?string, escaneado: ?DateTimeImmutable}> $lineas */
    public static function reconstituir(
        string $id, ?string $codigo, ?string $bodegaId, EstadoDevolucion $estado, string $motivo, ?string $nota, DatosTransporte $transporte,
        ?string $creadoPor, DateTimeImmutable $createdAt, ?string $confirmadoPor, ?DateTimeImmutable $confirmedAt, array $lineas,
    ): self {
        $orden = new self($id, $bodegaId, $estado, $motivo, $nota, $transporte, $creadoPor, $createdAt, $codigo, $confirmadoPor, $confirmedAt);

        foreach ($lineas as $l) {
            $orden->lineas[$l['lpnId']] = $l;
        }

        return $orden;
    }

    /** Bodega escanea el bulto al subirlo al camion. Devuelve el lpnId, o null si ya estaba escaneado. */
    public function escanear(string $codigo): ?string
    {
        $this->exigirPendiente();
        $lpnId = $this->buscar($codigo);

        if ($this->lineas[$lpnId]['escaneado'] !== null) {
            return null;
        }

        $this->lineas[$lpnId]['escaneado'] = new DateTimeImmutable();

        return $lpnId;
    }

    /** Datos del camion y nota: se pueden corregir mientras la orden esta abierta. */
    public function actualizarDatos(DatosTransporte $transporte, ?string $nota, ?string $motivo = null): void
    {
        $this->exigirPendiente();
        $this->transporte = $transporte;
        $this->nota = self::notaLimpia($nota);

        if ($motivo !== null) {
            $this->motivo = self::motivoValido($motivo);
        }
    }

    /**
     * Saca de la orden los bultos de una carga (vuelven a bodega).
     *
     * @return array<int, string> lpnIds quitados
     */
    public function quitarCarga(string $cargaId): array
    {
        $this->exigirPendiente();
        $quitar = array_keys(array_filter($this->lineas, static fn ($l) => $l['cargaId'] === $cargaId));

        if ($quitar === []) {
            throw new ValidationException('Esa carga no esta en la orden.');
        }

        if (count($quitar) === count($this->lineas)) {
            throw new ValidationException('Es la unica carga de la orden: cancele la orden en su lugar.');
        }

        foreach ($quitar as $lpnId) {
            unset($this->lineas[$lpnId]);
        }
        array_push($this->quitados, ...$quitar);

        return $quitar;
    }

    /** El camion salio: todos los bultos deben estar escaneados. */
    public function confirmar(string $usuarioId): void
    {
        $this->exigirPendiente();
        $faltan = count(array_filter($this->lineas, static fn ($l) => $l['escaneado'] === null));

        if ($faltan > 0) {
            throw new ValidationException("Faltan {$faltan} bulto(s) por escanear. Escanee todos los bultos que suben al camion (o quite la carga de la orden) antes de confirmar.");
        }

        $this->estado = EstadoDevolucion::DESPACHADA;
        $this->confirmadoPorUsuarioId = $usuarioId;
        $this->confirmedAt = new DateTimeImmutable();
    }

    /** @return array<int, string> lpnIds que se liberan */
    public function cancelar(): array
    {
        $this->exigirPendiente();
        $this->estado = EstadoDevolucion::CANCELADA;

        return array_keys($this->lineas);
    }

    private function exigirPendiente(): void
    {
        if ($this->estado !== EstadoDevolucion::PENDIENTE) {
            throw new ValidationException("La orden {$this->etiqueta()} ya esta " . mb_strtolower($this->estado->label()) . '.');
        }
    }

    private function buscar(string $codigo): string
    {
        foreach ($this->lineas as $lpnId => $l) {
            if (strcasecmp($l['codigo'], trim($codigo)) === 0) {
                return $lpnId;
            }
        }

        throw new ValidationException("El bulto {$codigo} no va en la orden de devolucion {$this->etiqueta()}.");
    }

    private static function motivoValido(string $motivo): string
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 3) {
            throw new ValidationException('Escriba el motivo de la devolucion al proveedor.');
        }

        return mb_substr($motivo, 0, 500);
    }

    private static function notaLimpia(?string $nota): ?string
    {
        return $nota !== null && trim($nota) !== '' ? mb_substr(trim($nota), 0, 2000) : null;
    }

    /** @return array{escaneados: int, total: int} */
    public function progreso(): array
    {
        return [
            'escaneados' => count(array_filter($this->lineas, static fn ($l) => $l['escaneado'] !== null)),
            'total' => count($this->lineas),
        ];
    }

    public function etiqueta(): string
    {
        return $this->codigo ?? 'DEV';
    }

    /** @return array<string, array<int, string>> cargaId => lpnIds */
    public function lpnsPorCarga(): array
    {
        $grupos = [];
        foreach ($this->lineas as $lpnId => $l) {
            $grupos[(string) $l['cargaId']][] = $lpnId;
        }

        return $grupos;
    }

    public function id(): string { return $this->id; }
    public function codigo(): ?string { return $this->codigo; }
    public function bodegaId(): ?string { return $this->bodegaId; }
    public function estado(): EstadoDevolucion { return $this->estado; }
    public function motivo(): string { return $this->motivo; }
    public function nota(): ?string { return $this->nota; }
    public function transporte(): DatosTransporte { return $this->transporte; }
    public function creadoPorUsuarioId(): ?string { return $this->creadoPorUsuarioId; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function confirmadoPorUsuarioId(): ?string { return $this->confirmadoPorUsuarioId; }
    public function confirmedAt(): ?DateTimeImmutable { return $this->confirmedAt; }

    /** @return array<int, array{lpnId: string, codigo: string, cargaId: ?string, escaneado: ?DateTimeImmutable}> */
    public function lineas(): array
    {
        return array_values($this->lineas);
    }

    /** @return array<int, string> */
    public function lpnIds(): array
    {
        return array_keys($this->lineas);
    }

    /** @return array<int, string> */
    public function lpnIdsQuitados(): array
    {
        return $this->quitados;
    }
}
