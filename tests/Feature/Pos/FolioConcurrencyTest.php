<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\SaleStatus;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * S9 - RF-24/RF-26: dos conexiones reales asignando folios del mismo tipo.
 *
 * No es un test secuencial: el padre lanza dos procesos PHP independientes,
 * cada uno con su propia conexión PostgreSQL, sincronizados por una barrera de
 * tiempo. Si la asignación de folios no estuviera serializada, ambos leerían el
 * mismo máximo y el índice único (type, folio) haría fallar uno de los dos
 * (venta/documento perdido).
 */
final class FolioConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Tiempo máximo de espera de los workers (F0.1).
     *
     * Cada worker arranca una aplicación Laravel completa, así que bajo carga
     * el arranque puede tardar más que el trabajo útil. El valor anterior (30 s)
     * quedaba al límite y rompía la corrida completa; 90 s deja margen de sobra
     * sin volver lento el caso normal, que termina en pocos segundos.
     */
    private const WORKER_DEADLINE_SECONDS = 90.0;

    public function test_two_real_connections_get_unique_folios_without_losing_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($cashier);
        $this->openCashRegister($cashier);

        $product = Product::factory()->create(['price' => 1000, 'stock' => 20]);

        // Cuatro ventas reales por la caja, con documento emitido.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/pos/cart', ['product_id' => $product->id])->assertRedirect('/pos');
            $this->post('/pos/checkout', ['payment_method' => 'debit'])->assertRedirect();
        }

        $this->assertSame(4, Sale::query()->count());
        $this->assertSame(4, Document::query()->count());

        // Se dejan los cuatro documentos pendientes y sin folio: ahora compiten
        // dos procesos por el mismo rango, como tras una caída del emisor.
        Document::query()->update([
            'folio' => null,
            'status' => DocumentStatus::pending,
            'issued_at' => null,
        ]);

        $ids = Document::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(4, $ids);

        $startAt = microtime(true) + 2.0;
        $workers = $this->launchWorkers([
            [$ids[0], $ids[1]],
            [$ids[2], $ids[3]],
        ], $startAt);

        foreach ($workers as $index => $worker) {
            $this->assertSame(0, $worker['exit'], "El worker {$index} falló: {$worker['stderr']}");
            $this->assertSame('', trim($worker['stderr']), "El worker {$index} reportó un error.");
        }

        $results = array_merge(...array_map(
            fn (array $worker): array => json_decode($worker['stdout'], true, 512, JSON_THROW_ON_ERROR),
            $workers,
        ));

        $this->assertCount(4, $results);

        $folios = array_map(fn (array $row): int => (int) $row['folio'], $results);
        sort($folios);

        // Sin folios duplicados y sin huecos dentro del rango usado.
        $this->assertSame([1, 2, 3, 4], $folios);
        $this->assertSame(4, count(array_unique($folios)));

        // Ninguna venta se perdió ni quedó sin documento emitido.
        $this->assertSame(4, Sale::query()->count());
        $this->assertSame(4, Sale::query()->where('status', SaleStatus::confirmed)->count());
        $this->assertSame(4, Document::query()->count());
        $this->assertSame(4, Document::query()->where('status', DocumentStatus::issued)->count());
        $this->assertSame(4, Document::query()->whereNotNull('folio')->count());
        $this->assertSame(4, Document::query()->distinct()->count('folio'));
    }

    /**
     * @param  array<int, array<int, int>>  $groups
     * @return array<int, array{exit: int, stdout: string, stderr: string}>
     */
    private function launchWorkers(array $groups, float $startAt): array
    {
        $workers = [];
        $failure = null;

        try {
            foreach ($groups as $index => $ids) {
                $command = [
                    PHP_BINARY,
                    base_path('tests/Fixtures/concurrent-retry.php'),
                    implode(',', $ids),
                    (string) $startAt,
                ];

                $descriptors = [
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $process = proc_open($command, $descriptors, $pipes, base_path(), $this->workerEnvironment());

                if (! is_resource($process)) {
                    throw new RuntimeException("No se pudo lanzar el worker {$index}.");
                }

                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                $workers[$index] = [
                    'process' => $process,
                    'pipes' => $pipes,
                    'stdout' => '',
                    'stderr' => '',
                    'exit' => null,
                ];
            }

            $deadline = microtime(true) + self::WORKER_DEADLINE_SECONDS;

            do {
                $running = false;

                foreach ($workers as $index => $worker) {
                    $workers[$index]['stdout'] .= $this->drain($worker['pipes'][1]);
                    $workers[$index]['stderr'] .= $this->drain($worker['pipes'][2]);

                    $status = proc_get_status($worker['process']);

                    if ($status['running']) {
                        $running = true;
                    } elseif ($workers[$index]['exit'] === null) {
                        $workers[$index]['exit'] = (int) $status['exitcode'];
                    }
                }

                if ($running && microtime(true) > $deadline) {
                    throw new RuntimeException(sprintf(
                        'Los workers de concurrencia no terminaron a tiempo (%d s).',
                        self::WORKER_DEADLINE_SECONDS,
                    ));
                }

                if ($running) {
                    usleep(20000);
                }
            } while ($running);
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            $workers = $this->closeWorkers($workers);
        }

        if ($failure !== null) {
            throw $failure;
        }

        return $workers;
    }

    /**
     * Cierra pipes y procesos aunque el test falle, para no dejar huérfanos.
     *
     * @param  array<int, array{process?: resource, pipes?: array<int, resource>, stdout: string, stderr: string, exit: int|null}>  $workers
     * @return array<int, array{exit: int, stdout: string, stderr: string}>
     */
    private function closeWorkers(array $workers): array
    {
        foreach ($workers as $index => $worker) {
            $process = $worker['process'] ?? null;
            $pipes = $worker['pipes'] ?? [];

            if (is_resource($process)) {
                $status = proc_get_status($process);

                if ($status['running']) {
                    proc_terminate($process);
                }

                if ($workers[$index]['exit'] === null) {
                    $workers[$index]['exit'] = (int) $status['exitcode'];
                }
            }

            $workers[$index]['stdout'] .= $this->drain($pipes[1] ?? null);
            $workers[$index]['stderr'] .= $this->drain($pipes[2] ?? null);

            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                $closed = (int) proc_close($process);

                if ($workers[$index]['exit'] === null || $workers[$index]['exit'] < 0) {
                    $workers[$index]['exit'] = $closed;
                }
            }

            if ($workers[$index]['exit'] === null) {
                $workers[$index]['exit'] = -1;
            }

            unset($workers[$index]['process'], $workers[$index]['pipes']);
        }

        return $workers;
    }

    /**
     * Lee lo disponible en un pipe sin bloquear.
     */
    private function drain(mixed $pipe): string
    {
        if (! is_resource($pipe)) {
            return '';
        }

        return (string) stream_get_contents($pipe);
    }

    /**
     * El proceso hijo hereda el entorno y apunta a la misma base de pruebas.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $connection = (array) config('database.connections.pgsql');

        $env = getenv();

        return array_merge(is_array($env) ? $env : [], [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($connection['port'] ?? '5432'),
            'DB_DATABASE' => (string) ($connection['database'] ?? 'farmacia_pos_test'),
            'DB_USERNAME' => (string) ($connection['username'] ?? 'postgres'),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        ]);
    }
}
