<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\GenerarIngresoReempaque;

use Courier\Carga\Application\RegistrarCarga\RegistrarCargaCommand;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\TipoEnvio;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaCommand;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaHandler;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\EstadoReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Nuevo ingreso de un reempaque terminado: las cajas armadas pasan a una carga nueva (tracking
 * nuevo) con sus propias medidas (W, H, L, P3, peso), una linea por caja, p.ej. para aereo.
 *
 *  - La carga nueva es del cliente de las cajas, con los proveedores y el aduanero de las originales.
 *  - Se generan sus etiquetas (una por caja): se imprimen, se pegan y bodega las escanea en
 *    Recepcion como cualquier ingreso. Cada caja queda enlazada a su bulto nuevo (genealogia).
 *  - Las cargas originales cuyos bultos quedaron todos en las cajas pasan a "Reempacada": sus
 *    kilos y bultos ya no se cuentan aparte (van en la carga nueva).
 */
final class GenerarIngresoReempaqueHandler
{
    private const PIE3_CM = 28_316.846592;

    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly CargaRepositoryInterface $cargas,
        private readonly RegistrarCargaHandler $registrarCarga,
        private readonly GenerarLpnsParaCargaHandler $generarLpns,
        private readonly PdoHistorialEstadoRepository $historial,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return array{cargaId: string, tracking: string, reempacadas: array<int, string>} */
    public function handle(GenerarIngresoReempaqueCommand $command): array
    {
        $orden = $this->ordenes->findById($command->ordenId) ?? throw new NotFoundException('Orden de reempaque no encontrada.');

        if ($orden->estado() !== EstadoReempaqueOrden::COMPLETADO) {
            throw new ValidationException('El reempaque debe estar terminado (todas las cajas cerradas) para generar el nuevo ingreso.');
        }

        if ($this->ordenes->cargaGenerada($orden->id()) !== null) {
            throw new ValidationException('Este reempaque ya tiene su nuevo ingreso.');
        }

        $tipoEnvio = TipoEnvio::desdeFormulario($command->tipoEnvio);
        $descripcion = trim($command->descripcion);
        if ($descripcion === '') {
            throw new ValidationException('Escriba la descripcion de la mercancia.');
        }
        if ($command->valorTotal < 0) {
            throw new ValidationException('El valor no puede ser negativo.');
        }

        // Cajas: todas deben estar disponibles (no en una OS) y con sus medidas.
        $cajas = [];
        foreach ($orden->destinoLpnIds() as $i => $cajaId) {
            $caja = $this->lpns->findById($cajaId) ?? throw new ValidationException('Una caja del reempaque ya no existe.');

            if ($caja->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("La caja {$caja->codigo()} esta " . mb_strtolower($caja->estado()->label()) . ': sáquela de la orden de salida antes de generar el nuevo ingreso.');
            }

            $m = $command->medidas[$cajaId] ?? null;
            if ($m === null || $m['ancho'] <= 0 || $m['alto'] <= 0 || $m['largo'] <= 0 || $m['peso'] <= 0) {
                throw new ValidationException("Escriba ancho, alto, largo y peso de la caja {$caja->codigo()}.");
            }

            $cajas[] = ['lpn' => $caja, 'medidas' => $m];
        }

        if ($cajas === []) {
            throw new ValidationException('El reempaque no tiene cajas.');
        }

        // Cargas originales (de los bultos que entraron al reempaque).
        $originales = [];
        foreach ($orden->origenLpnIds() as $origenId) {
            $cargaId = $this->lpns->findById($origenId)?->cargaId();
            if ($cargaId !== null && !isset($originales[$cargaId])) {
                $originales[$cargaId] = $this->cargas->findById($cargaId);
            }
        }
        $originales = array_filter($originales);

        $proveedores = array_values(array_unique(array_filter(array_map(static fn ($c) => trim($c->proveedor()), $originales))));
        $aduanero = null;
        foreach ($originales as $c) {
            $aduanero ??= $c->aduanero();
        }
        $trackings = array_map(static fn ($c) => (string) $c->trackingNumero(), $originales);
        $primera = reset($originales) ?: null;

        $n = count($cajas);
        $valorCaja = round($command->valorTotal / $n, 2);
        $lineas = [];
        foreach ($cajas as $i => $c) {
            $m = $c['medidas'];
            $lineas[] = [
                'descripcion' => mb_substr($descripcion, 0, 190),
                'unidadMedida' => 'caja',
                'cantidad' => 1,
                'ancho' => (float) $m['ancho'],
                'alto' => (float) $m['alto'],
                'largo' => (float) $m['largo'],
                'peso' => (float) $m['peso'],
                // El ultimo se lleva el redondeo para que la suma sea el valor total.
                'valor' => $i === $n - 1 ? round($command->valorTotal - $valorCaja * ($n - 1), 2) : $valorCaja,
                'marca' => $c['lpn']->marca(),
                'cubicajePies' => $m['p3'] !== null && $m['p3'] > 0 ? (float) $m['p3'] : round($m['ancho'] * $m['alto'] * $m['largo'] / self::PIE3_CM, 4),
            ];
        }

        $notas = trim("Nuevo ingreso del reempaque {$orden->codigo()}: " . $n . ' caja(s) con los bultos de ' . implode(', ', $trackings) . '.'
            . ($command->notas !== null && trim($command->notas) !== '' ? "\n" . trim($command->notas) : ''));

        $registro = $this->registrarCarga->handle(new RegistrarCargaCommand(
            $orden->clienteCajas(),
            $proveedores !== [] ? mb_substr(implode(' / ', $proveedores), 0, 190) : 'REEMPAQUE ' . $orden->codigo(),
            count($proveedores) === 1 && $primera !== null ? $primera->proveedorIdentificador() : null,
            $aduanero?->identificador(),
            $aduanero?->nombre(),
            null,
            null,
            [],
            $command->usuarioId,
            date('Y-m-d'),
            $lineas,
            $notas,
            null,
            null,
            false, // aviso interno: no se le escribe al cliente por el reingreso
            null,
            $cajas[0]['lpn']->bodegaId(),
            $tipoEnvio->value,
        ));

        // Una etiqueta por caja (lineas en el mismo orden que las cajas).
        $this->generarLpns->handle(new GenerarLpnsParaCargaCommand($registro->cargaId, $command->usuarioId));
        $nuevos = array_values($this->lpns->findByCargaId($registro->cargaId));

        $cajaABulto = [];
        foreach ($cajas as $i => $c) {
            if (isset($nuevos[$i])) {
                $cajaABulto[$c['lpn']->id()] = $nuevos[$i]->id();
            }
        }

        $reempacadas = $this->unitOfWork->run(function () use ($cajas, $orden, $registro, $cajaABulto, $originales, $command): array {
            // Las cajas dejan de existir por si mismas: ahora son los bultos de la carga nueva.
            foreach ($cajas as $c) {
                $c['lpn']->consumirPorReempaque();
                $this->lpns->save($c['lpn']);
            }
            $this->ordenes->registrarIngresoGenerado($orden->id(), $registro->cargaId, $cajaABulto);

            // Originales que ya no tienen bultos propios en bodega: pasan a "Reempacada".
            $reempacadas = [];
            foreach ($originales as $carga) {
                $quedan = array_filter($this->lpns->findByCargaId($carga->id()), static fn ($l) => $l->estado() !== EstadoLpn::CONSUMED);
                if ($quedan !== [] || !$carga->estado()->canTransitionTo(\Courier\Carga\Domain\CargaStatus::REEMPACADA)) {
                    continue;
                }
                $antes = count($carga->historial());
                $carga->marcarReempacada($command->usuarioId, "Reempacada en el nuevo ingreso {$registro->trackingNumero} ({$orden->codigo()})");
                $this->cargas->save($carga);
                foreach (array_slice($carga->historial(), $antes) as $entrada) {
                    $this->historial->append($carga->id(), $entrada);
                }
                $reempacadas[] = (string) $carga->trackingNumero();
            }

            return $reempacadas;
        });

        $this->bitacora?->registrarPorLpns($orden->origenLpnIds(), 'reempaque', "{n} bulto(s) reempacados pasan al nuevo ingreso {$registro->trackingNumero} ({$orden->codigo()})");
        $this->bitacora?->registrar($registro->cargaId, 'reempaque', "Nuevo ingreso del reempaque {$orden->codigo()}: {$n} caja(s) con los bultos de " . implode(', ', $trackings));

        return ['cargaId' => $registro->cargaId, 'tracking' => $registro->trackingNumero, 'reempacadas' => $reempacadas];
    }
}
