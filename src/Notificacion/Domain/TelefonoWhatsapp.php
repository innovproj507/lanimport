<?php

declare(strict_types=1);

namespace Courier\Notificacion\Domain;

/**
 * Telefono del cliente en formato internacional para WhatsApp (solo digitos, con codigo de pais).
 * Los clientes se registran con numeros locales: 809/829/849 (Republica Dominicana, +1)
 * o de 7-8 digitos (Panama, +507). Si ya trae codigo de pais (+...), se respeta.
 */
final class TelefonoWhatsapp
{
    private const PREFIJOS_RD = ['809', '829', '849'];

    private function __construct(private readonly string $digitos)
    {
    }

    public static function desde(?string $telefono, ?string $pais = null): ?self
    {
        $texto = trim((string) $telefono);

        if ($texto === '') {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $texto) ?? '';
        $pais = mb_strtolower(trim((string) $pais));

        if (str_starts_with($texto, '+') || str_starts_with($texto, '00')) {
            $digitos = ltrim($digitos, '0');

            return strlen($digitos) >= 10 ? new self($digitos) : null;
        }

        // Republica Dominicana: 10 digitos con area 809/829/849, o ya con el 1 delante.
        if (strlen($digitos) === 10 && in_array(substr($digitos, 0, 3), self::PREFIJOS_RD, true)) {
            return new self('1' . $digitos);
        }

        if (strlen($digitos) === 11 && $digitos[0] === '1' && in_array(substr($digitos, 1, 3), self::PREFIJOS_RD, true)) {
            return new self($digitos);
        }

        // Panama: 8 digitos (celular) o 7 (fijo); con o sin el 507 delante.
        if (str_starts_with($digitos, '507') && in_array(strlen($digitos), [10, 11], true)) {
            return new self($digitos);
        }

        if (in_array(strlen($digitos), [7, 8], true)) {
            return new self('507' . $digitos);
        }

        // Otro numero de 10 digitos: se usa el codigo del pais del cliente si se conoce.
        if (strlen($digitos) === 10 && str_contains($pais, 'dominicana')) {
            return new self('1' . $digitos);
        }

        return null;
    }

    /** Para el enlace https://wa.me/<digitos> */
    public function digitos(): string
    {
        return $this->digitos;
    }

    /** Para mostrar: +1 809 224 6781 / +507 6122 5813 */
    public function legible(): string
    {
        if (str_starts_with($this->digitos, '1') && strlen($this->digitos) === 11) {
            return '+1 ' . substr($this->digitos, 1, 3) . ' ' . substr($this->digitos, 4, 3) . ' ' . substr($this->digitos, 7);
        }

        if (str_starts_with($this->digitos, '507')) {
            $local = substr($this->digitos, 3);

            return '+507 ' . (strlen($local) === 8 ? substr($local, 0, 4) . ' ' . substr($local, 4) : substr($local, 0, 3) . ' ' . substr($local, 3));
        }

        return '+' . $this->digitos;
    }
}
