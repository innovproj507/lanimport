<?php
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var array<int, \Courier\Pais\Domain\Pais> $paises */
$cliente = $cliente ?? null;
$paises = $paises ?? [];
?>
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($cliente?->nombre() ?? '') ?>"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Apellido</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($cliente?->apellido() ?? '') ?>"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
</div>
<div>
    <label class="block text-sm font-medium text-zinc-700 mb-1">Empresa</label>
    <input type="text" name="empresa" value="<?= htmlspecialchars($cliente?->empresa() ?? '') ?>"
           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
</div>
<div>
    <label class="block text-sm font-medium text-zinc-700 mb-1">Correo electronico</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($cliente ? (string) $cliente->email() : '') ?>"
           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
</div>
<div>
    <label class="block text-sm font-medium text-zinc-700 mb-1">Telefono (opcional)</label>
    <input type="text" name="telefono" value="<?= htmlspecialchars($cliente?->telefono() ?? '') ?>"
           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
</div>
<div>
    <label class="block text-sm font-medium text-zinc-700 mb-1">Direccion (opcional)</label>
    <input type="text" name="direccion" value="<?= htmlspecialchars($cliente?->direccion() ?? '') ?>"
           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
</div>
<div>
    <label class="block text-sm font-medium text-zinc-700 mb-1">Pais</label>
    <select name="pais" required class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        <option value="">Seleccione pais...</option>
        <?php foreach ($paises as $pais): ?>
            <option value="<?= htmlspecialchars($pais->nombre()) ?>" <?= $cliente?->pais() === $pais->nombre() ? 'selected' : '' ?>>
                <?= htmlspecialchars($pais->nombre()) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
