<?php
use App\Libraries\Csrf;
use Courier\Carga\Domain\ActaRecepcion;
/** @var \Courier\Carga\Domain\Carga|null $carga */
/** @var ActaRecepcion|null $acta */
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var string $marcaPorDefecto */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, array{cargaId: string, trackingNumero: string, fechaIngreso: string}> $cargasDelCliente */
/** @var bool $puedeVincular */
/** @var string|null $verificadoPor */
/** @var string|null $actualizadoPor */
/** @var bool $puedeEditar */
/** @var array<string, mixed>|null $entrada */
/** @var string|null $error */
/** @var string|null $mensaje */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = $acta !== null ? 'Acta ' . $acta->codigo() : 'Nueva recepcion';
$pageSubtitle = $carga !== null ? 'Tracking: ' . $carga->trackingNumero() : ($acta !== null ? 'Sin carga asociada todavia' : 'Llene los datos de la mercancia que llego a bodega');
$backHref = '/recepciones';
$headerActions = $acta !== null
    ? header_button('/recepciones/' . $acta->id() . '/imprimir', 'printer', 'Imprimir acta', 'amber')
    : '';
require __DIR__ . '/../partials/header.php';

// Valor a mostrar en el formulario: lo enviado (si fallo la validacion) o lo guardado en el acta.
$valor = static function (string $campo, mixed $guardado) use ($entrada): string {
    if ($entrada !== null) {
        return (string) ($entrada[$campo] ?? '');
    }

    return $guardado === null ? '' : (string) $guardado;
};
$etiquetaCliente = static fn ($c): string => implode(' — ', array_filter([$c->codigo(), $c->nombre(), $c->empresa(), (string) $c->email()]));
$tiposMarcados = $entrada !== null
    ? (is_array($entrada['tipos_mercancia'] ?? null) ? $entrada['tipos_mercancia'] : [])
    : ($acta?->tiposMercancia() ?? []);
$elegirCliente = $clientes !== [];
$clienteIdActual = $entrada !== null ? (string) ($entrada['cliente_id'] ?? '') : ($cliente?->id() ?? '');
$clienteEtiquetaActual = '';
foreach ($clientes as $c) {
    if ($c->id() === $clienteIdActual) {
        $clienteEtiquetaActual = $etiquetaCliente($c);
    }
}
$inputClass = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
$tipoLabels = ['cajas' => 'Cajas', 'saco' => 'Saco', 'bultos' => 'Bultos'];
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-4xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<?php if (!empty($mensaje)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30 max-w-4xl">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($carga !== null): ?>
    <div class="bg-white rounded-xl shadow p-6 max-w-4xl mb-6">
        <?= card_header('receipt', 'Carga', 'indigo') ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-zinc-700">
            <p><strong>Tracking:</strong> <?php if ($esRecepcion): ?><?= htmlspecialchars((string) $carga->trackingNumero()) ?><?php else: ?><a href="/cargas/<?= htmlspecialchars($carga->id()) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars((string) $carga->trackingNumero()) ?></a><?php endif; ?></p>
            <p><strong>Fecha de ingreso:</strong> <?= htmlspecialchars($carga->fechaIngreso()->format('d/m/Y H:i')) ?></p>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($cliente?->nombreCompleto() ?? '—') ?></p>
        </div>
    </div>
<?php elseif ($acta !== null): ?>
    <div class="bg-amber-50 rounded-xl border border-amber-200 p-6 max-w-4xl mb-6">
        <p class="text-sm text-amber-800 font-medium">Esta acta todavia no tiene carga asociada.</p>
        <p class="text-xs text-amber-700 mt-1">Operaciones debe registrar la carga en Ingreso de Carga y elegir esta acta, o vincularla aqui a una carga ya registrada del mismo cliente.</p>
        <?php if ($puedeVincular): ?>
            <div class="flex flex-wrap items-center gap-3 mt-4">
                <a href="/cargas/ingreso?acta=<?= htmlspecialchars($acta->id()) ?>"
                   class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                    <?= icon_small('plus') ?><span>Registrar carga con esta acta</span>
                </a>
                <?php if ($cargasDelCliente !== []): ?>
                    <form method="post" action="/recepciones/<?= htmlspecialchars($acta->id()) ?>/vincular" class="flex items-center gap-2">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <select name="carga_id" required class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
                            <option value="">Vincular a carga ya registrada...</option>
                            <?php foreach ($cargasDelCliente as $c): ?>
                                <option value="<?= htmlspecialchars($c['cargaId']) ?>"><?= htmlspecialchars($c['trackingNumero'] . ' — ' . (new DateTimeImmutable($c['fechaIngreso']))->format('d/m/Y')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">Vincular</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($acta !== null): ?>
    <p class="text-xs text-zinc-500 mb-4 max-w-4xl">
        Recibida y verificada por <strong><?= htmlspecialchars($verificadoPor ?? '—') ?></strong>
        el <?= htmlspecialchars($acta->fechaRecepcion()->format('d/m/Y H:i')) ?>.
        <?php if ($acta->actualizadoPorUsuarioId() !== null): ?>
            Ultima correccion: <?= htmlspecialchars($actualizadoPor ?? '—') ?>, <?= htmlspecialchars($acta->updatedAt()->format('d/m/Y H:i')) ?>.
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<form method="post" action="<?= $acta !== null ? '/recepciones/' . htmlspecialchars($acta->id()) : '/recepciones' ?>" class="bg-white rounded-xl shadow p-4 sm:p-6 max-w-4xl space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?php if ($acta === null && $carga !== null): ?>
        <input type="hidden" name="carga_id" value="<?= htmlspecialchars($carga->id()) ?>">
    <?php endif; ?>

    <div>
        <?= card_header('users', 'Cliente y marca', 'indigo') ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php if ($elegirCliente): ?>
                <div class="relative" id="cliente-picker">
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del cliente</label>
                    <input type="text" id="cliente-buscar" placeholder="Escriba para buscar cliente..." autocomplete="off"
                           value="<?= htmlspecialchars($clienteEtiquetaActual) ?>" class="<?= $inputClass ?>">
                    <input type="hidden" name="cliente_id" id="cliente-id-hidden" value="<?= htmlspecialchars($clienteIdActual) ?>">
                    <div id="cliente-resultados" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
                    <script type="application/json" id="clientes-data"><?= json_encode(array_map(
                        fn ($c) => ['id' => $c->id(), 'nombre' => $c->nombre(), 'email' => (string) $c->email(), 'empresa' => $c->empresa(), 'codigo' => $c->codigo()],
                        $clientes,
                    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del cliente</label>
                    <input type="text" disabled value="<?= htmlspecialchars($cliente?->nombreCompleto() ?? '—') ?>" class="w-full rounded-lg border border-zinc-300 bg-slate-100 text-zinc-600 px-3 py-2">
                    <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($cliente?->id() ?? '') ?>">
                </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Marca</label>
                <input type="text" name="marca" maxlength="190" value="<?= htmlspecialchars($valor('marca', $acta?->marca() ?? ($marcaPorDefecto !== '' ? $marcaPorDefecto : null))) ?>" class="<?= $inputClass ?>">
            </div>
        </div>
    </div>

    <div>
        <?= card_header('truck', 'Transporte', 'sky') ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre de la empresa</label>
                <input type="text" name="empresa_transporte" maxlength="190" value="<?= htmlspecialchars($valor('empresa_transporte', $acta?->empresaTransporte())) ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del chofer</label>
                <input type="text" name="chofer_nombre" maxlength="190" value="<?= htmlspecialchars($valor('chofer_nombre', $acta?->choferNombre())) ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Placa</label>
                <input type="text" name="placa" maxlength="20" value="<?= htmlspecialchars($valor('placa', $acta?->placa())) ?>" class="<?= $inputClass ?> uppercase">
            </div>
        </div>
    </div>

    <div>
        <?= card_header('box', 'Mercancia recibida', 'amber') ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Cantidad de bultos</label>
                <input type="number" min="0" step="1" name="cantidad_bultos" id="acta-bultos" inputmode="numeric" value="<?= htmlspecialchars($valor('cantidad_bultos', $acta?->cantidadBultos() ?? 0)) ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Cantidad de rollos</label>
                <input type="number" min="0" step="1" name="cantidad_rollos" id="acta-rollos" inputmode="numeric" value="<?= htmlspecialchars($valor('cantidad_rollos', $acta?->cantidadRollos() ?? 0)) ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Total recibido</label>
                <input type="number" min="1" step="1" name="total_recibido" id="acta-total" inputmode="numeric" required value="<?= htmlspecialchars($valor('total_recibido', $acta?->totalRecibido())) ?>"
                       title="Se calcula como bultos + rollos, pero puede corregirlo manualmente." class="<?= $inputClass ?>">
            </div>
        </div>
        <div class="mt-4">
            <span class="block text-sm font-medium text-zinc-700 mb-1">Mercancia fisicamente</span>
            <div class="flex flex-wrap items-center gap-3">
                <?php foreach (ActaRecepcion::TIPOS_MERCANCIA as $tipo): ?>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 border border-zinc-300 rounded-lg px-4 py-2.5 cursor-pointer has-[:checked]:bg-amber-50 has-[:checked]:border-amber-400">
                        <input type="checkbox" name="tipos_mercancia[]" value="<?= $tipo ?>" <?= in_array($tipo, $tiposMarcados, true) ? 'checked' : '' ?>
                               class="w-5 h-5 rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
                        <?= $tipoLabels[$tipo] ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-zinc-700 mb-1">Descripcion</label>
            <textarea name="descripcion_mercancia" rows="3" class="<?= $inputClass ?>"><?= htmlspecialchars($valor('descripcion_mercancia', $acta?->descripcionMercancia())) ?></textarea>
        </div>
    </div>

    <div>
        <?= card_header('users', 'Entrega', 'emerald') ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Entregado por</label>
                <input type="text" name="entregado_por" required maxlength="190" value="<?= htmlspecialchars($valor('entregado_por', $acta?->entregadoPor())) ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Cedula</label>
                <input type="text" name="entregado_por_cedula" required maxlength="40" value="<?= htmlspecialchars($valor('entregado_por_cedula', $acta?->entregadoPorCedula())) ?>" class="<?= $inputClass ?>">
            </div>
        </div>
        <p class="text-xs text-zinc-500 mt-2">
            <?php if ($acta === null): ?>
                "Verificado por" se registra automaticamente con su usuario (<?= htmlspecialchars((string) ($nombreActual ?? '')) ?>) al guardar.
            <?php else: ?>
                El verificador original se conserva; esta correccion quedara registrada a su nombre.
            <?php endif; ?>
        </p>
    </div>

    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3">
        <button type="submit" class="inline-flex justify-center items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span><?= $acta === null ? 'Guardar acta de recepcion' : 'Guardar correccion' ?></span>
        </button>
        <a href="/recepciones" class="inline-flex justify-center items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bultos = document.getElementById('acta-bultos');
    var rollos = document.getElementById('acta-rollos');
    var total = document.getElementById('acta-total');
    // Si el total ya tiene un valor distinto a la suma, se respeta como correccion manual.
    var manual = total.value !== '' && parseInt(total.value, 10) !== (parseInt(bultos.value, 10) || 0) + (parseInt(rollos.value, 10) || 0);

    function recalcular() {
        if (!manual) {
            total.value = (parseInt(bultos.value, 10) || 0) + (parseInt(rollos.value, 10) || 0);
        }
    }

    bultos.addEventListener('input', recalcular);
    rollos.addEventListener('input', recalcular);
    total.addEventListener('input', function () { manual = total.value !== ''; });
});
</script>

<?php elseif ($acta !== null): ?>
<div class="bg-white rounded-xl shadow p-6 max-w-4xl">
    <?= card_header('truck', 'Acta de recepcion', 'sky') ?>
    <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-zinc-500">Cliente</dt><dd class="text-black"><?= htmlspecialchars($cliente?->nombreCompleto() ?? '—') ?></dd></div>
        <div><dt class="text-zinc-500">Marca</dt><dd class="text-black"><?= htmlspecialchars($acta->marca() ?? '—') ?></dd></div>
        <div><dt class="text-zinc-500">Empresa</dt><dd class="text-black"><?= htmlspecialchars($acta->empresaTransporte() ?? '—') ?></dd></div>
        <div><dt class="text-zinc-500">Chofer</dt><dd class="text-black"><?= htmlspecialchars($acta->choferNombre() ?? '—') ?></dd></div>
        <div><dt class="text-zinc-500">Placa</dt><dd class="text-black"><?= htmlspecialchars($acta->placa() ?? '—') ?></dd></div>
        <div><dt class="text-zinc-500">Cantidad de bultos</dt><dd class="text-black"><?= $acta->cantidadBultos() ?></dd></div>
        <div><dt class="text-zinc-500">Cantidad de rollos</dt><dd class="text-black"><?= $acta->cantidadRollos() ?></dd></div>
        <div><dt class="text-zinc-500">Total recibido</dt><dd class="text-black font-semibold"><?= $acta->totalRecibido() ?></dd></div>
        <div><dt class="text-zinc-500">Mercancia</dt><dd class="text-black"><?= htmlspecialchars(implode(', ', array_map(fn ($t) => $tipoLabels[$t] ?? $t, $acta->tiposMercancia())) ?: '—') ?></dd></div>
        <div><dt class="text-zinc-500">Entregado por</dt><dd class="text-black"><?= htmlspecialchars($acta->entregadoPor()) ?></dd></div>
        <div><dt class="text-zinc-500">Cedula</dt><dd class="text-black"><?= htmlspecialchars($acta->entregadoPorCedula()) ?></dd></div>
    </dl>
    <?php if ($acta->descripcionMercancia()): ?>
        <p class="text-sm text-zinc-500 mt-4">Descripcion</p>
        <p class="text-sm text-black whitespace-pre-line"><?= htmlspecialchars($acta->descripcionMercancia()) ?></p>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="bg-amber-50 text-amber-800 text-sm rounded-lg px-4 py-3 border border-amber-200 max-w-4xl">
    Esta carga aun no tiene acta de recepcion. Debe llenarla el personal de bodega al recibir la mercancia.
</div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
