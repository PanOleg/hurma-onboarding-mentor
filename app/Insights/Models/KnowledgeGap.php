<?php

namespace App\Insights\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeGap extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_asked_at' => 'datetime'];
    }
}
