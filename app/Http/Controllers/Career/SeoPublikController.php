<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — BERKAS YANG DIBACA MESIN PENCARI.
 *
 * robots.txt dan sitemap.xml TIDAK ditaruh sebagai berkas statis di public/.
 * Alasannya bukan selera:
 *
 *   · sitemap harus memuat lowongan yang sedang dibuka HARI INI. Berkas statis
 *     menua dalam hitungan hari, dan Google yang menemukan lowongan tertutup
 *     akan menurunkan kepercayaannya pada seluruh peta situs.
 *
 *   · robots.txt harus menutup seluruh situs saat staging. Berkas statis
 *     berarti staging dan produksi memakai berkas yang sama — dan staging
 *     ikut terindeks, bersaing dengan produksi untuk kata kunci yang sama.
 *
 * Keduanya dibaca dari HOST PERMINTAAN, bukan dari domain yang ditulis di
 * konfigurasi — sepola SeoMeta. Jadi lokal, staging, dan produksi memakai kode
 * yang sama persis tanpa satu pun nilai yang perlu ditukar.
 */
class SeoPublikController extends Controller
{
    /**
     * Perayap yang DIIZINKAN penuh.
     *
     * Google dan Bing memberi trafik pelamar; sisanya tidak, tapi tetap
     * membaca setiap halaman detail lowongan dan setiap kombinasi penyaring.
     * Untuk situs karier di Indonesia, Yandex/Baidu/DuckDuck tidak pernah
     * mengirim satu pelamar pun sementara ongkos perayapannya nyata.
     *
     * Perayap MEDIA SOSIAL sengaja tidak ikut daftar ini dan tidak ikut
     * dilarang: mereka mengambil satu halaman saat tautan dibagikan, dan
     * melarangnya berarti pratinjau WhatsApp/LinkedIn jadi kotak kosong.
     */
    private const DIIZINKAN = ['Googlebot', 'Googlebot-Image', 'Bingbot', 'Google-InspectionTool'];

    /** Perayap yang DITOLAK seluruhnya. */
    private const DITOLAK = [
        'YandexBot', 'Yandex', 'Baiduspider', 'DuckDuckBot', 'Sogou',
        'SeznamBot', 'MJ12bot', 'AhrefsBot', 'SemrushBot', 'DotBot',
        'PetalBot', 'BLEXBot', 'DataForSeoBot', 'ZoominfoBot',
    ];

    /**
     * Jalur yang tidak pernah pantas muncul di hasil pencarian: halaman
     * berakun, formulir, dan tautan bertanda tangan milik satu kandidat.
     */
    private const TERTUTUP = [
        '/kandidat/', '/profil', '/api/', '/login', '/register', '/logout',
        '/ganti-sandi', '/menunggu-verifikasi', '/verifikasi-email',
        '/karir/login', '/karir/register', '/karir/apply/',
        '/karir/konfirmasi/', '/karir/surat-jadwal/', '/feedback/',
    ];

    public function robots(Request $request)
    {
        $host = $request->getSchemeAndHttpHost();
        $bolehIndeks = (bool) config('seo.indexable');

        $b = [];
        $b[] = '# Web Careers — EVO Group';
        $b[] = '# Dibangkitkan otomatis. Jangan disunting di server.';
        $b[] = '';

        if (! $bolehIndeks) {
            // Bukan produksi: tutup seluruhnya. Satu aturan, tanpa celah —
            // daftar izin yang panjang di staging cepat atau lambat bocor.
            $b[] = '# Lingkungan NON-PRODUKSI — seluruh perayapan ditutup.';
            $b[] = 'User-agent: *';
            $b[] = 'Disallow: /';

            return response(implode("\n", $b)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        foreach (self::DITOLAK as $bot) {
            $b[] = "User-agent: {$bot}";
            $b[] = 'Disallow: /';
            $b[] = '';
        }

        foreach (self::DIIZINKAN as $bot) {
            $b[] = "User-agent: {$bot}";
            $b[] = 'Allow: /';
            foreach (self::TERTUTUP as $jalur) {
                $b[] = "Disallow: {$jalur}";
            }
            $b[] = '';
        }

        // Sisanya: boleh membaca halaman publik, tidak boleh masuk ke dalam.
        $b[] = 'User-agent: *';
        foreach (self::TERTUTUP as $jalur) {
            $b[] = "Disallow: {$jalur}";
        }
        // Penyaring menghasilkan ribuan URL yang isinya sama — perayapannya
        // habis di situ dan halaman lowongan yang sebenarnya tidak kebagian.
        $b[] = 'Disallow: /*?q=';
        $b[] = 'Disallow: /*?halaman=';
        $b[] = 'Disallow: /*?page=';
        $b[] = '';
        $b[] = "Sitemap: {$host}/sitemap.xml";
        $b[] = '';

        return response(implode("\n", $b), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Peta situs.
     *
     * Halaman depan berprioritas 1.0 dan seluruh lowongan yang MASIH DIBUKA
     * ikut di dalamnya. Lowongan yang sudah tutup sengaja tidak dimasukkan:
     * peta situs adalah janji "halaman ini layak diindeks", dan mengirim
     * Google ke lowongan mati menurunkan kepercayaannya pada seluruh peta.
     *
     * Di-cache 30 menit. Perayap datang berkali-kali dalam sehari, dan tiap
     * kunjungan menjalankan kueri yang menyisir seluruh pembukaan program.
     */
    public function sitemap(Request $request)
    {
        if (! config('seo.indexable')) {
            abort(404);
        }

        $host = $request->getSchemeAndHttpHost();

        $xml = Cache::remember('wc_sitemap_'.md5($host), now()->addMinutes(30), function () use ($host) {
            $u = [];

            // ── Halaman utama ────────────────────────────────────────────
            // "/" — BUKAN "/karir/landing-page". Keduanya melayani halaman
            // yang sama, dan SeoMeta::canonical() memilih "/" sebagai yang
            // resmi. Menyebut jalur yang lain di sini membuat Search Console
            // melaporkan "URL dikirim tidak dipilih sebagai kanonik" — peta
            // situs yang bertengkar dengan tag kanonik halamannya sendiri.
            $u[] = [$host.'/', 'daily', '1.0', now()];
            $u[] = [$host.'/karir/lowongan', 'daily', '0.9', now()];
            $u[] = [$host.'/karir/tim', 'weekly', '0.7', now()];
            $u[] = [$host.'/karir/faq', 'monthly', '0.5', now()];

            // URL lowongan datang dari pembangun kartu yang SAMA dengan yang
            // dipakai halamannya — lihat petaLowongan(). Menyusunnya di sini
            // berarti dua tempat harus tetap sepakat soal bentuk id, dan yang
            // ketinggalan menghasilkan sitemap penuh 404 tanpa gejala apa pun.
            foreach ((new CareerLandingController())->petaLowongan() as [$jalur, $jenis]) {
                $u[] = [$host.$jalur, 'weekly', $jenis === 'MT' ? '0.9' : '0.8', null];
            }

            foreach ($this->tim() as $slug => $ubah) {
                $u[] = [$host.'/karir/tim/'.$slug, 'monthly', '0.6', $ubah];
            }

            $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($u as [$loc, $freq, $prio, $mod]) {
                $x .= "  <url>\n";
                $x .= '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'."\n";
                if ($mod) {
                    $x .= '    <lastmod>'.\Carbon\Carbon::parse($mod)->toAtomString().'</lastmod>'."\n";
                }
                $x .= "    <changefreq>{$freq}</changefreq>\n";
                $x .= "    <priority>{$prio}</priority>\n";
                $x .= "  </url>\n";
            }
            $x .= '</urlset>'."\n";

            return $x;
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Slug tim dari Info Divisi.
     *
     * Slug-nya dibentuk dari Label_Division, sama seperti yang dipakai
     * halaman /karir/tim/{slug}. Bila kelak halaman itu memakai kolom slug
     * tersendiri, ganti di sini — satu tempat.
     */
    private function tim(): array
    {
        try {
            return DB::table('N_WEB_CAREERS_Division_Informations')
                ->where('Flag_Aktif', 'Y')
                ->get(['Label_Division', 'Updated_At'])
                ->filter(fn ($r) => ! empty($r->Label_Division))
                ->mapWithKeys(fn ($r) => [\Illuminate\Support\Str::slug($r->Label_Division) => $r->Updated_At])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
