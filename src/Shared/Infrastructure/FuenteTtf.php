<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure;

/**
 * Ubica una fuente TrueType para dibujar etiquetas con GD, tanto en Windows
 * (desarrollo con Laragon) como en servidores Linux. Se usa la primera que exista:
 * writable/fonts/ del proyecto (fuente propia opcional), Arial de Windows y las
 * rutas habituales de DejaVu/Liberation (Debian/Ubuntu y RHEL/Alma/CentOS).
 */
final class FuenteTtf
{
    private const NEGRITA = [
        'writable/fonts/bold.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/liberation-sans/LiberationSans-Bold.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
    ];

    private const REGULAR = [
        'writable/fonts/regular.ttf',
        'C:\\Windows\\Fonts\\arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
    ];

    /** @var array<int, string> cache por proceso: 1 = negrita, 0 = regular ('' = no hay) */
    private static array $cache = [];

    /** Ruta de la fuente, o null si no hay ninguna (se usara la fuente basica de GD). */
    public static function resolver(bool $negrita): ?string
    {
        $clave = (int) $negrita;

        if (!isset(self::$cache[$clave])) {
            self::$cache[$clave] = '';
            $raiz = dirname(__DIR__, 3);

            foreach ($negrita ? self::NEGRITA : self::REGULAR as $candidato) {
                $ruta = str_starts_with($candidato, 'writable/') ? $raiz . '/' . $candidato : $candidato;

                if (@is_file($ruta)) {
                    self::$cache[$clave] = $ruta;
                    break;
                }
            }
        }

        return self::$cache[$clave] !== '' ? self::$cache[$clave] : null;
    }
}
