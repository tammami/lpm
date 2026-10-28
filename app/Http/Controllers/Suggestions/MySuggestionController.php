<?php

namespace App\Http\Controllers\Suggestions;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mahasiswa & dosen mengirim saran kapan saja dan membaca tanggapan LPM.
 */
class MySuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('suggestions/mine', [
            'suggestions' => Suggestion::query()
                ->where('user_id', $request->user()->id)
                ->latest()->latest('id')
                ->get()
                ->map(fn (Suggestion $suggestion): array => [
                    'id' => $suggestion->id,
                    'category' => $suggestion->category->value,
                    'category_label' => $suggestion->category->label(),
                    'subject' => $suggestion->subject,
                    'message' => $suggestion->message,
                    'is_anonymous' => $suggestion->is_anonymous,
                    'status' => $suggestion->status->value,
                    'status_label' => $suggestion->status->label(),
                    'response' => $suggestion->response,
                    'responded_at' => $suggestion->responded_at?->toIso8601String(),
                    'created_at' => $suggestion->created_at?->toIso8601String(),
                    'can_delete' => $suggestion->status === SuggestionStatus::New,
                ])->all(),
            'categories' => SuggestionCategory::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::enum(SuggestionCategory::class)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'is_anonymous' => ['boolean'],
        ], [
            'subject.required' => 'Tuliskan pokok saran secara singkat.',
            'message.required' => 'Tuliskan isi saran Anda.',
            'message.min' => 'Isi saran terlalu singkat. Jelaskan sedikit lebih rinci agar LPM dapat menindaklanjuti.',
        ]);

        $user = $request->user();
        $anonymous = (bool) ($validated['is_anonymous'] ?? false);

        Suggestion::query()->create([
            ...$validated,
            'is_anonymous' => $anonymous,
            'user_id' => $user->id,
            'sender_role' => $this->senderRole($user),
            'study_program_id' => $anonymous ? null : ($user->student?->study_program_id ?? $user->lecturer?->study_program_id),
            'status' => SuggestionStatus::New,
        ]);

        $this->toast('Terima kasih, saran Anda sudah terkirim ke LPM.');

        return back();
    }

    public function destroy(Request $request, Suggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->user_id === $request->user()->id, 403);

        if ($suggestion->status !== SuggestionStatus::New) {
            $this->failWith('Saran yang sudah ditinjau LPM tidak dapat ditarik kembali.');
        }

        $suggestion->delete();
        $this->toast('Saran ditarik kembali.');

        return back();
    }

    private function senderRole(User $user): string
    {
        return match (true) {
            $user->hasUserRole(UserRole::Mahasiswa) => UserRole::Mahasiswa->value,
            $user->hasUserRole(UserRole::Dosen) => UserRole::Dosen->value,
            default => $user->primaryRole()?->value ?? 'lainnya',
        };
    }
}
