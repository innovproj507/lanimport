<?php

declare(strict_types=1);

namespace Courier\Lpn\Infrastructure\Barcode;

use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Picqer\Barcode\BarcodeGeneratorPNG;
use RuntimeException;

final class LpnBarcodeGeneratorService implements LpnBarcodeGeneratorInterface
{
    private const RELATIVE_DIR = 'uploads/lpns';

    // 10cm x 7.5cm a ~203dpi (impresoras termicas de etiquetas comunes).
    private const ANCHO_PX = 800;
    private const ALTO_PX = 600;
    private const MARGEN = 30;

    private const FONTS_BOLD = [
        'C:\\Windows\\Fonts\\arialbd.ttf',
    ];

    private const FONTS_REGULAR = [
        'C:\\Windows\\Fonts\\arial.ttf',
    ];

    public function __construct(private readonly string $publicPath)
    {
    }

    public function generate(
        string $codigo,
        ?string $marca = null,
        ?string $descripcion = null,
        ?string $proveedorNombre = null,
        ?int $ordenEnCarga = null,
        ?int $totalEnCarga = null,
        bool $mostrarSerie = false,
    ): string {
        $etiqueta = $this->componerEtiqueta($codigo, $marca, $descripcion, $proveedorNombre, $ordenEnCarga, $totalEnCarga, $mostrarSerie);

        $dir = $this->publicPath . '/' . self::RELATIVE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de etiquetas: {$dir}");
        }

        $fileName = $codigo . '.png';
        $fullPath = $dir . '/' . $fileName;

        if (imagepng($etiqueta, $fullPath) === false) {
            imagedestroy($etiqueta);

            throw new RuntimeException("No se pudo escribir la etiqueta: {$fullPath}");
        }

        imagedestroy($etiqueta);

        return self::RELATIVE_DIR . '/' . $fileName;
    }

    public function eliminar(string $codigo): void
    {
        $fullPath = $this->publicPath . '/' . self::RELATIVE_DIR . '/' . $codigo . '.png';

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    /** @return \GdImage */
    private function componerEtiqueta(
        string $codigo,
        ?string $marca,
        ?string $descripcion,
        ?string $proveedorNombre,
        ?int $ordenEnCarga,
        ?int $totalEnCarga,
        bool $mostrarSerie,
    ) {
        $lienzo = imagecreatetruecolor(self::ANCHO_PX, self::ALTO_PX);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $negro = imagecolorallocate($lienzo, 0, 0, 0);
        imagefill($lienzo, 0, 0, $blanco);

        $anchoMax = self::ANCHO_PX - (self::MARGEN * 2);
        $y = 18;

        $y = $this->dibujarTexto($lienzo, 'LAN IMPORT - EXPORT S.A.', $y, 22, $negro, false, $anchoMax, 14);
        $y += 6;
        imagesetthickness($lienzo, 3);
        imageline($lienzo, self::MARGEN, $y, self::ANCHO_PX - self::MARGEN, $y, $negro);
        imagesetthickness($lienzo, 1);

        $yCodigoBarra = $this->dibujarCodigoBarra($lienzo, $codigo, $negro);

        // [texto, peso relativo, negrita, tamano minimo]: el texto crece hasta llenar el
        // espacio libre entre la linea del encabezado y el codigo de barras.
        $lineas = [];

        if ($marca !== null && $marca !== '') {
            $lineas[] = [mb_strtoupper($marca), 1.0, true, 28];
        }

        if ($proveedorNombre !== null && $proveedorNombre !== '') {
            $lineas[] = [mb_strtoupper($proveedorNombre), 0.75, true, 22];
        }

        if ($mostrarSerie && $ordenEnCarga !== null && $totalEnCarga !== null) {
            $lineas[] = ["{$ordenEnCarga}/{$totalEnCarga}", 0.9, true, 28];
        }

        if ($descripcion !== null && $descripcion !== '') {
            $lineas[] = [$this->truncar($descripcion, 60), 0.32, false, 14];
        }

        if ($lineas !== []) {
            $this->dibujarBloqueAjustado($lienzo, $lineas, $y + 14, $yCodigoBarra - 14, $anchoMax, $negro);
        }

        return $lienzo;
    }

    /**
     * Dibuja las lineas centradas en el area [$yInicio, $yFin], con el mayor tamano
     * posible: cada linea respeta su peso relativo, nunca excede el ancho disponible
     * y el bloque completo cabe en la altura del area.
     *
     * @param array<int, array{0: string, 1: float, 2: bool, 3: int}> $lineas
     */
    private function dibujarBloqueAjustado($lienzo, array $lineas, int $yInicio, int $yFin, int $anchoMax, int $color): void
    {
        $fuentes = [$this->resolverFuente(true), $this->resolverFuente(false)];

        if (in_array(null, $fuentes, true)) {
            $y = $yInicio;
            foreach ($lineas as [$texto, $peso, $negrita]) {
                $y = $this->dibujarTextoFallback($lienzo, $texto, $y, (int) (80 * $peso), $color, $negrita);
            }

            return;
        }

        $altoDisponible = $yFin - $yInicio;
        $separacion = 0.18;
        $base = 160.0;
        $medidas = [];

        for ($escala = 1.0; $escala >= 0.05; $escala -= 0.02) {
            $medidas = [];
            $altoTotal = 0;

            foreach ($lineas as [$texto, $peso, $negrita, $minimo]) {
                $fuente = $negrita ? $fuentes[0] : $fuentes[1];
                $tamano = $base * $peso * $escala;

                // El ancho crece en proporcion al tamano: lo limitamos al ancho disponible.
                $bbox = imagettfbbox($tamano, 0, $fuente, $texto);
                $ancho = $bbox[2] - $bbox[0];
                if ($ancho > $anchoMax) {
                    // Margen de 2%: el ancho del texto no escala exactamente lineal con el tamano.
                    $tamano *= ($anchoMax / $ancho) * 0.98;
                }

                $tamano = max($tamano, $minimo);
                $bbox = imagettfbbox($tamano, 0, $fuente, $texto);
                $medidas[] = [$texto, $tamano, $fuente, $bbox];
                $altoTotal += ($bbox[1] - $bbox[7]) + $this->espacioEntreLineas($tamano, $separacion);
            }

            $altoTotal -= $this->espacioEntreLineas(end($medidas)[1], $separacion);

            if ($altoTotal <= $altoDisponible) {
                break;
            }
        }

        $y = $yInicio + max(0, (int) (($altoDisponible - $altoTotal) / 2));

        foreach ($medidas as [$texto, $tamano, $fuente, $bbox]) {
            // Si aun con el tamano minimo no cabe a lo ancho, se recorta con "…".
            while (($bbox[2] - $bbox[0]) > $anchoMax && mb_strlen($texto) > 1) {
                $texto = mb_substr($texto, 0, mb_strlen($texto) - 2) . '…';
                $bbox = imagettfbbox($tamano, 0, $fuente, $texto);
            }

            $x = (int) ((self::ANCHO_PX - ($bbox[2] - $bbox[0])) / 2) - $bbox[0];
            imagettftext($lienzo, $tamano, 0, max($x, 0), (int) ($y - $bbox[7]), $color, $fuente, $texto);
            $y += ($bbox[1] - $bbox[7]) + $this->espacioEntreLineas($tamano, $separacion);
        }
    }

    private function espacioEntreLineas(float $tamano, float $proporcion): float
    {
        return max(14.0, $tamano * $proporcion);
    }

    /**
     * Dibuja texto centrado horizontalmente empezando en $yTop (borde superior).
     * Si el texto no cabe en $anchoMax, reduce el tamano hasta $tamanoMin antes de truncar.
     * Devuelve el siguiente $y disponible para la proxima linea.
     */
    private function dibujarTexto(
        $lienzo,
        string $texto,
        int $yTop,
        int $tamano,
        int $color,
        bool $negrita,
        int $anchoMax,
        int $tamanoMin,
    ): int {
        $fuente = $this->resolverFuente($negrita);

        if ($fuente === null) {
            return $this->dibujarTextoFallback($lienzo, $texto, $yTop, $tamano, $color, $negrita);
        }

        $tamanoActual = $tamano;
        $bbox = imagettfbbox($tamanoActual, 0, $fuente, $texto);

        while (($bbox[2] - $bbox[0]) > $anchoMax && $tamanoActual > $tamanoMin) {
            $tamanoActual -= 2;
            $bbox = imagettfbbox($tamanoActual, 0, $fuente, $texto);
        }

        while (($bbox[2] - $bbox[0]) > $anchoMax && mb_strlen($texto) > 1) {
            $texto = mb_substr($texto, 0, mb_strlen($texto) - 2) . '…';
            $bbox = imagettfbbox($tamanoActual, 0, $fuente, $texto);
        }

        $ancho = $bbox[2] - $bbox[0];
        $x = (int) ((self::ANCHO_PX - $ancho) / 2) - $bbox[0];
        $baseline = $yTop - $bbox[7];

        imagettftext($lienzo, $tamanoActual, 0, max($x, 0), $baseline, $color, $fuente, $texto);

        return $baseline + $bbox[1] + 12;
    }

    private function dibujarTextoFallback($lienzo, string $texto, int $yTop, int $tamanoTtf, int $color, bool $negrita): int
    {
        $fuenteGd = min(5, max(1, (int) round($tamanoTtf / 16)));
        $ancho = imagefontwidth($fuenteGd) * strlen($texto);
        $x = max((int) ((self::ANCHO_PX - $ancho) / 2), 0);

        imagestring($lienzo, $fuenteGd, $x, $yTop, $texto, $color);

        if ($negrita) {
            imagestring($lienzo, $fuenteGd, $x + 1, $yTop, $texto, $color);
        }

        return $yTop + imagefontheight($fuenteGd) + 8;
    }

    private function resolverFuente(bool $negrita): ?string
    {
        static $cache = [];

        if (isset($cache[(int) $negrita])) {
            return $cache[(int) $negrita] ?: null;
        }

        foreach ($negrita ? self::FONTS_BOLD : self::FONTS_REGULAR as $candidato) {
            if (is_file($candidato)) {
                return $cache[(int) $negrita] = $candidato;
            }
        }

        $cache[(int) $negrita] = '';

        return null;
    }

    /** Dibuja el codigo de barras y su texto al pie de la etiqueta; devuelve el y superior de las barras. */
    private function dibujarCodigoBarra($lienzo, string $codigo, int $negro): int
    {
        $generator = new BarcodeGeneratorPNG();
        $maxAncho = self::ANCHO_PX - (self::MARGEN * 2);

        // Barras lo mas anchas posible sin pasar del margen (modulos enteros = lectura nitida).
        $factor = 4;
        do {
            $png = $generator->getBarcode($codigo, $generator::TYPE_CODE_128, $factor, 130);
            $barcodeImg = imagecreatefromstring($png);
        } while ($barcodeImg !== false && imagesx($barcodeImg) > $maxAncho && $factor-- > 1);

        $altoTextoCodigo = 40;

        if ($barcodeImg === false) {
            return self::ALTO_PX - $altoTextoCodigo;
        }

        $barcodeAncho = imagesx($barcodeImg);
        $barcodeAlto = imagesy($barcodeImg);
        $anchoFinal = min($barcodeAncho, $maxAncho);
        $altoFinal = (int) ($barcodeAlto * ($anchoFinal / $barcodeAncho));

        $x = (int) ((self::ANCHO_PX - $anchoFinal) / 2);
        $y = self::ALTO_PX - $altoFinal - $altoTextoCodigo - 12;

        imagecopyresampled($lienzo, $barcodeImg, $x, $y, 0, 0, $anchoFinal, $altoFinal, $barcodeAncho, $barcodeAlto);
        imagedestroy($barcodeImg);

        $this->dibujarTexto($lienzo, $codigo, $y + $altoFinal + 8, 24, $negro, true, $maxAncho, 14);

        return $y;
    }

    private function truncar(string $texto, int $longitud): string
    {
        return mb_strlen($texto) > $longitud ? mb_substr($texto, 0, $longitud - 1) . '…' : $texto;
    }
}
