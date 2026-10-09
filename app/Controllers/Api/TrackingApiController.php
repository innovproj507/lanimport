<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Carga\Application\ConsultarTrackingPorContenedor\ConsultarTrackingPorContenedorHandler;
use Courier\Carga\Application\ConsultarTrackingPorContenedor\ConsultarTrackingPorContenedorQuery;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroQuery;
use Throwable;

final class TrackingApiController extends BaseApiController
{
    public function byNumero(string $numero): ResponseInterface
    {
        try {
            $view = $this->container()->get(ConsultarTrackingPorNumeroHandler::class)
                ->handle(new ConsultarTrackingPorNumeroQuery($numero));

            return $this->success($view);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function byContenedor(string $numero): ResponseInterface
    {
        try {
            $views = $this->container()->get(ConsultarTrackingPorContenedorHandler::class)
                ->handle(new ConsultarTrackingPorContenedorQuery($numero));

            return $this->success($views);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }
}
