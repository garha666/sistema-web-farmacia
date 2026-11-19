<?php

declare(strict_types=1);

namespace App\Services\Reportes;

/**
 * Exportador de CSV para reportes (RF-93, RF-97).
 *
 * Genera CSV UTF-8 con encabezados y filas a partir de un array de datos.
 * No modifica el estado del sistema.
 */
final class CsvExporter
{
    /**
     * @param  array<string>  $headers
     * @param  array<int, array<string|int|float|null>>  $rows
     */
    public function export(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
