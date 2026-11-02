<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Receta médica asociada a una venta (RF-8, RF-10, RF-11).
 */
final class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'prescriber_name',
        'prescriber_rut',
        'issued_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
        ];
    }

    /**
     * Último día en que la receta es válida (RF-8): el día de emisión más la
     * vigencia configurada, inclusive.
     */
    public function validUntil(): Carbon
    {
        return $this->issued_on->copy()
            ->addDays((int) config('pos.prescription.validity_days'));
    }

    /**
     * @return HasMany<ControlledRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(ControlledRegisterEntry::class);
    }
}
