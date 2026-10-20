<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Evidencia\Application\EliminarFotoEvidencia\EliminarFotoEvidenciaCommand;
use Courier\Evidencia\Application\EliminarFotoEvidencia\EliminarFotoEvidenciaHandler;
use Courier\Evidencia\Domain\FotoEvidencia;
use Courier\Shared\Domain\Exception\NotFoundException;

/** Borrar fotos de evidencia: solo gerencia y owner (la evidencia no la borra quien la tomo). */
final class FotoEvidenciaController extends BaseController
{
    public function eliminar(string $id): RedirectResponse
    {
        $volver = (string) $this->request->getPost('volver');
        $volver = preg_match('#^/(cargas|salidas)/[A-Za-z0-9-]+$#', $volver) ? $volver : '/dashboard';

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to($volver);
        }

        try {
            /** @var FotoEvidencia $foto */
            $foto = $this->container()->get(EliminarFotoEvidenciaHandler::class)->handle(new EliminarFotoEvidenciaCommand($id));

            return redirect()->to($foto->cargaId() !== null ? '/cargas/' . $foto->cargaId() : '/salidas/' . $foto->salidaOrdenId());
        } catch (NotFoundException) {
            return redirect()->to($volver);
        }
    }
}
