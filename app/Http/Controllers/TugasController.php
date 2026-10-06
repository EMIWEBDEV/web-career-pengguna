<?php

namespace App\Http\Controllers;

use App\Support\Sinkron\PenerbitOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pemicu tugas berkala dari Cloud Scheduler — satu-satunya: penyapu Outbox.
 *
 * Bertoken (X-Tugas-Token = TUGAS_TOKEN). Token kosong atau lebih pendek dari
 * 32 karakter = semua ditolak, supaya rute ini tidak pernah terbuka karena lupa
 * mengatur rahasia atau karena rahasianya mudah ditebak.
 */
class TugasController extends Controller
{
    private const PANJANG_MIN_TOKEN = 32;

    public function terbitOutbox(Request $request, PenerbitOutbox $penerbit): JsonResponse
    {
        $token = (string) config('sinkron.token_tugas');
        if (strlen($token) < self::PANJANG_MIN_TOKEN || ! hash_equals($token, (string) $request->header('X-Tugas-Token'))) {
            return response()->json(['ok' => false, 'pesan' => 'Ditolak.'], 403);
        }

        return response()->json(['ok' => true] + $penerbit->sapu());
    }
}
