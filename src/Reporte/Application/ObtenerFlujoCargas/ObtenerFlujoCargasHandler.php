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

        $porCliente = [];
        $porAduanero = [];
        $porContenedor = ['pendientes' => [], 'salieron' => []];
        // Alertas: antiguedad en bodega por cliente y OS que esperan DO.
        $antiguedad = [];
        $osSinDo = [];
        // Consulta de Reportes: una fila por carga y estado (bodega, en salida por OS/DO, salieron).
        $consulta = [];

        foreach ($this->repository->cargas($query->aduanero, $query->clienteId) as $c) {
            $id = (string) $c['id'];
            $dos = $salidas[$id] ?? [];
            $pendientes = array_values(array_filter($dos, static fn (array $d) => $d['estado'] === 'pendiente'));
            $confirmadas = array_values(array_filter($dos, static fn (array $d) => $d['estado'] === 'confirmada'));
            $despachados = (int) array_sum(array_column($confirmadas, 'bultos'));

            $this->acumularResumenes($c, $dos, $despachados, in_array($c['estado'], $despachadas, true), $query, $porCliente, $porAduanero, $porContenedor, $antiguedad, $osSinDo, $ahora, $consulta);

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

        // Mas bultos en salida primero; los contenedores por numero de DO (o el ultimo confirmado primero).
        $ordenar = static fn (array $a, array $b) => [$b['os'] + $b['do'], $b['bodega']] <=> [$a['os'] + $a['do'], $a['bodega']];
        $porCliente = array_values(array_map([$this, 'cerrarGrupo'], $porCliente));
        $porAduanero = array_values(array_map([$this, 'cerrarGrupo'], $porAduanero));
        usort($porCliente, $ordenar);
        usort($porAduanero, $ordenar);

        foreach ($porContenedor as $tipo => $dos) {
            $dos = array_values(array_map([$this, 'cerrarGrupo'], $dos));
            usort($dos, $tipo === 'salieron'
                ? static fn (array $a, array $b) => $b['confirmada'] <=> $a['confirmada']
                : static fn (array $a, array $b) => $a['orden'] <=> $b['orden']);
            $porContenedor[$tipo] = $dos;
        }

        // Antiguedad: mas bultos viejos primero (los de +60 dias pesan mas).
        $antiguedad = array_values(array_map([$this, 'cerrarGrupo'], $antiguedad));
        usort($antiguedad, static fn (array $a, array $b) => [$b['r60'], $b['r30'], $b['diasMax']] <=> [$a['r60'], $a['r30'], $a['diasMax']]);

        // OS sin DO: la mas antigua primero.
        $osSinDo = array_values(array_map([$this, 'cerrarGrupo'], $osSinDo));
        usort($osSinDo, static fn (array $a, array $b) => $a['creada'] <=> $b['creada']);

        // Contenedores con escaneo incompleto: los que cargan o salen antes, primero (sin fecha al final).
        $incompletos = array_values(array_filter($porContenedor['pendientes'], static fn (array $d) => $d['escaneados'] < $d['bultos']));
        usort($incompletos, static fn (array $a, array $b) => [$a['fechaLimite'] === null, $a['fechaLimite']] <=> [$b['fechaLimite'] === null, $b['fechaLimite']]);

        return [
            'etapas' => $resultado,
            'resumen' => ['cliente' => $porCliente, 'aduanero' => $porAduanero, 'contenedor' => $porContenedor],
            'alertas' => ['antiguedad' => $antiguedad, 'osSinDo' => $osSinDo, 'incompletos' => $incompletos],
            'consulta' => $consulta,
        ];
    }

    /**
     * Suma una carga en los resumenes por cliente, aduanero y contenedor (DO). Los kg, p3 y valor de
     * lo que esta en salida se prorratean por los bultos de la carga que van en cada OS/DO.
     *
     * @param array<string, mixed> $c fila de la carga
     * @param array<int, array<string, mixed>> $dos sus salidas (OS y DO, pendientes o confirmadas)
     * @param array<string, array<string, mixed>> $porCliente
     * @param array<string, array<string, mixed>> $porAduanero
     * @param array{pendientes: array<string, array<string, mixed>>, salieron: array<string, array<string, mixed>>} $porContenedor
     * @param array<string, array<string, mixed>> $antiguedad bultos en bodega por cliente y rango de dias
     * @param array<string, array<string, mixed>> $osSinDo OS pendientes que aun no tienen DO
     * @param array<int, array<string, mixed>> $consulta filas carga + estado para la consulta de Reportes
     */
    private function acumularResumenes(array $c, array $dos, int $despachados, bool $despachada, ObtenerFlujoCargasQuery $query, array &$porCliente, array &$porAduanero, array &$porContenedor, array &$antiguedad, array &$osSinDo, DateTimeImmutable $ahora, array &$consulta): void
    {
        $cliente = trim((string) $c['cliente']) !== '' ? trim((string) $c['cliente']) : '—';
        $aduanero = (string) $c['aduanero'] !== '' ? (string) $c['aduanero'] : '—';
        $base = max(1, (int) $c['etiquetas'] > 0 ? (int) $c['etiquetas'] : (int) $c['bultos']);
        $prorrateo = static fn (int $bultos): array => [
            'kg' => (float) $c['peso'] * $bultos / $base,
            'p3' => (float) $c['p3'] * $bultos / $base,
            'valor' => (float) $c['valor'] * $bultos / $base,
        ];

        $suma = ['bodega' => 0, 'os' => 0, 'do' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0, 'salieron' => 0, 'dos' => []];
        $enOrdenes = 0;

        foreach ($dos as $d) {
            $bultos = (int) $d['bultos'];
            $p = $prorrateo($bultos);
            $etiqueta = $d['do_numero'] !== null ? "DO.{$d['do_numero']} / {$d['do_anio']}" : (string) $d['codigo'];

            if ($d['estado'] === 'pendiente') {
                $enOrdenes += $bultos;
                $suma[$d['do_numero'] === null ? 'os' : 'do'] += $bultos;
                $suma['kg'] += $p['kg'];
                $suma['p3'] += $p['p3'];
                $suma['valor'] += $p['valor'];
                $suma['dos'][$etiqueta] = (string) $d['id'];
            } elseif ($d['estado'] === 'confirmada') {
                $fecha = $d['confirmed_at'] !== null ? substr((string) $d['confirmed_at'], 0, 10) : null;

                if ($fecha === null || $fecha < $query->desde || $fecha > $query->hasta) {
                    continue;
                }

                $suma['salieron'] += $bultos;
            } else {
                continue;
            }

            $contenedorFila = (string) ($d['numero_contenedor'] ?? '');
            $consulta[] = [
                'cargaId' => (string) $c['id'],
                'tracking' => (string) $c['tracking_numero'],
                'cliente' => $cliente,
                'aduanero' => $aduanero,
                'estado' => $d['estado'] === 'pendiente' ? 'salida' : 'salieron',
                'ordenId' => (string) $d['id'],
                'orden' => $etiqueta,
                'esOs' => $d['do_numero'] === null,
                'contenedor' => $contenedorFila !== '' ? substr($contenedorFila, 0, -1) . '-' . substr($contenedorFila, -1) : null,
                'naviera' => $d['naviera'] ?? null,
                'bultos' => $bultos,
                'escaneados' => (int) $d['escaneados'],
                'kg' => $p['kg'],
                'p3' => $p['p3'],
                'valor' => $p['valor'],
                'ingreso' => $c['fecha_ingreso'] !== null ? substr((string) $c['fecha_ingreso'], 0, 10) : null,
                // En salida: cuando se armo la orden; salieron: cuando se confirmo.
                'fecha' => substr((string) ($d['estado'] === 'pendiente' ? $d['created_at'] : $d['confirmed_at']), 0, 10),
                'dias' => null,
            ];

            // Por contenedor: solo los que ya tienen DO (una OS aun no tiene contenedor).
            if ($d['do_numero'] === null) {
                if ($d['estado'] === 'pendiente') {
                    $os = &$osSinDo[(string) $d['id']];
                    $os ??= [
                        'id' => (string) $d['id'],
                        'etiqueta' => $etiqueta,
                        'creada' => new DateTimeImmutable((string) $d['created_at']),
                        'cargas' => [], 'clientes' => [], 'aduaneros' => [],
                        'bultos' => 0, 'escaneados' => 0, 'kg' => 0.0, 'p3' => 0.0,
                    ];
                    $os['dias'] = (int) $os['creada']->diff($ahora)->days;
                    $os['horas'] = (int) floor(($ahora->getTimestamp() - $os['creada']->getTimestamp()) / 3600);
                    $os['cargas'][$c['id']] = true;
                    $os['clientes'][$cliente] = true;
                    $os['aduaneros'][$aduanero] = true;
                    $os['bultos'] += $bultos;
                    $os['escaneados'] += (int) $d['escaneados'];
                    $os['kg'] += $p['kg'];
                    $os['p3'] += $p['p3'];
                    unset($os);
                }

                continue;
            }

            $tipo = $d['estado'] === 'pendiente' ? 'pendientes' : 'salieron';
            $contenedor = (string) ($d['numero_contenedor'] ?? '');
            $grupo = &$porContenedor[$tipo][(string) $d['id']];
            $grupo ??= [
                'id' => (string) $d['id'],
                'etiqueta' => $etiqueta,
                'orden' => (int) $d['do_anio'] * 1000000 + (int) $d['do_numero'],
                'contenedor' => $contenedor !== '' ? substr($contenedor, 0, -1) . '-' . substr($contenedor, -1) : null,
                'sello' => $d['numero_sello'] ?? null,
                'naviera' => $d['naviera'] ?? null,
                'barco' => $d['nombre_barco'] ?? null,
                'etd' => $d['fecha_salida_etd'] !== null ? new DateTimeImmutable((string) $d['fecha_salida_etd']) : null,
                'cargue' => $d['fecha_cargue'] !== null ? new DateTimeImmutable((string) $d['fecha_cargue']) : null,
                // Para alertas: la fecha mas proxima entre cargue y ETD.
                'fechaLimite' => ($d['fecha_cargue'] ?? $d['fecha_salida_etd']) !== null ? substr((string) ($d['fecha_cargue'] ?? $d['fecha_salida_etd']), 0, 10) : null,
                'destino' => $d['destino_final'] ?? null,
                'creada' => new DateTimeImmutable((string) $d['created_at']),
                'confirmada' => $d['confirmed_at'] !== null ? new DateTimeImmutable((string) $d['confirmed_at']) : null,
                'cargas' => [], 'clientes' => [], 'aduaneros' => [],
                'bultos' => 0, 'escaneados' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0,
            ];
            $grupo['cargas'][$c['id']] = true;
            $grupo['clientes'][$cliente] = true;
            $grupo['aduaneros'][$aduanero] = true;
            $grupo['bultos'] += $bultos;
            $grupo['escaneados'] += (int) $d['escaneados'];
            $grupo['kg'] += $p['kg'];
            $grupo['p3'] += $p['p3'];
            $grupo['valor'] += $p['valor'];
            unset($grupo);
        }

        // En bodega: bultos de la carga que no estan en ninguna OS/DO ni salieron.
        if (!$despachada) {
            $suma['bodega'] = max(0, $base - $enOrdenes - $despachados);
        }

        if ($suma['bodega'] > 0) {
            $desde = new DateTimeImmutable((string) ($c['en_bodega_at'] ?? $c['fecha_ingreso'] ?? $c['created_at']));
            $dias = (int) $desde->diff($ahora)->days;
            $rango = $dias <= 15 ? 'r15' : ($dias <= 30 ? 'r30' : ($dias <= 60 ? 'r60m' : 'r60'));
            $pb = $prorrateo($suma['bodega']);
            $consulta[] = [
                'cargaId' => (string) $c['id'],
                'tracking' => (string) $c['tracking_numero'],
                'cliente' => $cliente,
                'aduanero' => $aduanero,
                'estado' => 'bodega',
                'ordenId' => null,
                'orden' => null,
                'esOs' => false,
                'contenedor' => null,
                'naviera' => null,
                'bultos' => $suma['bodega'],
                'escaneados' => null,
                'kg' => $pb['kg'],
                'p3' => $pb['p3'],
                'valor' => $pb['valor'],
                'ingreso' => $c['fecha_ingreso'] !== null ? substr((string) $c['fecha_ingreso'], 0, 10) : null,
                'fecha' => $desde->format('Y-m-d'),
                'dias' => $dias,
            ];
            $a = &$antiguedad[$cliente];
            $a ??= ['nombre' => $cliente, 'cargas' => [], 'r15' => 0, 'r30' => 0, 'r60m' => 0, 'r60' => 0, 'total' => 0, 'diasMax' => 0, 'masVieja' => null];
            $a['cargas'][$c['id']] = true;
            $a[$rango] += $suma['bodega'];
            $a['total'] += $suma['bodega'];

            if ($dias >= $a['diasMax']) {
                $a['diasMax'] = $dias;
                $a['masVieja'] = ['id' => (string) $c['id'], 'tracking' => (string) $c['tracking_numero']];
            }

            unset($a);
        }

        if ($suma['bodega'] + $suma['os'] + $suma['do'] + $suma['salieron'] === 0) {
            return;
        }

        foreach ([[&$porCliente, $cliente], [&$porAduanero, $aduanero]] as [&$destino, $nombre]) {
            $g = &$destino[$nombre];
            $g ??= ['nombre' => $nombre, 'cargas' => [], 'clientes' => [], 'bodega' => 0, 'os' => 0, 'do' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0, 'salieron' => 0, 'dos' => []];
            $g['cargas'][$c['id']] = true;
            $g['clientes'][$cliente] = true;

            foreach (['bodega', 'os', 'do', 'kg', 'p3', 'valor', 'salieron'] as $k) {
                $g[$k] += $suma[$k];
            }

            $g['dos'] += $suma['dos'];
            unset($g);
        }
        unset($destino);
    }

    /** Conjuntos (cargas, clientes, aduaneros) a conteos y listas, para la vista y el Excel. */
    private function cerrarGrupo(array $g): array
    {
        foreach (['cargas', 'clientes', 'aduaneros'] as $k) {
            if (isset($g[$k])) {
                $g[$k . 'Lista'] = array_keys($g[$k]);
                $g[$k] = count($g[$k]);
            }
        }

        if (isset($g['dos'])) {
            ksort($g['dos'], SORT_NATURAL);
        }

        return $g;
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
