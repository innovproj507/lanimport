<?php
/** @var \Courier\Cliente\Domain\Cliente $cliente */
/** @var array<int, \Courier\Cliente\Domain\ClienteDocumento> $documentos */
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = $cliente->nombreCompleto();
$pageSubtitle = $cliente->codigo() ?? '';
$backHref = '/clientes';
$headerActions = header_button('/clientes/' . $cliente->id() . '/editar', 'pencil', 'Editar', 'amber');
require __DIR__ . '/../partials/header.php';

$estadoColores = ['pendiente' => 'amber', 'aprobado' => 'emerald', 'rechazado' => 'red'];
$estadoColor = $estadoColores[$cliente->estado()->value] ?? 'slate';
?>

<div class="grid grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <?= card_header('users', 'Datos del cliente', 'amber') ?>
        <dl class="space-y-3 text-sm">
            <div><dt class="text-zinc-500">Correo</dt><dd class="text-black font-medium"><?= htmlspecialchars((string) $cliente->email()) ?></dd></div>
            <div><dt class="text-zinc-500">Telefono</dt><dd class="text-black"><?= htmlspecialchars($cliente->telefono() ?? '—') ?></dd></div>
            <div><dt class="text-zinc-500">Empresa</dt><dd class="text-black"><?= htmlspecialchars($cliente->empresa() ?? '—') ?></dd></div>
            <div><dt class="text-zinc-500">Direccion</dt><dd class="text-black"><?= htmlspecialchars($cliente->direccion() ?? '—') ?></dd></div>
            <div><dt class="text-zinc-500">Pais</dt><dd class="text-black"><?= htmlspecialchars($cliente->pais() ?? '—') ?></dd></div>
            <div><dt class="text-zinc-500">Estado</dt><dd><?= badge(ucfirst($cliente->estado()->value), $estadoColor) ?></dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?= card_header('receipt', 'Documentos', 'indigo') ?>
        <?php if (empty($documentos)): ?>
            <p class="text-sm text-zinc-500">No hay documentos cargados.</p>
        <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($documentos as $documento): ?>
                    <li>
                        <a href="/<?= htmlspecialchars($documento->rutaArchivo()) ?>" target="_blank" class="text-amber-600 hover:underline">
                            <?= htmlspecialchars($documento->nombreArchivo()) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mt-6">
    <?= card_header('truck', 'Cargas', 'purple') ?>
    <?php if (empty($cargas)): ?>
        <p class="text-sm text-zinc-500">Este cliente no tiene cargas registradas.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Tracking</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cargas as $carga): ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars((string) $carga->trackingNumero()) ?></td>
                        <td class="py-2 pr-4"><?= badge($carga->estado()->label(), 'amber') ?></td>
                        <td class="py-2 text-zinc-500"><?= htmlspecialchars($carga->createdAt()->format('d/m/Y')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
