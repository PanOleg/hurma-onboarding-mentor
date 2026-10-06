<?php

namespace App\Knowledge\Models;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'audience_type' => AudienceType::class,
            'failure_code' => FailureCode::class,
        ];
    }

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    /** @return HasMany<DocumentChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /** @return HasMany<IngestionRun, $this> */
    public function ingestionRuns(): HasMany
    {
        return $this->hasMany(IngestionRun::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Ready->value);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        self::visibilityWhere($query, $user, $query->getModel()->getTable());

        return $query;
    }

    /**
     * The single place that encodes audience rules. Works for Eloquent and raw query builders,
     * so ChunkSearchRepository reuses it instead of re-implementing the rules.
     */
    public static function visibilityWhere(Builder|QueryBuilder $query, User $user, string $table = 'documents'): void
    {
        $query->where(function ($q) use ($user, $table) {
            $q->where("$table.audience_type", AudienceType::All->value)
                ->orWhere(function ($q2) use ($user, $table) {
                    $q2->where("$table.audience_type", AudienceType::Department->value)
                        ->where("$table.audience_value", (string) $user->department_id);
                })
                ->orWhere(function ($q3) use ($user, $table) {
                    $q3->where("$table.audience_type", AudienceType::Role->value)
                        ->where("$table.audience_value", (string) $user->job_role);
                });
        });
    }

    public function markFailed(FailureCode $code, string $message): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Failed,
            'failure_code' => $code,
            'failure_message' => mb_substr($message, 0, 2000),
        ])->save();
    }
}
