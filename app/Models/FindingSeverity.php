<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'description', 'color', 'requires_corrective_action', 'default_due_days', 'sort_order', 'is_active'])]
class FindingSeverity extends Model
{
    protected function casts(): array
    {
        return ['requires_corrective_action' => 'boolean', 'is_active' => 'boolean'];
    }
}
