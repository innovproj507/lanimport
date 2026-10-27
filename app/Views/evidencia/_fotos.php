<?php
/**
 * Galeria de fotos de evidencia por carga del cliente, en grupos (p.ej. "Recepcion" y cada
 * salida en el detalle de la carga; o una carga por grupo en la salida).
 *
 * @var string $fotosTitulo
 * @var string $fotosAyuda
 * @var array<int, array{titulo: ?string, subtitulo?: ?string, urlSubir: ?string,
 *     fotos: array<int, array{id: string, ruta: string, usuario: ?string, fecha: string}>}> $fotosGrupos
 *     urlSubir: endpoint bodega/api/... para agregar fotos a ese grupo (null = solo ver)
 * @var string $fotosVolver pagina a la que vuelve al borrar
 */
use App\Libraries\Csrf;

$rolFotos = $_SESSION['usuario_rol'] ?? null;
$puedeSubirFotos = in_array($rolFotos, ['operaciones', 'bodega', 'gerente', 'admin'], true);
$puedeBorrarFotos = in_array($rolFotos, ['gerente', 'admin'], true);
$totalFotos = array_sum(array_map(static fn (array $g) => count($g['fotos']), $fotosGrupos));
?>
<div class="bg-white rounded-xl shadow p-6 mt-6">
    <?= card_header('camera', $fotosTitulo . ($totalFotos > 0 ? ' (' . $totalFotos . ')' : ''), 'teal') ?>
    <p class="text-xs text-zinc-500 -mt-2 mb-4"><?= htmlspecialchars($fotosAyuda) ?></p>

    <?php if ($fotosGrupos === []): ?>
        <p class="text-sm text-zinc-500">Todavia no hay fotos.</p>
    <?php endif; ?>

    <div class="space-y-5">
        <?php foreach ($fotosGrupos as $grupo): ?>
            <section class="<?= count($fotosGrupos) > 1 ? 'border-t border-slate-100 pt-4 first:border-0 first:pt-0' : '' ?>">
                <?php if ($grupo['titulo'] !== null || ($puedeSubirFotos && $grupo['urlSubir'] !== null)): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div>
                            <?php if ($grupo['titulo'] !== null): ?>
                                <h3 class="text-sm font-semibold text-black"><?= htmlspecialchars($grupo['titulo']) ?> <span class="text-zinc-400 font-normal">· <?= count($grupo['fotos']) ?> foto(s)</span></h3>
                            <?php endif; ?>
                            <?php if (!empty($grupo['subtitulo'])): ?>
                                <p class="text-xs text-zinc-500"><?= htmlspecialchars($grupo['subtitulo']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($puedeSubirFotos && $grupo['urlSubir'] !== null): ?>
                            <div data-fotos-subir="<?= htmlspecialchars($grupo['urlSubir']) ?>" data-csrf="<?= htmlspecialchars(Csrf::token()) ?>" class="flex items-center gap-3">
                                <span data-fotos-estado class="text-sm text-zinc-600"></span>
                                <label class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-slate-100 transition cursor-pointer">
                                    <?= icon_small('camera') ?><span>Agregar fotos</span>
                                    <input type="file" accept="image/*" multiple class="hidden">
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($grupo['fotos'] === []): ?>
                    <p class="text-sm text-zinc-400">Sin fotos.</p>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" data-fotos-galeria>
                        <?php foreach ($grupo['fotos'] as $foto): ?>
                            <figure class="relative group">
                                <?php // Se abre en el visor (modal) de abajo; el enlace queda para abrir en otra pestaña con clic central. ?>
                                <a href="/<?= htmlspecialchars($foto['ruta']) ?>" data-foto-ver
                                   data-foto-texto="<?= htmlspecialchars(($grupo['titulo'] !== null ? $grupo['titulo'] . ' · ' : '') . (new DateTimeImmutable($foto['fecha']))->format('d/m/Y H:i') . ($foto['usuario'] ? ' · ' . $foto['usuario'] : '')) ?>">
                                    <img src="/<?= htmlspecialchars($foto['ruta']) ?>" alt="Foto de evidencia" loading="lazy"
                                         class="w-full h-32 object-cover rounded-lg border border-slate-200 hover:opacity-90 transition">
                                </a>
                                <figcaption class="text-xs text-zinc-500 mt-1 truncate">
                                    <?= htmlspecialchars((new DateTimeImmutable($foto['fecha']))->format('d/m/Y H:i')) ?><?= $foto['usuario'] ? ' · ' . htmlspecialchars($foto['usuario']) : '' ?>
                                </figcaption>
                                <?php if ($puedeBorrarFotos): ?>
                                    <form method="post" action="/fotos-evidencia/<?= htmlspecialchars($foto['id']) ?>/eliminar"
                                          onsubmit="return confirm('¿Eliminar esta foto de evidencia?');" class="absolute top-1 right-1">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="volver" value="<?= htmlspecialchars($fotosVolver) ?>">
                                        <button type="submit" title="Eliminar foto"
                                                class="w-8 h-8 rounded-lg bg-white/90 text-red-600 flex items-center justify-center shadow opacity-0 group-hover:opacity-100 focus:opacity-100 transition">
                                            <?= icon_small('trash') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<!-- Visor de fotos: anterior/siguiente dentro del mismo grupo (carga) -->
<div id="foto-visor" class="hidden fixed inset-0 z-[60] bg-black/90 flex-col items-center justify-center select-none" role="dialog" aria-modal="true">
    <div class="absolute top-0 inset-x-0 flex items-center justify-between gap-3 px-4 py-3 text-white">
        <p class="text-sm truncate"><span id="foto-visor-contador" class="font-semibold mr-2"></span><span id="foto-visor-texto" class="text-white/80"></span></p>
        <div class="flex items-center gap-2 shrink-0">
            <a id="foto-visor-original" href="#" target="_blank" rel="noopener" class="text-xs text-white/80 hover:text-white underline">Abrir original</a>
            <button type="button" data-visor-cerrar title="Cerrar (Esc)" class="w-10 h-10 rounded-full flex items-center justify-center text-2xl hover:bg-white/10">&times;</button>
        </div>
    </div>
    <img id="foto-visor-img" src="" alt="Foto de evidencia" class="max-w-[94vw] max-h-[82vh] object-contain rounded-lg shadow-2xl">
    <button type="button" data-visor-paso="-1" title="Anterior" class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/15 text-white text-3xl hover:bg-white/25 flex items-center justify-center">&#8249;</button>
    <button type="button" data-visor-paso="1" title="Siguiente" class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/15 text-white text-3xl hover:bg-white/25 flex items-center justify-center">&#8250;</button>
</div>
<script>
(function () {
    var visor = document.getElementById('foto-visor');
    var img = document.getElementById('foto-visor-img');
    var fotos = [];
    var actual = 0;

    function mostrar(i) {
        actual = (i + fotos.length) % fotos.length;
        var a = fotos[actual];
        img.src = a.getAttribute('href');
        document.getElementById('foto-visor-texto').textContent = a.getAttribute('data-foto-texto') || '';
        document.getElementById('foto-visor-contador').textContent = (actual + 1) + ' / ' + fotos.length;
        document.getElementById('foto-visor-original').href = a.getAttribute('href');
        visor.querySelectorAll('[data-visor-paso]').forEach(function (b) { b.classList.toggle('hidden', fotos.length < 2); });
    }

    function cerrar() {
        visor.classList.add('hidden');
        visor.classList.remove('flex');
        img.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-foto-ver]');

        // Ctrl/clic central: abrir en otra pestaña como antes.
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
            return;
        }

        e.preventDefault();
        fotos = Array.prototype.slice.call(a.closest('[data-fotos-galeria]').querySelectorAll('[data-foto-ver]'));
        mostrar(fotos.indexOf(a));
        visor.classList.remove('hidden');
        visor.classList.add('flex');
        document.body.style.overflow = 'hidden';
    });

    visor.addEventListener('click', function (e) {
        var paso = e.target.closest('[data-visor-paso]');

        if (paso) {
            mostrar(actual + parseInt(paso.getAttribute('data-visor-paso'), 10));
        } else if (e.target === visor || e.target.closest('[data-visor-cerrar]')) {
            cerrar();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (visor.classList.contains('hidden')) {
            return;
        }

        if (e.key === 'Escape') { cerrar(); }
        if (e.key === 'ArrowLeft') { mostrar(actual - 1); }
        if (e.key === 'ArrowRight') { mostrar(actual + 1); }
    });

    // Celular/tablet: deslizar para pasar de foto.
    var inicioX = null;
    visor.addEventListener('touchstart', function (e) { inicioX = e.touches[0].clientX; }, { passive: true });
    visor.addEventListener('touchend', function (e) {
        if (inicioX === null || fotos.length < 2) { return; }
        var dx = e.changedTouches[0].clientX - inicioX;
        if (Math.abs(dx) > 50) { mostrar(actual + (dx < 0 ? 1 : -1)); }
        inicioX = null;
    });
})();
</script>
<script src="/assets/js/fotos-evidencia.js?v=<?= @filemtime(FCPATH . 'assets/js/fotos-evidencia.js') ?>"></script>
