<?php

namespace App\Http\Middleware;

use App\Support\Portal\Potret;
use App\Support\Portal\TautanFeedback;
use App\Support\Sinkron\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Vinkla\Hashids\Facades\Hashids;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props minimal. Shell portal (`layout`, `auth`) dibangun
     * CareerShell::props() di controller halamannya sendiri.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // VERSI APLIKASI — dibagikan ke SELURUH halaman (kaki halaman publik,
            // layar masuk, portal). Statis dari config, tanpa kueri.
            'appVersion' => config('app.version', '1.0.0'),

            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
            ],

            // Sesi login kandidat (tabel milik N_WEB_CAREERS_Users).
            'careerAuth' => fn () => $request->session()->get('career_auth'),

            // Feedback yang menunggu diisi — banner di portal kandidat. Dari
            // potret lamaran yang sudah berakhir (termasuk lamaran lama yang
            // hanya punya potret); yang kirimannya sudah tercatat di Outbox
            // tidak ditawarkan lagi.
            'feedbackPending' => function () use ($request) {
                $auth = $request->session()->get('career_auth');
                if (! $auth || ($auth['role'] ?? '') !== 'KANDIDAT') {
                    return null;
                }
                $userId = (int) $auth['id'];

                foreach (Potret::milikAkun($userId) as $kode => $potret) {
                    $hasil = $potret['lamaran']['hasilAkhir'] ?? null;
                    if (! in_array($hasil, ['DITERIMA', 'DITOLAK'], true)) {
                        continue;
                    }
                    $fb = collect($potret['feedback'] ?? [])->first(fn ($f) => ($f['status'] ?? null) !== 'TERISI');
                    if ($fb && ! Outbox::status('Feedback.Dikirim:'.(int) $fb['id'])) {
                        $idPublik = DB::table('N_WEB_CAREERS_Lamaran')
                            ->where('Id_Users', $userId)->where('Kode', $kode)->value('Id_Lamaran');

                        return [
                            'Hasil_Akhir' => $hasil,
                            'lamaran_hashid' => $idPublik ? Hashids::encode($idPublik) : null,
                            'kode_lamaran' => (string) $kode,
                            'feedback_url' => TautanFeedback::url((int) $fb['id'], (string) $kode),
                        ];
                    }
                }

                return null;
            },
        ]);
    }
}
