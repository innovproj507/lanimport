<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\NotificarDevolucionProveedor;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Notificacion\Application\PlantillaCorreo;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;

/**
 * Avisa por correo al cliente que su carga (toda o parte) se devolvio al proveedor, con el motivo,
 * y deja registro del envio en `notificaciones`.
 */
final class NotificarDevolucionProveedorHandler
{
    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly NotificacionRepositoryInterface $notificaciones,
        private readonly ConfiguracionRepositoryInterface $configuracion,
    ) {
    }

    /** @return Notificacion|null null si el aviso esta desactivado en Configuracion */
    public function handle(
        string $cargaId,
        string $clienteEmail,
        string $clienteNombre,
        string $trackingNumero,
        string $proveedor,
        int $bultosDevueltos,
        bool $completa,
        string $motivo,
    ): ?Notificacion {
        if (!$this->configuracion->preferenciasNotificacion()->notificarDevolucionProveedor) {
            return null;
        }

        $e = static fn (?string $texto): string => PlantillaCorreo::e($texto);
        $filas = [
            ['Numero de tracking', '<strong>' . $e($trackingNumero) . '</strong>'],
            ['Proveedor', $e($proveedor !== '' ? $proveedor : '—')],
            ['Bultos devueltos', $e((string) $bultosDevueltos) . ($completa ? ' (toda la carga)' : ' (devolucion parcial)')],
            ['Motivo', $e($motivo)],
        ];

        $asunto = "Su carga fue devuelta al proveedor - Tracking {$trackingNumero}";
        $cuerpo = PlantillaCorreo::render(
            $this->configuracion->datosEmpresa(),
            $clienteNombre,
            $completa
                ? 'Le informamos que <strong>su carga fue devuelta al proveedor</strong>.'
                : 'Le informamos que <strong>parte de su carga fue devuelta al proveedor</strong>. El resto sigue en nuestra bodega.',
            $filas,
            'Si tiene alguna consulta sobre esta devolucion, comuniquese con nosotros indicando el numero de tracking.',
        );

        $notificacion = Notificacion::crear($cargaId, $clienteEmail, $asunto, $cuerpo);

        if (PlantillaCorreo::esCorreoProvisional($clienteEmail)) {
            $notificacion->marcarFallido(PlantillaCorreo::SIN_CORREO_REAL);
        } elseif ($this->emailService->send($clienteEmail, $asunto, $cuerpo)) {
            $notificacion->marcarEnviado();
        } else {
            $notificacion->marcarFallido($this->emailService->ultimoError() ?? 'Error desconocido al enviar el correo.');
        }

        $this->notificaciones->save($notificacion);

        return $notificacion;
    }
}
