<?php
/** @var array<int, array{actaId: string, codigo: string, clienteNombre: string, marca: ?string, totalRecibido: int, entregadoPor: string, fechaRecepcion: string}> $actasSinCarga */
/** @var array<int, array{cargaId: string, trackingNumero: string, clienteNombre: string, proveedor: string, fechaIngreso: string}> $cargasPendientes */
/** @var array<int, array{actaId: string, codigo: string, trackingNumero: ?string, clienteNombre: string, totalRecibido: int, entregadoPor: string, verificadoPor: string, fechaRecepcion: string}> $recientes */
/** @var bool $puedeLlenar */
/** @var bool $puedeVincular */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Recepcion de Carga';
$pageSubtitle = 'Actas de recepcion que llena bodega al recibir la mercancia';
$headerActions = $puedeLlenar ? '<span class="hidden sm:inline-flex">' . header_button('/recepciones/nueva', 'plus', 'Nueva recepcion', 'amber') . '</span>' : '';
require __DIR__ . '/../partials/header.php';

$fecha = static fn (string $valor): string => (new DateTimeImmutable($valor))->format('d/m/Y');
$fechaHora = static fn (string $valor): string => (new DateTimeImmutable($valor))->format('d/m/Y H:i');
$th = 'text-left text-xs text-zinc-500 uppercase border-b border-slate-200';
$btnAmber = 'inline-flex items-center justify-center gap-2 bg-amber-500 text-black rounded-lg px-3 py-2 text-sm font-medium hover:bg-amber-400 transition';
$btnIcono = 'inline-flex items-center justify-center w-9 h-9 rounded-lg text-white transition';
// En celular/tablet vertical cada fila se muestra como tarjeta; desde md, como tabla.
$tarjeta = 'md:hidden border border-slate-200 rounded-xl p-3 space-y-2';
?>

<?php if ($puedeLlenar): ?>
    <a href="/recepciones/nueva" class="flex items-center gap-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6 hover:shadow-md active:scale-[0.99] transition">
        <span class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center shrink-0"><?= icon_small('plus', 'w-7 h-7') ?></span>
        <span>
            <span class="block text-lg font-semibold">Nueva recepcion</span>
            <span class="block text-sm text-white/85">Llego mercancia? Llene el acta con el chofer, los bultos y quien entrega.</span>
        </span>
    </a>
<?php endif; ?>

<!-- Actas sin carga -->
<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6">
    <h2 class="font-semibold text-amber-600 flex items-center gap-2 mb-1">
        <?= icon_small('clock', 'w-5 h-5') ?> Recibidas, esperando registro de carga
        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold"><?= count($actasSinCarga) ?></span>
    </h2>
    <p class="text-xs text-zinc-500 mb-4">Actas llenadas por bodega cuya carga aun no fue registrada por operaciones.</p>

    <?php if ($actasSinCarga === []): ?>
        <p class="text-sm text-zinc-500">No hay actas esperando registro de carga.</p>
    <?php else: ?>
        <div class="space-y-3 md:hidden">
            <?php foreach ($actasSinCarga as $acta): ?>
                <div class="<?= $tarjeta ?>">
                    <div class="flex items-center justify-between">
                        <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" class="font-semibold text-black"><?= htmlspecialchars($acta['codigo']) ?></a>
                        <span class="text-xs text-zinc-500"><?= htmlspecialchars($fechaHora($acta['fechaRecepcion'])) ?></span>
                    </div>
                    <p class="text-sm text-zinc-700"><?= htmlspecialchars($acta['clienteNombre']) ?><?= $acta['marca'] ? ' · ' . htmlspecialchars($acta['marca']) : '' ?></p>
                    <p class="text-sm text-zinc-500">Total recibido: <strong class="text-black"><?= $acta['totalRecibido'] ?></strong></p>
                    <div class="flex gap-2 pt-1">
                        <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-500 text-white rounded-lg px-3 py-2 text-sm font-medium"><?= icon_small('eye') ?> Ver acta</a>
                        <?php if ($puedeVincular): ?>
                            <a href="/cargas/ingreso?acta=<?= htmlspecialchars($acta['actaId']) ?>" class="flex-1 <?= $btnAmber ?>"><?= icon_small('plus') ?> Registrar carga</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="<?= $th ?>">
                        <th class="py-2 pr-4">Acta</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4">Marca</th>
                        <th class="py-2 pr-4">Total recibido</th>
                        <th class="py-2 pr-4">Recibida</th>
                        <th class="py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actasSinCarga as $acta): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2.5 pr-4 font-medium text-black"><a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" class="hover:text-blue-600"><?= htmlspecialchars($acta['codigo']) ?></a></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($acta['clienteNombre']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($acta['marca'] ?? '—') ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= $acta['totalRecibido'] ?></td>
                            <td class="py-2.5 pr-4 text-zinc-500"><?= htmlspecialchars($fechaHora($acta['fechaRecepcion'])) ?></td>
                            <td class="py-2.5 text-right whitespace-nowrap">
                                <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" title="Ver acta" class="<?= $btnIcono ?> bg-blue-500 hover:bg-blue-600"><?= icon_small('eye') ?></a>
                                <?php if ($puedeVincular): ?>
                                    <a href="/cargas/ingreso?acta=<?= htmlspecialchars($acta['actaId']) ?>" class="<?= $btnAmber ?>"><?= icon_small('plus') ?><span>Registrar carga</span></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Cargas sin acta -->
<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6">
    <h2 class="font-semibold text-red-600 flex items-center gap-2 mb-1">
        <?= icon_small('receipt', 'w-5 h-5') ?> Cargas registradas sin acta
        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-xs font-semibold"><?= count($cargasPendientes) ?></span>
    </h2>
    <p class="text-xs text-zinc-500 mb-4">Cargas que operaciones ya registro y a las que bodega aun no les lleno el acta.</p>

    <?php if ($cargasPendientes === []): ?>
        <p class="text-sm text-zinc-500">Todas las cargas registradas tienen su acta de recepcion.</p>
    <?php else: ?>
        <div class="space-y-3 md:hidden">
            <?php foreach ($cargasPendientes as $carga): ?>
                <div class="<?= $tarjeta ?>">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-black"><?= htmlspecialchars($carga['trackingNumero']) ?></span>
                        <span class="text-xs text-zinc-500"><?= htmlspecialchars($fecha($carga['fechaIngreso'])) ?></span>
                    </div>
                    <p class="text-sm text-zinc-700"><?= htmlspecialchars($carga['clienteNombre']) ?></p>
                    <p class="text-sm text-zinc-500">Proveedor: <?= htmlspecialchars($carga['proveedor']) ?></p>
                    <?php if ($puedeLlenar): ?>
                        <a href="/recepciones/nueva?carga=<?= htmlspecialchars($carga['cargaId']) ?>" class="w-full <?= $btnAmber ?>"><?= icon_small('pencil') ?> Llenar acta</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="<?= $th ?>">
                        <th class="py-2 pr-4">Tracking</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">Fecha de ingreso</th>
                        <th class="py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cargasPendientes as $carga): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2.5 pr-4 font-medium text-black"><?= htmlspecialchars($carga['trackingNumero']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($carga['clienteNombre']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($carga['proveedor']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-500"><?= htmlspecialchars($fecha($carga['fechaIngreso'])) ?></td>
                            <td class="py-2.5 text-right">
                                <?php if ($puedeLlenar): ?>
                                    <a href="/recepciones/nueva?carga=<?= htmlspecialchars($carga['cargaId']) ?>" class="<?= $btnAmber ?>"><?= icon_small('pencil') ?><span>Llenar acta</span></a>
                                <?php else: ?>
                                    <a href="/cargas/<?= htmlspecialchars($carga['cargaId']) ?>"
                                       class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 transition">
                                        <?= icon_small('eye') ?><span>Ver carga</span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Actas recientes -->
<div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5">
    <h2 class="font-semibold text-[#0b1b4d] mb-4 flex items-center gap-2"><?= icon_small('check', 'w-5 h-5 text-emerald-600') ?> Actas registradas recientemente</h2>

    <?php if ($recientes === []): ?>
        <p class="text-sm text-zinc-500">Todavia no se ha registrado ninguna acta.</p>
    <?php else: ?>
        <div class="space-y-3 md:hidden">
            <?php foreach ($recientes as $acta): ?>
                <div class="<?= $tarjeta ?>">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-black"><?= htmlspecialchars($acta['codigo']) ?></span>
                        <?= $acta['trackingNumero'] !== null
                            ? '<span class="text-xs text-zinc-600">' . htmlspecialchars($acta['trackingNumero']) . '</span>'
                            : '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs">Sin carga</span>' ?>
                    </div>
                    <p class="text-sm text-zinc-700"><?= htmlspecialchars($acta['clienteNombre']) ?> · <strong><?= $acta['totalRecibido'] ?></strong> recibido(s)</p>
                    <p class="text-xs text-zinc-500">Entrego <?= htmlspecialchars($acta['entregadoPor']) ?> · verifico <?= htmlspecialchars($acta['verificadoPor']) ?> · <?= htmlspecialchars($fechaHora($acta['fechaRecepcion'])) ?></p>
                    <div class="flex gap-2 pt-1">
                        <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-500 text-white rounded-lg px-3 py-2 text-sm font-medium"><?= icon_small('eye') ?> Ver</a>
                        <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>/imprimir" class="flex-1 inline-flex items-center justify-center gap-2 bg-slate-500 text-white rounded-lg px-3 py-2 text-sm font-medium"><?= icon_small('printer') ?> Imprimir</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="<?= $th ?>">
                        <th class="py-2 pr-4">Acta</th>
                        <th class="py-2 pr-4">Tracking</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4">Total recibido</th>
                        <th class="py-2 pr-4">Entregado por</th>
                        <th class="py-2 pr-4">Verificado por</th>
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recientes as $acta): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2.5 pr-4 font-medium text-black"><?= htmlspecialchars($acta['codigo']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700">
                                <?= $acta['trackingNumero'] !== null
                                    ? htmlspecialchars($acta['trackingNumero'])
                                    : '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs">Sin carga</span>' ?>
                            </td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($acta['clienteNombre']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= $acta['totalRecibido'] ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($acta['entregadoPor']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-700"><?= htmlspecialchars($acta['verificadoPor']) ?></td>
                            <td class="py-2.5 pr-4 text-zinc-500"><?= htmlspecialchars($fechaHora($acta['fechaRecepcion'])) ?></td>
                            <td class="py-2.5 text-right whitespace-nowrap">
                                <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>" title="Ver acta" class="<?= $btnIcono ?> bg-blue-500 hover:bg-blue-600"><?= icon_small('eye') ?></a>
                                <a href="/recepciones/<?= htmlspecialchars($acta['actaId']) ?>/imprimir" title="Imprimir acta" class="<?= $btnIcono ?> bg-slate-500 hover:bg-slate-600"><?= icon_small('printer') ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
