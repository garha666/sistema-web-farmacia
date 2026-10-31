<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Convenio de salud con porcentaje de descuento y vigencia (RF-39, RF-40).
 */
final class Convention extends Model
{
    /** @use HasFactory<\Database\Factories\ConventionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'percent',
        'valid_from',
        'valid_until',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * RF-39: un convenio desactivado o fuera de vigencia no se aplica.
     */
    public function isApplicableOn(DateTimeImmutable $today): bool
    {
        $day = $today->setTime(0, 0, 0);

        return $this->active
            && $day >= $this->valid_from->toDateTimeImmutable()->setTime(0, 0, 0)
            && $day <= $this->valid_until->toDateTimeImmutable()->setTime(0, 0, 0);
    }

    /**
     * @param  Builder<Convention>  $query
     * @return Builder<Convention>
     */
    public function scopeApplicableOn(Builder $query, DateTimeImmutable $today): Builder
    {
        $day = $today->format('Y-m-d');

        return $query
            ->where('active', true)
            ->whereDate('valid_from', '<=', $day)
            ->whereDate('valid_until', '>=', $day);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
