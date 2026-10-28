<?php

namespace App\Http\Controllers\Suggestions;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use App\Notifications\SuggestionRespondedNotification;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kotak saran yang dibaca dan ditanggapi LPM. Identitas pengirim tanpa nama tidak pernah dikirim ke halaman ini.
 */
class SuggestionController extends Controller
{
    private const SENDER_ROLES = ['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen'];

    public function index(Request $request): Response
    {
        $query = Suggestion::query()
            ->with(['user:id,name,username', 'studyProgram:id,name,degree', 'responder:id,name'])
            ->when($request->input('sender_role') === 'lainnya', fn (Builder $q) => $q->whereNotIn('sender_role', array_keys(self::SENDER_ROLES)));

        // Pencarian sengaja tidak menyentuh nama pengirim agar saran tanpa nama tidak dapat dilacak lewat kotak cari.
        $suggestions = TableQuery::for($query, $request)
            ->search(['subject', 'message'])
            ->filter(['status' => 'status', 'category' => 'category'])
            ->when(array_key_exists((string) $request->input('sender_role'), self::SENDER_ROLES), fn (TableQuery $table) => $table->filter(['sender_role' => 'sender_role']))
            ->sort(['created_at', 'updated_at'], '-created_at')
            ->paginate()
            ->through(fn (Suggestion $suggestion): array => $this->payload($suggestion));

        $counts = Suggestion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('suggestions/index', [
            'suggestions' => $suggestions,
            'filters' => $this->filters($request, ['status', 'category', 'sender_role']),
            'statusCounts' => collect(SuggestionStatus::cases())->mapWithKeys(fn (SuggestionStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)]),
            'categories' => SuggestionCategory::options(),
            'statuses' => SuggestionStatus::options(),
            'senderRoles' => collect(self::SENDER_ROLES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
        ]);
    }

    public function update(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SuggestionStatus::class)],
            'response' => [
                'nullable', 'string', 'max:5000',
                Rule::requiredIf(fn (): bool => in_array($request->input('status'), [SuggestionStatus::FollowedUp->value, SuggestionStatus::Closed->value], true)),
            ],
        ], ['response.required' => 'Tuliskan tanggapan untuk pengirim sebelum menandai saran ditindaklanjuti atau ditutup.']);

        $response = trim((string) ($validated['response'] ?? '')) ?: null;
        $responseChanged = $response !== null && $response !== $suggestion->response;

        $suggestion->update([
            'status' => $validated['status'],
            'response' => $response,
            'responded_by' => $responseChanged ? $request->user()->id : $suggestion->responded_by,
            'responded_at' => $responseChanged ? now() : $suggestion->responded_at,
        ]);

        if ($responseChanged && ($sender = $suggestion->user) && $sender->isNot($request->user())) {
            $sender->notify(new SuggestionRespondedNotification($suggestion));
        }

        $this->toast($responseChanged ? 'Tanggapan tersimpan dan dikirim ke pengirim.' : 'Status saran diperbarui.');

        return back();
    }

    public function destroy(Suggestion $suggestion): RedirectResponse
    {
        $suggestion->delete();
        $this->toast('Saran dihapus.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Suggestion $suggestion): array
    {
        $anonymous = $suggestion->is_anonymous;

        return [
            'id' => $suggestion->id,
            'category' => $suggestion->category->value,
            'category_label' => $suggestion->category->label(),
            'subject' => $suggestion->subject,
            'message' => $suggestion->message,
            'is_anonymous' => $anonymous,
            'sender' => $anonymous ? null : $suggestion->user?->name,
            'sender_username' => $anonymous ? null : $suggestion->user?->username,
            'sender_role' => $suggestion->sender_role,
            'sender_role_label' => self::SENDER_ROLES[$suggestion->sender_role] ?? (UserRole::tryFrom($suggestion->sender_role)?->label() ?? 'Lainnya'),
            'study_program' => $anonymous ? null : $suggestion->studyProgram?->full_name,
            'status' => $suggestion->status->value,
            'status_label' => $suggestion->status->label(),
            'response' => $suggestion->response,
            'responder' => $suggestion->responder?->name,
            'responded_at' => $suggestion->responded_at?->toIso8601String(),
            'created_at' => $suggestion->created_at?->toIso8601String(),
        ];
    }
}
