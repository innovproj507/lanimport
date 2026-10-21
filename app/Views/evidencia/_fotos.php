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
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <?php foreach ($grupo['fotos'] as $foto): ?>
                            <figure class="relative group">
                                <a href="/<?= htmlspecialchars($foto['ruta']) ?>" target="_blank" rel="noopener">
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
<script src="/assets/js/fotos-evidencia.js?v=<?= @filemtime(FCPATH . 'assets/js/fotos-evidencia.js') ?>"></script>
