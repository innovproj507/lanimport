<?php
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
$pageTitle = 'Mis Cargas';
$pageSubtitle = $cliente ? 'Bienvenido, ' . $cliente->nombreCompleto() : '';
require __DIR__ . '/../partials/header_cliente.php';
?>

<?php if ($cliente === null): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
        <p class="text-sm text-zinc-600">No encontramos un perfil de cliente asociado a tu cuenta. Por favor contacta a soporte.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-slate-200">
        <p class="text-xs text-orange-600 font-semibold uppercase tracking-wide mb-1">Codigo de cliente</p>
        <p class="text-lg font-bold text-black"><?= htmlspecialchars($cliente->codigo() ?? '—') ?></p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
        <h2 class="text-lg font-semibold text-black mb-4">Mis envios</h2>
        <?php if (empty($cargas)): ?>
            <p class="text-sm text-zinc-500">Todavia no tienes envios registrados.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4">Tracking</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cargas as $carga): ?>
                        <tr class="border-b border-slate-200">
                            <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars((string) $carga->trackingNumero()) ?></td>
                            <td class="py-2 pr-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-500/10 text-orange-600">
                                    <?= htmlspecialchars($carga->estado()->label()) ?>
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($carga->proveedor()) ?></td>
                            <td class="py-2 text-zinc-500"><?= htmlspecialchars($carga->createdAt()->format('d/m/Y')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer_cliente.php'; ?>
