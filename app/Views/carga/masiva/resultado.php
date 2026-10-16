<?php
/** @var array{creadas: array<int, array<string, mixed>>, fallidas: array<int, array<string, mixed>>, proveedoresCreados: array<int, string>} $resultado */
/** @var string $archivo */
/** @var bool $notificar */
require_once __DIR__ . '/../../partials/view_helpers.php';
$pageTitle = 'Carga masiva registrada';
$pageSubtitle = $archivo;
$backHref = '/cargas/ingreso';
$headerActions = header_button('/cargas/masiva', 'plus', 'Otra carga masiva', 'amber');
require __DIR__ . '/../../partials/header.php';

$creadas = $resultado['creadas'];
$fallidas = $resultado['fallidas'];
$num = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
?>

<div class="<?= $fallidas === [] ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800' ?> border rounded-xl px-4 py-3 mb-6 text-sm">
    Se registraron <strong><?= count($creadas) ?></strong> carga<?= count($creadas) === 1 ? '' : 's' ?>
    <?= $fallidas !== [] ? ' y <strong>' . count($fallidas) . '</strong> no se pudieron registrar' : '' ?>.
    <?= $notificar ? 'Se envio el aviso por correo a los clientes.' : '' ?>
    <?php if ($resultado['proveedoresCreados'] !== []): ?>
        <br>Proveedores creados: <?= htmlspecialchars(implode(', ', $resultado['proveedoresCreados'])) ?>.
    <?php endif; ?>
</div>

<?php if ($fallidas !== []): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 sm:p-5 mb-6">
        <h2 class="font-semibold text-red-700 mb-3">No registradas</h2>
        <ul class="space-y-2 text-sm">
            <?php foreach ($fallidas as $f): ?>
                <li><strong><?= htmlspecialchars($f['referencia']) ?>:</strong> <span class="text-red-700"><?= htmlspecialchars($f['error']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($creadas !== []): ?>
<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5">
    <h2 class="font-semibold text-[#0b1b4d] mb-3">Cargas registradas</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Referencia</th>
                    <th class="py-2 pr-4">Tracking</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4 text-right">Bultos</th>
                    <th class="py-2">Correo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($creadas as $c): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-4 text-black"><?= htmlspecialchars($c['referencia']) ?></td>
                        <td class="py-2.5 pr-4"><a href="/cargas/<?= htmlspecialchars($c['cargaId']) ?>" class="font-medium text-blue-600 hover:underline"><?= htmlspecialchars($c['tracking']) ?></a></td>
                        <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($c['cliente']) ?></td>
                        <td class="py-2.5 pr-4 text-right text-zinc-700"><?= $num((float) $c['bultos']) ?></td>
                        <td class="py-2.5"><?= match ($c['correo']) { 'enviado' => badge('Enviado', 'emerald'), 'fallido' => badge('Fallido', 'red'), default => badge('No enviado', 'slate') } ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
