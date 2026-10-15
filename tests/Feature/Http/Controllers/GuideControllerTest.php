<?php

use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

it('opens the guide for guests so login help is reachable before signing in', function () {
    $this->get(route('guide.index', ['kategori' => 'akun']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guide/index')
            ->where('auth.user', null)
            ->where('filters.kategori', 'akun')
            ->where('filters.semua', false));
});

it('opens the guide for signed-in users with the search state from the url', function () {
    $this->actingAs(userWithRole(UserRole::Dosen))
        ->get(route('guide.index', ['q' => 'tambah pengguna', 'artikel' => 'tambah-pengguna', 'semua' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guide/index')
            ->where('filters.q', 'tambah pengguna')
            ->where('filters.artikel', 'tambah-pengguna')
            ->where('filters.semua', true));
});
