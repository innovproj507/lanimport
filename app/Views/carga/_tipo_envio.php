<?php
/**
 * Tipo de envio (como sale la carga hacia el cliente). Obligatorio.
 * Espera $tipoEnvioActual (string|null): el valor marcado (null = ninguno, hay que elegir).
 */

use Courier\Carga\Domain\TipoEnvio;

$tipoEnvioActual = $tipoEnvioActual ?? null;
?>
<div class="mb-4">
    <label class="block text-sm font-medium text-zinc-700 mb-1">Tipo de envio al cliente <span class="text-red-600">*</span></label>
    <div class="grid grid-cols-3 gap-2 max-w-xl">
        <?php foreach (TipoEnvio::cases() as $tipo): ?>
            <label class="cursor-pointer">
                <input type="radio" name="tipo_envio" value="<?= $tipo->value ?>" class="peer sr-only" required
                       <?= $tipoEnvioActual === $tipo->value ? 'checked' : '' ?>>
                <span class="flex items-center justify-center gap-2 rounded-lg border-2 border-zinc-300 bg-white px-3 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-amber-400 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-black peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400">
                    <span class="text-lg leading-none"><?= $tipo->icono() ?></span><?= $tipo->label() ?>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
</div>
