<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerFlujoCargas;

use Courier\Carga\Domain\CargaStatus;
use Courier\Reporte\Infrastructure\Persistence\PdoFlujoCargasRepository;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;
use DateTimeImmutable;

/**
 * Reparte las cargas en las 4 etapas del recorrido, para gerencia:
 *  - llegando:  registrada, con bultos por escanear en recepcion (o aun sin etiquetas);
 *  - deposito:  recibida, en bodega y en ninguna salida pendiente;
 *  - salida:    algun bulto en una salida (DO) pendiente, armando el contenedor;
 *  - salieron:  despachada (en transito o despues), con fecha de salida dentro del periodo.
 * Cada carga esta en una sola etapa. Las despachadas en parte llevan "parcial" con los bultos ya despachados.
 */
final class ObtenerFlujoCargasHandler implements QueryHandlerInterface
{
    public const ETAPAS = [
        'llegando' => 'Llegando',
        'deposito' => 'En deposito',
        'salida' => 'En salida',
        'salieron' => 'Salieron',
    ];

    public function __construct(private readonly PdoFlujoCargasRepository $repository)
    {
    }

    /**
     * @return array{etapas: array<string, array{cargas: int, bultos: float, peso: float, p3: float, filas: array<int, array<string, mixed>>}>}
     */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ObtenerFlujoCargasQuery);

        $despachadas = array_map(static fn (CargaStatus $e) => $e->value, CargaStatus::despachadas());
        $salidas = $this->repository->salidasPorCarga();
        $ahora = new DateTimeImmutable();
        $etapas = array_fill_keys(array_keys(self::ETAPAS), []);

        foreach ($this->repository->cargas($query->aduanero, $query->clienteId) as $c) {
            $id = (string) $c['id'];
            $dos = $salidas[$id] ?? [];
            $pendientes = array_values(array_filter($dos, static fn (array $d) => $d['estado'] === 'pendiente'));
            $confirmadas = array_values(array_filter($dos, static fn (array $d) => $d['estado'] === 'confirmada'));
            $despachados = (int) array_sum(array_column($confirmadas, 'bultos'));

            $fila = [
                'id' => $id,
                'tracking' => (string) $c['tracking_numero'],
                'cliente' => trim((string) $c['cliente']) !== '' ? trim((string) $c['cliente']) : '—',
                'aduanero' => (string) $c['aduanero'] !== '' ? (string) $c['aduanero'] : '—',
                'bultos' => (float) $c['bultos'],
                'peso' => (float) $c['peso'],
                'p3' => (float) $c['p3'],
                'fechaIngreso' => $c['fecha_ingreso'] !== null ? new DateTimeImmutable((string) $c['fecha_ingreso']) : null,
                'etiquetas' => (int) $c['etiquetas'],
                'porEscanear' => (int) $c['por_escanear'],
                'despachados' => $despachados,
            ];

            if (in_array($c['estado'], $despachadas, true)) {
                // Fecha de salida: la ultima DO confirmada con sus bultos; si no hay, cuando paso a en transito.
                $fechas = array_filter(array_column($confirmadas, 'confirmed_at'));
                $fecha = $fechas !== [] ? max($fechas) : ($c['en_transito_at'] ?? $c['updated_at']);
                $fechaSalida = new DateTimeImmutable((string) $fecha);

                if ($fechaSalida->format('Y-m-d') < $query->desde || $fechaSalida->format('Y-m-d') > $query->hasta) {
                    continue;
                }

                $ultima = $confirmadas !== [] ? $confirmadas[count($confirmadas) - 1] : null;
                $etapas['salieron'][] = $fila + [
                    'fechaSalida' => $fechaSalida,
                    'dos' => array_map([$this, 'resumenDo'], $confirmadas),
                    'naviera' => $ultima['naviera'] ?? null,
                ];
                continue;
            }

            if ($pendientes !== []) {
                $etapas['salida'][] = $fila + [
                    'dos' => array_map([$this, 'resumenDo'], $pendientes),
                    'enDo' => (int) array_sum(array_column($pendientes, 'bultos')),
                    'escaneadosDo' => (int) array_sum(array_column($pendientes, 'escaneados')),
                ];
                continue;
            }

            if ($c['estado'] === CargaStatus::INGRESADO->value) {
                $registrada = new DateTimeImmutable((string) $c['created_at']);
                $etapas['llegando'][] = $fila + [
                    'horas' => (int) floor(($ahora->getTimestamp() - $registrada->getTimestamp()) / 3600),
                ];
                continue;
            }

            $desde = new DateTimeImmutable((string) ($c['en_bodega_at'] ?? $c['fecha_ingreso'] ?? $c['created_at']));
            $etapas['deposito'][] = $fila + [
                'dias' => (int) $desde->diff($ahora)->days,
                'estado' => (string) $c['estado'],
            ];
        }

        usort($etapas['llegando'], static fn (array $a, array $b) => $b['horas'] <=> $a['horas']);
        usort($etapas['deposito'], static fn (array $a, array $b) => $b['dias'] <=> $a['dias']);
        usort($etapas['salida'], static fn (array $a, array $b) => [$a['dos'][0]['orden'], $a['tracking']] <=> [$b['dos'][0]['orden'], $b['tracking']]);
        usort($etapas['salieron'], static fn (array $a, array $b) => $b['fechaSalida'] <=> $a['fechaSalida']);

        $resultado = [];

        foreach ($etapas as $clave => $filas) {
            $resultado[$clave] = [
                'cargas' => count($filas),
                'bultos' => (float) array_sum(array_column($filas, 'bultos')),
                'peso' => (float) array_sum(array_column($filas, 'peso')),
                'p3' => (float) array_sum(array_column($filas, 'p3')),
                'filas' => $filas,
            ];
        }

        return ['etapas' => $resultado];
    }

    /** @param array<string, mixed> $d */
    private function resumenDo(array $d): array
    {
        $contenedor = (string) ($d['numero_contenedor'] ?? '');

        return [
            'id' => (string) $d['id'],
            'etiqueta' => $d['do_numero'] !== null ? "DO.{$d['do_numero']} / {$d['do_anio']}" : (string) $d['codigo'],
            'orden' => (int) $d['do_anio'] * 1000000 + (int) $d['do_numero'],
            'contenedor' => $contenedor !== '' ? substr($contenedor, 0, -1) . '-' . substr($contenedor, -1) : null,
            'bultos' => (int) $d['bultos'],
            'escaneados' => (int) $d['escaneados'],
        ];
    }
}
