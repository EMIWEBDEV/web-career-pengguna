<?php

namespace App\Http\Controllers\Career\Faq;

use App\Http\Controllers\Career\CareerLandingController;
use App\Http\Controllers\Controller;
use App\Support\Career\FaqPublik;
use App\Support\Seo\Seo;
use Inertia\Inertia;

/**
 * WEB CAREER — HALAMAN FAQ PUBLIK (/karir/faq).
 *
 * Controller sendiri, BUKAN method tambahan di CareerLandingController: berkas
 * itu sudah 2200+ baris dan jadi titik bentrok merge. Payload layout (navbar +
 * footer) tetap diambil dari sana lewat layoutShared() supaya semua halaman
 * publik memakai sumber yang sama.
 *
 * Route: GET /karir/faq → index. Isinya salinan Master FAQ (hanya dibaca).
 */
class FaqPublikController extends Controller
{
    public function index()
    {
        $faq = new FaqPublik();
        $data = $faq->semua();

        Seo::set([
            'jsonLd' => array_filter([
                $this->faqLd($data['faq'] ?? []),
                \App\Support\Seo\RemahRoti::dari([
                    ['Karier EVO Group', url('/')],
                    ['FAQ Kandidat', null],
                ]),
            ]),
        ]);

        return Inertia::render(
            'Career/Faq',
            array_merge(
                (new CareerLandingController())->layoutShared(),
                $data
            )
        );
    }

    /**
     * schema.org/FAQPage.
     *
     * FAQPage adalah salah satu dari sedikit tipe yang MASIH ditampilkan
     * Google sebagai hasil melebar — pertanyaannya bisa dibuka langsung di
     * halaman pencarian. Untuk kandidat yang mencari "syarat lamar EVO Group",
     * jawabannya terbaca tanpa perlu mengklik apa pun.
     *
     * Jawaban diambil dari DETAIL bila ada, karena itulah jawaban sebenarnya;
     * ringkasan cuma cadangan. Keduanya dipolos-kan: Google menolak FAQPage
     * yang jawabannya memuat markup di luar tag teks sederhana.
     */
    private function faqLd(array $faq): ?array
    {
        $butir = [];

        foreach ($faq as $f) {
            $tanya = trim((string) ($f['pertanyaan'] ?? ''));
            $jawab = trim((string) ($f['cari'] ?? '')) ?: trim(strip_tags((string) ($f['jawaban'] ?? '')));

            // Pasangan yang salah satunya kosong dilewati, bukan dicetak
            // setengah: FAQPage dengan jawaban kosong ditolak seluruhnya.
            if ($tanya === '' || $jawab === '') {
                continue;
            }

            $butir[] = [
                '@type' => 'Question',
                'name' => $tanya,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $jawab],
            ];
        }

        // Dibatasi supaya simpulnya tidak membengkak; Google hanya menampilkan
        // beberapa teratas, dan sisanya tetap terbaca dari halamannya.
        $butir = array_slice($butir, 0, 30);

        return $butir ? ['@type' => 'FAQPage', 'mainEntity' => $butir] : null;
    }
}
