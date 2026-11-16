<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Courier\Shared\Infrastructure\Container;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Composition root del sistema Courier original: mismo `Container` y
     * los mismos handlers de `src/`, ver `bootstrap/courier.php`.
     */
    protected function container(): Container
    {
        return Services::courierContainer();
    }

    /**
     * El personal de una bodega solo abre lo que es de su bodega (por enlace directo tambien).
     * Una OS/DO es "suya" si se armo ahi o lleva algun bulto de ahi.
     */
    protected function salidaDeMiBodega(\Courier\Salida\Domain\SalidaOrden $orden): bool
    {
        $fija = $this->container()->get(\Courier\Shared\Domain\BodegaActivaInterface::class)->fija();

        return $fija === null
            || $orden->bodegaId() === $fija
            // avancePorCarga() ya viene filtrado a los bultos de la bodega fija.
            || $this->container()->get(\Courier\Salida\Domain\SalidaOrdenRepositoryInterface::class)->avancePorCarga($orden->id()) !== [];
    }

    /** Una carga es "suya" si ingreso en su bodega o tiene algun bulto ahi. */
    protected function cargaDeMiBodega(\Courier\Carga\Domain\Carga $carga): bool
    {
        $fija = $this->container()->get(\Courier\Shared\Domain\BodegaActivaInterface::class)->fija();

        if ($fija === null || $carga->bodegaId() === $fija) {
            return true;
        }

        foreach ($this->container()->get(\Courier\Lpn\Domain\LpnRepositoryInterface::class)->findByCargaId($carga->id()) as $lpn) {
            if ($lpn->bodegaId() === $fija) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bodega en la que se registra algo nuevo (carga, ubicacion...): la fija del usuario; si no
     * tiene, la elegida en el formulario (debe existir y estar activa), la activa del header, o la
     * Bodega Principal.
     */
    protected function bodegaParaOperar(?string $pedida = null): string
    {
        $activa = $this->container()->get(\Courier\Shared\Domain\BodegaActivaInterface::class);

        if ($activa->fija() !== null) {
            return $activa->fija();
        }

        $pedida = trim((string) $pedida);

        if ($pedida !== '') {
            $bodega = $this->container()->get(\Courier\Bodega\Domain\BodegaRepositoryInterface::class)->findById($pedida);

            if ($bodega === null || !$bodega->activo()) {
                throw new \Courier\Shared\Domain\Exception\ValidationException('Seleccione una bodega valida.');
            }

            return $bodega->id();
        }

        return $activa->id() ?? \Courier\Bodega\Domain\Bodega::PRINCIPAL;
    }

    /**
     * Mensaje de un solo uso para la siguiente pagina (patron POST/redirect/GET),
     * guardado en la sesion nativa. Se muestra con partials/_flash.php.
     */
    protected function flash(string $tipo, string $texto): void
    {
        $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /** @return array{tipo: string, texto: string}|null */
    protected function tomarFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $flash;
    }
}
