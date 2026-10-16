<?php

declare(strict_types=1);

namespace Courier\Shared\Domain\Exception;

/**
 * No se puede eliminar un registro de catalogo (cliente, aduanero, naviera) que ya
 * se uso en cargas/salidas: se romperia el historial. Se ofrece desactivarlo.
 */
final class RegistroEnUsoException extends ValidationException
{
    /** @param array<string, int> $referencias etiqueta en plural => cantidad (ej. ['cargas' => 3]) */
    public static function para(string $nombre, array $referencias): self
    {
        $partes = [];

        foreach ($referencias as $etiqueta => $cantidad) {
            // "1 cargas" -> "1 carga"
            $partes[] = $cantidad . ' ' . ($cantidad === 1 ? rtrim($etiqueta, 's') : $etiqueta);
        }

        $ultimo = array_pop($partes);
        $lista = $partes === [] ? $ultimo : implode(', ', $partes) . ' y ' . $ultimo;

        return new self("No se puede eliminar {$nombre}: tiene {$lista}. Use \"Desactivar\" para que no aparezca mas, sin perder el historial.");
    }
}
