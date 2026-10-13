<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Importacion;

use RuntimeException;
use ZipArchive;

/**
 * Genera un .xlsx sencillo (solo texto, sin librerias externas) con varias hojas.
 * La primera fila de cada hoja va en negrita con fondo, y se congela.
 */
final class EscritorXlsx
{
    /**
     * @param array<string, array{filas: array<int, array<int, string|int|float>>, anchos?: array<int, int>}> $hojas
     *        nombre de hoja => filas (la primera es el encabezado) y anchos de columna opcionales
     * @return string contenido binario del .xlsx
     */
    public function generar(array $hojas): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();

        if ($temporal === false || $zip->open($temporal, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar el archivo Excel.');
        }

        $nombres = array_keys($hojas);
        $overrides = '';
        $sheets = '';
        $rels = '';

        foreach ($nombres as $i => $nombre) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $this->hoja($hojas[$nombre]['filas'], $hojas[$nombre]['anchos'] ?? []));
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $sheets .= '<sheet name="' . $this->e(mb_substr($nombre, 0, 31)) . "\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }

        $estilosId = count($nombres) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . "<Relationship Id=\"rId{$estilosId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            . '</Relationships>');
        // Estilo 0: normal. Estilo 1: encabezado (negrita, blanco sobre azul).
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B1B4D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>');

        $zip->close();
        $contenido = (string) file_get_contents($temporal);
        @unlink($temporal);

        return $contenido;
    }

    /**
     * @param array<int, array<int, string|int|float>> $filas
     * @param array<int, int> $anchos
     */
    private function hoja(array $filas, array $anchos): string
    {
        $cols = '';
        foreach ($anchos as $i => $ancho) {
            $n = $i + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$ancho}\" customWidth=\"1\"/>";
        }

        $datos = '';
        foreach (array_values($filas) as $i => $fila) {
            $r = $i + 1;
            $estilo = $i === 0 ? ' s="1"' : '';
            $datos .= "<row r=\"{$r}\">";

            foreach (array_values($fila) as $j => $valor) {
                $ref = $this->letra($j) . $r;

                if (is_int($valor) || is_float($valor)) {
                    $datos .= "<c r=\"{$ref}\"{$estilo}><v>{$valor}</v></c>";
                } elseif ($valor !== '') {
                    $datos .= "<c r=\"{$ref}\" t=\"inlineStr\"{$estilo}><is><t xml:space=\"preserve\">" . $this->e($valor) . '</t></is></c>';
                }
            }

            $datos .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols !== '' ? "<cols>{$cols}</cols>" : '')
            . "<sheetData>{$datos}</sheetData></worksheet>";
    }

    /** 0 -> A, 25 -> Z, 26 -> AA. */
    private function letra(int $indice): string
    {
        $letra = '';
        $indice++;

        while ($indice > 0) {
            $resto = ($indice - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $indice = intdiv($indice - 1, 26);
        }

        return $letra;
    }

    private function e(string $texto): string
    {
        // Quita caracteres de control que invalidan el XML.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto) ?? $texto;

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
