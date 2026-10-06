<?php

namespace App\Chat\Models;

use App\Chat\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'grounded' => 'boolean',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return HasMany<MessageCitation, $this> */
    public function citations(): HasMany
    {
        return $this->hasMany(MessageCitation::class)->orderBy('marker');
    }
}
