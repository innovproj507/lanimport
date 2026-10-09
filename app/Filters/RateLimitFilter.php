<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Shared\Infrastructure\Config;

/**
 * Equivalente a RateLimitMiddleware (interfaces/Api/Middleware) del sistema
 * original: limite de intentos por IP+ruta, respaldado en archivos de cache.
 * Argumentos del filtro: "clave" o "clave,maxIntentos,ventanaSegundos"
 * (si se omiten maxIntentos/ventanaSegundos, usa RATE_LIMIT_* del .env).
 */
final class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $routeKey = $arguments[0] ?? 'default';
        $maxAttempts = isset($arguments[1]) ? (int) $arguments[1] : Config::int('RATE_LIMIT_MAX_ATTEMPTS', 60);
        $windowSeconds = isset($arguments[2]) ? (int) $arguments[2] : Config::int('RATE_LIMIT_WINDOW_SECONDS', 60);

        $cacheDir = WRITEPATH . 'cache';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }

        $ip = $request->getIPAddress();
        $file = $cacheDir . '/ratelimit_' . md5($routeKey . '_' . $ip) . '.json';

        $now = time();
        $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!is_array($state) || ($now - (int) $state['window_start']) >= $windowSeconds) {
            $state = ['window_start' => $now, 'count' => 0];
        }

        $state['count']++;

        if ($state['count'] > $maxAttempts) {
            return service('response')->setStatusCode(429)->setJSON([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intente de nuevo mas tarde.',
                'errors' => [],
            ]);
        }

        file_put_contents($file, json_encode($state));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
