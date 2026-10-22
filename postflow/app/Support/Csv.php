<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read and write CSV files the way Google Sheets and Excel produce them:
 * comma or semicolon, with or without the UTF-8 BOM (needed for Arabic in Excel).
 */
class Csv
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     *                                                                                   rows are keyed by the normalized header (see key())
     */
    public static function read(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n");
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = array_map('trim', fgetcsv($handle, null, $delimiter, '"', '') ?: []);
        $keys = array_map([self::class, 'key'], $headers);
        $rows = [];

        while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if (count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue; // empty line
            }
            $row = [];
            foreach ($keys as $i => $key) {
                $row[$key] = trim((string) ($line[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** "Légende (IG)" -> "legende_ig": lowercase, no accents, underscores. */
    public static function key(string $header): string
    {
        return Str::of($header)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell) => $cell instanceof \BackedEnum ? $cell->value : $cell, $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
