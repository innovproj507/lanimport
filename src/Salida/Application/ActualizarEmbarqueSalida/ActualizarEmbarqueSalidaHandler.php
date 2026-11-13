<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ActualizarEmbarqueSalida;

use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Domain\ValueObject\DatosEmbarque;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Carga\Domain\BitacoraCargaInterface;

/**
 * Guarda o corrige los datos de embarque (contenedor, sello, naviera, barco, fechas, destino).
 * Si la orden de salida aun no tiene DO (la armo inventario), trafico la ordena aqui y se genera el DO.
 */
final class ActualizarEmbarqueSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly NavieraRepositoryInterface $navieras,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarEmbarqueSalidaCommand);

        $orden = $this->ordenes->findById($command->ordenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException('Orden de salida no encontrada.');
        }

        $navieraNombre = null;

        if ($command->navieraId !== null && $command->navieraId !== '') {
            $naviera = $this->navieras->findById($command->navieraId);

            // Una naviera desactivada solo se acepta si la orden ya la tenia.
            if ($naviera === null || (!$naviera->activo() && $orden->embarque()->navieraId !== $naviera->id())) {
                throw new ValidationException('Seleccione una naviera valida.');
            }

            $navieraNombre = $naviera->nombre();
        }

        $etiquetaAntes = $orden->etiquetaDo();
        $contenedorAntes = $orden->embarque()->contenedorFormateado();
        $numeroDo = preg_replace('/\D/', '', (string) $command->numeroDo);
        // Orden de salida de inventario sin DO: se genera abajo, dentro de la transaccion.
        $generarDo = !$orden->tieneDo();

        if (!$generarDo && $numeroDo !== '' && (int) $numeroDo !== $orden->doNumero()) {
            if ($orden->doAnio() !== null && $this->ordenes->existeNumeroDo($orden->doAnio(), (int) $numeroDo, $orden->id())) {
                throw new ValidationException("Ya existe el DO.{$numeroDo} / {$orden->doAnio()}. Escriba otro numero.");
            }

            $orden->cambiarNumeroDo((int) $numeroDo);
        }

        $orden->actualizarEmbarque(DatosEmbarque::desdeArreglo([
            'numeroContenedor' => $command->numeroContenedor,
            'numeroSello' => $command->numeroSello,
            'navieraId' => $command->navieraId,
            'navieraNombre' => $navieraNombre,
            'nombreBarco' => $command->nombreBarco,
            'fechaCargue' => $command->fechaCargue,
            'fechaSalidaEtd' => $command->fechaSalidaEtd,
            'destinoFinal' => $command->destinoFinal,
        ]));

        $this->unitOfWork->run(function () use ($orden, $generarDo, $numeroDo): void {
            if ($generarDo) {
                $anio = (int) date('Y');

                if ($numeroDo !== '') {
                    if ($this->ordenes->existeNumeroDo($anio, (int) $numeroDo, $orden->id())) {
                        throw new ValidationException("Ya existe el DO.{$numeroDo} / {$anio}. Escriba otro numero o dejelo vacio para tomar el siguiente.");
                    }

                    $orden->asignarDo($anio, (int) $numeroDo);
                } else {
                    $orden->asignarDo($anio, $this->ordenes->siguienteNumeroDo($anio));
                }
            }

            $this->ordenes->save($orden);
        });

        $lpnIds = array_map(static fn ($l) => $l->lpnId(), $orden->lineas());
        $contenedor = $orden->embarque()->contenedorFormateado();

        if ($etiquetaAntes !== $orden->etiquetaDo()) {
            $this->bitacora?->registrarPorLpns($lpnIds, 'do_generado', ($generarDo ? "Se genero el DO: {$etiquetaAntes} paso a ser {$orden->etiquetaDo()}" : "Numero de DO cambiado: {$etiquetaAntes} ahora es {$orden->etiquetaDo()}") . ' ({n} bulto(s))', $orden->id());
        }

        if ($contenedor !== $contenedorAntes && $contenedor !== null) {
            $this->bitacora?->registrarPorLpns($lpnIds, 'contenedor', "{$orden->etiquetaDo()}: contenedor {$contenedor}" . ($contenedorAntes !== null ? " (antes {$contenedorAntes})" : '') . ' ({n} bulto(s))', $orden->id());
        }

        return null;
    }
}
