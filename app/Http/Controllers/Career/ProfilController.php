<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Support\Career\ProfilPengguna;
use App\Support\CareerShell;
use Inertia\Inertia;

/** WEB CAREER — /profil kandidat yang sedang masuk. */
class ProfilController extends Controller
{
    public function profil()
    {
        return Inertia::render(
            'Career/portal/Profil',
            CareerShell::props('/profil', 'Profil Saya', [
                'user' => ProfilPengguna::payload(),
            ]),
        );
    }
}
