<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hasil evaluasi pembelajaran untuk dosen yang bersangkutan (US-005 s.d. US-007).
 */
class MyEvaluationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user()->lecturer?->load('studyProgram');

        abort_unless($lecturer !== null, 403, 'Akun Anda belum terhubung dengan data dosen.');

        $surveys = Survey::query()
            ->whereIn('status', [SurveyStatus::Closed, SurveyStatus::Archived, SurveyStatus::Active])
            ->whereIn('id', DB::table('responses')->where('lecturer_id', $lecturer->id)->select('survey_id'))
            ->orderByDesc('starts_at')
            ->get();

        $survey = $request->filled('survey')
            ? $surveys->firstWhere('id', $request->integer('survey'))
            : ($surveys->firstWhere('status', '!=', SurveyStatus::Active) ?? $surveys->first());

        return Inertia::render('analytics/lecturer', [
            ...AnalyticsController::lecturerPayload($lecturer, $survey, null, forEvaluatee: true),
            'surveys' => $surveys->map(fn (Survey $item): array => [
                'value' => $item->id,
                'label' => $item->title.($item->status === SurveyStatus::Active ? ' (berjalan)' : ''),
            ])->values()->all(),
            'selfView' => true,
        ]);
    }
}
