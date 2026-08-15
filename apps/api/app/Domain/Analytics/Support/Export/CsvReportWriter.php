<?php

namespace App\Domain\Analytics\Support\Export;

final class CsvReportWriter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(array $rows): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]), escape: '\\');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, $row), escape: '\\');
            }
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }
}
