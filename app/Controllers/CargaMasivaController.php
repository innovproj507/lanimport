<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Application\ImportarCargas\ImportarCargasCommand;
use Courier\Carga\Application\ImportarCargas\ImportarCargasHandler;
use Courier\Carga\Application\ImportarCargas\PrepararImportacionCargas;
use Courier\Carga\Infrastructure\Importacion\EscritorXlsx;
use Courier\Carga\Infrastructure\Importacion\LectorHojaCalculo;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Carga masiva de cargas por Excel/CSV (solo gerencia y owner): subir archivo ->
 * vista previa con validaciones (no guarda nada) -> confirmar -> resultado.
 * El plan validado se guarda en sesion entre la vista previa y la confirmacion.
 */
final class CargaMasivaController extends BaseController
{
    private const TAMANO_MAXIMO = 5 * 1024 * 1024;
    private const CLAVE_SESION = 'carga_masiva';

    public function index(): string
    {
        return view('carga/masiva/index', ['error' => null]);
    }

    /** Plantilla Excel con la hoja para llenar, instrucciones y los codigos validos. */
    public function plantilla(): ResponseInterface
    {
        $columnas = PrepararImportacionCargas::COLUMNAS;

        $instrucciones = [['Columna', 'Obligatoria', 'Que poner']];
        foreach ($columnas as [$encabezado, $obligatoria, $descripcion]) {
            $instrucciones[] = [$encabezado, $obligatoria ? 'SI' : 'no', $descripcion];
        }
        $instrucciones[] = ['', '', ''];
        $instrucciones[] = ['Como funciona', '', 'Cada fila es una linea de mercancia. Las filas con la misma Referencia forman una sola carga (con el mismo cliente, proveedor, aduanero, contenedor y fecha).'];
        $instrucciones[] = ['Ejemplo', '', 'GUIA-001 con 2 filas (10 cajas + 5 sacos) = 1 carga de 15 bultos. GUIA-002 con 1 fila = otra carga.'];
        $instrucciones[] = ['Limite', '', 'Hasta ' . PrepararImportacionCargas::MAX_FILAS . ' filas por archivo.'];

        $clientes = [['Codigo', 'Nombre', 'Correo']];
        foreach ($this->container()->get(ClienteRepositoryInterface::class)->findApproved() as $c) {
            $clientes[] = [(string) $c->codigo(), $c->nombreCompleto(), (string) $c->email()];
        }

        $proveedores = [['Identificador', 'Nombre']];
        foreach ($this->container()->get(ProveedorRepositoryInterface::class)->findAll() as $p) {
            $proveedores[] = [$p->identificador(), $p->nombre()];
        }

        $aduaneros = [['Identificador', 'Nombre']];
        foreach ($this->container()->get(AduaneroRepositoryInterface::class)->findAllActivos() as $a) {
            $aduaneros[] = [$a->identificador(), $a->nombre()];
        }

        $contenido = $this->container()->get(EscritorXlsx::class)->generar([
            'Cargas' => [
                'filas' => [array_map(static fn (array $c) => $c[0], $columnas)],
                'anchos' => [14, 16, 26, 24, 14, 15, 34, 16, 16, 10, 11, 11, 11, 11, 11, 30],
            ],
            'Instrucciones' => ['filas' => $instrucciones, 'anchos' => [20, 12, 110]],
            'Clientes' => ['filas' => $clientes, 'anchos' => [10, 45, 40]],
            'Proveedores' => ['filas' => $proveedores, 'anchos' => [16, 45]],
            'Aduaneros' => ['filas' => $aduaneros, 'anchos' => [16, 45]],
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla-carga-masiva.xlsx"')
            ->setBody($contenido);
    }

    public function previsualizar(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('carga/masiva/index', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        $archivo = $_FILES['archivo'] ?? null;

        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return view('carga/masiva/index', ['error' => 'Seleccione el archivo Excel o CSV.']);
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            return view('carga/masiva/index', ['error' => 'No se pudo subir el archivo.']);
        }

        if ($archivo['size'] > self::TAMANO_MAXIMO) {
            return view('carga/masiva/index', ['error' => 'El archivo no puede pesar mas de 5 MB.']);
        }

        try {
            $filas = $this->container()->get(LectorHojaCalculo::class)->leer($archivo['tmp_name'], (string) $archivo['name']);
            $plan = $this->container()->get(PrepararImportacionCargas::class)->preparar($filas);
        } catch (ValidationException $e) {
            return view('carga/masiva/index', ['error' => $e->getMessage()]);
        }

        $token = bin2hex(random_bytes(16));
        $_SESSION[self::CLAVE_SESION] = [
            'token' => $token,
            'plan' => $plan,
            'archivo' => basename((string) $archivo['name']),
            'notificar' => $this->request->getPost('notificar') !== null,
        ];

        return view('carga/masiva/previsualizar', $_SESSION[self::CLAVE_SESION]);
    }

    public function confirmar(): RedirectResponse|string
    {
        $datos = $_SESSION[self::CLAVE_SESION] ?? null;

        if (!Csrf::verify($this->request->getPost('_csrf')) || $datos === null
            || !hash_equals($datos['token'], (string) $this->request->getPost('token'))) {
            return view('carga/masiva/index', ['error' => 'La vista previa expiro o ya se proceso. Suba el archivo de nuevo.']);
        }

        // Se quita de la sesion antes de importar: evita que un doble clic cree las cargas dos veces.
        unset($_SESSION[self::CLAVE_SESION]);

        $resultado = $this->container()->get(ImportarCargasHandler::class)->handle(new ImportarCargasCommand(
            $datos['plan']['cargas'],
            (bool) $datos['notificar'],
            $datos['archivo'],
            (string) $this->container()->get(SessionManager::class)->currentUsuarioId(),
        ));

        return view('carga/masiva/resultado', [
            'resultado' => $resultado,
            'archivo' => $datos['archivo'],
            'notificar' => (bool) $datos['notificar'],
        ]);
    }
}
