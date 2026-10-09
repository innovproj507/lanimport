<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Configuracion\Application\GuardarConfiguracionSmtp\GuardarConfiguracionSmtpCommand;
use Courier\Configuracion\Application\GuardarConfiguracionSmtp\GuardarConfiguracionSmtpHandler;
use Courier\Configuracion\Application\GuardarDatosEmpresa\GuardarDatosEmpresaCommand;
use Courier\Configuracion\Application\GuardarDatosEmpresa\GuardarDatosEmpresaHandler;
use Courier\Configuracion\Application\GuardarPreferenciasNotificacion\GuardarPreferenciasNotificacionCommand;
use Courier\Configuracion\Application\GuardarPreferenciasNotificacion\GuardarPreferenciasNotificacionHandler;
use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\ProbadorSmtpInterface;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Shared\Domain\Exception\DomainException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\EmailAddress;

final class ConfiguracionController extends BaseController
{
    public function index(): string
    {
        return view('configuracion/index', [
            'smtpGuardado' => $this->configuracion()->smtpGuardadoEnSistema(),
        ]);
    }

    public function showCorreo(): string
    {
        return $this->renderCorreo(null, $this->mensajeFlash());
    }

    public function guardarCorreo(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderCorreo('Sesion expirada, por favor intente de nuevo.', null);
        }

        $password = (string) $this->request->getPost('password');

        try {
            $this->container()->get(GuardarConfiguracionSmtpHandler::class)->handle(new GuardarConfiguracionSmtpCommand(
                (string) $this->request->getPost('host'),
                (int) $this->request->getPost('puerto'),
                $this->textoOpcional('usuario'),
                $password !== '' ? $password : null,
                $this->request->getPost('borrar_password') !== null,
                (string) $this->request->getPost('cifrado'),
                (string) $this->request->getPost('remitente_email'),
                (string) $this->request->getPost('remitente_nombre'),
                $this->usuarioId(),
            ));

            return $this->redirectConMensaje('/configuracion/correo', 'Configuracion de correo guardada.');
        } catch (ValidationException $e) {
            return $this->renderCorreo($e->getMessage(), null, $this->request->getPost());
        }
    }

    public function probarCorreo(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderCorreo('Sesion expirada, por favor intente de nuevo.', null);
        }

        $destinatario = trim((string) $this->request->getPost('destinatario'));

        try {
            new EmailAddress($destinatario);
            $this->container()->get(ProbadorSmtpInterface::class)->enviarPrueba($destinatario);

            return $this->redirectConMensaje('/configuracion/correo', "Correo de prueba enviado a {$destinatario}.");
        } catch (ValidationException|DomainException $e) {
            return $this->renderCorreo($e->getMessage(), null);
        }
    }

    public function showEmpresa(): string
    {
        return $this->renderEmpresa(null, $this->mensajeFlash());
    }

    public function guardarEmpresa(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderEmpresa('Sesion expirada, por favor intente de nuevo.', null);
        }

        try {
            $this->container()->get(GuardarDatosEmpresaHandler::class)->handle(new GuardarDatosEmpresaCommand(
                (string) $this->request->getPost('nombre'),
                $this->textoOpcional('ruc'),
                $this->textoOpcional('dv'),
                $this->textoOpcional('clave_operaciones'),
                $this->textoOpcional('direccion'),
                $this->textoOpcional('telefono'),
                $this->textoOpcional('email'),
                $this->usuarioId(),
            ));

            return $this->redirectConMensaje('/configuracion/empresa', 'Datos de la empresa guardados.');
        } catch (ValidationException $e) {
            return $this->renderEmpresa($e->getMessage(), null, $this->request->getPost());
        }
    }

    public function showNotificaciones(): string
    {
        return view('configuracion/notificaciones', [
            'preferencias' => $this->configuracion()->preferenciasNotificacion(),
            'error' => null,
            'mensaje' => $this->mensajeFlash(),
        ]);
    }

    public function guardarNotificaciones(): RedirectResponse
    {
        if (Csrf::verify($this->request->getPost('_csrf'))) {
            $this->container()->get(GuardarPreferenciasNotificacionHandler::class)->handle(new GuardarPreferenciasNotificacionCommand(
                $this->request->getPost('notificar_ingreso_carga') !== null,
                $this->usuarioId(),
            ));

            return $this->redirectConMensaje('/configuracion/notificaciones', 'Preferencias de notificacion guardadas.');
        }

        return redirect()->to('/configuracion/notificaciones');
    }

    public function showEtiquetaLpn(): string
    {
        return view('configuracion/etiqueta_lpn', [
            'etiquetaConfig' => $this->container()->get(EtiquetaLpnConfigRepositoryInterface::class)->obtener(),
        ]);
    }

    /** @param array<string, mixed>|null $entrada */
    private function renderCorreo(?string $error, ?string $mensaje, ?array $entrada = null): string
    {
        return view('configuracion/correo', [
            'smtp' => $this->configuracion()->smtp(),
            'smtpGuardado' => $this->configuracion()->smtpGuardadoEnSistema(),
            'entrada' => $entrada,
            'error' => $error,
            'mensaje' => $mensaje,
        ]);
    }

    /** @param array<string, mixed>|null $entrada */
    private function renderEmpresa(?string $error, ?string $mensaje, ?array $entrada = null): string
    {
        return view('configuracion/empresa', [
            'empresa' => $this->configuracion()->datosEmpresa(),
            'entrada' => $entrada,
            'error' => $error,
            'mensaje' => $mensaje,
        ]);
    }

    private function configuracion(): ConfiguracionRepositoryInterface
    {
        return $this->container()->get(ConfiguracionRepositoryInterface::class);
    }

    private function usuarioId(): string
    {
        return (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
    }

    private function textoOpcional(string $campo): ?string
    {
        $valor = trim((string) $this->request->getPost($campo));

        return $valor !== '' ? $valor : null;
    }

    /** Mensaje de exito de un solo uso (patron POST/redirect/GET), en la sesion nativa. */
    private function redirectConMensaje(string $url, string $mensaje): RedirectResponse
    {
        $_SESSION['config_mensaje'] = $mensaje;

        return redirect()->to($url);
    }

    private function mensajeFlash(): ?string
    {
        $mensaje = $_SESSION['config_mensaje'] ?? null;
        unset($_SESSION['config_mensaje']);

        return $mensaje;
    }
}
