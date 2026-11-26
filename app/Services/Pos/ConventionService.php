<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Convention;
use App\Pos\Value\ChileanRut;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de convenios de salud (RF-39) y resolución del convenio aplicable
 * en una venta (RF-40).
 */
final class ConventionService
{
    /** @return Collection<int, Convention> */
    public function forCheckout(?string $rut): Collection
    {
        $query = Convention::query()->applicableOn(new DateTimeImmutable('today', new DateTimeZone(config('pos.timezone'))));

        if ($rut === null) {
            $query->where('requires_membership', false);
        } else {
            $query->whereIn('id', DB::table('convention_memberships')->select('convention_id')->where('rut', $rut));
        }

        return $query->orderBy('name')->get();
    }

    public function findForCheckout(int $id, DateTimeImmutable $today, ?string $rut): Convention
    {
        $convention = $this->findApplicable($id, $today);

        if ($rut !== null || $convention->requires_membership) {
            $normalized = $rut === null ? null : ChileanRut::normalize($rut);
            if ($normalized === null || ! DB::table('convention_memberships')->where('rut', $normalized)->where('convention_id', $id)->exists()) {
                throw new DomainException(__('pos.checkout.benefit_ineligible'));
            }
        }

        return $convention;
    }

    public function registerMembership(string $rut, string $name, int $conventionId, int $authorizedBy): void
    {
        DB::transaction(function () use ($rut, $name, $conventionId, $authorizedBy): void {
            $today = new DateTimeImmutable('today', new DateTimeZone(config('pos.timezone')));
            $convention = Convention::query()->applicableOn($today)->lockForUpdate()->find($conventionId);
            if ($convention === null) {
                throw new DomainException;
            }

            DB::table('pos_clients')->insertOrIgnore(['rut' => $rut, 'name' => $name]);
            DB::table('convention_memberships')->insertOrIgnore([
                'rut' => $rut, 'convention_id' => $conventionId, 'authorized_by' => $authorizedBy,
            ]);
        });
    }

    /**
     * @return Collection<int, Convention>
     */
    public function all(): Collection
    {
        return Convention::query()
            ->select('conventions.*')
            ->selectSub(
                DB::table('convention_memberships')
                    ->selectRaw('count(*)')
                    ->whereColumn('convention_memberships.convention_id', 'conventions.id'),
                'memberships_count',
            )
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
            'applies_to_controlled' => (bool) ($data['applies_to_controlled'] ?? false),
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
            'applies_to_controlled' => (bool) ($data['applies_to_controlled'] ?? $convention->applies_to_controlled),
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
     * Alta lógica desde el interruptor del panel (RF-50).
     *
     * Un convenio vencido no se reactiva: primero hay que extender su vigencia.
     */
    public function activate(int $id, ?DateTimeImmutable $today = null): Convention
    {
        $convention = Convention::query()->findOrFail($id);
        $day = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0, 0);

        if ($convention->valid_until->toDateTimeImmutable()->setTime(0, 0, 0) < $day) {
            throw new DomainException(__('pos.panel.conventions.error.expired'));
        }

        $convention->update(['active' => true]);

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
            throw new DomainException(__('pos.errors.convention_missing'));
        }

        if (! $convention->active) {
            throw new DomainException(__('pos.errors.convention_inactive'));
        }

        if (! $convention->isApplicableOn($today)) {
            throw new DomainException(__('pos.errors.convention_expired'));
        }

        return $convention;
    }
}
