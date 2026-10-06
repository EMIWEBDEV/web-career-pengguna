<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * MESIN META HALAMAN — judul tab + kartu pratinjau saat tautan dibagikan.
 * ---------------------------------------------------------------------------
 * Satu objek per permintaan (singleton). Alur kerjanya:
 *
 *   1. Nilai dasar diambil dari config/seo.php.
 *   2. Rute yang punya entri di config('seo.pages') menimpa judul/deskripsi.
 *   3. Controller boleh menimpa lagi lewat Seo::set([...]) untuk halaman yang
 *      isinya dinamis (detail lowongan, program MT, halaman tim).
 *   4. resources/views/components/seo.blade.php mencetak hasil akhirnya.
 *
 * KENAPA HARUS DICETAK DI SISI SERVER, BUKAN DI VUE?
 * WhatsApp, Facebook, LinkedIn, dan Telegram TIDAK menjalankan JavaScript.
 * Mereka mengambil HTML mentah lalu berhenti. Tag <meta property="og:*"> yang
 * dipasang komponen <Head> Inertia baru ada SETELAH Vue jalan di peramban —
 * artinya perayap tidak pernah melihatnya, dan kartu pratinjau muncul kosong.
 * Karena itu seluruh og:* di proyek ini dicetak Blade, bukan Vue.
 *
 * KENAPA DOMAIN TIDAK PERNAH DI-HARDCODE?
 * Seluruh URL absolut (canonical, og:url, og:image) dibangun dari host
 * permintaan yang sedang berjalan — bukan dari APP_URL dan bukan dari
 * konstanta. Jadi berkas yang sama melayani web-careers.test, domain staging,
 * dan career.evopet.id dengan benar tanpa satu pun perubahan kode. APP_URL
 * yang basi (kasus yang sangat lazim saat baru naik staging) tidak bisa
 * merusak pratinjau.
 */
class SeoMeta
{
    /** Nilai yang ditimpa controller untuk permintaan ini. */
    protected array $overrides = [];

    /**
     * Timpa satu atau beberapa nilai meta dari controller.
     *
     * Kunci yang dikenal: title, description, image, image_alt, type, url,
     * keywords, jsonLd.
     *
     * `jsonLd` diperlakukan berbeda: ia BUKAN teks, melainkan larik yang
     * dicetak apa adanya ke dalam @graph schema.org. Dipakai halaman detail
     * lowongan untuk menyisipkan JobPosting — satu-satunya cara lowongan bisa
     * muncul di Google Jobs alih-alih sekadar sebagai baris biru biasa.
     */
    public function set(array $values): static
    {
        $this->overrides = array_merge($this->overrides, array_filter(
            $values,
            static fn ($v) => $v !== null && $v !== '',
        ));

        return $this;
    }

    /** Alias enak-dibaca untuk satu nilai. */
    public function title(string $title): static
    {
        return $this->set(['title' => $title]);
    }

    public function description(string $description): static
    {
        return $this->set(['description' => $description]);
    }

    /**
     * Hasil akhir yang siap dicetak Blade.
     *
     * @return array<string, mixed>
     */
    public function resolve(Request $request): array
    {
        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? (string) $route->getName() : '';

        // Ambil seluruh peta lalu indeks langsung — JANGAN
        // config('seo.pages.' . $routeName). Nama rute mengandung titik
        // ('career.lowongan.semua'), dan config() memperlakukan tiap titik
        // sebagai tingkat array bersarang, sehingga pencariannya selalu meleset
        // dan setiap halaman diam-diam jatuh ke judul default.
        $pages = (array) config('seo.pages', []);
        $perRoute = (array) ($pages[$routeName] ?? []);
        $siteName = (string) config('seo.site_name', 'Careers Evo Group');
        $separator = (string) config('seo.title_separator', ' | ');

        $pageTitle = $this->overrides['title']
            ?? ($perRoute['title'] ?? null)
            ?? (string) config('seo.default_title', $siteName);

        $description = $this->overrides['description']
            ?? ($perRoute['description'] ?? null)
            ?? (string) config('seo.default_description', '');

        // Larik tidak boleh lewat array_filter di set() sebagai teks kosong —
        // ia diambil terpisah, sesudahnya.
        $jsonLd = $this->overrides['jsonLd'] ?? null;

        $imagePath = (string) ($this->overrides['image'] ?? config('seo.image', 'og/og-default.jpg'));

        return [
            'site_name' => $siteName,
            // Judul pendek — dipakai og:title/twitter:title. Akhiran nama situs
            // sengaja TIDAK ikut di sini: kartu WhatsApp sudah menampilkan nama
            // situs di barisnya sendiri, jadi mengulanginya cuma buang ruang.
            'title' => $pageTitle,
            // Judul tab peramban lengkap: "{Nama Halaman} | Careers Evo Group".
            'full_title' => $this->composeTitle($pageTitle, $siteName, $separator),
            'description' => $this->trim($description, 200),
            'keywords' => (string) ($this->overrides['keywords'] ?? config('seo.keywords', '')),
            'url' => (string) ($this->overrides['url'] ?? $this->canonical($request)),
            'type' => (string) ($this->overrides['type'] ?? 'website'),
            'image' => $this->absolute($request, $imagePath),
            'image_width' => (int) config('seo.image_width', 1200),
            'image_height' => (int) config('seo.image_height', 630),
            'image_type' => (string) config('seo.image_type', 'image/jpeg'),
            'image_alt' => (string) ($this->overrides['image_alt'] ?? config('seo.image_alt', $siteName)),
            'locale' => (string) config('seo.locale', 'id_ID'),
            'theme_color' => (string) config('seo.theme_color', '#4f46e5'),
            'twitter_site' => (string) config('seo.twitter_site', ''),
            'robots' => $this->robots($routeName, $perRoute),
            // Simpul schema.org tambahan dari controller — mis. JobPosting.
            'json_ld' => self::simpulJamak($jsonLd),
            'organization_name' => (string) config('seo.organization_name', 'EVO Group'),
            'organization_logo' => $this->absolute(
                $request,
                (string) config('seo.organization_logo', 'logo/EVOGROUP.png'),
            ),
            'social_profiles' => array_values((array) config('seo.social_profiles', [])),
            'home' => $this->absolute($request, ''),
        ];
    }

    /**
     * Rapikan `jsonLd` menjadi DAFTAR simpul.
     *
     * Semula ia satu simpul saja (JobPosting). Halaman detail sekarang punya
     * dua — JobPosting dan BreadcrumbList — dan halaman daftar punya ItemList.
     * Menerima keduanya berarti controller tidak perlu tahu bentuk mana yang
     * sedang dipakai, dan simpul yang null (data belum cukup) rontok sendiri
     * alih-alih mencetak `null` ke dalam @graph.
     *
     * @return array<int, array>|null
     */
    protected static function simpulJamak($jsonLd): ?array
    {
        if (! is_array($jsonLd) || $jsonLd === []) {
            return null;
        }

        // Satu simpul dikenali dari adanya '@type'; selain itu ia daftar.
        $daftar = isset($jsonLd['@type']) ? [$jsonLd] : $jsonLd;

        $bersih = array_values(array_filter(
            $daftar,
            static fn ($s) => is_array($s) && $s !== [] && isset($s['@type']),
        ));

        return $bersih ?: null;
    }

    /**
     * "{Nama Halaman} | Careers Evo Group".
     *
     * Judul yang SUDAH memuat nama situs tidak ditempeli lagi — supaya tidak
     * pernah lahir "Careers Evo Group | Careers Evo Group" saat halaman depan
     * memakai nama situs sebagai judulnya sendiri.
     */
    public function composeTitle(string $pageTitle, ?string $siteName = null, ?string $separator = null): string
    {
        $siteName = $siteName ?? (string) config('seo.site_name', 'Careers Evo Group');
        $separator = $separator ?? (string) config('seo.title_separator', ' | ');

        $pageTitle = trim($pageTitle);

        if ($pageTitle === '' || Str::lower($pageTitle) === Str::lower($siteName)) {
            return $siteName;
        }

        if (Str::contains(Str::lower($pageTitle), Str::lower($siteName))) {
            return $pageTitle;
        }

        return $pageTitle . $separator . $siteName;
    }

    /**
     * URL kanonik: skema + host PERMINTAAN + path, tanpa query.
     *
     * Query dibuang dengan sengaja — /karir/lowongan?halaman=2&cari=admin
     * bukan halaman yang berbeda bagi perayap, dan membiarkannya masuk
     * membuat WhatsApp menyimpan cache pratinjau terpisah untuk tiap filter.
     */
    protected function canonical(Request $request): string
    {
        $host = rtrim($request->getSchemeAndHttpHost(), '/');
        $jalur = $request->getPathInfo();

        // ── SATU HALAMAN, SATU URL ──────────────────────────────────────
        //
        // "/" dan "/karir/landing-page" melayani halaman yang sama persis
        // (routes/web.php dan routes/kpi/fransDevEvo.php sama-sama menunjuk
        // CareerLandingController::index). Tanpa kanonik yang menyatukannya,
        // Google menganggap keduanya dua halaman yang BERSAING: ia memilih
        // sendiri mana yang ditampilkan, dan yang kalah membawa serta seluruh
        // tautan masuk yang mengarah padanya.
        //
        // Halaman depan dipilih sebagai "/" karena itulah yang diketik orang,
        // dibagikan, dan dicetak di kartu nama.
        $samaDenganDepan = ['/karir/landing-page', '/karir', '/index.php'];
        if (in_array(rtrim($jalur, '/'), $samaDenganDepan, true)) {
            return $host . '/';
        }

        // Garis miring di ujung membuat URL kembar untuk halaman yang sama.
        $jalur = $jalur === '/' ? '/' : rtrim($jalur, '/');

        return $host . $jalur;
    }

    /** Jadikan path di public/ sebagai URL absolut memakai host permintaan. */
    public function absolute(Request $request, string $path): string
    {
        if ($path === '') {
            return rtrim($request->getSchemeAndHttpHost(), '/') . '/';
        }

        // Sudah absolut (mis. gambar dari CDN/GCS) — biarkan apa adanya.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Nilai tag <meta name="robots">.
     *
     * Perhatikan: ini HANYA mengatur mesin pencari. Perayap media sosial
     * (WhatsApp, facebookexternalhit, Twitterbot) mengabaikan noindex, jadi
     * pratinjau tetap tampil di halaman yang ditandai noindex sekalipun —
     * termasuk halaman masuk, persis seperti yang diinginkan.
     */
    protected function robots(string $routeName, array $perRoute): string
    {
        if (! config('seo.indexable', false)) {
            return 'noindex, nofollow';
        }

        if (! empty($perRoute['noindex'])) {
            return 'noindex, follow';
        }

        foreach ((array) config('seo.noindex_route_prefixes', []) as $prefix) {
            if ($routeName !== '' && Str::startsWith($routeName, $prefix)) {
                return 'noindex, follow';
            }
        }

        return 'index, follow, max-image-preview:large, max-snippet:-1';
    }

    /** Bersihkan HTML lalu potong pada batas kata. */
    protected function trim(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        return Str::limit($text, $length, '…');
    }
}
