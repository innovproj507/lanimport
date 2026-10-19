<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\NotificarIngresoCarga;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Notificacion\Application\PlantillaCorreo;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;
use DateTimeImmutable;

/**
 * Avisa por correo al cliente que su carga ingreso a bodega y deja registro
 * del envio (enviado/fallido con el motivo) en `notificaciones`.
 */
final class NotificarIngresoCargaHandler
{
    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly NotificacionRepositoryInterface $notificaciones,
        private readonly ConfiguracionRepositoryInterface $configuracion,
    ) {
    }

    /**
     * @param bool $forzar envia aunque el aviso automatico este desactivado (reenvio manual)
     * @return Notificacion|null null si el aviso automatico esta desactivado en Configuracion
     */
    public function handle(
        string $cargaId,
        string $clienteEmail,
        string $clienteNombre,
        string $trackingNumero,
        ?DateTimeImmutable $fechaEstimadaLlegada,
        ?DateTimeImmutable $fechaIngreso = null,
        ?string $proveedor = null,
        ?float $totalBultos = null,
        bool $forzar = false,
    ): ?Notificacion {
        if (!$forzar && !$this->configuracion->preferenciasNotificacion()->notificarIngresoCarga) {
            return null;
        }

        $asunto = "Su carga ingreso a bodega - Tracking {$trackingNumero}";
        $cuerpo = $this->cuerpo($clienteNombre, $trackingNumero, $fechaEstimadaLlegada, $fechaIngreso, $proveedor, $totalBultos);

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

    private function cuerpo(
        string $clienteNombre,
        string $trackingNumero,
        ?DateTimeImmutable $fechaEstimadaLlegada,
        ?DateTimeImmutable $fechaIngreso,
        ?string $proveedor,
        ?float $totalBultos,
    ): string {
        $e = static fn (?string $texto): string => PlantillaCorreo::e($texto);
        $empresa = $this->configuracion->datosEmpresa();

        $filas = [['Numero de tracking', '<strong>' . $e($trackingNumero) . '</strong>']];

        if ($fechaIngreso !== null) {
            $filas[] = ['Fecha de ingreso', $e($fechaIngreso->format('d/m/Y H:i'))];
        }

        if ($proveedor !== null && $proveedor !== '') {
            $filas[] = ['Proveedor', $e($proveedor)];
        }

        if ($totalBultos !== null && $totalBultos > 0) {
            $filas[] = ['Bultos', $e(rtrim(rtrim(number_format($totalBultos, 2, '.', ''), '0'), '.'))];
        }

        if ($fechaEstimadaLlegada !== null) {
            $filas[] = ['Fecha estimada de llegada', $e($fechaEstimadaLlegada->format('d/m/Y'))];
        }

        return PlantillaCorreo::render(
            $empresa,
            $clienteNombre,
            'Le informamos que <strong>su carga ingreso a nuestra bodega</strong>.',
            $filas,
            'Puede consultar el estado de su carga en cualquier momento con el numero de tracking.',
        );
    }
}
