<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\ValueObject\Aduanero;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Domain\ValueObject\DetalleAereo;
use Courier\Carga\Domain\ValueObject\DetalleFreight;
use Courier\Carga\Domain\ValueObject\DetalleMaritimo;
use Courier\Carga\Domain\ValueObject\DetalleServicioInterface;
use Courier\Carga\Domain\ValueObject\LineaCarga;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Carga extends AggregateRoot
{
    /** @var array<int, HistorialEstado> */
    private array $historial = [];

    /** @var array<int, LineaCarga> */
    private array $lineas = [];

    private function __construct(
        private readonly Uuid $id,
        private readonly TrackingNumero $trackingNumero,
        private string $clienteId,
        private string $proveedor,
        private ?string $proveedorIdentificador,
        private ?Aduanero $aduanero,
        private ?Contenedor $numeroContenedor,
        private readonly ?TipoServicio $tipoServicio,
        private readonly ?DetalleServicioInterface $detalle,
        private CargaStatus $estado,
        private readonly string $registradoPorUsuarioId,
        private DateTimeImmutable $fechaIngreso,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?string $notas = null,
        private ?string $facturaProveedorNombre = null,
        private ?string $facturaProveedorRuta = null,
    ) {
        $this->assertDetalleCoincideConTipoServicio($tipoServicio, $detalle);
    }

    /** @param array<int, LineaCarga> $lineas */
    public static function registrar(
        TrackingNumero $trackingNumero,
        string $clienteId,
        string $proveedor,
        ?string $proveedorIdentificador,
        ?Aduanero $aduanero,
        ?Contenedor $numeroContenedor,
        ?TipoServicio $tipoServicio,
        ?DetalleServicioInterface $detalle,
        string $registradoPorUsuarioId,
        DateTimeImmutable $fechaIngreso,
        array $lineas = [],
        ?string $notas = null,
        ?string $facturaProveedorNombre = null,
        ?string $facturaProveedorRuta = null,
    ): self {
        $now = new DateTimeImmutable();

        $carga = new self(
            Uuid::generate(),
            $trackingNumero,
            $clienteId,
            $proveedor,
            $proveedorIdentificador,
            $aduanero,
            $numeroContenedor,
            $tipoServicio,
            $detalle,
            CargaStatus::INGRESADO,
            $registradoPorUsuarioId,
            $fechaIngreso,
            $now,
            $now,
            $notas,
            $facturaProveedorNombre,
            $facturaProveedorRuta,
        );

        $carga->historial[] = HistorialEstado::registrar(CargaStatus::INGRESADO, $registradoPorUsuarioId);
        $carga->lineas = $lineas;

        return $carga;
    }

    /**
     * @param array<int, HistorialEstado> $historial
     * @param array<int, LineaCarga> $lineas
     */
    public static function reconstituir(
        Uuid $id,
        TrackingNumero $trackingNumero,
        string $clienteId,
        string $proveedor,
        ?string $proveedorIdentificador,
        ?Aduanero $aduanero,
        ?Contenedor $numeroContenedor,
        ?TipoServicio $tipoServicio,
        ?DetalleServicioInterface $detalle,
        CargaStatus $estado,
        string $registradoPorUsuarioId,
        DateTimeImmutable $fechaIngreso,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        array $historial = [],
        array $lineas = [],
        ?string $notas = null,
        ?string $facturaProveedorNombre = null,
        ?string $facturaProveedorRuta = null,
    ): self {
        $carga = new self(
            $id,
            $trackingNumero,
            $clienteId,
            $proveedor,
            $proveedorIdentificador,
            $aduanero,
            $numeroContenedor,
            $tipoServicio,
            $detalle,
            $estado,
            $registradoPorUsuarioId,
            $fechaIngreso,
            $createdAt,
            $updatedAt,
            $notas,
            $facturaProveedorNombre,
            $facturaProveedorRuta,
        );

        $carga->historial = $historial;
        $carga->lineas = $lineas;

        return $carga;
    }

    /** @param array<int, LineaCarga> $lineas */
    public function actualizarDatos(
        string $clienteId,
        string $proveedor,
        ?string $proveedorIdentificador,
        ?Aduanero $aduanero,
        ?Contenedor $numeroContenedor,
        DateTimeImmutable $fechaIngreso,
        array $lineas,
        ?string $notas,
        ?string $facturaProveedorNombre,
        ?string $facturaProveedorRuta,
    ): void {
        $this->clienteId = $clienteId;
        $this->proveedor = $proveedor;
        $this->proveedorIdentificador = $proveedorIdentificador;
        $this->aduanero = $aduanero;
        $this->numeroContenedor = $numeroContenedor;
        $this->fechaIngreso = $fechaIngreso;
        $this->lineas = $lineas;
        $this->notas = $notas;
        $this->facturaProveedorNombre = $facturaProveedorNombre;
        $this->facturaProveedorRuta = $facturaProveedorRuta;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function cambiarEstado(CargaStatus $nuevoEstado, string $usuarioId, ?string $comentario = null): void
    {
        $this->estado->assertTransitionTo($nuevoEstado);

        $this->estado = $nuevoEstado;
        $this->updatedAt = new DateTimeImmutable();
        $this->historial[] = HistorialEstado::registrar($nuevoEstado, $usuarioId, $comentario);
    }

    private function assertDetalleCoincideConTipoServicio(?TipoServicio $tipo, ?DetalleServicioInterface $detalle): void
    {
        if ($tipo === null) {
            if ($detalle !== null) {
                throw new ValidationException('No se puede proporcionar detalle de servicio sin un tipo de servicio.');
            }

            return;
        }

        if ($detalle === null) {
            throw new ValidationException("Debe proporcionar el detalle de servicio correspondiente al tipo '{$tipo->value}'.");
        }

        $esperado = match ($tipo) {
            TipoServicio::AEREO => DetalleAereo::class,
            TipoServicio::MARITIMO => DetalleMaritimo::class,
            TipoServicio::FREIGHT => DetalleFreight::class,
        };

        if (!($detalle instanceof $esperado)) {
            throw new ValidationException(
                "El detalle de servicio no corresponde al tipo '{$tipo->value}'."
            );
        }
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function trackingNumero(): TrackingNumero
    {
        return $this->trackingNumero;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function proveedor(): string
    {
        return $this->proveedor;
    }

    public function proveedorIdentificador(): ?string
    {
        return $this->proveedorIdentificador;
    }

    public function aduanero(): ?Aduanero
    {
        return $this->aduanero;
    }

    public function numeroContenedor(): ?Contenedor
    {
        return $this->numeroContenedor;
    }

    public function notas(): ?string
    {
        return $this->notas;
    }

    public function facturaProveedorNombre(): ?string
    {
        return $this->facturaProveedorNombre;
    }

    public function facturaProveedorRuta(): ?string
    {
        return $this->facturaProveedorRuta;
    }

    public function tipoServicio(): ?TipoServicio
    {
        return $this->tipoServicio;
    }

    public function detalle(): ?DetalleServicioInterface
    {
        return $this->detalle;
    }

    public function estado(): CargaStatus
    {
        return $this->estado;
    }

    public function registradoPorUsuarioId(): string
    {
        return $this->registradoPorUsuarioId;
    }

    public function fechaIngreso(): DateTimeImmutable
    {
        return $this->fechaIngreso;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return array<int, HistorialEstado> */
    public function historial(): array
    {
        return $this->historial;
    }

    /** @return array<int, LineaCarga> */
    public function lineas(): array
    {
        return $this->lineas;
    }
}
