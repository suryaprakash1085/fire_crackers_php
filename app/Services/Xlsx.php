<?php
namespace App\Services;

/**
 * Tiny dependency-free .xlsx writer / reader (needs PHP's zip + SimpleXML extensions, both on by default in XAMPP).
 * Writer: several sheets, bold header, frozen header row, column widths, dropdown (list) validations.
 * Reader: returns the rows of one sheet as arrays of strings (shared strings, inline strings, numbers, booleans).
 */
final class Xlsx {
    private const MAX_UNZIPPED = 60 * 1024 * 1024;

    public static function available(): bool { return class_exists(\ZipArchive::class) && function_exists('simplexml_load_string'); }

    public static function colName(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }
    private static function colIndex(string $ref): int { preg_match('/^([A-Z]+)/', strtoupper($ref), $m); $n = 0; foreach (str_split($m[1] ?? 'A') as $ch) $n = $n * 26 + ord($ch) - 64; return $n - 1; }
    private static function x(string $s): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /**
     * @param array $sheets list of ['name'=>string,'rows'=>array<array>,'widths'=>?int[],'header'=>?bool,'validations'=>?array<['col'=>int,'list'=>string,'rows'=>int]>, 'wrap'=>?bool]
     *   cell values: string|int|float|null. Numbers are written as numbers, everything else as text.
     * @return string binary .xlsx
     */
    public static function build(array $sheets): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wbSheets = ''; $wbRels = '';
        foreach ($sheets as $i => $sh) {
            $n = $i + 1;
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="' . self::x(mb_substr($sh['name'], 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', self::sheetXml($sh));
        }
        $ct .= '</Types>';
        $wbRels .= '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('[Content_Types].xml', $ct);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
        // styles: 0 normal, 1 header (bold white on orange), 2 wrapped text, 3 bold, 4 text format (@)
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8590C"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();
        $bin = (string)file_get_contents($tmp); @unlink($tmp);
        return $bin;
    }

    private static function sheetXml(array $sh): string {
        $rows = $sh['rows']; $header = $sh['header'] ?? true; $wrap = !empty($sh['wrap']);
        $textCols = $sh['textCols'] ?? [];
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($header) $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        if (!empty($sh['widths'])) {
            $x .= '<cols>';
            foreach ($sh['widths'] as $i => $w) {
                $stAttr = in_array($i, $textCols, true) ? ' style="4"' : '';
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (int)$w . '" customWidth="1"' . $stAttr . '/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($rows as $r => $row) {
            $x .= '<row r="' . ($r + 1) . '"' . ($r === 0 && $header ? ' ht="30" customHeight="1"' : '') . '>';
            foreach (array_values($row) as $c => $v) {
                if ($v === null || $v === '') continue;
                $ref = self::colName($c) . ($r + 1);
                $isTextCol = in_array($c, $textCols, true);
                $style = ($r === 0 && $header) ? 1 : ($isTextCol ? 4 : ($wrap ? 2 : (($sh['boldFirstCol'] ?? false) && $c === 0 ? 3 : 0)));
                if ($isTextCol && $r > 0) {
                    $x .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::x((string)$v) . '</t></is></c>';
                } elseif (is_int($v) || is_float($v)) {
                    $x .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::x((string)$v) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($sh['validations'])) {
            $x .= '<dataValidations count="' . count($sh['validations']) . '">';
            foreach ($sh['validations'] as $v) {
                $col = self::colName($v['col']); $last = max(2, (int)$v['rows']);
                $x .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Invalid value" error="' . self::x($v['error'] ?? 'Choose a value from the list.') . '" sqref="' . $col . '2:' . $col . $last . '"><formula1>' . self::x($v['list']) . '</formula1></dataValidation>';
            }
            $x .= '</dataValidations>';
        }
        return $x . '</worksheet>';
    }

    /** Reads one sheet (by name, default first) into array<int, array<int,string>>. Throws \RuntimeException on a bad file. */
    public static function read(string $path, ?string $sheetName = null): array {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \RuntimeException('This is not a valid .xlsx file. Open it in Excel and use Save As → Excel Workbook (.xlsx).');
        $total = 0; for ($i = 0; $i < $zip->numFiles; $i++) $total += (int)($zip->statIndex($i)['size'] ?? 0);
        if ($total > self::MAX_UNZIPPED) { $zip->close(); throw new \RuntimeException('The spreadsheet is too large.'); }
        $get = function (string $n) use ($zip) { $s = $zip->getFromName($n); return $s === false ? null : $s; };
        $parse = function (?string $xml) { if ($xml === null) return null; $o = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT); return $o ?: null; };

        $wb = $parse($get('xl/workbook.xml'));
        if (!$wb) { $zip->close(); throw new \RuntimeException('Could not read the workbook.'); }
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $target = null; $first = null;
        foreach ($wb->xpath('//m:sheets/m:sheet') ?: [] as $sh) {
            $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $first ??= $rid;
            if ($sheetName !== null && strcasecmp((string)$sh['name'], $sheetName) === 0) $target = $rid;
        }
        $target ??= $first;
        $rels = $parse($get('xl/_rels/workbook.xml.rels')); $file = null;
        foreach ($rels ? $rels->Relationship : [] as $rel) if ((string)$rel['Id'] === $target) { $t = (string)$rel['Target']; $file = ltrim(str_starts_with($t, '/') ? $t : 'xl/' . $t, '/'); }
        if (!$file) { $zip->close(); throw new \RuntimeException('No sheet found in the workbook.'); }

        $shared = [];
        if (($ss = $parse($get('xl/sharedStrings.xml')))) foreach ($ss->si as $si) { $t = ''; if (isset($si->t)) $t = (string)$si->t; else foreach ($si->r as $r) $t .= (string)$r->t; $shared[] = $t; }

        $sheetXml = $get($file); $zip->close();
        $sx = $parse($sheetXml);
        if (!$sx) throw new \RuntimeException('Could not read the sheet.');
        $out = [];
        foreach ($sx->sheetData->row ?? [] as $row) {
            $r = (int)$row['r'] - 1; $cells = []; $max = -1; $auto = 0;
            foreach ($row->c as $c) {
                $idx = isset($c['r']) ? self::colIndex((string)$c['r']) : $auto; $auto = $idx + 1; $t = (string)$c['t'];
                if ($t === 's') $v = $shared[(int)$c->v] ?? '';
                elseif ($t === 'inlineStr') { $v = ''; if (isset($c->is->t)) $v = (string)$c->is->t; else foreach ($c->is->r ?? [] as $run) $v .= (string)$run->t; }
                elseif ($t === 'b') $v = ((string)$c->v) === '1' ? 'TRUE' : 'FALSE';
                else { $v = (string)$c->v; if ($t === '' && $v !== '' && is_numeric($v)) $v = rtrim(rtrim(number_format((float)$v, 10, '.', ''), '0'), '.'); }
                $cells[$idx] = $v; $max = max($max, $idx);
            }
            $line = []; for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? '';
            $out[$r >= 0 ? $r : count($out)] = $line;
        }
        if ($out) { ksort($out); $last = array_key_last($out); $full = []; for ($i = 0; $i <= $last; $i++) $full[] = $out[$i] ?? []; $out = $full; }
        return $out;
    }
}
