<?php

use App\Enums\SuggestionStatus;
use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Suggestion;
use App\Notifications\SuggestionRespondedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->program = StudyProgram::factory()->create();
    $this->studentUser = userWithRole(UserRole::Mahasiswa, ['name' => 'Siti Mahasiswa']);
    Student::factory()->for($this->program)->create(['user_id' => $this->studentUser->id]);
    $this->lpm = userWithRole(UserRole::AdminLpm);
});

it('lets a student send a suggestion with their study program', function () {
    $this->actingAs($this->studentUser)->post(route('suggestions.mine.store'), [
        'category' => 'facility',
        'subject' => 'Wi-Fi perpustakaan',
        'message' => 'Mohon jaringan Wi-Fi di perpustakaan diperkuat.',
    ])->assertSessionHasNoErrors();

    $suggestion = Suggestion::query()->sole();

    expect($suggestion->sender_role)->toBe('mahasiswa')
        ->and($suggestion->study_program_id)->toBe($this->program->id)
        ->and($suggestion->status)->toBe(SuggestionStatus::New);

    $this->actingAs($this->studentUser)->get(route('suggestions.mine.index'))
        ->assertInertia(fn (Assert $page) => $page->component('suggestions/mine')->has('suggestions', 1)->where('suggestions.0.can_delete', true));
});

it('lets a lecturer send a suggestion', function () {
    $dosen = userWithRole(UserRole::Dosen);
    Lecturer::factory()->for($this->program)->create(['user_id' => $dosen->id]);

    $this->actingAs($dosen)->post(route('suggestions.mine.store'), ['category' => 'academic', 'subject' => 'Pelatihan RPS', 'message' => 'Usul pelatihan penyusunan RPS berbasis OBE.'])
        ->assertSessionHasNoErrors();

    expect(Suggestion::query()->sole()->sender_role)->toBe('dosen');
});

it('never shows the name or program of an anonymous sender to LPM, even through search', function () {
    $this->actingAs($this->studentUser)->post(route('suggestions.mine.store'), [
        'category' => 'service',
        'subject' => 'Jadwal layanan akademik',
        'message' => 'Mohon layanan akademik dibuka sampai sore.',
        'is_anonymous' => true,
    ])->assertSessionHasNoErrors();

    expect(Suggestion::query()->sole()->study_program_id)->toBeNull();

    $this->actingAs($this->lpm)->get(route('suggestions.index'))
        ->assertInertia(fn (Assert $page) => $page->component('suggestions/index')
            ->where('suggestions.data.0.is_anonymous', true)
            ->where('suggestions.data.0.sender', null)
            ->where('suggestions.data.0.sender_username', null)
            ->where('suggestions.data.0.study_program', null)
            ->where('suggestions.data.0.sender_role_label', 'Mahasiswa'));

    $this->actingAs($this->lpm)->get(route('suggestions.index', ['search' => 'Siti']))
        ->assertInertia(fn (Assert $page) => $page->has('suggestions.data', 0));
});

it('notifies the sender when LPM responds and stops them from withdrawing it', function () {
    Notification::fake();
    $suggestion = Suggestion::factory()->create(['user_id' => $this->studentUser->id]);

    $this->actingAs($this->lpm)->put(route('suggestions.update', $suggestion), ['status' => 'followed_up', 'response' => 'Sudah diteruskan ke unit TI.'])
        ->assertSessionHasNoErrors();

    expect($suggestion->refresh()->status)->toBe(SuggestionStatus::FollowedUp)
        ->and($suggestion->responded_by)->toBe($this->lpm->id);
    Notification::assertSentTo($this->studentUser, SuggestionRespondedNotification::class);

    $this->actingAs($this->studentUser)->delete(route('suggestions.mine.destroy', $suggestion))->assertRedirect();
    expect(Suggestion::query()->count())->toBe(1);
});

it('requires a response before following up or closing', function () {
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($this->lpm)->put(route('suggestions.update', $suggestion), ['status' => 'closed', 'response' => ''])
        ->assertSessionHasErrors('response');

    $this->actingAs($this->lpm)->put(route('suggestions.update', $suggestion), ['status' => 'in_review'])->assertSessionHasNoErrors();
    expect($suggestion->refresh()->status)->toBe(SuggestionStatus::InReview);
});

it('keeps the LPM inbox and other senders\' suggestions private', function () {
    $other = Suggestion::factory()->create();

    $this->actingAs($this->studentUser)->get(route('suggestions.index'))->assertForbidden();
    $this->actingAs($this->studentUser)->delete(route('suggestions.mine.destroy', $other))->assertForbidden();
    $this->actingAs($this->lpm)->post(route('suggestions.mine.store'), ['category' => 'other', 'subject' => 'x', 'message' => 'xxxxxxxxxxxx'])->assertForbidden();
});

it('counts new suggestions for the LPM menu badge', function () {
    Suggestion::factory()->count(2)->create();
    Suggestion::factory()->create(['status' => SuggestionStatus::Closed]);

    $this->actingAs($this->lpm)->get(route('suggestions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('navCounts.newSuggestions', 2));
});
