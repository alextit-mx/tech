<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Hoja de Excel (.xlsx) sin dependencias: un .xlsx es un zip de XML. Encabezados en negritas sobre rojo,
 * primera fila fija y filtros. Formatos por columna: text, int, money (pesos) y percent.
 */
class Xlsx
{
    private const STYLE = ['header' => 1, 'money' => 2, 'percent' => 3];

    /**
     * @param  array<string, array{0: string, 1: int}>  $columns  título => [formato, ancho]
     * @param  iterable<array<string, mixed>>  $rows  cada fila con las llaves de $columns (las que falten quedan vacías)
     */
    public static function write(string $path, string $sheet, array $columns, iterable $rows): void
    {
        $last = self::col(count($columns));
        $cols = '';
        $i = 0;
        foreach ($columns as [, $width]) {
            $i++;
            $cols .= "<col min=\"{$i}\" max=\"{$i}\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $data = self::row(1, array_map(fn ($title) => [$title, 'header'], array_keys($columns)));
        $n = 1;
        foreach ($rows as $row) {
            $n++;
            $data .= self::row($n, array_map(fn ($title, $c) => [$row[$title] ?? null, $c[0]], array_keys($columns), $columns));
        }

        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $name = self::esc(mb_substr($sheet, 0, 31));

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear {$path}");
        }
        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            ."<Relationship Id=\"rId1\" Type=\"{$rel}/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            ."<Relationship Id=\"rId1\" Type=\"{$rel}/worksheet\" Target=\"worksheets/sheet1.xml\"/>"
            ."<Relationship Id=\"rId2\" Type=\"{$rel}/styles\" Target=\"styles.xml\"/></Relationships>");
        $zip->addFromString('xl/workbook.xml', $head."<workbook {$ns} xmlns:r=\"{$rel}\"><sheets><sheet name=\"{$name}\" sheetId=\"1\" r:id=\"rId1\"/></sheets>"
            ."<definedNames><definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"0\" hidden=\"1\">'{$name}'!\$A\$1:\${$last}\${$n}</definedName></definedNames></workbook>");
        $zip->addFromString('xl/styles.xml', $head."<styleSheet {$ns}>"
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE10600"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $head."<worksheet {$ns}>"
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            ."<cols>{$cols}</cols><sheetData>{$data}</sheetData><autoFilter ref=\"A1:{$last}{$n}\"/></worksheet>");
        $zip->close();
    }

    /** @param  list<array{0: mixed, 1: string}>  $cells  [valor, formato] */
    private static function row(int $n, array $cells): string
    {
        $xml = "<row r=\"{$n}\">";
        foreach ($cells as $i => [$value, $format]) {
            if ($value === null || $value === '') {
                continue;
            }
            $ref = self::col($i + 1).$n;
            $style = isset(self::STYLE[$format]) ? ' s="'.self::STYLE[$format].'"' : '';
            $xml .= $format !== 'text' && $format !== 'header' && is_numeric($value)
                ? "<c r=\"{$ref}\"{$style}><v>".(0 + $value).'</v></c>'
                : "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">".self::esc((string) $value).'</t></is></c>';
        }

        return $xml.'</row>';
    }

    /** 1 → A, 27 → AA. */
    private static function col(int $n): string
    {
        $s = '';
        for (; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
