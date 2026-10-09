<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\NotificarIngresoCarga;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
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

        if ($this->emailService->send($clienteEmail, $asunto, $cuerpo)) {
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
        $e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
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

        $tabla = '';
        foreach ($filas as [$etiqueta, $valor]) {
            $tabla .= '<tr><td style="padding:8px 12px;color:#64748b;border-bottom:1px solid #e2e8f0;">' . $etiqueta . '</td>'
                . '<td style="padding:8px 12px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $valor . '</td></tr>';
        }

        $contacto = array_filter([
            $empresa->telefono ? 'Tel. ' . $e($empresa->telefono) : null,
            $empresa->email ? $e($empresa->email) : null,
        ]);

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#0f172a;">'
            . '<div style="background:#0b1b4d;color:#ffffff;padding:16px 20px;border-radius:8px 8px 0 0;font-size:18px;font-weight:bold;">' . $e($empresa->nombre) . '</div>'
            . '<div style="border:1px solid #e2e8f0;border-top:none;padding:20px;border-radius:0 0 8px 8px;">'
            . '<p>Hola ' . $e($clienteNombre) . ',</p>'
            . '<p>Le informamos que <strong>su carga ingreso a nuestra bodega</strong>.</p>'
            . '<table style="border-collapse:collapse;width:100%;margin:16px 0;font-size:14px;">' . $tabla . '</table>'
            . '<p>Puede consultar el estado de su carga en cualquier momento con el numero de tracking.</p>'
            . '<p style="color:#64748b;font-size:12px;margin-top:24px;">' . $e($empresa->nombre)
            . ($empresa->direccion ? '<br>' . $e($empresa->direccion) : '')
            . ($contacto !== [] ? '<br>' . implode(' · ', $contacto) : '')
            . '</p></div></div>';
    }
}
