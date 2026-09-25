<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibilitas data mutu berbasis organisasi (kolom study_program_id & faculty_id)
 * ditambah akses personal (PIC/auditor) yang didefinisikan model lewat `scopePersonalAccess`.
 */
trait ScopedToOrganization
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, bool $organizational = true): void
    {
        if ($organizational && $user->isInstitutionWide()) {
            return;
        }

        $query->where(function (Builder $inner) use ($user, $organizational): void {
            if ($organizational) {
                $programIds = $user->accessibleStudyProgramIds() ?? [];

                if ($programIds !== []) {
                    $inner->orWhereIn($this->qualifyColumn('study_program_id'), $programIds);
                }

                if ($user->hasUserRole(UserRole::AdminFakultas) && $user->faculty_id) {
                    $inner->orWhere($this->qualifyColumn('faculty_id'), $user->faculty_id);
                }
            }

            $inner->orWhere(fn (Builder $personal) => $this->personalAccess($personal, $user));
        });
    }

    /**
     * Akses personal tambahan (mis. PIC atau auditor yang ditugaskan).
     *
     * @param  Builder<static>  $query
     */
    abstract protected function personalAccess(Builder $query, User $user): void;
}
