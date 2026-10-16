<?php
use Courier\Carga\Domain\ValueObject\DatosEntrega;
/** @var DatosEntrega|null $entrega datos guardados (al editar) o null (ingreso nuevo) */
$entrega ??= null;
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
$tiposMarcados = $entrega?->tiposMercancia ?? [];
?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <?= card_header('truck', 'Datos de entrega', 'sky') ?>
    <p class="text-xs text-zinc-500 -mt-2 mb-4">Lo que llena bodega al recibir la mercancia: quien la trajo y quien la entrego. Opcional. "Verificado por" sera el usuario que registra la carga.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre de la empresa</label>
            <input type="text" name="entrega_empresa_transporte" maxlength="190" value="<?= htmlspecialchars($entrega?->empresaTransporte ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del chofer</label>
            <input type="text" name="entrega_chofer" maxlength="190" value="<?= htmlspecialchars($entrega?->chofer ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Placa</label>
            <input type="text" name="entrega_placa" maxlength="20" value="<?= htmlspecialchars($entrega?->placa ?? '') ?>" class="<?= $claseInput ?> uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Entregado por</label>
            <input type="text" name="entrega_por" maxlength="190" value="<?= htmlspecialchars($entrega?->entregadoPor ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Cedula</label>
            <input type="text" name="entrega_cedula" maxlength="40" value="<?= htmlspecialchars($entrega?->cedula ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div>
            <span class="block text-sm font-medium text-zinc-700 mb-1">Mercancia fisicamente</span>
            <div class="flex flex-wrap gap-2">
                <?php foreach (DatosEntrega::TIPOS_MERCANCIA as $tipo => $etiqueta): ?>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 border border-zinc-300 rounded-lg px-3 py-2 cursor-pointer has-[:checked]:bg-amber-50 has-[:checked]:border-amber-400">
                        <input type="checkbox" name="entrega_tipos_mercancia[]" value="<?= $tipo ?>" <?= in_array($tipo, $tiposMarcados, true) ? 'checked' : '' ?>
                               class="w-4 h-4 rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
                        <?= $etiqueta ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
