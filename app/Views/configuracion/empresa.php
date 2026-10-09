<?php
use App\Libraries\Csrf;
/** @var \Courier\Configuracion\Domain\DatosEmpresa $empresa */
/** @var array<string, mixed>|null $entrada */
/** @var string|null $error */
/** @var string|null $mensaje */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Datos de la empresa';
$pageSubtitle = 'Aparecen en los documentos impresos (acta de recepcion)';
$backHref = '/configuracion';
require __DIR__ . '/../partials/header.php';

$valor = static fn (string $campo, ?string $guardado): string => (string) ($entrada !== null ? ($entrada[$campo] ?? '') : ($guardado ?? ''));
$inputClass = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
?>

<?php require __DIR__ . '/_mensajes.php'; ?>

<form method="post" action="/configuracion/empresa" class="bg-white rounded-xl shadow-sm p-6 max-w-2xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('building', 'Empresa', 'indigo') ?>

    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Razon social</label>
        <input type="text" name="nombre" required maxlength="190" value="<?= htmlspecialchars($valor('nombre', $empresa->nombre)) ?>" class="<?= $inputClass ?>">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">R.U.C.</label>
            <input type="text" name="ruc" maxlength="40" value="<?= htmlspecialchars($valor('ruc', $empresa->ruc)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">D.V.</label>
            <input type="text" name="dv" maxlength="5" value="<?= htmlspecialchars($valor('dv', $empresa->dv)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Clave de operaciones</label>
            <input type="text" name="clave_operaciones" maxlength="20" value="<?= htmlspecialchars($valor('clave_operaciones', $empresa->claveOperaciones)) ?>" class="<?= $inputClass ?>">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Direccion</label>
        <input type="text" name="direccion" maxlength="255" value="<?= htmlspecialchars($valor('direccion', $empresa->direccion)) ?>" class="<?= $inputClass ?>">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Telefono</label>
            <input type="text" name="telefono" maxlength="60" value="<?= htmlspecialchars($valor('telefono', $empresa->telefono)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Correo de contacto</label>
            <input type="email" name="email" maxlength="190" value="<?= htmlspecialchars($valor('email', $empresa->email)) ?>" class="<?= $inputClass ?>">
        </div>
    </div>

    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
        <?= icon_small('check') ?><span>Guardar</span>
    </button>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
