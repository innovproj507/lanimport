<?php
/** @var array<int, \Courier\Pais\Domain\Pais> $paises */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($paises)):
?>
    <tr><td colspan="4" class="py-4 text-sm text-zinc-500">No se encontraron paises.</td></tr>
<?php else: foreach ($paises as $pais): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black"><?= htmlspecialchars($pais->identificador()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($pais->nombre()) ?></td>
        <td class="py-2 pr-4"><?= $pais->mostrarSerieEtiqueta() ? badge('Si', 'emerald') : badge('No', 'slate') ?></td>
        <td class="py-2 text-right"><?= icon_button('/paises/' . $pais->id() . '/editar', 'pencil', 'Editar', 'amber') ?></td>
    </tr>
<?php endforeach; unset($pais); endif; ?>
