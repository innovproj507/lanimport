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

        $etapa = (string) ($this->request->getGet('etapa') ?? '');
        $resumen = (string) ($this->request->getGet('resumen') ?? '');
        $flujo = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId));

        return view('reporte/index', [
            'reporte' => $reporte,
            'flujo' => $flujo,
            // Resumen de salidas por cliente, aduanero y contenedor (mismos filtros que el flujo).
            'resumen' => $flujo['resumen'],
            'resumenActivo' => in_array($resumen, ['cliente', 'aduanero', 'contenedor'], true) ? $resumen : 'cliente',
            // Alertas: antiguedad por cliente, OS sin DO y contenedores con escaneo incompleto.
            'alertas' => $flujo['alertas'],
            'alertaActiva' => in_array($this->request->getGet('alerta'), ['antiguedad', 'os', 'incompletos'], true) ? (string) $this->request->getGet('alerta') : 'antiguedad',
            'etapaActiva' => isset(ObtenerFlujoCargasHandler::ETAPAS[$etapa]) ? $etapa : 'llegando',
            'aduaneros' => $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery()),
            'clientes' => $clientes,
            'filtroAduanero' => $aduanero ?? '',
            'filtroCliente' => $clienteId ?? '',
        ]);
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

    /** Excel del resumen de salidas (por cliente, aduanero o contenedor), con los filtros de la pantalla. */
    public function resumenExcel(): ResponseInterface
    {
        [$desde, $hasta, $aduanero, $clienteId] = $this->filtros();
        $tipo = (string) ($this->request->getGet('resumen') ?? 'cliente');
        $tipo = in_array($tipo, ['cliente', 'aduanero', 'contenedor'], true) ? $tipo : 'cliente';
        $resumen = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId))['resumen'];
        $hojas = [];

        if ($tipo === 'contenedor') {
            foreach (['pendientes' => 'En salida', 'salieron' => 'Salieron'] as $clave => $nombre) {
                $filas = [['DO', 'CONTENEDOR', 'SELLO', 'NAVIERA', 'BARCO', 'DESTINO', $clave === 'salieron' ? 'CONFIRMADA' : 'ETD', 'ADUANEROS', 'CLIENTES', 'CARGAS', 'BULTOS', 'ESCANEADOS', 'KG', 'P3', 'VALOR']];
                $t = ['cargas' => 0, 'bultos' => 0, 'escaneados' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0];

                foreach ($resumen['contenedor'][$clave] as $d) {
                    $filas[] = [
                        $d['etiqueta'], $d['contenedor'] ?? '', $d['sello'] ?? '', $d['naviera'] ?? '', $d['barco'] ?? '', $d['destino'] ?? '',
                        ($clave === 'salieron' ? $d['confirmada']?->format('d/m/Y') : $d['etd']?->format('d/m/Y')) ?? '',
                        implode(', ', $d['aduanerosLista']), implode(', ', $d['clientesLista']),
                        $d['cargas'], $d['bultos'], $d['escaneados'], round($d['kg'], 2), round($d['p3'], 2), round($d['valor'], 2),
                    ];

                    foreach ($t as $k => $_) {
                        $t[$k] += $d[$k];
                    }
                }

                $filas[] = ['TOTALES', '', '', '', '', '', '', '', '', $t['cargas'], $t['bultos'], $t['escaneados'], round($t['kg'], 2), round($t['p3'], 2), round($t['valor'], 2)];
                $hojas[$nombre] = ['filas' => $filas, 'anchos' => [14, 16, 16, 16, 18, 22, 12, 24, 40, 8, 9, 11, 12, 12, 14]];
            }
        } else {
            $titulo = $tipo === 'cliente' ? 'CLIENTE' : 'ADUANERO';
            $filas = [array_merge([$titulo], $tipo === 'aduanero' ? ['CLIENTES'] : [], ['CARGAS', 'EN BODEGA', 'EN OS', 'EN CONTENEDOR', 'KG EN SALIDA', 'P3 EN SALIDA', 'VALOR EN SALIDA', 'SALIERON', 'OS / DO'])];
            $t = ['cargas' => 0, 'bodega' => 0, 'os' => 0, 'do' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0, 'salieron' => 0];

            foreach ($resumen[$tipo] as $g) {
                $filas[] = array_merge(
                    [$g['nombre']],
                    $tipo === 'aduanero' ? [$g['clientes']] : [],
                    [$g['cargas'], $g['bodega'], $g['os'], $g['do'], round($g['kg'], 2), round($g['p3'], 2), round($g['valor'], 2), $g['salieron'], implode(', ', array_keys($g['dos']))],
                );

                foreach ($t as $k => $_) {
                    $t[$k] += $g[$k];
                }
            }

            $filas[] = array_merge(['TOTALES'], $tipo === 'aduanero' ? [''] : [], [$t['cargas'], $t['bodega'], $t['os'], $t['do'], round($t['kg'], 2), round($t['p3'], 2), round($t['valor'], 2), $t['salieron'], '']);
            $hojas[$tipo === 'cliente' ? 'Por cliente' : 'Por aduanero'] = ['filas' => $filas, 'anchos' => array_merge([34], $tipo === 'aduanero' ? [9] : [], [8, 11, 9, 14, 13, 13, 15, 10, 40])];
        }

        $contenido = $this->container()->get(EscritorXlsx::class)->generar($hojas);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="resumen-salidas-por-' . $tipo . '-' . date('Y-m-d') . '.xlsx"')
            ->setBody($contenido);
    }

    /** Excel de una etapa del flujo de cargas, con los mismos filtros de la pantalla. */
    public function flujoExcel(): ResponseInterface
    {
        [$desde, $hasta, $aduanero, $clienteId] = $this->filtros();
        $etapa = (string) ($this->request->getGet('etapa') ?? 'llegando');
        $etapa = isset(ObtenerFlujoCargasHandler::ETAPAS[$etapa]) ? $etapa : 'llegando';

        $flujo = $this->container()->get(ObtenerFlujoCargasHandler::class)
            ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId));
        $datos = $flujo['etapas'][$etapa];

        $extra = match ($etapa) {
            'llegando' => ['ESCANEADOS', 'HORAS DESDE REGISTRO'],
            'deposito' => ['DIAS EN BODEGA', 'DESPACHADOS (PARCIAL)'],
            'salida' => ['DO', 'CONTENEDOR', 'BULTOS EN DO', 'ESCANEADOS PARA SALIDA'],
            default => ['FECHA SALIDA', 'DO', 'CONTENEDOR', 'NAVIERA'],
        };
        $filas = [array_merge(['TRACKING', 'CLIENTE', 'ADUANERO', 'FECHA INGRESO', 'BULTOS', 'PESO (KG)', 'CUBICAJE P3'], $extra)];

        foreach ($datos['filas'] as $f) {
            $base = [
                $f['tracking'], $f['cliente'], $f['aduanero'],
                $f['fechaIngreso']?->format('d/m/Y') ?? '',
                $f['bultos'], round($f['peso'], 2), round($f['p3'], 2),
            ];
            $dos = implode(', ', array_column($f['dos'] ?? [], 'etiqueta'));
            $contenedores = implode(', ', array_unique(array_filter(array_column($f['dos'] ?? [], 'contenedor'))));

            $filas[] = array_merge($base, match ($etapa) {
                'llegando' => [$f['etiquetas'] > 0 ? ($f['etiquetas'] - $f['porEscanear']) . ' de ' . $f['etiquetas'] : 'Sin etiquetas', $f['horas']],
                'deposito' => [$f['dias'], $f['despachados'] > 0 ? $f['despachados'] : ''],
                'salida' => [$dos, $contenedores, $f['enDo'], $f['escaneadosDo']],
                default => [$f['fechaSalida']->format('d/m/Y'), $dos, $contenedores, $f['naviera'] ?? ''],
            });
        }

        $filas[] = ['TOTALES', $datos['cargas'] . ' cargas', '', '', $datos['bultos'], round($datos['peso'], 2), round($datos['p3'], 2)];

        $nombre = ObtenerFlujoCargasHandler::ETAPAS[$etapa];
        $contenido = $this->container()->get(EscritorXlsx::class)->generar([
            $nombre => ['filas' => $filas, 'anchos' => [18, 30, 20, 13, 9, 11, 12, 22, 22, 14, 18]],
        ]);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($nombre)), '-');

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="flujo-cargas-' . $slug . '-' . date('Y-m-d') . '.xlsx"')
            ->setBody($contenido);
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
