<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

use Courier\Salida\Domain\ValueObject\DatosEmbarque;
use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class SalidaOrden extends AggregateRoot
{
    /** @param array<int, SalidaLinea> $lineas */
    public function __construct(
        private readonly Uuid $id,
        // Solo si todas las cargas son de un mismo cliente; null en una salida consolidada.
        private ?string $clienteId,
        private readonly ?string $aduaneroIdentificador,
        private EstadoSalidaOrden $estado,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $confirmedAt,
        private array $lineas,
        private ?string $codigo = null,
        private ?int $secuencia = null,
        // Consecutivo DO por ano: lo asigna el repositorio al insertar.
        private ?int $doAnio = null,
        private ?int $doNumero = null,
        private ?DatosEmbarque $embarque = null,
    ) {
    }

    /** Contenedor, sello, naviera, barco, fechas y destino; se puede completar o corregir mientras no este cancelada. */
    public function actualizarEmbarque(DatosEmbarque $embarque): void
    {
        if ($this->estado === EstadoSalidaOrden::CANCELADA) {
            throw new ValidationException('No se pueden modificar los datos de embarque de una salida cancelada.');
        }

        $this->embarque = $embarque;
    }

    public function embarque(): DatosEmbarque
    {
        return $this->embarque ?? DatosEmbarque::vacio();
    }

    public function doAnio(): ?int
    {
        return $this->doAnio;
    }

    public function doNumero(): ?int
    {
        return $this->doNumero;
    }

    /** "DO.193" (sin ano), como en los documentos. */
    public function numeroDo(): string
    {
        return $this->doNumero !== null ? 'DO.' . $this->doNumero : (string) $this->codigo;
    }

    /** "DO.193 / 2026": el consecutivo reinicia cada ano, el ano evita confusiones. */
    public function etiquetaDo(): string
    {
        return $this->doNumero !== null ? "DO.{$this->doNumero} / {$this->doAnio}" : (string) $this->codigo;
    }

    /**
     * Datos del despacho que se le informan al cliente (correo y WhatsApp), en orden.
     *
     * @return array<string, string|null> etiqueta => valor (null si no se cargo)
     */
    public function datosParaCliente(): array
    {
        $embarque = $this->embarque();

        return [
            'Documento de salida' => $this->etiquetaDo(),
            'Contenedor' => $embarque->contenedorFormateado(),
            'Naviera' => $embarque->navieraNombre,
            'Barco' => $embarque->nombreBarco,
            'Fecha estimada de salida (ETD)' => $embarque->fechaSalidaEtd?->format('d/m/Y'),
            'Destino' => $embarque->destinoFinal,
        ];
    }

    /** @param array<int, SalidaLinea> $lineas */
    public static function crear(
        Uuid $id,
        ?string $clienteId,
        ?string $aduaneroIdentificador,
        array $lineas,
        string $usuarioId,
    ): self {
        if ($lineas === []) {
            throw new ValidationException('La orden de salida debe tener al menos un LPN.');
        }

        return new self(
            $id,
            $clienteId,
            $aduaneroIdentificador,
            EstadoSalidaOrden::PENDIENTE,
            $usuarioId,
            new DateTimeImmutable(),
            null,
            $lineas,
        );
    }

    /**
     * Suben mas cargas al mismo contenedor: solo mientras la salida esta pendiente (bodega
     * aun no la finalizo). Si las nuevas son de otro cliente, la salida pasa a consolidada.
     *
     * @param array<int, SalidaLinea> $lineas
     * @param array<int, string> $clienteIds clientes de los LPNs que se agregan
     */
    public function agregarLineas(array $lineas, array $clienteIds): void
    {
        if ($this->estado !== EstadoSalidaOrden::PENDIENTE) {
            throw new ValidationException("La salida {$this->etiquetaDo()} esta " . mb_strtolower($this->estado->label()) . ": ya no se le pueden agregar cargas.");
        }

        if ($lineas === []) {
            throw new ValidationException('Seleccione al menos una carga con bultos listos para agregar.');
        }

        $existentes = array_map(static fn (SalidaLinea $l) => $l->lpnId(), $this->lineas);

        foreach ($lineas as $linea) {
            if (in_array($linea->lpnId(), $existentes, true)) {
                throw new ValidationException("El LPN {$linea->codigoLpn()} ya esta en esta salida.");
            }

            $this->lineas[] = $linea;
            $existentes[] = $linea->lpnId();
        }

        if ($this->clienteId !== null && array_diff(array_unique($clienteIds), [$this->clienteId]) !== []) {
            $this->clienteId = null;
        }
    }

    public function registrarEscaneo(string $codigoLpn): void
    {
        foreach ($this->lineas as $linea) {
            if ($linea->codigoLpn() === $codigoLpn) {
                if (!$linea->escaneado()) {
                    $linea->marcarEscaneado();
                }

                return;
            }
        }

        throw new ValidationException("El LPN {$codigoLpn} no pertenece a esta orden de salida.");
    }

    public function confirmar(string $usuarioId): void
    {
        $pendientes = array_values(array_filter(
            $this->lineas,
            fn (SalidaLinea $linea) => !$linea->escaneado(),
        ));

        if ($pendientes !== []) {
            $codigos = implode(', ', array_map(fn (SalidaLinea $linea) => $linea->codigoLpn(), $pendientes));

            throw new ValidationException("Quedan LPNs sin escanear: {$codigos}.");
        }

        $this->estado->assertTransitionTo(EstadoSalidaOrden::CONFIRMADA);
        $this->estado = EstadoSalidaOrden::CONFIRMADA;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function cancelar(): void
    {
        $this->estado->assertTransitionTo(EstadoSalidaOrden::CANCELADA);
        $this->estado = EstadoSalidaOrden::CANCELADA;
    }

    /** @return array{escaneadas: int, total: int} */
    public function progreso(): array
    {
        $total = count($this->lineas);
        $escaneadas = count(array_filter($this->lineas, fn (SalidaLinea $linea) => $linea->escaneado()));

        return ['escaneadas' => $escaneadas, 'total' => $total];
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function clienteId(): ?string
    {
        return $this->clienteId;
    }

    public function aduaneroIdentificador(): ?string
    {
        return $this->aduaneroIdentificador;
    }

    public function estado(): EstadoSalidaOrden
    {
        return $this->estado;
    }

    public function creadoPorUsuarioId(): string
    {
        return $this->creadoPorUsuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /** @return array<int, SalidaLinea> */
    public function lineas(): array
    {
        return $this->lineas;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function secuencia(): ?int
    {
        return $this->secuencia;
    }
}
