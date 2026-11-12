<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosHandler;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosQuery;
use Courier\Carga\Infrastructure\Importacion\EscritorXlsx;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Reporte\Application\ObtenerFlujoCargas\ObtenerFlujoCargasHandler;
use Courier\Reporte\Application\ObtenerFlujoCargas\ObtenerFlujoCargasQuery;
use Courier\Reporte\Application\ObtenerReporteOperativo\ObtenerReporteOperativoHandler;
use Courier\Reporte\Application\ObtenerReporteOperativo\ObtenerReporteOperativoQuery;

final class ReporteController extends BaseController
{
    public function index(): string
    {
        [$desde, $hasta, $aduanero, $clienteId] = $this->filtros();

        $reporte = $this->container()->get(ObtenerReporteOperativoHandler::class)
            ->handle(new ObtenerReporteOperativoQuery($desde, $hasta));

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        usort($clientes, static fn ($a, $b) => strcasecmp($a->nombreCompleto(), $b->nombreCompleto()));

        $flujo = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId));
        [$estado, $ver] = $this->opcionesConsulta();

        return view('reporte/index', [
            'reporte' => $reporte,
            // Consulta: cargas filtradas por estado, cliente y aduanero, en detalle o con totales por grupo.
            'consulta' => $this->agruparConsulta($this->filtrarConsulta($flujo['consulta'], $estado), $ver),
            'estadoConsulta' => $estado,
            'verConsulta' => $ver,
            // Alertas: antiguedad por cliente, OS sin DO y contenedores con escaneo incompleto.
            'alertas' => $flujo['alertas'],
            'alertaActiva' => in_array($this->request->getGet('alerta'), ['antiguedad', 'os', 'incompletos'], true) ? (string) $this->request->getGet('alerta') : 'antiguedad',
            'aduaneros' => $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery()),
            'clientes' => $clientes,
            'filtroAduanero' => $aduanero ?? '',
            'filtroCliente' => $clienteId ?? '',
        ]);
    }

    /** Estados y vistas de la consulta: estado de lo que se busca y como se muestra. */
    public const ESTADOS_CONSULTA = ['todos' => 'Todos', 'bodega' => 'En bodega', 'salida' => 'En salida (OS / contenedor)', 'salieron' => 'Salieron (en el periodo)'];
    public const VISTAS_CONSULTA = ['detalle' => 'Detalle por carga', 'cliente' => 'Totales por cliente', 'aduanero' => 'Totales por aduanero', 'contenedor' => 'Totales por OS / contenedor'];

    /** Excel de la consulta, exactamente con los filtros y la vista de la pantalla. */
    public function consultaExcel(): ResponseInterface
    {
        [$desde, $hasta, $aduanero, $clienteId] = $this->filtros();
        [$estado, $ver] = $this->opcionesConsulta();
        $flujo = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId));
        $consulta = $this->agruparConsulta($this->filtrarConsulta($flujo['consulta'], $estado), $ver);
        $estados = ['bodega' => 'En bodega', 'salida' => 'En salida', 'salieron' => 'Salio'];
        $t = $consulta['totales'];

        if ($ver === 'detalle') {
            $filas = [['TRACKING', 'CLIENTE', 'ADUANERO', 'ESTADO', 'OS / DO', 'CONTENEDOR', 'NAVIERA', 'BULTOS', 'KG', 'P3', 'VALOR', 'INGRESO', 'FECHA', 'DIAS EN BODEGA']];

            foreach ($consulta['filas'] as $f) {
                $filas[] = [
                    $f['tracking'], $f['cliente'], $f['aduanero'], $estados[$f['estado']] . ($f['estado'] === 'salida' ? ($f['esOs'] ? ' (OS)' : ' (contenedor)') : ''),
                    $f['orden'] ?? '', $f['contenedor'] ?? '', $f['naviera'] ?? '',
                    $f['bultos'], round($f['kg'], 2), round($f['p3'], 2), round($f['valor'], 2),
                    $this->fechaDmy($f['ingreso']), $this->fechaDmy($f['fecha']), $f['dias'] ?? '',
                ];
            }

            $filas[] = ['TOTALES', $t['cargas'] . ' cargas', '', '', '', '', '', $t['bultos'], round($t['kg'], 2), round($t['p3'], 2), round($t['valor'], 2), '', '', ''];
            $anchos = [18, 30, 18, 20, 15, 16, 16, 9, 12, 11, 13, 12, 12, 10];
        } else {
            $titulo = ['cliente' => 'CLIENTE', 'aduanero' => 'ADUANERO', 'contenedor' => 'OS / DO'][$ver];
            $filas = [array_merge([$titulo], $ver === 'contenedor' ? ['CONTENEDOR', 'NAVIERA'] : [], ['CARGAS', 'EN BODEGA', 'EN SALIDA', 'SALIERON', 'TOTAL BULTOS', 'KG', 'P3', 'VALOR'])];

            foreach ($consulta['filas'] as $g) {
                $filas[] = array_merge([$g['nombre']], $ver === 'contenedor' ? [$g['contenedor'] ?? '', $g['naviera'] ?? ''] : [], [$g['cargas'], $g['bodega'], $g['salida'], $g['salieron'], $g['bultos'], round($g['kg'], 2), round($g['p3'], 2), round($g['valor'], 2)]);
            }

            $filas[] = array_merge(['TOTALES'], $ver === 'contenedor' ? ['', ''] : [], [$t['cargas'], $t['bodega'], $t['salida'], $t['salieron'], $t['bultos'], round($t['kg'], 2), round($t['p3'], 2), round($t['valor'], 2)]);
            $anchos = array_merge([34], $ver === 'contenedor' ? [16, 16] : [], [8, 11, 11, 10, 13, 12, 11, 13]);
        }

        $nombre = 'reporte-' . $estado . '-' . $ver . '-' . date('Y-m-d') . '.xlsx';

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nombre . '"')
            ->setBody($this->container()->get(EscritorXlsx::class)->generar(['Reporte' => ['filas' => $filas, 'anchos' => $anchos]]));
    }

    /** @return array{0: string, 1: string} estado y vista elegidos (con valores por defecto) */
    private function opcionesConsulta(): array
    {
        $estado = (string) ($this->request->getGet('estado') ?? 'todos');
        $ver = (string) ($this->request->getGet('ver') ?? 'detalle');

        return [
            isset(self::ESTADOS_CONSULTA[$estado]) ? $estado : 'todos',
            isset(self::VISTAS_CONSULTA[$ver]) ? $ver : 'detalle',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private function filtrarConsulta(array $filas, string $estado): array
    {
        $filas = $estado === 'todos' ? $filas : array_values(array_filter($filas, static fn (array $f) => $f['estado'] === $estado));
        $orden = ['salida' => 0, 'bodega' => 1, 'salieron' => 2];
        usort($filas, static fn (array $a, array $b) => [$orden[$a['estado']], $a['cliente'], $a['tracking']] <=> [$orden[$b['estado']], $b['cliente'], $b['tracking']]);

        return $filas;
    }

    /**
     * Detalle (las filas tal cual) o totales por cliente, aduanero u OS/DO, siempre con totales generales.
     *
     * @param array<int, array<string, mixed>> $filas
     * @return array{filas: array<int, array<string, mixed>>, totales: array<string, int|float>}
     */
    private function agruparConsulta(array $filas, string $ver): array
    {
        $totales = ['cargas' => count(array_unique(array_column($filas, 'cargaId'))), 'bodega' => 0, 'salida' => 0, 'salieron' => 0, 'bultos' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0];

        foreach ($filas as $f) {
            $totales[$f['estado']] += $f['bultos'];
            $totales['bultos'] += $f['bultos'];
            $totales['kg'] += $f['kg'];
            $totales['p3'] += $f['p3'];
            $totales['valor'] += $f['valor'];
        }

        if ($ver === 'detalle') {
            return ['filas' => $filas, 'totales' => $totales];
        }

        $grupos = [];

        foreach ($filas as $f) {
            // Por OS/DO: lo que esta en bodega no tiene orden y no se muestra en esa vista.
            if ($ver === 'contenedor' && $f['orden'] === null) {
                continue;
            }

            $clave = $ver === 'contenedor' ? (string) $f['ordenId'] : (string) $f[$ver];
            $g = &$grupos[$clave];
            $g ??= [
                'nombre' => $ver === 'contenedor' ? $f['orden'] : $f[$ver],
                'ordenId' => $ver === 'contenedor' ? $f['ordenId'] : null,
                'contenedor' => $f['contenedor'] ?? null,
                'naviera' => $f['naviera'] ?? null,
                'cargasSet' => [], 'bodega' => 0, 'salida' => 0, 'salieron' => 0, 'bultos' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0,
            ];
            $g['cargasSet'][$f['cargaId']] = true;
            $g[$f['estado']] += $f['bultos'];
            $g['bultos'] += $f['bultos'];
            $g['kg'] += $f['kg'];
            $g['p3'] += $f['p3'];
            $g['valor'] += $f['valor'];
            unset($g);
        }

        $grupos = array_values(array_map(static function (array $g): array {
            $g['cargas'] = count($g['cargasSet']);
            unset($g['cargasSet']);

            return $g;
        }, $grupos));
        usort($grupos, static fn (array $a, array $b) => $b['bultos'] <=> $a['bultos']);

        if ($ver === 'contenedor') {
            $totales = ['cargas' => array_sum(array_column($grupos, 'cargas'))] + array_combine(
                ['bodega', 'salida', 'salieron', 'bultos', 'kg', 'p3', 'valor'],
                array_map(static fn (string $k) => array_sum(array_column($grupos, $k)), ['bodega', 'salida', 'salieron', 'bultos', 'kg', 'p3', 'valor']),
            );
        }

        return ['filas' => $grupos, 'totales' => $totales];
    }

    private function fechaDmy(?string $ymd): string
    {
        return $ymd !== null && $ymd !== '' ? substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4) : '';
    }

    /** Excel de una alerta (antiguedad, os, incompletos), con los filtros de la pantalla. */
    public function alertaExcel(): ResponseInterface
    {
        [$desde, $hasta, $aduanero, $clienteId] = $this->filtros();
        $tipo = (string) ($this->request->getGet('alerta') ?? 'antiguedad');
        $tipo = in_array($tipo, ['antiguedad', 'os', 'incompletos'], true) ? $tipo : 'antiguedad';
        $alertas = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId))['alertas'];

        if ($tipo === 'antiguedad') {
            $filas = [['CLIENTE', 'CARGAS', '0-15 DIAS', '16-30 DIAS', '31-60 DIAS', '+60 DIAS', 'TOTAL BULTOS', 'CARGA MAS ANTIGUA', 'DIAS']];

            foreach ($alertas['antiguedad'] as $a) {
                $filas[] = [$a['nombre'], $a['cargas'], $a['r15'], $a['r30'], $a['r60m'], $a['r60'], $a['total'], $a['masVieja']['tracking'], $a['diasMax']];
            }

            $hoja = ['Antiguedad por cliente' => ['filas' => $filas, 'anchos' => [34, 8, 10, 11, 11, 10, 13, 20, 7]]];
        } elseif ($tipo === 'os') {
            $filas = [['OS', 'CREADA', 'DIAS ESPERANDO', 'ADUANEROS', 'CLIENTES', 'CARGAS', 'BULTOS', 'ESCANEADOS', 'KG', 'P3']];

            foreach ($alertas['osSinDo'] as $o) {
                $filas[] = [$o['etiqueta'], $o['creada']->format('d/m/Y H:i'), $o['dias'], implode(', ', $o['aduanerosLista']), implode(', ', $o['clientesLista']), $o['cargas'], $o['bultos'], $o['escaneados'], round($o['kg'], 2), round($o['p3'], 2)];
            }

            $hoja = ['OS sin DO' => ['filas' => $filas, 'anchos' => [14, 17, 14, 26, 40, 8, 9, 11, 12, 12]]];
        } else {
            $filas = [['DO', 'CONTENEDOR', 'NAVIERA', 'CARGUE', 'ETD', 'FALTAN', 'ESCANEADOS', 'BULTOS', 'ADUANEROS', 'CLIENTES']];

            foreach ($alertas['incompletos'] as $d) {
                $filas[] = [$d['etiqueta'], $d['contenedor'] ?? '', $d['naviera'] ?? '', $d['cargue']?->format('d/m/Y') ?? '', $d['etd']?->format('d/m/Y') ?? '', $d['bultos'] - $d['escaneados'], $d['escaneados'], $d['bultos'], implode(', ', $d['aduanerosLista']), implode(', ', $d['clientesLista'])];
            }

            $hoja = ['Contenedores incompletos' => ['filas' => $filas, 'anchos' => [14, 16, 16, 12, 12, 9, 11, 9, 26, 40]]];
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="alerta-' . $tipo . '-' . date('Y-m-d') . '.xlsx"')
            ->setBody($this->container()->get(EscritorXlsx::class)->generar($hoja));
    }

    /** @return array{0: string, 1: string, 2: ?string, 3: ?string} desde, hasta, aduanero, cliente */
    private function filtros(): array
    {
        $fecha = static fn (mixed $v, string $defecto): string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $defecto;
        $texto = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            $fecha($this->request->getGet('desde'), date('Y-m-01')),
            $fecha($this->request->getGet('hasta'), date('Y-m-d')),
            $texto($this->request->getGet('aduanero')),
            $texto($this->request->getGet('cliente')),
        ];
    }
}
