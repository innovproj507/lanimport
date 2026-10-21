<?php
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
/** @var array<string, string> $nombresClientes */
require_once __DIR__ . '/../partials/view_helpers.php';
$n = static fn (float $v, int $d = 2): string => number_format($v, $d, '.', ',');
if (empty($cargas)):
?>
    <tr><td colspan="10" class="py-4 text-sm text-zinc-500">No se encontraron cargas.</td></tr>
<?php else: foreach ($cargas as $carga):
    // Totales de la carga: suma de sus lineas (peso y valor son totales de linea).
    $bultos = 0.0;
    $peso = 0.0;
    $valor = 0.0;
    foreach ($carga->lineas() as $linea) {
        $bultos += $linea->cantidad();
        $peso += $linea->peso();
        $valor += $linea->valor();
    }
    ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black whitespace-nowrap"><?= htmlspecialchars((string) $carga->trackingNumero()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($nombresClientes[$carga->clienteId()] ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($carga->proveedor()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($carga->aduanero()?->nombre() ?? '—') ?></td>
        <td class="py-2 pr-4 text-right text-zinc-700"><?= $n($bultos, 0) ?></td>
        <td class="py-2 pr-4 text-right text-black font-medium whitespace-nowrap"><?= $n($peso) ?></td>
        <td class="py-2 pr-4 text-right text-zinc-700 whitespace-nowrap"><?= $n($valor) ?></td>
        <td class="py-2 pr-4"><?= badge_estado_carga($carga->estado()) ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($carga->fechaIngreso()->format('d/m/Y')) ?></td>
        <td class="py-2 text-right space-x-2 whitespace-nowrap">
            <?php if ($carga->facturaProveedorRuta()): ?>
                <a href="/<?= htmlspecialchars($carga->facturaProveedorRuta()) ?>" target="_blank" title="Ver factura del proveedor"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500 text-white hover:bg-red-600 transition">
                    <?= icon_small('receipt') ?>
                </a>
            <?php endif; ?>
            <?= icon_button('/cargas/' . $carga->id(), 'eye', 'Ver', 'blue') ?>
            <?= icon_button('/cargas/' . $carga->id() . '/editar', 'pencil', 'Editar', 'amber') ?>
        </td>
    </tr>
<?php endforeach; unset($carga); endif; ?>
