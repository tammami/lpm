<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penulis bersama yang ditandai pada karya ilmiah: dosen rekan IAIA (`lecturer`) atau mahasiswa (`student`).
 */
#[Fillable(['scientific_work_id', 'kind', 'lecturer_id', 'student_id', 'name', 'nim', 'position'])]
class ScientificWorkMember extends Model
{
    public const LECTURER = 'lecturer';

    public const STUDENT = 'student';

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScientificWork::class, 'scientific_work_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
