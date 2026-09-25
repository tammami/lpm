<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'sort_order', 'is_active'])]
class RootCauseCategory extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
