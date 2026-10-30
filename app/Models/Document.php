<?php

declare(strict_types=1);

namespace App\Models;

use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Document extends Model
{
    protected $fillable = [
        'sale_id',
        'reversal_of_id',
        'type',
        'folio',
        'status',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folio' => 'integer',
            'issued_at' => 'datetime',
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Documento original que esta nota de crédito revierte (RF-34).
     *
     * @return BelongsTo<Document, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
