<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Convention;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Catálogo de convenios de salud (RF-39) y resolución del convenio aplicable
 * en una venta (RF-40).
 */
final class ConventionService
{
    /**
     * @return Collection<int, Convention>
     */
    public function all(): Collection
    {
        return Convention::query()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Convention>
     */
    public function applicable(?DateTimeImmutable $today = null): Collection
    {
        $today ??= new DateTimeImmutable('today');

        return Convention::query()
            ->applicableOn($today)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, percent: float|int|string, valid_from: string, valid_until: string, active?: bool}  $data
     */
    public function create(array $data): Convention
    {
        return Convention::query()->create([
            'name' => trim($data['name']),
            'percent' => (float) $data['percent'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'active' => (bool) ($data['active'] ?? true),
        ]);
    }

    /**
     * @param  array{name: string, percent: float|int|string, valid_from: string, valid_until: string, active?: bool}  $data
     */
    public function update(int $id, array $data): Convention
    {
        $convention = Convention::query()->findOrFail($id);

        $convention->update([
            'name' => trim($data['name']),
            'percent' => (float) $data['percent'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'active' => (bool) ($data['active'] ?? $convention->active),
        ]);

        return $convention;
    }

    /**
     * Baja lógica: el convenio se conserva para el historial (RF-40).
     */
    public function deactivate(int $id): Convention
    {
        $convention = Convention::query()->findOrFail($id);
        $convention->update(['active' => false]);

        return $convention;
    }

    /**
     * Convenio utilizable hoy; si no existe, está inactivo o fuera de
     * vigencia, la venta se rechaza con el motivo (RF-39, RF-40).
     */
    public function findApplicable(int $id, DateTimeImmutable $today): Convention
    {
        $convention = Convention::query()->find($id);

        if ($convention === null) {
            throw new DomainException('El convenio seleccionado no existe.');
        }

        if (! $convention->active) {
            throw new DomainException('El convenio seleccionado está desactivado.');
        }

        if (! $convention->isApplicableOn($today)) {
            throw new DomainException('El convenio seleccionado está fuera de vigencia.');
        }

        return $convention;
    }
}
