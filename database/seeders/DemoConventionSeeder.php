<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Convention;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoConventionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Demo memberships are restricted to local and testing environments.');
        }

        DB::transaction(function (): void {
            $today = now(config('pos.timezone'));
            $convention = Convention::query()->firstOrNew(['name' => 'Convenio Académico Demo']);
            $convention->forceFill([
                'percent' => 10,
                'valid_from' => $today->copy()->subDay()->toDateString(),
                'valid_until' => $today->copy()->addYear()->toDateString(),
                'active' => true,
                'requires_membership' => true,
            ])->save();

            DB::table('convention_memberships')->insertOrIgnore(['convention_id' => $convention->id, 'rut' => '1-9']);
        });
    }
}
