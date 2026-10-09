<?php
/** @var array<int, \Courier\Reempaque\Domain\ReempaqueOrden> $ordenes */
/** @var array<string, string> $mapaClientes */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array{estado: string, cliente_id: string} $filtros */
$pageTitle = 'Historial de reempaque';
$pageSubtitle = 'Ordenes de reempaque creadas';
require __DIR__ . '/../partials/header.php';

$estados = [
    'pendiente' => 'Pendiente',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado',
];

$estadoColores = ['pendiente' => 'amber', 'completado' => 'emerald', 'cancelado' => 'red'];
?>

<div class="flex justify-end mb-4">
    <?= header_button('/reempaques', 'plus', 'Nueva orden de reempaque', 'amber') ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/reempaques/historial" class="flex items-end gap-4 flex-wrap">
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Estado</label>
        <select name="estado" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($estados as $valor => $etiqueta): ?>
                <option value="<?= htmlspecialchars($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
        <select name="cliente_id" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente->id()) ?>" <?= $filtros['cliente_id'] === $cliente->id() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cliente->nombreCompleto()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
    <a href="/reempaques/historial" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <?php if (empty($ordenes)): ?>
        <p class="text-sm text-zinc-500">No se encontraron ordenes de reempaque con estos filtros.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Codigo</th>
                    <th class="py-2 pr-4">Tipo</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2">Creada</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-mono font-medium text-black">
                            <a href="/reempaques/<?= htmlspecialchars($orden->id()) ?>" class="text-amber-600 hover:underline">
                                <?= htmlspecialchars((string) $orden->codigo()) ?>
                            </a>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($orden->tipo()->label()) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($mapaClientes[$orden->clienteId()] ?? '—') ?></td>
                        <td class="py-2 pr-4"><?= badge($orden->estado()->label(), $estadoColores[$orden->estado()->value] ?? 'slate') ?></td>
                        <td class="py-2 text-zinc-700"><?= htmlspecialchars($orden->createdAt()->format('Y-m-d H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
