<?php
/** @var array<int, \Courier\Salida\Domain\SalidaOrden> $ordenes */
/** @var array<string, string> $mapaClientes */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array{estado: string, cliente_id: string} $filtros */
$pageTitle = 'Historial de salidas';
$pageSubtitle = 'Ordenes de salida creadas';
require __DIR__ . '/../partials/header.php';

$estados = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
];

$estadoColores = ['pendiente' => 'amber', 'confirmada' => 'emerald', 'cancelada' => 'red'];

$clienteFiltroEtiqueta = '';
foreach ($clientes as $c) {
    if ($c->id() === $filtros['cliente_id']) {
        $partes = [];
        if ($c->codigo()) {
            $partes[] = $c->codigo();
        }
        $partes[] = $c->nombre();
        if ($c->empresa()) {
            $partes[] = $c->empresa();
        }
        if ((string) $c->email() !== '') {
            $partes[] = (string) $c->email();
        }
        $clienteFiltroEtiqueta = implode(' — ', $partes);
        break;
    }
}
?>

<div class="flex justify-end mb-4">
    <?= header_button('/salidas', 'plus', 'Nueva orden de salida', 'amber') ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/salidas/historial" class="flex items-end gap-4 flex-wrap">
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
    <div class="relative" id="cliente-picker">
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
        <input type="text" id="cliente-buscar" placeholder="Escriba para buscar cliente..." autocomplete="off"
               value="<?= htmlspecialchars($clienteFiltroEtiqueta) ?>"
               class="w-64 rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
        <input type="hidden" name="cliente_id" id="cliente-id-hidden" value="<?= htmlspecialchars($filtros['cliente_id']) ?>">
        <div id="cliente-resultados" class="hidden absolute z-20 mt-1 w-64 max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
        <script type="application/json" id="clientes-data"><?= json_encode(array_map(
            fn ($cliente) => [
                'id' => $cliente->id(),
                'nombre' => $cliente->nombre(),
                'email' => (string) $cliente->email(),
                'empresa' => $cliente->empresa(),
                'codigo' => $cliente->codigo(),
            ],
            $clientes,
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
    <a href="/salidas/historial" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <?php if (empty($ordenes)): ?>
        <p class="text-sm text-zinc-500">No se encontraron ordenes de salida con estos filtros.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Codigo</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Creada</th>
                    <th class="py-2">Confirmada</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-mono font-medium text-black">
                            <a href="/salidas/<?= htmlspecialchars($orden->id()) ?>" class="text-amber-600 hover:underline">
                                <?= htmlspecialchars((string) $orden->codigo()) ?>
                            </a>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($mapaClientes[$orden->clienteId()] ?? '—') ?></td>
                        <td class="py-2 pr-4"><?= badge($orden->estado()->label(), $estadoColores[$orden->estado()->value] ?? 'slate') ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($orden->createdAt()->format('Y-m-d H:i')) ?></td>
                        <td class="py-2 text-zinc-700"><?= htmlspecialchars($orden->confirmedAt()?->format('Y-m-d H:i') ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
