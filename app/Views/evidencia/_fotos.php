<?php
/**
 * Galeria de fotos de evidencia (recepcion de una carga o salida de una orden).
 *
 * @var array<int, array{id: string, ruta: string, usuario: ?string, fecha: string}> $fotos
 * @var string $fotosTitulo
 * @var string $fotosAyuda
 * @var string $fotosUrlSubir endpoint bodega/api/{cargas|salidas}/{id}/fotos
 * @var string $fotosVolver pagina a la que vuelve al borrar
 */
use App\Libraries\Csrf;

$rolFotos = $_SESSION['usuario_rol'] ?? null;
$puedeSubirFotos = in_array($rolFotos, ['operaciones', 'bodega', 'gerente', 'admin'], true);
$puedeBorrarFotos = in_array($rolFotos, ['gerente', 'admin'], true);
?>
<div class="bg-white rounded-xl shadow p-6 mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
        <?= card_header('camera', $fotosTitulo . ($fotos !== [] ? ' (' . count($fotos) . ')' : ''), 'teal') ?>
        <?php if ($puedeSubirFotos): ?>
            <div data-fotos-subir="<?= htmlspecialchars($fotosUrlSubir) ?>" data-csrf="<?= htmlspecialchars(Csrf::token()) ?>" class="flex items-center gap-3">
                <span data-fotos-estado class="text-sm text-zinc-600"></span>
                <label class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition cursor-pointer">
                    <?= icon_small('camera') ?><span>Agregar fotos</span>
                    <input type="file" accept="image/*" multiple class="hidden">
                </label>
            </div>
        <?php endif; ?>
    </div>
    <p class="text-xs text-zinc-500 mb-4"><?= htmlspecialchars($fotosAyuda) ?></p>

    <?php if ($fotos === []): ?>
        <p class="text-sm text-zinc-500">Todavia no hay fotos.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <?php foreach ($fotos as $foto): ?>
                <figure class="relative group">
                    <a href="/<?= htmlspecialchars($foto['ruta']) ?>" target="_blank" rel="noopener">
                        <img src="/<?= htmlspecialchars($foto['ruta']) ?>" alt="Foto de evidencia" loading="lazy"
                             class="w-full h-36 object-cover rounded-lg border border-slate-200 hover:opacity-90 transition">
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
</div>
<script src="/assets/js/fotos-evidencia.js?v=<?= @filemtime(FCPATH . 'assets/js/fotos-evidencia.js') ?>"></script>
