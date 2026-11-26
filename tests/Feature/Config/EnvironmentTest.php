<?php

declare(strict_types=1);

namespace Tests\Feature\Config;

use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * T20 — contrato del entorno (H1 del informe de revisión).
 *
 * El sistema escribe las fechas con `now()` (huso de `config('app.timezone')`) y
 * los paneles filtran «de hoy» con el huso del negocio (`config('pos.timezone')`).
 * Si los dos husos difieren, los totales del día se corren de fecha entre las
 * 21:00 y las 23:59 locales. El primer test fija ese acoplamiento; los otros
 * comprueban que el archivo de ejemplo (` .env.example`) declare el stack real,
 * porque un clon que copie el ejemplo corría con otro reloj, otro motor de base
 * de datos y otro idioma.
 */
final class EnvironmentTest extends TestCase
{
    public function test_the_application_timezone_matches_the_business_timezone(): void
    {
        $this->assertSame(
            config('pos.timezone'),
            config('app.timezone'),
            'El reloj de la aplicación y el del negocio deben coincidir: con husos distintos, las ventas se escriben en una fecha y los paneles «de hoy» las buscan en otra.',
        );
    }

    public function test_the_sample_environment_declares_the_business_timezone(): void
    {
        $this->assertContains(
            'APP_TIMEZONE='.config('pos.timezone'),
            $this->sampleEnvironment(),
            'El archivo .env.example debe declarar la zona horaria del negocio.',
        );
    }

    public function test_the_sample_environment_points_at_postgresql(): void
    {
        $lines = $this->sampleEnvironment();

        $this->assertContains('DB_CONNECTION=pgsql', $lines, 'El stack es PostgreSQL, no SQLite.');
        $this->assertContains('DB_DATABASE=farmacia_pos', $lines);
        $this->assertContains('DB_USERNAME=postgres', $lines);
    }

    public function test_the_sample_environment_keeps_sessions_in_the_database(): void
    {
        $this->assertContains(
            'SESSION_DRIVER=database',
            $this->sampleEnvironment(),
            'La sesión vive en la base: si el ejemplo usa el driver por archivos, la sesión no se comparte entre procesos.',
        );
    }

    public function test_postgresql_tls_can_verify_the_server_using_the_configured_ca(): void
    {
        $environment = Env::getRepository();
        $settings = ['DB_SSLMODE' => 'verify-full', 'DB_SSLROOTCERT' => '/run/certs/rds-ca.pem'];
        $previous = [];

        foreach ($settings as $key => $value) {
            $previous[$key] = $environment->get($key);
            $environment->clear($key);
            $environment->set($key, $value);
        }

        try {
            $connection = (require config_path('database.php'))['connections']['pgsql'];

            $this->assertSame('verify-full', $connection['sslmode']);
            $this->assertSame('/run/certs/rds-ca.pem', $connection['sslrootcert']);
        } finally {
            foreach ($previous as $key => $value) {
                $environment->clear($key);

                if ($value !== null) {
                    $environment->set($key, $value);
                }
            }
        }
    }

    /**
     * Líneas útiles de `.env.example`, sin comentarios ni líneas vacías.
     *
     * @return list<string>
     */
    private function sampleEnvironment(): array
    {
        $path = base_path('.env.example');

        $this->assertFileExists($path, 'Falta el archivo de ejemplo del entorno.');

        $lines = file((string) $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        $useful = array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        );

        return array_values($useful);
    }
}
