<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/** Minimal XLSX writer (no PhpSpreadsheet) for small Platform Admin exports. */
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
            'Cache-Control' => 'max-age=0',
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

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
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
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
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
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="1"><xf/></cellXfs>
</styleSheet>
XML);

        $strings = [];
        $stringIndex = [];
        $addString = static function (string $value) use (&$strings, &$stringIndex): int {
            if (array_key_exists($value, $stringIndex)) {
                return $stringIndex[$value];
            }
            $idx = count($strings);
            $strings[] = $value;
            $stringIndex[$value] = $idx;

            return $idx;
        };

        $colCount = max(count($headers), 1);
        foreach ($rows as $row) {
            $colCount = max($colCount, count($row));
        }

        $sheetRowsXml = '';
        $allRows = array_merge([$headers], $rows);
        $totalCells = 0;
        foreach ($allRows as $r => $row) {
            $rowNum = $r + 1;
            $cells = '';
            $padded = array_values($row);
            while (count($padded) < $colCount) {
                $padded[] = '';
            }
            foreach ($padded as $c => $value) {
                $col = self::columnName($c + 1);
                $ref = $col.$rowNum;
                $text = self::cellText($value);
                // Keep password-like numeric values as text in Excel.
                if ($text !== '' && preg_match('/^\d+$/', $text) === 1) {
                    $text = "\u{200B}".$text;
                }
                $idx = $addString($text);
                $cells .= '<c r="'.$ref.'" t="s"><v>'.$idx.'</v></c>';
                $totalCells++;
            }
            $sheetRowsXml .= '<row r="'.$rowNum.'">'.$cells.'</row>';
        }

        $uniqueCount = count($strings);
        $shared = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$totalCells.'" uniqueCount="'.$uniqueCount.'">';
        foreach ($strings as $s) {
            $attrs = ($s === '' || preg_match('/^\s|\s$/', $s) === 1) ? ' xml:space="preserve"' : '';
            $shared .= '<si><t'.$attrs.'>'.self::xml($s).'</t></si>';
        }
        $shared .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $shared);

        $lastCol = self::columnName($colCount);
        $lastRow = max(count($allRows), 1);
        $dimension = 'A1:'.$lastCol.$lastRow;

        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="'.$dimension.'"/>'
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
