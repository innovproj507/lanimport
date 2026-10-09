<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaCommand;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Throwable;

final class CargaApiController extends BaseApiController
{
    public function store(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            /** @var ProveedorRepositoryInterface $proveedorRepository */
            $proveedorRepository = $this->container()->get(ProveedorRepositoryInterface::class);
            $proveedorIdentificador = (string) ($body['proveedor_identificador'] ?? '');
            $proveedor = $proveedorRepository->findByIdentificador($proveedorIdentificador);

            if ($proveedor === null) {
                throw new ValidationException('Seleccione un proveedor valido.');
            }

            $aduaneroIdentificador = (string) ($body['aduanero_identificador'] ?? '');
            $tipoServicio = (string) ($body['tipo_servicio'] ?? '');

            $command = new RegistrarCargaCommand(
                (string) ($body['cliente_id'] ?? ''),
                $proveedor->nombre(),
                $proveedor->identificador(),
                $aduaneroIdentificador !== '' ? $aduaneroIdentificador : null,
                $aduaneroIdentificador !== '' ? (string) ($body['aduanero_nombre'] ?? '') : null,
                isset($body['numero_contenedor']) ? (string) $body['numero_contenedor'] : null,
                $tipoServicio !== '' ? $tipoServicio : null,
                (array) ($body['detalle_servicio'] ?? []),
                (string) $this->usuarioIdJwt(),
                (string) ($body['fecha_ingreso'] ?? date('Y-m-d\TH:i:s')),
            );

            $result = $this->container()->get(RegistrarCargaHandler::class)->handle($command);

            return $this->success([
                'carga_id' => $result->cargaId,
                'tracking_numero' => $result->trackingNumero,
                'etiqueta_url' => $result->etiquetaUrl,
            ], 201);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function show(string $id): ResponseInterface
    {
        try {
            /** @var CargaRepositoryInterface $cargas */
            $cargas = $this->container()->get(CargaRepositoryInterface::class);
            $carga = $cargas->findById($id);

            if ($carga === null) {
                throw new CargaNotFoundException("No se encontro la carga con id {$id}");
            }

            $view = ConsultarTrackingPorNumeroHandler::toView($carga, $this->container()->get(PdoHistorialEstadoRepository::class), $this->container()->get(LpnRepositoryInterface::class));

            return $this->success($view);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }
}
