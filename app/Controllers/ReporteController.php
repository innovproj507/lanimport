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

        return view('reporte/index', [
            'reporte' => $reporte,
            'flujo' => $this->container()->get(ObtenerFlujoCargasHandler::class)
                ->handle(new ObtenerFlujoCargasQuery($desde, $hasta, $aduanero, $clienteId)),
            'etapaActiva' => isset(ObtenerFlujoCargasHandler::ETAPAS[$etapa]) ? $etapa : 'llegando',
            'aduaneros' => $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery()),
            'clientes' => $clientes,
            'filtroAduanero' => $aduanero ?? '',
            'filtroCliente' => $clienteId ?? '',
        ]);
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
