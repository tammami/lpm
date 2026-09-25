<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembatasan data hierarkis (institusi → fakultas → prodi) berdasarkan cakupan pengguna.
 *
 * Model dapat menimpa kolom acuan dengan properti `$studyProgramColumn`.
 */
trait ScopedToUser
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->accessibleStudyProgramIds();

        if ($ids !== null) {
            $query->whereIn($this->qualifyColumn($this->studyProgramColumn()), $ids);
        }
    }

    public function isVisibleTo(User $user): bool
    {
        $ids = $user->accessibleStudyProgramIds();

        return $ids === null || in_array((int) $this->getAttribute($this->studyProgramColumn()), $ids, true);
    }

    protected function studyProgramColumn(): string
    {
        return property_exists($this, 'studyProgramColumn') ? $this->studyProgramColumn : 'study_program_id';
    }
}
