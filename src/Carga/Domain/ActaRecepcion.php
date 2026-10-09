<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Auth\Domain\Rol;
use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Acta de recepcion fisica de mercancia: equivalente digital del formulario
 * en papel que llena bodega cuando la carga llega (chofer, placa, bultos,
 * quien entrega y quien verifica). Puede registrarse antes que la carga
 * (queda "sin carga") y operaciones la vincula despues al hacer el ingreso.
 */
final class ActaRecepcion extends AggregateRoot
{
    public const TIPOS_MERCANCIA = ['cajas', 'saco', 'bultos'];

    /** Roles que pueden llenar el acta por primera vez. */
    private const ROLES_REGISTRAR = [Rol::RECEPCION, Rol::BODEGA, Rol::ADMIN];

    /** Roles que pueden corregir un acta ya guardada. */
    private const ROLES_CORREGIR = [Rol::RECEPCION, Rol::BODEGA, Rol::GERENTE, Rol::ADMIN];

    /** @param array<int, string> $tiposMercancia */
    public function __construct(
        private readonly Uuid $id,
        private readonly ?int $numero,
        private ?string $cargaId,
        private string $clienteId,
        private ?string $marca,
        private ?string $empresaTransporte,
        private ?string $choferNombre,
        private ?string $placa,
        private int $cantidadBultos,
        private int $cantidadRollos,
        private int $totalRecibido,
        private array $tiposMercancia,
        private ?string $descripcionMercancia,
        private string $entregadoPor,
        private string $entregadoPorCedula,
        private readonly string $verificadoPorUsuarioId,
        private readonly DateTimeImmutable $fechaRecepcion,
        private ?string $actualizadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function puedeRegistrar(?Rol $rol): bool
    {
        return $rol !== null && in_array($rol, self::ROLES_REGISTRAR, true);
    }

    public static function puedeCorregir(?Rol $rol): bool
    {
        return $rol !== null && in_array($rol, self::ROLES_CORREGIR, true);
    }

    /** @param array<int, string> $tiposMercancia */
    public static function registrar(
        Uuid $id,
        ?string $cargaId,
        string $clienteId,
        ?string $marca,
        ?string $empresaTransporte,
        ?string $choferNombre,
        ?string $placa,
        int $cantidadBultos,
        int $cantidadRollos,
        int $totalRecibido,
        array $tiposMercancia,
        ?string $descripcionMercancia,
        string $entregadoPor,
        string $entregadoPorCedula,
        string $verificadoPorUsuarioId,
    ): self {
        self::validar($cantidadBultos, $cantidadRollos, $totalRecibido, $tiposMercancia, $entregadoPor, $entregadoPorCedula);
        $ahora = new DateTimeImmutable();

        return new self(
            $id,
            null,
            $cargaId,
            $clienteId,
            $marca,
            $empresaTransporte,
            $choferNombre,
            $placa,
            $cantidadBultos,
            $cantidadRollos,
            $totalRecibido,
            array_values(array_unique($tiposMercancia)),
            $descripcionMercancia,
            $entregadoPor,
            $entregadoPorCedula,
            $verificadoPorUsuarioId,
            $ahora,
            null,
            $ahora,
            $ahora,
        );
    }

    /** @param array<int, string> $tiposMercancia */
    public function corregir(
        string $clienteId,
        ?string $marca,
        ?string $empresaTransporte,
        ?string $choferNombre,
        ?string $placa,
        int $cantidadBultos,
        int $cantidadRollos,
        int $totalRecibido,
        array $tiposMercancia,
        ?string $descripcionMercancia,
        string $entregadoPor,
        string $entregadoPorCedula,
        string $usuarioId,
    ): void {
        self::validar($cantidadBultos, $cantidadRollos, $totalRecibido, $tiposMercancia, $entregadoPor, $entregadoPorCedula);

        if ($this->cargaId !== null && $clienteId !== $this->clienteId) {
            throw new ValidationException('No se puede cambiar el cliente de un acta ya vinculada a una carga.');
        }

        $this->clienteId = $clienteId;
        $this->marca = $marca;
        $this->empresaTransporte = $empresaTransporte;
        $this->choferNombre = $choferNombre;
        $this->placa = $placa;
        $this->cantidadBultos = $cantidadBultos;
        $this->cantidadRollos = $cantidadRollos;
        $this->totalRecibido = $totalRecibido;
        $this->tiposMercancia = array_values(array_unique($tiposMercancia));
        $this->descripcionMercancia = $descripcionMercancia;
        $this->entregadoPor = $entregadoPor;
        $this->entregadoPorCedula = $entregadoPorCedula;
        $this->actualizadoPorUsuarioId = $usuarioId;
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @param array<int, string> $tiposMercancia */
    private static function validar(
        int $cantidadBultos,
        int $cantidadRollos,
        int $totalRecibido,
        array $tiposMercancia,
        string $entregadoPor,
        string $entregadoPorCedula,
    ): void {
        if ($cantidadBultos < 0 || $cantidadRollos < 0) {
            throw new ValidationException('Las cantidades de bultos y rollos no pueden ser negativas.');
        }

        if ($totalRecibido < 1) {
            throw new ValidationException('El total recibido debe ser al menos 1.');
        }

        foreach ($tiposMercancia as $tipo) {
            if (!in_array($tipo, self::TIPOS_MERCANCIA, true)) {
                throw new ValidationException("Tipo de mercancia invalido: {$tipo}.");
            }
        }

        if (trim($entregadoPor) === '') {
            throw new ValidationException('Indique el nombre de quien entrega la carga.');
        }

        if (trim($entregadoPorCedula) === '') {
            throw new ValidationException('Indique la cedula de quien entrega la carga.');
        }
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    /** Asocia el acta (registrada antes que la carga) a la carga que operaciones registro despues. */
    public function vincularCarga(string $cargaId, string $clienteIdDeLaCarga): void
    {
        if ($this->cargaId !== null) {
            throw new ValidationException("El acta {$this->codigo()} ya esta vinculada a otra carga.");
        }

        if ($clienteIdDeLaCarga !== $this->clienteId) {
            throw new ValidationException("El acta {$this->codigo()} es de otro cliente.");
        }

        $this->cargaId = $cargaId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function numero(): ?int
    {
        return $this->numero;
    }

    /** Numero visible del acta, p.ej. REC-000012 (solo existe una vez guardada). */
    public function codigo(): string
    {
        return $this->numero !== null ? 'REC-' . str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT) : 'REC-(nueva)';
    }

    public function cargaId(): ?string
    {
        return $this->cargaId;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function marca(): ?string
    {
        return $this->marca;
    }

    public function empresaTransporte(): ?string
    {
        return $this->empresaTransporte;
    }

    public function choferNombre(): ?string
    {
        return $this->choferNombre;
    }

    public function placa(): ?string
    {
        return $this->placa;
    }

    public function cantidadBultos(): int
    {
        return $this->cantidadBultos;
    }

    public function cantidadRollos(): int
    {
        return $this->cantidadRollos;
    }

    public function totalRecibido(): int
    {
        return $this->totalRecibido;
    }

    /** @return array<int, string> */
    public function tiposMercancia(): array
    {
        return $this->tiposMercancia;
    }

    public function descripcionMercancia(): ?string
    {
        return $this->descripcionMercancia;
    }

    public function entregadoPor(): string
    {
        return $this->entregadoPor;
    }

    public function entregadoPorCedula(): string
    {
        return $this->entregadoPorCedula;
    }

    public function verificadoPorUsuarioId(): string
    {
        return $this->verificadoPorUsuarioId;
    }

    public function fechaRecepcion(): DateTimeImmutable
    {
        return $this->fechaRecepcion;
    }

    public function actualizadoPorUsuarioId(): ?string
    {
        return $this->actualizadoPorUsuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
