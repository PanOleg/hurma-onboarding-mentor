<?php

namespace App\Knowledge\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }
}
