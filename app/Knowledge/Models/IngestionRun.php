<?php

namespace App\Knowledge\Models;

use App\Knowledge\Enums\IngestionStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property IngestionStep $step
 */
class IngestionRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'step' => IngestionStep::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
