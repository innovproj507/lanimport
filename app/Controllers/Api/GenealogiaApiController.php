<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Reempaque\Application\ConsultarGenealogiaLpn\ConsultarGenealogiaLpnHandler;
use Courier\Reempaque\Application\ConsultarGenealogiaLpn\ConsultarGenealogiaLpnQuery;
use Throwable;

final class GenealogiaApiController extends BaseApiController
{
    public function show(string $id): ResponseInterface
    {
        try {
            $genealogia = $this->container()->get(ConsultarGenealogiaLpnHandler::class)->handle(new ConsultarGenealogiaLpnQuery($id));

            return $this->success($genealogia);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }
}
