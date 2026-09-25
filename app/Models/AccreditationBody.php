<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AccreditationBodyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'website', 'is_active'])]
class AccreditationBody extends Model
{
    /** @use HasFactory<AccreditationBodyFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }
}
