<?php
/**
 * Campos del camion y la nota de una orden de devolucion (crear o editar).
 *
 * @var \Courier\Devolucion\Domain\DatosTransporte|null $transporte valores actuales
 * @var string|null $motivoActual
 * @var string|null $notaActual
 */
$t = $transporte ?? new \Courier\Devolucion\Domain\DatosTransporte();
$campo = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none';
$hh = static fn ($v): string => htmlspecialchars((string) $v);
?>
<div class="grid gap-4 md:grid-cols-2">
    <label class="block md:col-span-2">
        <span class="block text-sm font-medium text-black mb-1">Motivo de la devolucion <span class="text-red-600">*</span></span>
        <input type="text" name="motivo" required minlength="3" maxlength="500" value="<?= $hh($motivoActual ?? '') ?>"
               placeholder="Ej.: el cliente ya no quiere la mercancia; pedido equivocado; llego danada..." class="<?= $campo ?>">
    </label>
    <label class="block">
        <span class="block text-sm font-medium text-black mb-1">Empresa de transporte</span>
        <input type="text" name="transporte_empresa" maxlength="150" value="<?= $hh($t->empresa) ?>" class="<?= $campo ?>">
    </label>
    <label class="block">
        <span class="block text-sm font-medium text-black mb-1">Placa del camion</span>
        <input type="text" name="placa" maxlength="30" value="<?= $hh($t->placa) ?>" class="<?= $campo ?> uppercase">
    </label>
    <label class="block">
        <span class="block text-sm font-medium text-black mb-1">Chofer</span>
        <input type="text" name="chofer" maxlength="150" value="<?= $hh($t->chofer) ?>" class="<?= $campo ?>">
    </label>
    <label class="block">
        <span class="block text-sm font-medium text-black mb-1">Cedula del chofer</span>
        <input type="text" name="cedula_chofer" maxlength="40" value="<?= $hh($t->cedula) ?>" class="<?= $campo ?>">
    </label>
    <label class="block md:col-span-2">
        <span class="block text-sm font-medium text-black mb-1">Nota / comentario</span>
        <textarea name="nota" rows="3" maxlength="2000" placeholder="Instrucciones, quien autorizo la devolucion, observaciones de la mercancia..."
                  class="<?= $campo ?>"><?= $hh($notaActual ?? '') ?></textarea>
    </label>
</div>
