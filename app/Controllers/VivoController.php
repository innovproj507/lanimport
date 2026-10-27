<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\HuellaCambios;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Actualizacion automatica de las pantallas (public/assets/js/vivo.js): responde solo la
 * huella de cada recurso pedido; la pantalla se refresca cuando cambia.
 */
final class VivoController extends BaseController
{
    public function huella(): ResponseInterface
    {
        // Tras leer la sesion (filtro auth) se libera, para no bloquear las demas peticiones del usuario.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $recursos = array_filter(array_map('trim', explode(',', (string) $this->request->getGet('r'))));

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['huellas' => HuellaCambios::de($recursos)]);
    }

    /** Contador y lista de la campana (zonas #campana-total y #campana-menu del header). */
    public function campana(): ResponseInterface
    {
        require APPPATH . 'Views/partials/_campana.php';

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setBody('<span id="campana-total">' . $campanaTotalHtml . '</span><div id="campana-menu">' . $campanaMenuHtml . '</div>');
    }
}
