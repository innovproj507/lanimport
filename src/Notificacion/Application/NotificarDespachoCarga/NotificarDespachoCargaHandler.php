<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\NotificarDespachoCarga;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Notificacion\Application\PlantillaCorreo;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;

/**
 * Avisa por correo al cliente que su carga salio de bodega (salida confirmada con todos
 * sus bultos) y deja registro del envio en `notificaciones`.
 */
final class NotificarDespachoCargaHandler
{
    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly NotificacionRepositoryInterface $notificaciones,
        private readonly ConfiguracionRepositoryInterface $configuracion,
    ) {
    }

    /**
     * @param array<string, string|null> $datosEmbarque etiqueta => valor (DO, contenedor, naviera...); los vacios se omiten
     * @return Notificacion|null null si el aviso de despacho esta desactivado en Configuracion
     */
    public function handle(
        string $cargaId,
        string $clienteEmail,
        string $clienteNombre,
        string $trackingNumero,
        int $totalBultos,
        array $datosEmbarque,
    ): ?Notificacion {
        if (!$this->configuracion->preferenciasNotificacion()->notificarDespachoCarga) {
            return null;
        }

        $e = static fn (?string $texto): string => PlantillaCorreo::e($texto);
        $filas = [
            ['Numero de tracking', '<strong>' . $e($trackingNumero) . '</strong>'],
            ['Bultos', $e((string) $totalBultos)],
        ];

        foreach ($datosEmbarque as $etiqueta => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $filas[] = [$e($etiqueta), $e($valor)];
            }
        }

        $asunto = "Su carga fue despachada - Tracking {$trackingNumero}";
        $cuerpo = PlantillaCorreo::render(
            $this->configuracion->datosEmpresa(),
            $clienteNombre,
            'Le informamos que <strong>su carga salio de nuestra bodega</strong> y va en camino.',
            $filas,
            'Puede consultar el estado de su carga en cualquier momento con el numero de tracking.',
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
