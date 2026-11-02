<?php

declare(strict_types=1);

/**
 * Worker del test de concurrencia de folios (S9).
 *
 * Arranca una aplicación Laravel completa con su propia conexión PostgreSQL,
 * espera una marca de tiempo común y reintenta los documentos indicados. El
 * proceso padre lanza dos de estos workers a la vez: son dos conexiones
 * reales compitiendo por el mismo rango de folios.
 *
 * Uso: php tests/Fixtures/concurrent-retry.php "1,2" 1767225600.123
 */

use App\Models\Document;
use App\Services\Pos\DocumentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;

$base = dirname(__DIR__, 2);

require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ids = array_values(array_filter(array_map('intval', explode(',', (string) ($argv[1] ?? '')))));
$startAt = (float) ($argv[2] ?? 0);

if ($ids === []) {
    fwrite(STDERR, 'Sin documentos para reintentar.'.PHP_EOL);
    exit(2);
}

// Barrera de arranque: ambos workers entran al crítico casi al mismo tiempo.
while (microtime(true) < $startAt) {
    usleep(500);
}

$service = $app->make(DocumentService::class);
$results = [];

try {
    foreach ($ids as $id) {
        $issued = $service->retry(Document::query()->findOrFail($id));

        $results[] = [
            'id' => $issued->id,
            'sale_id' => $issued->sale_id,
            'folio' => $issued->folio,
            'status' => $issued->status->value,
        ];
    }
} catch (QueryException $exception) {
    fwrite(STDERR, 'QueryException: '.$exception->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
}

echo json_encode($results, JSON_THROW_ON_ERROR);
exit(0);
