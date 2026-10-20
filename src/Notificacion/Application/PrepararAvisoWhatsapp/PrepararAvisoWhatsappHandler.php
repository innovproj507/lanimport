<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\PrepararAvisoWhatsapp;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;
use Courier\Notificacion\Domain\TelefonoWhatsapp;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Aviso al cliente por WhatsApp sin API: arma el mensaje y devuelve el enlace https://wa.me/...
 * que abre WhatsApp (web o telefono) con el texto listo; el usuario solo pulsa Enviar.
 * Queda registrado en `notificaciones` (canal whatsapp) como abierto en WhatsApp.
 */
final class PrepararAvisoWhatsappHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NotificacionRepositoryInterface $notificaciones,
        private readonly ConfiguracionRepositoryInterface $configuracion,
    ) {
    }

    /** @return array{url: string, telefono: string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof PrepararAvisoWhatsappCommand);

        $telefono = TelefonoWhatsapp::desde($command->telefono, $command->pais);

        if ($telefono === null) {
            throw new ValidationException(
                $command->telefono === null || trim($command->telefono) === ''
                    ? 'El cliente no tiene telefono registrado. Agreguelo en Clientes para avisarle por WhatsApp.'
                    : "El telefono del cliente ({$command->telefono}) no tiene un formato valido para WhatsApp. Corrijalo en Clientes."
            );
        }

        $despacho = $command->tipo === PrepararAvisoWhatsappCommand::DESPACHO;
        $empresa = $this->configuracion->datosEmpresa()->nombre;
        $asunto = ($despacho ? 'Su carga fue despachada' : 'Su carga ingreso a bodega') . " - Tracking {$command->trackingNumero}";

        $lineas = ['• Numero de tracking: *' . $command->trackingNumero . '*'];
        foreach ($command->datos as $etiqueta => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $lineas[] = "• {$etiqueta}: {$valor}";
            }
        }

        $texto = "Hola {$command->clienteNombre},\n\n"
            . "{$empresa} le informa que "
            . ($despacho ? '*su carga salio de nuestra bodega* y va en camino.' : '*su carga ingreso a nuestra bodega*.')
            . "\n\n" . implode("\n", $lineas)
            . "\n\nPuede consultar el estado de su carga en cualquier momento con el numero de tracking.";

        $notificacion = Notificacion::crear($command->cargaId, $telefono->legible(), $asunto, $texto, 'whatsapp');
        // Sin API no se sabe si el usuario pulso Enviar: queda como abierto en WhatsApp.
        $notificacion->marcarEnviado();
        $this->notificaciones->save($notificacion);

        return [
            'url' => 'https://wa.me/' . $telefono->digitos() . '?text=' . rawurlencode($texto),
            'telefono' => $telefono->legible(),
        ];
    }
}
