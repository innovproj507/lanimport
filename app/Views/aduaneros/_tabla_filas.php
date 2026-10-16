<?php
/** @var array<int, \Courier\Aduanero\Domain\Aduanero> $aduaneros */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($aduaneros)):
?>
    <tr><td colspan="5" class="py-4 text-sm text-zinc-500">No se encontraron aduaneros.</td></tr>
<?php else: foreach ($aduaneros as $aduanero): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black"><?= htmlspecialchars($aduanero->identificador()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($aduanero->nombre()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($aduanero->pais() ?? '—') ?></td>
        <td class="py-2"><?= $aduanero->activo() ? badge('Activo', 'emerald') : badge('Inactivo', 'slate') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/aduaneros/' . $aduanero->id() . '/editar', 'pencil', 'Editar', 'amber') ?>
            <?php $base = '/aduaneros/' . $aduanero->id(); $activo = $aduanero->activo(); $nombre = 'al aduanero ' . $aduanero->nombre(); require __DIR__ . '/../partials/_acciones_estado.php'; ?>
        </td>
    </tr>
<?php endforeach; unset($aduanero); endif; ?>
