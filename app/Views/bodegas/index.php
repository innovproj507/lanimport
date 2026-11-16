<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Bodega\Domain\Bodega> $bodegas */
/** @var \Courier\Bodega\Domain\Bodega|null $editar */
$pageTitle = 'Bodegas';
$pageSubtitle = 'Bodegas de la empresa: cada carga, bulto, ubicacion y orden de salida es de una bodega';
$backHref = '/configuracion';
require __DIR__ . '/../partials/header.php';

$h = static fn ($v): string => htmlspecialchars((string) $v);
$post = $post ?? null;
$valor = static fn (string $campo, ?string $actual) => $post !== null ? (string) ($post[$campo] ?? '') : (string) $actual;
$activoMarcado = $post !== null ? isset($post['activo']) : ($editar?->activo() ?? true);
?>

<?php if (!empty($aviso)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30 max-w-4xl"><?= $h($aviso) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-4xl"><?= $h($error) ?></div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-5 max-w-6xl">
    <div class="lg:col-span-3 bg-white rounded-xl shadow p-6">
        <?= card_header('building', 'Bodegas', 'indigo') ?>
        <table class="w-full text-sm mt-2">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-3">Codigo</th>
                    <th class="py-2 pr-3">Nombre</th>
                    <th class="py-2 pr-3">Direccion</th>
                    <th class="py-2 pr-3">Estado</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bodegas as $b): ?>
                    <tr class="border-b border-slate-100 <?= $b->activo() ? '' : 'text-zinc-400' ?>">
                        <td class="py-2 pr-3 font-mono font-semibold"><?= $h($b->codigo()) ?></td>
                        <td class="py-2 pr-3 text-black"><?= $h($b->nombre()) ?></td>
                        <td class="py-2 pr-3"><?= $h($b->direccion() ?? '—') ?></td>
                        <td class="py-2 pr-3">
                            <?= $b->activo()
                                ? '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-700">Activa</span>'
                                : '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/10 text-slate-500">Inactiva</span>' ?>
                        </td>
                        <td class="py-2 text-right"><?= icon_button('/bodegas/' . $b->id() . '/editar', 'pencil', 'Editar', 'amber') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-zinc-500 mt-4">
            El personal de una bodega (Bodega, Inventario) se asigna a su bodega en Configuracion &gt; Usuarios y solo ve lo de ella.
            Trafico, Gerencia y Owner pueden ver todas y elegir con cual trabajar arriba, junto a la fecha.
        </p>
    </div>

    <form method="post" action="/bodegas" class="lg:col-span-2 bg-white rounded-xl shadow p-6 space-y-4 self-start">
        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= $h($editar?->id() ?? '') ?>">
        <?= card_header($editar !== null ? 'pencil' : 'plus', $editar !== null ? 'Editar bodega' : 'Nueva bodega', 'amber') ?>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Codigo</label>
            <input type="text" name="codigo" required maxlength="20" placeholder="Ej: COLON" value="<?= $h($valor('codigo', $editar?->codigo())) ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 uppercase focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
            <input type="text" name="nombre" required maxlength="120" placeholder="Ej: Bodega Colon" value="<?= $h($valor('nombre', $editar?->nombre())) ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Direccion</label>
            <input type="text" name="direccion" maxlength="255" value="<?= $h($valor('direccion', $editar?->direccion())) ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <?php if ($editar !== null): ?>
            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" name="activo" value="1" <?= $activoMarcado ? 'checked' : '' ?> class="rounded border-zinc-300">
                Activa (si se desactiva, ya no se puede elegir para cargas nuevas)
            </label>
        <?php else: ?>
            <input type="hidden" name="activo" value="1">
        <?php endif; ?>
        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
                <?= icon_small('check') ?><span><?= $editar !== null ? 'Guardar cambios' : 'Crear bodega' ?></span>
            </button>
            <?php if ($editar !== null): ?>
                <a href="/bodegas" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
