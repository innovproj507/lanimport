<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Barcode;

use Courier\Carga\Domain\BarcodeGeneratorInterface;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Picqer\Barcode\BarcodeGeneratorPNG;
use RuntimeException;

final class PicqerBarcodeGeneratorService implements BarcodeGeneratorInterface
{
    private const RELATIVE_DIR = 'uploads/etiquetas';

    private const ANCHO_PX = 700;
    private const ALTO_PX = 260;
    private const MARGEN = 30;

    private const FONT_BOLD = 'C:\\Windows\\Fonts\\arialbd.ttf';
    private const FONT_REGULAR = 'C:\\Windows\\Fonts\\arial.ttf';

    public function __construct(private readonly string $publicPath)
    {
    }

    public function generate(TrackingNumero $numero): string
    {
        $etiqueta = $this->componerEtiqueta((string) $numero);

        $dir = $this->publicPath . '/' . self::RELATIVE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de etiquetas: {$dir}");
        }

        $fileName = (string) $numero . '.png';
        $fullPath = $dir . '/' . $fileName;

        if (imagepng($etiqueta, $fullPath) === false) {
            imagedestroy($etiqueta);

            throw new RuntimeException("No se pudo escribir la etiqueta: {$fullPath}");
        }

        imagedestroy($etiqueta);

        return self::RELATIVE_DIR . '/' . $fileName;
    }

    /** @return \GdImage */
    private function componerEtiqueta(string $numero)
    {
        $lienzo = imagecreatetruecolor(self::ANCHO_PX, self::ALTO_PX);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $negro = imagecolorallocate($lienzo, 0, 0, 0);
        imagefill($lienzo, 0, 0, $blanco);

        $anchoMax = self::ANCHO_PX - (self::MARGEN * 2);
        $y = 18;

        $y = $this->dibujarTexto($lienzo, 'LAN IMPORT - EXPORT S.A.', $y, 18, $negro, self::FONT_REGULAR, $anchoMax);
        $y = $this->dibujarTexto($lienzo, 'CODIGO DE CARGA (TRACKING)', $y + 4, 15, $negro, self::FONT_REGULAR, $anchoMax);
        $y += 14;

        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($numero, $generator::TYPE_CODE_128, 2, 70);
        $barcodeImg = imagecreatefromstring($png);

        if ($barcodeImg !== false) {
            $barcodeAncho = imagesx($barcodeImg);
            $barcodeAlto = imagesy($barcodeImg);
            $anchoFinal = min($barcodeAncho, $anchoMax);
            $altoFinal = (int) ($barcodeAlto * ($anchoFinal / $barcodeAncho));
            $x = (int) ((self::ANCHO_PX - $anchoFinal) / 2);

            imagecopyresampled($lienzo, $barcodeImg, $x, $y, 0, 0, $anchoFinal, $altoFinal, $barcodeAncho, $barcodeAlto);
            imagedestroy($barcodeImg);

            $y += $altoFinal + 10;
        }

        $this->dibujarTexto($lienzo, $numero, $y, 24, $negro, self::FONT_BOLD, $anchoMax);

        return $lienzo;
    }

    private function dibujarTexto($lienzo, string $texto, int $yTop, int $tamano, int $color, string $fontPathPreferida, int $anchoMax): int
    {
        $fuente = is_file($fontPathPreferida) ? $fontPathPreferida : null;

        if ($fuente === null) {
            $fuenteGd = min(5, max(1, (int) round($tamano / 6)));
            $ancho = imagefontwidth($fuenteGd) * strlen($texto);
            $x = max((int) ((self::ANCHO_PX - $ancho) / 2), 0);
            imagestring($lienzo, $fuenteGd, $x, $yTop, $texto, $color);

            return $yTop + imagefontheight($fuenteGd) + 6;
        }

        $bbox = imagettfbbox($tamano, 0, $fuente, $texto);
        $ancho = $bbox[2] - $bbox[0];
        $x = (int) ((self::ANCHO_PX - $ancho) / 2) - $bbox[0];
        $baseline = $yTop - $bbox[7];

        imagettftext($lienzo, $tamano, 0, max($x, 0), $baseline, $color, $fuente, $texto);

        return $baseline + $bbox[1] + 6;
    }
}
