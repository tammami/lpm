<?php

namespace App\Http\Controllers\Evidence;

use App\Http\Controllers\Controller;
use App\Models\EvidenceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvidenceCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('evidence/categories', [
            'categories' => EvidenceCategory::query()->withCount('evidence')->orderBy('name')->get()
                ->map(fn (EvidenceCategory $category): array => [...$category->only(['id', 'code', 'name', 'description', 'is_active']), 'evidence_count' => $category->evidence_count])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EvidenceCategory::query()->create($this->validated($request));
        $this->toast('Kategori ditambahkan.');

        return back();
    }

    public function update(Request $request, EvidenceCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        $this->toast('Kategori diperbarui.');

        return back();
    }

    public function destroy(EvidenceCategory $category): RedirectResponse
    {
        $this->deleteSafely($category, 'Kategori');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EvidenceCategory $category = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('evidence_categories')->ignore($category)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
