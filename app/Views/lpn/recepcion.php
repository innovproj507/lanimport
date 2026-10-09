<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Domain\Carga $carga */
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
/** @var array<int, \Courier\Lpn\Domain\Ubicacion> $ubicaciones */
$pageTitle = 'Recepcion de LPNs';
$pageSubtitle = 'Tracking: ' . (string) $carga->trackingNumero();
$mapaUbicaciones = [];
foreach ($ubicaciones as $ubicacion) {
    $mapaUbicaciones[$ubicacion->id()] = $ubicacion->codigo();
}
require __DIR__ . '/../partials/header.php';

$estadoColores = [
    'receiving' => 'amber',
    'available' => 'emerald',
    'in_repack' => 'indigo',
    'reserved' => 'blue',
    'quarantine' => 'red',
    'consumed' => 'slate',
];
?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <?= card_header('search', 'Escaneo de recepcion', 'amber') ?>
        <a href="?print=1" target="_blank" class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">
            <?= icon_small('printer') ?><span>Imprimir todas las etiquetas (<?= count($lpns) ?>)</span>
        </a>
    </div>
    <div class="flex items-end gap-4 flex-wrap">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Escanear codigo de LPN</label>
            <input type="text" id="lpn-scan-input" autocomplete="off" autofocus
                   placeholder="Escanea o escribe el codigo y presiona Enter"
                   class="w-80 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Ubicacion (opcional)</label>
            <select id="lpn-ubicacion-select" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option value="">Sin ubicacion (bodega general)</option>
                <?php foreach ($ubicaciones as $ubicacion): ?>
                    <option value="<?= htmlspecialchars($ubicacion->id()) ?>"><?= htmlspecialchars($ubicacion->codigo()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p id="lpn-scan-mensaje" class="text-sm"></p>
    </div>
    <input type="hidden" id="lpn-csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" id="lpn-carga-id" value="<?= htmlspecialchars($carga->id()) ?>">
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Codigo</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 pr-4">Ubicacion</th>
                <th class="py-2">Etiqueta</th>
                <th class="py-2"></th>
            </tr>
        </thead>
        <tbody id="lpn-tbody">
            <?php foreach ($lpns as $lpn): ?>
                <tr class="border-b border-slate-200" data-codigo="<?= htmlspecialchars((string) $lpn->codigo()) ?>">
                    <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn->codigo()) ?></td>
                    <td class="py-2 pr-4 lpn-estado-cell"><?= badge($lpn->estado()->label(), $estadoColores[$lpn->estado()->value] ?? 'slate') ?></td>
                    <td class="py-2 pr-4 lpn-ubicacion-cell text-zinc-700">
                        <?= htmlspecialchars($lpn->ubicacionId() !== null ? ($mapaUbicaciones[$lpn->ubicacionId()] ?? '—') : '—') ?>
                    </td>
                    <td class="py-2">
                        <button type="button" class="lpn-ver-etiqueta-btn inline-block bg-white rounded p-1 border border-transparent hover:border-amber-400 transition"
                                data-etiqueta="/uploads/lpns/<?= htmlspecialchars((string) $lpn->codigo()) ?>.png"
                                data-codigo="<?= htmlspecialchars((string) $lpn->codigo()) ?>" title="Ver etiqueta completa">
                            <img src="/uploads/lpns/<?= htmlspecialchars((string) $lpn->codigo()) ?>.png" alt="Etiqueta LPN" class="h-10">
                        </button>
                    </td>
                    <td class="py-2 text-right">
                        <button type="button" class="lpn-imprimir-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-zinc-300 text-zinc-700 hover:bg-slate-100 transition"
                                data-etiqueta="/uploads/lpns/<?= htmlspecialchars((string) $lpn->codigo()) ?>.png" title="Imprimir etiqueta">
                            <?= icon_small('printer') ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="etiqueta-modal" class="hidden fixed inset-0 bg-black/70 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg max-w-sm w-full p-4 relative">
        <button type="button" id="etiqueta-modal-cerrar" title="Cerrar"
                class="absolute top-2 right-2 w-8 h-8 rounded-lg flex items-center justify-center text-zinc-500 hover:bg-slate-100 hover:text-black transition">
            <?= icon_small('x') ?>
        </button>
        <p id="etiqueta-modal-codigo" class="text-sm font-mono font-medium text-black mb-2 pr-8"></p>
        <img id="etiqueta-modal-img" src="" alt="Etiqueta LPN" class="w-full rounded-lg border border-zinc-200">
        <div class="flex justify-end mt-3">
            <button type="button" id="etiqueta-modal-imprimir"
                    class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">
                <?= icon_small('printer') ?><span>Imprimir</span>
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/local-print.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('lpn-tbody');

    function imprimirEtiqueta(rutaEtiqueta) {
        var printerName = LocalPrint.getImpresoraEtiquetas();

        if (!printerName) {
            alert('Configura una impresora de etiquetas en Impresoras.');
            return;
        }

        LocalPrint.imprimirImagen(printerName, window.location.origin + rutaEtiqueta)
            .catch(function (err) {
                alert(err && err.message ? err.message : String(err));
            });
    }

    tbody.addEventListener('click', function (event) {
        var btn = event.target.closest('.lpn-imprimir-btn');

        if (!btn) {
            return;
        }

        imprimirEtiqueta(btn.getAttribute('data-etiqueta'));
    });

    var modal = document.getElementById('etiqueta-modal');
    var modalImg = document.getElementById('etiqueta-modal-img');
    var modalCodigo = document.getElementById('etiqueta-modal-codigo');
    var modalImprimir = document.getElementById('etiqueta-modal-imprimir');
    var modalCerrar = document.getElementById('etiqueta-modal-cerrar');

    function abrirModal(rutaEtiqueta, codigo) {
        modalImg.setAttribute('src', rutaEtiqueta);
        modalCodigo.textContent = codigo;
        modalImprimir.setAttribute('data-etiqueta', rutaEtiqueta);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalImg.setAttribute('src', '');
    }

    tbody.addEventListener('click', function (event) {
        var btn = event.target.closest('.lpn-ver-etiqueta-btn');

        if (!btn) {
            return;
        }

        abrirModal(btn.getAttribute('data-etiqueta'), btn.getAttribute('data-codigo'));
    });

    modalCerrar.addEventListener('click', cerrarModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            cerrarModal();
        }
    });

    modalImprimir.addEventListener('click', function () {
        imprimirEtiqueta(modalImprimir.getAttribute('data-etiqueta'));
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
