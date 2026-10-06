<?php

namespace App\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $page
 */
class DocumentChunk extends Model
{
    protected $guarded = [];

    /** The vector is read only through the search repository, never serialized. */
    protected $hidden = ['embedding'];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
