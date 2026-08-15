<?php

namespace App\Domain\Analytics\Support\Export;

use ZipArchive;

/**
 * A minimal but genuinely valid single-sheet XLSX writer — no
 * PhpSpreadsheet/external dependency, matching this codebase's
 * standing preference for small hand-rolled solutions over adding a
 * library for one narrow need (see e.g. DeliverWebhookJob's own HMAC
 * signing instead of a webhook SDK). Uses inline strings (`t="inlineStr"`)
 * rather than a shared-strings table, which keeps the writer to one
 * pass over the data at the cost of a slightly larger file — a
 * reasonable tradeoff for report-sized exports (see RunReport::MAX_ROWS).
 */
final class XlsxReportWriter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(array $rows): string
    {
        $headers = $rows === [] ? [] : array_keys($rows[0]);

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($headers, $rows));

        $zip->close();

        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return $contents;
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
        <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
        <Default Extension="xml" ContentType="application/xml"/>
        <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
        <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
        </Types>
        XML;
    }

    private function rootRelsXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
        </Relationships>
        XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
        <sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>
        </workbook>
        XML;
    }

    private function workbookRelsXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
        </Relationships>
        XML;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function sheetXml(array $headers, array $rows): string
    {
        $xmlRows = [];
        $rowNumber = 1;

        if ($headers !== []) {
            $xmlRows[] = $this->rowXml($rowNumber++, $headers);
        }

        foreach ($rows as $row) {
            $xmlRows[] = $this->rowXml($rowNumber++, array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, array_values($row)));
        }

        $body = implode('', $xmlRows);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>{$body}</sheetData></worksheet>
        XML;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        $cells = '';

        foreach ($values as $columnIndex => $value) {
            $ref = $this->columnLetter($columnIndex).$rowNumber;

            if (is_numeric($value)) {
                $cells .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
            } else {
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
        }

        return "<row r=\"{$rowNumber}\">{$cells}</row>";
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        do {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }
}
