<?php
use App\Libraries\Csrf;
/** @var string $token */
/** @var array{cargas: array<int, array<string, mixed>>, totalFilas: int, proveedoresNuevos: array<int, string>} $plan */
/** @var string $archivo */
/** @var bool $notificar */
require_once __DIR__ . '/../../partials/view_helpers.php';
$pageTitle = 'Revisar carga masiva';
$pageSubtitle = $archivo;
$backHref = '/cargas/masiva';
require __DIR__ . '/../../partials/header.php';

$validas = array_values(array_filter($plan['cargas'], static fn ($c) => $c['errores'] === []));
$conError = array_values(array_filter($plan['cargas'], static fn ($c) => $c['errores'] !== []));
$bultos = array_sum(array_column($validas, 'bultos'));
$num = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-zinc-500">Filas leidas</p><p class="text-2xl font-bold text-black"><?= $plan['totalFilas'] ?></p></div>
    <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-zinc-500">Cargas listas</p><p class="text-2xl font-bold text-emerald-600"><?= count($validas) ?></p></div>
    <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-zinc-500">Cargas con errores</p><p class="text-2xl font-bold <?= $conError ? 'text-red-600' : 'text-black' ?>"><?= count($conError) ?></p></div>
    <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-zinc-500">Bultos a registrar</p><p class="text-2xl font-bold text-black"><?= $num((float) $bultos) ?></p></div>
</div>

<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6 text-sm text-zinc-700 space-y-1">
    <p><strong>Correo a los clientes:</strong> <?= $notificar ? 'si se enviara el aviso a cada cliente.' : 'no se enviara.' ?></p>
    <?php if ($plan['proveedoresNuevos'] !== []): ?>
        <p><strong>Proveedores nuevos que se crearan:</strong> <?= htmlspecialchars(implode(', ', $plan['proveedoresNuevos'])) ?></p>
    <?php endif; ?>
</div>

<?php if ($conError !== []): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 sm:p-5 mb-6">
        <h2 class="font-semibold text-red-700 mb-1">Cargas con errores (no se registraran)</h2>
        <p class="text-xs text-red-600 mb-3">Corrija el archivo y vuelva a subirlo, o confirme para registrar solo las cargas listas.</p>
        <ul class="space-y-3">
            <?php foreach ($conError as $c): ?>
                <li class="text-sm">
                    <p class="font-semibold text-black"><?= htmlspecialchars($c['referencia']) ?> <span class="text-xs font-normal text-zinc-500">(fila<?= count($c['filas']) > 1 ? 's' : '' ?> <?= htmlspecialchars(implode(', ', $c['filas'])) ?>)</span></p>
                    <ul class="list-disc ml-5 text-red-700">
                        <?php foreach ($c['errores'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6">
    <h2 class="font-semibold text-[#0b1b4d] mb-3">Cargas listas para registrar</h2>
    <?php if ($validas === []): ?>
        <p class="text-sm text-zinc-500">Ninguna carga esta lista. Corrija los errores y suba el archivo de nuevo.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4">Referencia</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">Ingreso</th>
                        <th class="py-2 pr-4">Mercancia</th>
                        <th class="py-2 text-right">Bultos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($validas as $c): ?>
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-2.5 pr-4 font-medium text-black whitespace-nowrap"><?= htmlspecialchars($c['referencia']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($c['clienteNombre']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700">
                                <?= htmlspecialchars($c['proveedorNombre']) ?>
                                <?php if ($c['proveedorNuevo']): ?><span class="ml-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs">nuevo</span><?php endif; ?>
                            </td>
                            <td class="py-2.5 pr-4 text-zinc-500 whitespace-nowrap"><?= htmlspecialchars((new DateTimeImmutable($c['fechaIngreso']))->format('d/m/Y H:i')) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700">
                                <?php foreach ($c['lineas'] as $l): ?>
                                    <div><?= htmlspecialchars($num((float) $l['cantidad']) . ' ' . mb_strtolower($l['unidadMedida']) . ' · ' . $l['descripcion'] . ($l['marca'] ? ' (' . $l['marca'] . ')' : '')) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="py-2.5 text-right font-semibold text-black"><?= $num((float) $c['bultos']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<form method="post" action="/cargas/masiva/confirmar" class="flex flex-col-reverse sm:flex-row sm:items-center gap-3"
      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button span').textContent = 'Registrando...';">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <button type="submit" <?= $validas === [] ? 'disabled' : '' ?>
            class="inline-flex justify-center items-center gap-2 bg-emerald-500 text-white rounded-lg px-6 py-2.5 font-medium hover:bg-emerald-600 transition disabled:opacity-40 disabled:cursor-not-allowed">
        <?= icon_small('check') ?><span>Registrar <?= count($validas) ?> carga<?= count($validas) === 1 ? '' : 's' ?></span>
    </button>
    <a href="/cargas/masiva" class="inline-flex justify-center items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">Cancelar y subir otro archivo</a>
</form>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
