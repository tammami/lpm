<?php

namespace App\Http\Controllers\Instruments;

use App\Http\Controllers\Controller;
use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstrumentSectionController extends Controller
{
    public function store(Request $request, InstrumentVersion $version): RedirectResponse
    {
        $this->ensureEditable($version);

        $validated = $this->validated($request);
        $version->sections()->create([...$validated, 'sort_order' => (int) $version->sections()->max('sort_order') + 1]);
        $this->toast('Bagian ditambahkan.');

        return back();
    }

    public function update(Request $request, InstrumentSection $section): RedirectResponse
    {
        $this->ensureEditable($section->version);

        $section->update($this->validated($request));
        $this->toast('Bagian diperbarui.');

        return back();
    }

    public function destroy(InstrumentSection $section): RedirectResponse
    {
        $this->ensureEditable($section->version);

        if ($section->questions()->exists()) {
            $this->failWith('Kosongkan atau pindahkan pertanyaan pada bagian ini terlebih dahulu.');
        }

        $section->delete();
        $this->toast('Bagian dihapus.');

        return back();
    }

    public function move(Request $request, InstrumentSection $section): RedirectResponse
    {
        $this->ensureEditable($section->version);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $sections = $section->version->sections()->get()->values();
        $index = $sections->search(fn (InstrumentSection $item) => $item->id === $section->id);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex >= 0 && $swapIndex < $sections->count()) {
            $ordered = $sections->all();
            [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];

            DB::transaction(function () use ($ordered): void {
                foreach (array_values($ordered) as $position => $item) {
                    $item->updateQuietly(['sort_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureEditable(InstrumentVersion $version): void
    {
        if (! $version->isEditable()) {
            $this->failWith('Versi ini terkunci. Buat versi baru untuk melakukan perubahan.');
        }
    }
}
