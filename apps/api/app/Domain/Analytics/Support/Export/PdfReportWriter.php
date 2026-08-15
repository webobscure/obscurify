<?php

namespace App\Domain\Analytics\Support\Export;

/**
 * A minimal but genuinely valid, paginated PDF writer — no dompdf/
 * external dependency, same reasoning as XlsxReportWriter. Renders each
 * report row as one monospace line of pipe-separated column values; not
 * styled, but a real, openable PDF, not a stub. Objects are built as
 * strings and their byte offsets tracked as they're appended, so the
 * final xref table's offsets are always correct regardless of content
 * length (report data is arbitrary merchant text, so nothing about its
 * length can be assumed ahead of time).
 */
final class PdfReportWriter
{
    private const int ROWS_PER_PAGE = 50;

    private const int PAGE_HEIGHT = 792;

    private const int PAGE_WIDTH = 612;

    private const int LINE_HEIGHT = 12;

    private const int TOP_MARGIN = 740;

    private const int LEFT_MARGIN = 40;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(array $rows): string
    {
        $lines = [];

        if ($rows !== []) {
            $lines[] = implode(' | ', array_keys($rows[0]));
        }

        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map(fn ($value) => is_array($value) ? json_encode($value) : (string) $value, $row));
        }

        if ($lines === []) {
            $lines[] = '(no rows)';
        }

        $pages = array_chunk($lines, self::ROWS_PER_PAGE);
        $pageCount = count($pages);

        // Object numbering: 1 = Catalog, 2 = Pages, 3 = Font, then for
        // each page i (0-indexed): (4 + i*2) = Page object, (5 + i*2) =
        // its content stream.
        $objects = [];

        $pageObjectIds = [];
        foreach ($pages as $i => $pageLines) {
            $pageObjectIds[] = 4 + $i * 2;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjectIds));
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        foreach ($pages as $i => $pageLines) {
            $pageObjId = 4 + $i * 2;
            $contentObjId = 5 + $i * 2;

            $objects[$pageObjId] = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> '
                .'/MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Contents '."{$contentObjId} 0 R >>";

            $stream = $this->contentStream($pageLines);
            $length = strlen($stream);
            $objects[$contentObjId] = "<< /Length {$length} >>\nstream\n{$stream}\nendstream";
        }

        return $this->assemble($objects);
    }

    /**
     * @param  list<string>  $lines
     */
    private function contentStream(array $lines): string
    {
        $body = 'BT /F1 9 Tf '.self::LEFT_MARGIN.' '.self::TOP_MARGIN." Td\n";

        foreach ($lines as $i => $line) {
            $leading = $i === 0 ? 0 : -self::LINE_HEIGHT;
            $body .= "0 {$leading} Td ({$this->escape($line)}) Tj\n";
        }

        $body .= 'ET';

        return $body;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * @param  array<int, string>  $objects  keyed by object id
     */
    private function assemble(array $objects): string
    {
        ksort($objects);

        $out = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $maxId = max(array_keys($objects));

        $out .= "xref\n0 ".($maxId + 1)."\n";
        $out .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $out .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $out .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $out;
    }
}
