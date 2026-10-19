<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application;

use Courier\Configuracion\Domain\DatosEmpresa;

/** HTML comun de los avisos al cliente: encabezado con la empresa, tabla de datos y pie de contacto. */
final class PlantillaCorreo
{
    public const SIN_CORREO_REAL = 'El cliente no tiene un correo real registrado; actualicelo en Clientes.';

    /**
     * @param string $mensajeHtml parrafo principal (ya escapado; puede llevar <strong>)
     * @param array<int, array{0: string, 1: string}> $filas [etiqueta, valor ya escapado]
     */
    public static function render(DatosEmpresa $empresa, string $clienteNombre, string $mensajeHtml, array $filas, string $cierre): string
    {
        $e = static fn (?string $texto): string => self::e($texto);

        $tabla = '';
        foreach ($filas as [$etiqueta, $valor]) {
            $tabla .= '<tr><td style="padding:8px 12px;color:#64748b;border-bottom:1px solid #e2e8f0;">' . $etiqueta . '</td>'
                . '<td style="padding:8px 12px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $valor . '</td></tr>';
        }

        $contacto = array_filter([
            $empresa->telefono ? 'Tel. ' . $e($empresa->telefono) : null,
            $empresa->email ? $e($empresa->email) : null,
        ]);

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#0f172a;">'
            . '<div style="background:#0b1b4d;color:#ffffff;padding:16px 20px;border-radius:8px 8px 0 0;font-size:18px;font-weight:bold;">' . $e($empresa->nombre) . '</div>'
            . '<div style="border:1px solid #e2e8f0;border-top:none;padding:20px;border-radius:0 0 8px 8px;">'
            . '<p>Hola ' . $e($clienteNombre) . ',</p>'
            . '<p>' . $mensajeHtml . '</p>'
            . '<table style="border-collapse:collapse;width:100%;margin:16px 0;font-size:14px;">' . $tabla . '</table>'
            . '<p>' . $e($cierre) . '</p>'
            . '<p style="color:#64748b;font-size:12px;margin-top:24px;">' . $e($empresa->nombre)
            . ($empresa->direccion ? '<br>' . $e($empresa->direccion) : '')
            . ($contacto !== [] ? '<br>' . implode(' · ', $contacto) : '')
            . '</p></div></div>';
    }

    public static function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    /** Correos provisionales de clientes importados sin correo real: no se les envia nada. */
    public static function esCorreoProvisional(string $email): bool
    {
        return str_ends_with(strtolower(trim($email)), '@importado.local');
    }
}
