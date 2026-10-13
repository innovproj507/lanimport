<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Importacion;

use Courier\Shared\Domain\Exception\ValidationException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee la primera hoja de un .xlsx (con ZipArchive + XML, sin librerias externas)
 * o un .csv, y devuelve las filas como arreglos indexados por el encabezado.
 *
 * Los encabezados se normalizan: minusculas, sin tildes y con "_" en vez de espacios
 * ("Fecha de ingreso" -> "fecha_de_ingreso"). Cada fila trae ademas '_fila' con su
 * numero en la hoja, para poder reportar errores ("fila 7: ...").
 */
final class LectorHojaCalculo
{
    /** @return array<int, array<string, string>> */
    public function leer(string $ruta, string $nombreOriginal): array
    {
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        $filas = match ($extension) {
            'xlsx' => $this->leerXlsx($ruta),
            'csv', 'txt' => $this->leerCsv($ruta),
            default => throw new ValidationException('Formato no soportado: suba un archivo Excel (.xlsx) o CSV.'),
        };

        return $this->conEncabezados($filas);
    }

    /**
     * @param array<int, array<int, string>> $filas numero de fila => [columna => valor]
     * @return array<int, array<string, string>>
     */
    private function conEncabezados(array $filas): array
    {
        $encabezados = null;
        $resultado = [];

        foreach ($filas as $numero => $celdas) {
            $celdas = array_map(static fn ($v) => trim((string) $v), $celdas);

            if (implode('', $celdas) === '') {
                continue;
            }

            if ($encabezados === null) {
                $encabezados = array_map([self::class, 'normalizarEncabezado'], $celdas);
                continue;
            }

            $fila = ['_fila' => (string) $numero];

            foreach ($encabezados as $columna => $encabezado) {
                if ($encabezado !== '') {
                    $fila[$encabezado] = $celdas[$columna] ?? '';
                }
            }

            $resultado[] = $fila;
        }

        if ($encabezados === null) {
            throw new ValidationException('El archivo esta vacio.');
        }

        return $resultado;
    }

    public static function normalizarEncabezado(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $texto = preg_replace('/\(.*?\)/', '', $texto) ?? $texto; // "Peso (kg)" -> "peso"
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto) ?? $texto;

        return trim($texto, '_');
    }

    /** @return array<int, array<int, string>> */
    private function leerCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido; // BOM

        if (!mb_check_encoding($contenido, 'UTF-8')) {
            // Excel en Windows guarda los CSV en Windows-1252.
            $contenido = (string) mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $primeraLinea = strtok($contenido, "\n") ?: '';
        $delimitador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);

        $filas = [];
        $numero = 0;

        while (($celdas = fgetcsv($flujo, 0, $delimitador, '"', '')) !== false) {
            $numero++;
            $filas[$numero] = array_map('strval', $celdas);
        }

        fclose($flujo);

        return $filas;
    }

    /** @return array<int, array<int, string>> */
    private function leerXlsx(string $ruta): array
    {
        $zip = new ZipArchive();

        if ($zip->open($ruta) !== true) {
            throw new ValidationException('No se pudo abrir el archivo Excel. Verifique que sea un .xlsx valido.');
        }

        try {
            $compartidos = $this->textosCompartidos($zip);
            $xmlHoja = $zip->getFromName($this->rutaPrimeraHoja($zip));

            if ($xmlHoja === false) {
                throw new ValidationException('El archivo Excel no tiene hojas.');
            }

            $hoja = $this->xml($xmlHoja);
            $filas = [];

            foreach ($hoja->sheetData->row as $row) {
                $numero = (int) $row['r'];
                $celdas = [];
                $siguiente = 0;

                foreach ($row->c as $c) {
                    $columna = isset($c['r']) ? $this->indiceColumna((string) $c['r']) : $siguiente;
                    $siguiente = $columna + 1;
                    $celdas[$columna] = $this->valorCelda($c, $compartidos);
                }

                if ($celdas !== []) {
                    $completa = array_fill(0, max(array_keys($celdas)) + 1, '');
                    $filas[$numero] = array_replace($completa, $celdas);
                }
            }

            return $filas;
        } finally {
            $zip->close();
        }
    }

    /** @param array<int, string> $compartidos */
    private function valorCelda(SimpleXMLElement $c, array $compartidos): string
    {
        $tipo = (string) ($c['t'] ?? 'n');

        return match ($tipo) {
            's' => $compartidos[(int) $c->v] ?? '',
            'inlineStr' => $this->textoRico($c->is),
            'b' => ((string) $c->v) === '1' ? 'VERDADERO' : 'FALSO',
            default => (string) $c->v, // numeros y fechas (las fechas llegan como numero de serie)
        };
    }

    /** @return array<int, string> */
    private function textosCompartidos(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $textos = [];

        foreach ($this->xml($xml)->si as $si) {
            $textos[] = $this->textoRico($si);
        }

        return $textos;
    }

    /** Texto de un <si>/<is>: simple (<t>) o con formato (<r><t>…</t></r>). */
    private function textoRico(?SimpleXMLElement $nodo): string
    {
        if ($nodo === null) {
            return '';
        }

        if (isset($nodo->t)) {
            return (string) $nodo->t;
        }

        $texto = '';

        foreach ($nodo->r as $r) {
            $texto .= (string) $r->t;
        }

        return $texto;
    }

    private function rutaPrimeraHoja(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relaciones === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $libro = $this->xml($workbook);
        $hoja = $libro->sheets->sheet[0] ?? null;
        $rId = $hoja !== null ? (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        foreach ($this->xml($relaciones)->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $destino = ltrim((string) $rel['Target'], '/');

                return str_starts_with($destino, 'xl/') ? $destino : 'xl/' . $destino;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** "C12" -> 2 (columna A = 0). */
    private function indiceColumna(string $referencia): int
    {
        $letras = preg_replace('/\d+/', '', strtoupper($referencia)) ?? 'A';
        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    private function xml(string $contenido): SimpleXMLElement
    {
        $previo = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previo);

        if ($xml === false) {
            throw new ValidationException('El archivo Excel esta danado o no es un .xlsx valido.');
        }

        return $xml;
    }
}
