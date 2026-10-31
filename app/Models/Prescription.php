<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * @return HasMany<ControlledRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(ControlledRegisterEntry::class);
    }
}
