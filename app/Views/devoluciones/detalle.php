<?php
use App\Libraries\Csrf;
require_once __DIR__ . '/../partials/view_helpers.php';
/** @var \Courier\Devolucion\Domain\OrdenDevolucion $orden */
/** @var array<int, array<string, mixed>> $lineas bultos con carga, cliente y proveedor */
/** @var array<int, array{id: string, nombre: string, ruta: string, fecha: string, usuario: ?string}> $documentos */
/** @var array<int, array{id: string, ruta: string, usuario: ?string, fecha: string, cargaId: ?string}> $fotos */
$abierta = $orden->estado() === \Courier\Devolucion\Domain\EstadoDevolucion::PENDIENTE;
$p = $orden->progreso();
$t = $orden->transporte();
$pageTitle = 'Devolucion ' . $orden->etiqueta();
$pageSubtitle = 'Devolucion al proveedor' . (hay_varias_bodegas() && $orden->bodegaId() ? ' · ' . bodega_nombre($orden->bodegaId()) : '');
$backHref = '/devoluciones';
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
$extensiones = implode(',', array_map(static fn ($e) => '.' . $e, \Courier\Devolucion\Application\AdjuntarDocumentoDevolucion\AdjuntarDocumentoDevolucionHandler::EXTENSIONES));

// Bultos agrupados por carga.
$grupos = [];
foreach ($lineas as $l) {
    $clave = (string) ($l['cargaId'] ?? '');
    $grupos[$clave] ??= ['cargaId' => $l['cargaId'], 'tracking' => $l['tracking'], 'cliente' => $l['cliente'], 'proveedor' => $l['proveedor'], 'aduanero' => $l['aduanero'], 'lineas' => [], 'escaneados' => 0];
    $grupos[$clave]['lineas'][] = $l;
    $grupos[$clave]['escaneados'] += $l['escaneado'] !== null ? 1 : 0;
}
$proveedores = array_values(array_unique(array_filter(array_column($grupos, 'proveedor'))));
?>

<?php if (!empty($aviso)): ?>
    <div class="<?= $aviso['tipo'] === 'ok' ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'bg-red-500/10 text-red-600 border-red-500/30' ?> text-sm rounded-lg px-4 py-3 mb-4 border"><?= $h($aviso['texto']) ?></div>
<?php endif; ?>

<div class="space-y-4">
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
        <div><p class="text-xs text-zinc-500 uppercase">Estado</p><?= badge($orden->estado()->label(), $orden->estado()->color()) ?></div>
        <div><p class="text-xs text-zinc-500 uppercase">Escaneados</p><p class="text-lg font-semibold <?= $abierta && $p['escaneados'] < $p['total'] ? 'text-red-600' : 'text-emerald-700' ?>"><?= $p['escaneados'] ?> / <?= $p['total'] ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Cargas</p><p class="font-semibold text-black"><?= count($grupos) ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Proveedor(es)</p><p class="font-semibold text-black"><?= $h($proveedores !== [] ? implode(', ', $proveedores) : '—') ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Creada</p><p class="text-black"><?= $h($orden->createdAt()->format('d/m/Y H:i')) ?></p></div>
        <?php if ($orden->confirmedAt() !== null): ?>
            <div><p class="text-xs text-zinc-500 uppercase">Salio el camion</p><p class="text-black"><?= $h($orden->confirmedAt()->format('d/m/Y H:i')) ?></p></div>
        <?php endif; ?>
    </div>

    <?php if ($abierta): ?>
        <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/escanear" class="bg-white rounded-xl shadow p-5 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
            <div class="flex-1 min-w-[16rem]">
                <label class="block text-sm font-medium text-zinc-700 mb-1" for="codigo">Escanee cada bulto al subirlo al camion</label>
                <input type="text" name="codigo" id="codigo" autofocus autocomplete="off" placeholder="LPN-XXXXXX-1"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-lg font-mono focus:ring-2 focus:ring-orange-400 focus:outline-none">
            </div>
            <button type="submit" class="bg-orange-600 text-white rounded-lg px-6 py-2.5 font-medium hover:bg-orange-500">Escanear</button>
        </form>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-5">
        <?= card_header('truck', 'Motivo, camion y nota', 'orange') ?>
        <?php if ($abierta && puede_armar_salida()): ?>
            <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/datos" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                <?php $transporte = $t; $motivoActual = $orden->motivo(); $notaActual = $orden->nota(); require __DIR__ . '/_transporte.php'; ?>
                <button type="submit" class="inline-flex items-center gap-2 bg-white border border-orange-300 text-orange-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-orange-50"><?= icon_small('check') ?>Guardar datos</button>
            </form>
        <?php else: ?>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <?php foreach ([
                    'Motivo' => $orden->motivo(),
                    'Empresa de transporte' => $t->empresa,
                    'Placa' => $t->placa,
                    'Chofer' => $t->chofer,
                    'Cedula del chofer' => $t->cedula,
                ] as $etiqueta => $valor): ?>
                    <div><dt class="text-zinc-500"><?= $etiqueta ?></dt><dd class="text-black"><?= $h($valor !== null && $valor !== '' ? $valor : '—') ?></dd></div>
                <?php endforeach; ?>
                <div class="sm:col-span-2"><dt class="text-zinc-500">Nota / comentario</dt><dd class="text-black whitespace-pre-line"><?= $h($orden->nota() ?? '—') ?></dd></div>
            </dl>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow p-5 overflow-x-auto">
        <?= card_header('box', 'Bultos (' . $p['total'] . ')', 'indigo') ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-3">Etiqueta</th>
                    <th class="py-2 pr-3">Ubicacion</th>
                    <th class="py-2">Subio al camion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $g): ?>
                    <tr class="bg-slate-50">
                        <td colspan="3" class="py-1.5 px-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($g['cargaId'] !== null): ?><a href="/cargas/<?= $h($g['cargaId']) ?>" class="font-semibold text-black hover:underline"><?= $h($g['tracking']) ?></a><?php else: ?><span class="font-semibold"><?= $h($g['tracking']) ?></span><?php endif; ?>
                                <span class="text-zinc-600">· <?= $h($g['cliente']) ?> · Proveedor: <?= $h($g['proveedor'] !== '' ? $g['proveedor'] : '—') ?></span>
                                <span class="<?= $g['escaneados'] < count($g['lineas']) ? 'text-red-600' : 'text-emerald-700' ?>">· <?= $g['escaneados'] ?>/<?= count($g['lineas']) ?> escaneados</span>
                                <?php if ($abierta && puede_armar_salida() && count($grupos) > 1 && $g['cargaId'] !== null): ?>
                                    <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/quitar" class="ml-auto"
                                          onsubmit="return confirm('¿Quitar la carga <?= $h($g['tracking']) ?> de la orden? Sus bultos vuelven a estar disponibles en bodega.');">
                                        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                                        <input type="hidden" name="carga_id" value="<?= $h($g['cargaId']) ?>">
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Quitar de la orden</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($g['lineas'] as $l): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-3 font-mono"><?= $h($l['codigo']) ?></td>
                            <td class="py-1.5 pr-3"><?= $h($l['ubicacion'] ?? '—') ?></td>
                            <td class="py-1.5"><?= $l['escaneado'] !== null ? '<span class="text-emerald-700">Si · ' . $h(date('d/m H:i', strtotime((string) $l['escaneado']))) . '</span>' : '<span class="text-red-600">Falta</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if ($grupos === []): ?>
                    <tr><td colspan="3" class="py-3 text-zinc-500">Devolucion registrada antes de las ordenes: ver la carga para el detalle.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow p-5">
        <?= card_header('receipt', 'Documentos (' . count($documentos) . ')', 'orange') ?>
        <div class="flex flex-wrap gap-2 mb-3">
            <?php foreach ($documentos as $doc): ?>
                <a href="/<?= $h($doc['ruta']) ?>" target="_blank" data-doc-ver data-doc-titulo="<?= $h($doc['nombre']) ?>"
                   title="Subido <?= $h(date('d/m/Y H:i', strtotime($doc['fecha']))) ?><?= $doc['usuario'] ? ' por ' . $h($doc['usuario']) : '' ?>"
                   class="inline-flex items-center gap-1.5 text-xs bg-slate-100 hover:bg-orange-100 text-zinc-800 rounded-full px-3 py-1">
                    <?= icon_small('receipt', 'w-3.5 h-3.5') ?><?= $h($doc['nombre']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($documentos === []): ?><span class="text-sm text-zinc-500">Sin documentos todavia.</span><?php endif; ?>
        </div>
        <?php if (puede_armar_salida()): ?>
            <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/documentos" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                <input type="file" name="documentos[]" multiple required accept="<?= $h($extensiones) ?>"
                       class="text-sm text-zinc-700 file:mr-2 file:rounded-lg file:border-0 file:bg-orange-100 file:px-3 file:py-1.5 file:text-orange-800 hover:file:bg-orange-200">
                <button type="submit" class="inline-flex items-center gap-1.5 bg-white border border-orange-300 text-orange-700 rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-orange-50"><?= icon_small('plus') ?>Adjuntar</button>
                <span class="text-xs text-zinc-500">Factura, nota de credito, guia... PDF, imagen, Word, Excel; maximo 10 MB c/u.</span>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($abierta): ?>
        <div class="flex flex-wrap items-center gap-3">
            <?php if (puede_confirmar_salida()): ?>
                <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/confirmar"
                      onsubmit="return confirm('¿Confirmar que el camion salio? Los <?= $p['total'] ?> bultos quedan devueltos al proveedor y salen del inventario. Se avisa a cada cliente.');">
                    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                    <button type="submit" <?= $p['escaneados'] < $p['total'] || $p['total'] === 0 ? 'disabled title="Faltan bultos por escanear"' : '' ?>
                            class="inline-flex items-center gap-2 bg-orange-600 text-white rounded-lg px-5 py-2.5 font-medium hover:bg-orange-500 disabled:opacity-40 disabled:cursor-not-allowed">
                        <?= icon_small('truck') ?>Confirmar devolucion (el camion salio)
                    </button>
                </form>
                <?php if ($p['escaneados'] < $p['total']): ?>
                    <span class="text-sm text-red-600">Faltan <?= $p['total'] - $p['escaneados'] ?> bulto(s) por escanear.</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (puede_armar_salida()): ?>
                <form method="post" action="/devoluciones/<?= $h($orden->id()) ?>/cancelar" class="ml-auto" onsubmit="return confirm('¿Cancelar la orden de devolucion? Los bultos vuelven a estar disponibles en bodega.');">
                    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                    <button type="submit" class="bg-slate-200 text-zinc-800 rounded-lg px-5 py-2.5 font-medium hover:bg-slate-300">Cancelar orden</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Fotos por carga al subirla al camion (como en las salidas).
$fotosTitulo = 'Fotos de los bultos';
$fotosAyuda = 'Bodega toma fotos de cada carga al subirla al camion (desde el celular o la PDA se abre la camara).';
$fotosGrupos = [];
foreach ($grupos as $g) {
    if ($g['cargaId'] === null) {
        continue;
    }
    $fotosGrupos[] = [
        'titulo' => $g['tracking'] . ' · ' . $g['cliente'],
        'subtitulo' => 'Proveedor: ' . ($g['proveedor'] !== '' ? $g['proveedor'] : '—'),
        'urlSubir' => $abierta ? '/bodega/api/devoluciones/' . $orden->id() . '/fotos?carga=' . $g['cargaId'] : null,
        'fotos' => array_values(array_filter($fotos, static fn ($f) => $f['cargaId'] === $g['cargaId'])),
    ];
}
$fotosVolver = '/devoluciones/' . $orden->id();
require __DIR__ . '/../evidencia/_fotos.php';
?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
