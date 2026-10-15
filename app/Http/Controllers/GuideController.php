<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pusat Panduan penggunaan. Isi panduan ada di frontend (resources/js/lib/guide)
 * dan disaring sesuai peran pembaca. Tamu hanya melihat panduan masuk & akun.
 */
class GuideController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('guide/index', [
            'filters' => [
                'q' => (string) $request->string('q'),
                'kategori' => (string) $request->string('kategori'),
                'artikel' => (string) $request->string('artikel'),
                'semua' => $request->boolean('semua'),
            ],
        ]);
    }
}
