<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/** Minimal XLSX writer using inline strings (Excel-compatible, no sharedStrings). */
class SimpleXlsxExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, string $sheetTitle, array $headers, array $rows): Response
    {
        $binary = self::build($sheetTitle, $headers, $rows);
        $safeName = preg_replace('/[^\w.\-Α-Ωα-ωΆ-Ώά-ώ ]+/u', '_', $filename) ?: 'export.xlsx';
        if (! str_ends_with(strtolower($safeName), '.xlsx')) {
            $safeName .= '.xlsx';
        }

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function build(string $sheetTitle, array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tsx');
        if ($tmp === false) {
            throw new RuntimeException('Cannot create temp file for XLSX.');
        }
        $zipPath = $tmp.'.xlsx';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open ZipArchive for XLSX.');
        }

        $sheetName = self::xml(mb_substr($sheetTitle !== '' ? $sheetTitle : 'Sheet1', 0, 31));
        $colCount = max(count($headers), 1);
        foreach ($rows as $row) {
            $colCount = max($colCount, count($row));
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML);

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{$sheetName}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

        $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="12"/><name val="Calibri"/></font>
    <font><b/><sz val="12"/><name val="Calibri"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
XML);

        $colsXml = '';
        for ($i = 1; $i <= $colCount; $i++) {
            $colsXml .= '<col min="'.$i.'" max="'.$i.'" width="18" customWidth="1"/>';
        }

        $sheetRowsXml = '';
        $allRows = array_merge([$headers], $rows);
        foreach ($allRows as $r => $row) {
            $rowNum = $r + 1;
            $style = $r === 0 ? 1 : 0;
            $cells = '';
            $padded = array_values($row);
            while (count($padded) < $colCount) {
                $padded[] = '';
            }
            foreach ($padded as $c => $value) {
                $ref = self::columnName($c + 1).$rowNum;
                $text = self::cellText($value);
                $cells .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($text).'</t></is></c>';
            }
            $sheetRowsXml .= '<row r="'.$rowNum.'">'.$cells.'</row>';
        }

        $lastCol = self::columnName($colCount);
        $lastRow = max(count($allRows), 1);

        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
            .'<cols>'.$colsXml.'</cols>'
            .'<sheetData>'.$sheetRowsXml.'</sheetData>'
            .'</worksheet>'
        );

        $zip->close();
        $binary = file_get_contents($zipPath);
        @unlink($zipPath);
        if ($binary === false) {
            throw new RuntimeException('Failed reading XLSX binary.');
        }

        return $binary;
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function cellText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
