<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Application\Login\LoginCommand;
use Courier\Auth\Application\Login\LoginHandler;
use Courier\Auth\Application\Logout\LogoutHandler;
use Courier\Auth\Application\RestablecerPassword\RestablecerPasswordCommand;
use Courier\Auth\Application\RestablecerPassword\RestablecerPasswordHandler;
use Courier\Auth\Application\SolicitarRecuperacionPassword\SolicitarRecuperacionPasswordCommand;
use Courier\Auth\Application\SolicitarRecuperacionPassword\SolicitarRecuperacionPasswordHandler;
use Courier\Auth\Domain\Exception\InvalidCredentialsException;
use Courier\Auth\Domain\Exception\TokenRecuperacionInvalidoException;
use Courier\Auth\Domain\Exception\TooManyAttemptsException;
use Courier\Auth\Domain\Rol;
use Courier\Shared\Domain\Exception\ValidationException;

final class AuthController extends BaseController
{
    public function showLogin(): string
    {
        return view('auth/login', ['error' => null]);
    }

    public function login(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('auth/login', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        try {
            $command = new LoginCommand(
                (string) $this->request->getPost('email'),
                (string) $this->request->getPost('password'),
                'web',
                $this->request->getIPAddress(),
                $this->request->getPost('recordar') !== null,
            );

            $result = $this->container()->get(LoginHandler::class)->handle($command);

            return redirect()->to($result->rol === Rol::CLIENTE->value ? '/portal' : '/dashboard');
        } catch (InvalidCredentialsException|TooManyAttemptsException $e) {
            return view('auth/login', ['error' => $e->getMessage()]);
        }
    }

    public function logout(): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/login');
        }

        $this->container()->get(LogoutHandler::class)->handle();

        return redirect()->to('/login');
    }

    public function showForgotPasswordForm(): string
    {
        return view('auth/forgot-password', ['error' => null, 'enviado' => false]);
    }

    public function forgotPassword(): string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('auth/forgot-password', ['error' => 'Sesion expirada, por favor intente de nuevo.', 'enviado' => false]);
        }

        $command = new SolicitarRecuperacionPasswordCommand((string) $this->request->getPost('email'));
        $this->container()->get(SolicitarRecuperacionPasswordHandler::class)->handle($command);

        return view('auth/forgot-password', ['error' => null, 'enviado' => true]);
    }

    public function showResetPasswordForm(string $token): string
    {
        return view('auth/reset-password', ['error' => null, 'token' => $token]);
    }

    public function resetPassword(string $token): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('auth/reset-password', ['error' => 'Sesion expirada, por favor intente de nuevo.', 'token' => $token]);
        }

        $nuevaPassword = (string) $this->request->getPost('password');
        $confirmacion = (string) $this->request->getPost('password_confirmacion');

        if ($nuevaPassword !== $confirmacion) {
            return view('auth/reset-password', ['error' => 'Las contrasenas no coinciden.', 'token' => $token]);
        }

        try {
            $command = new RestablecerPasswordCommand($token, $nuevaPassword);
            $this->container()->get(RestablecerPasswordHandler::class)->handle($command);

            return redirect()->to('/login');
        } catch (TokenRecuperacionInvalidoException|ValidationException $e) {
            return view('auth/reset-password', ['error' => $e->getMessage(), 'token' => $token]);
        }
    }
}
