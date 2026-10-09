<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView $view */
/** @var bool $lpnsGenerados */
/** @var \Courier\Lpn\Domain\EtiquetaLpnConfig $etiquetaConfig */
/** @var string|null $error */
/** @var bool $tieneActaRecepcion */
/** @var array<int, array{destinatario: string, asunto: string, estado: string, error: ?string, fecha: string}> $correos */
/** @var array{tipo: string, texto: string}|null $aviso */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Carga registrada';
$pageSubtitle = 'Tracking: ' . $view->trackingNumero;
$backHref = '/cargas/ingreso';
$headerActions = header_button('/cargas/' . $view->cargaId . '/acta-recepcion', 'receipt', $tieneActaRecepcion ? 'Acta de recepcion' : 'Acta de recepcion (pendiente)', $tieneActaRecepcion ? 'emerald' : 'blue')
    . header_button('/cargas/' . $view->cargaId . '/editar', 'pencil', 'Editar', 'amber');
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($aviso !== null): ?>
    <?php
    $estiloAviso = [
        'ok' => ['bg-emerald-50 border-emerald-300 text-emerald-800', 'check', 'bg-emerald-500'],
        'error' => ['bg-red-50 border-red-300 text-red-800', 'x', 'bg-red-500'],
        'info' => ['bg-sky-50 border-sky-300 text-sky-800', 'bell', 'bg-sky-500'],
    ][$aviso['tipo']] ?? ['bg-slate-50 border-slate-300 text-slate-800', 'bell', 'bg-slate-500'];
    ?>
    <div id="aviso-correo" class="flex items-start gap-3 border rounded-xl px-4 py-3 mb-4 shadow-sm <?= $estiloAviso[0] ?>">
        <span class="w-8 h-8 rounded-full text-white flex items-center justify-center shrink-0 <?= $estiloAviso[2] ?>"><?= icon_small($estiloAviso[1]) ?></span>
        <div class="flex-1 text-sm pt-1.5">
            <?= htmlspecialchars($aviso['texto']) ?>
            <?php if ($aviso['tipo'] === 'error' && $esAdminOGerente): ?>
                <a href="/configuracion/correo" class="underline font-medium ml-1">Revisar configuracion de correo</a>
            <?php endif; ?>
        </div>
        <button type="button" onclick="this.parentElement.remove()" title="Cerrar" class="text-current/60 hover:opacity-70 pt-1"><?= icon_small('x') ?></button>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <div class="flex items-center gap-2 justify-between">
            <div class="flex items-center gap-2 flex-wrap">
                <?= badge($view->estadoActualLabel, 'amber') ?>
                <?php if ($view->tipoServicio !== null): ?>
                    <span class="text-sm text-zinc-500">Servicio: <?= htmlspecialchars(ucfirst($view->tipoServicio)) ?></span>
                <?php endif; ?>
                <?php
                $bultoEstadoColores = ['receiving' => 'amber', 'available' => 'emerald', 'in_repack' => 'indigo', 'reserved' => 'blue', 'quarantine' => 'red', 'consumed' => 'slate'];
                ?>
                <?php foreach ($view->bultosPorEstado as $grupo): ?>
                    <?= badge($grupo['cantidad'] . ' ' . $grupo['estadoLabel'], $bultoEstadoColores[$grupo['estado']] ?? 'slate') ?>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($lpnsGenerados): ?>
                    <?= header_button('/cargas/' . $view->cargaId . '/recepcion', 'eye', 'Ver recepcion de LPNs', 'amber') ?>
                    <?php if ($esAdminOGerente): ?>
                        <form method="post" action="/cargas/<?= htmlspecialchars($view->cargaId) ?>/lpns/eliminar"
                              onsubmit="return confirm('Esto borrara todos los LPNs generados para esta carga (solo funciona si ninguno fue recibido aun). Podras volver a generarlos despues. ¿Continuar?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button type="submit" class="inline-flex items-center gap-2 bg-white border border-red-300 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-50 transition">
                                <?= icon_small('trash') ?><span>Borrar LPNs y regenerar</span>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="/cargas/<?= htmlspecialchars($view->cargaId) ?>/lpns/generar">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                            <?= icon_small('box') ?><span>Generar LPNs</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-sm text-zinc-700">
            <strong>Fecha de ingreso:</strong> <?= htmlspecialchars($view->fechaIngreso) ?>
        </p>

        <?php if ($view->proveedor): ?>
            <p class="text-sm text-zinc-700">
                <strong>Proveedor:</strong> <?= htmlspecialchars($view->proveedor) ?>
            </p>
        <?php endif; ?>

        <?php if ($view->fechaEstimadaLlegada): ?>
            <p class="text-sm text-zinc-700">
                <strong>Fecha estimada de llegada:</strong> <?= htmlspecialchars($view->fechaEstimadaLlegada) ?>
            </p>
        <?php endif; ?>

        <?php if ($view->numeroContenedor): ?>
            <p class="text-sm text-zinc-700">
                <strong>Numero de contenedor:</strong> <?= htmlspecialchars($view->numeroContenedor) ?>
            </p>
        <?php endif; ?>

        <?php if ($view->notas): ?>
            <div class="pt-2">
                <p class="text-sm font-medium text-black mb-1">Notas y Observaciones</p>
                <p class="text-sm text-zinc-600 whitespace-pre-line"><?= htmlspecialchars($view->notas) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($view->facturaProveedorRuta): ?>
            <p class="text-sm text-zinc-700">
                <strong>Factura del proveedor:</strong>
                <a href="/<?= htmlspecialchars($view->facturaProveedorRuta) ?>" target="_blank" class="text-amber-600 hover:underline">
                    <?= htmlspecialchars($view->facturaProveedorNombre ?? 'Ver archivo') ?>
                </a>
            </p>
        <?php endif; ?>

        <h2 class="font-semibold text-black pt-4 border-t border-slate-200 flex items-center gap-2"><?= icon_small('clock', 'w-4 h-4 text-zinc-500') ?> Historial</h2>
        <ol class="space-y-3">
            <?php foreach ($view->historial as $entry): ?>
                <li class="flex items-start gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full bg-amber-400"></span>
                    <div>
                        <p class="text-sm font-medium text-black"><?= htmlspecialchars($entry['estadoLabel']) ?></p>
                        <p class="text-xs text-zinc-500"><?= htmlspecialchars($entry['fecha']) ?></p>
                        <?php if ($entry['comentario']): ?>
                            <p class="text-xs text-zinc-500"><?= htmlspecialchars($entry['comentario']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <?php if ($esAdminOGerente): ?>
    <div class="bg-white rounded-xl shadow p-6">
        <?php $volver = "/cargas/" . $view->cargaId; require __DIR__ . "/../lpn/_plantilla_etiqueta.php"; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center justify-center">
        <h2 class="font-semibold text-black mb-1 flex items-center gap-2"><?= icon_small('receipt', 'w-4 h-4 text-zinc-500') ?> Codigo de la carga</h2>
        <p class="text-xs text-zinc-500 text-center mb-3">
            Identifica la carga completa (tracking). No es la etiqueta de LPN/bulto
            <?php if ($lpnsGenerados): ?>
                &mdash; esas estan en <a href="/cargas/<?= htmlspecialchars($view->cargaId) ?>/recepcion" class="text-amber-600 hover:underline">Recepcion de LPNs</a>.
            <?php else: ?>
                .
            <?php endif; ?>
        </p>
        <div class="bg-white rounded-lg p-4">
            <img id="etiqueta-carga-img" src="/uploads/etiquetas/<?= htmlspecialchars($view->trackingNumero) ?>.png" alt="Codigo de barras de la carga <?= htmlspecialchars($view->trackingNumero) ?>"
                 class="max-w-full">
        </div>
        <button type="button" id="btn-imprimir-etiqueta" class="mt-3 inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">
            <?= icon_small('printer') ?><span>Imprimir etiqueta</span>
        </button>
        <p id="etiqueta-print-msg" class="text-xs mt-2 hidden"></p>
    </div>
</div>

<script src="/assets/js/local-print.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-imprimir-etiqueta');
    var msg = document.getElementById('etiqueta-print-msg');
    var img = document.getElementById('etiqueta-carga-img');

    btn.addEventListener('click', function () {
        var printerName = LocalPrint.getImpresoraEtiquetas();

        msg.classList.remove('hidden', 'text-red-600', 'text-emerald-600');

        if (!printerName) {
            msg.textContent = 'Configura una impresora de etiquetas en Impresoras.';
            msg.classList.add('text-red-600');
            return;
        }

        msg.textContent = 'Imprimiendo...';

        LocalPrint.imprimirImagen(printerName, window.location.origin + img.getAttribute('src'))
            .then(function () {
                msg.textContent = 'Enviado a la impresora.';
                msg.classList.add('text-emerald-600');
            })
            .catch(function (err) {
                msg.textContent = err && err.message ? err.message : String(err);
                msg.classList.add('text-red-600');
            });
    });
});
</script>

<?php if (!empty($view->lineas)): ?>
<div class="bg-white rounded-xl shadow p-6 mt-6">
    <?= card_header('box', 'Detalle de la carga', 'indigo') ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Descripcion</th>
                    <th class="py-2 pr-4">Marca</th>
                    <th class="py-2 pr-4">UoM</th>
                    <th class="py-2 pr-4">Cantidad</th>
                    <th class="py-2 pr-4">Dimensiones (cm)</th>
                    <th class="py-2 pr-4">p3</th>
                    <th class="py-2 pr-4">Peso (kg)</th>
                    <th class="py-2">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($view->lineas as $linea): ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 text-black font-medium"><?= htmlspecialchars($linea['descripcion']) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($linea['marca'] ?? '—') ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($linea['unidadMedida']) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $linea['cantidad']) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($linea['ancho'] . ' x ' . $linea['alto'] . ' x ' . $linea['largo']) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars(number_format($linea['cubicajePies'], 4)) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $linea['peso']) ?></td>
                        <td class="py-2 text-zinc-700"><?= htmlspecialchars(number_format($linea['valor'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow p-6 mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <?= card_header('mail', 'Correos al cliente', 'amber') ?>
        <?php if (in_array($rolActual, ['operaciones', 'gerente', 'admin'], true)): ?>
            <form method="post" action="/cargas/<?= htmlspecialchars($view->cargaId) ?>/reenviar-correo">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">
                    <?= icon_small('mail') ?><span><?= $correos === [] ? 'Enviar correo al cliente' : 'Reenviar correo' ?></span>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($correos === []): ?>
        <p class="text-sm text-zinc-500">No se ha enviado ningun correo al cliente por esta carga.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($correos as $correo): ?>
                <li class="py-3 flex flex-wrap items-start justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <p class="text-black font-medium"><?= htmlspecialchars($correo['asunto']) ?></p>
                        <p class="text-xs text-zinc-500">Para <?= htmlspecialchars($correo['destinatario']) ?> · <?= htmlspecialchars((new DateTimeImmutable($correo['fecha']))->format('d/m/Y H:i')) ?></p>
                        <?php if ($correo['estado'] === 'fallido' && $correo['error']): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($correo['error']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?= badge(['enviado' => 'Enviado', 'fallido' => 'Fallido', 'pendiente' => 'Pendiente'][$correo['estado']] ?? $correo['estado'], ['enviado' => 'emerald', 'fallido' => 'red'][$correo['estado']] ?? 'slate') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
