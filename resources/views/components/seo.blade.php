{{--
    META HALAMAN — judul tab + kartu pratinjau tautan (WhatsApp, FB, LinkedIn, X).
    ---------------------------------------------------------------------------
    SEMUA nilai di sini datang dari App\Support\Seo\SeoMeta lewat view composer
    di AppServiceProvider, jadi berkas ini murni cetakan — jangan taruh logika
    (apalagi nama domain) di sini.

    URUTAN ITU PENTING: <title> harus tag judul PERTAMA di dokumen. Peramban
    memakai yang pertama ditemukan dan MENGABAIKAN sisanya — itulah sebab lama
    kenapa tab menampilkan URL mentah: header.blade.php mencetak
    "<title>@yield('title')</title>" yang selalu kosong di halaman Inertia,
    sehingga judul dari komponen <Head> Vue tidak pernah dipakai.

    Nilai $seo['full_title'] di bawah adalah judul yang dilihat PERAYAP dan
    dilihat pengguna pada cat pertama. Setelah Vue hidup, komponen <Head>
    menimpanya lewat document.title dengan judul halaman yang sama persis
    formatnya ("{Nama Halaman} | Careers Evo Group"), jadi tidak ada kedipan
    judul yang terlihat.
--}}
<title>{{ $seo['full_title'] }}</title>

<meta name="description" content="{{ $seo['description'] }}">
@if ($seo['keywords'] !== '')
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif
<meta name="robots" content="{{ $seo['robots'] }}">
<meta name="googlebot" content="{{ $seo['robots'] }}">
<meta name="author" content="{{ $seo['organization_name'] }}">
<meta name="application-name" content="{{ $seo['site_name'] }}">
<meta name="apple-mobile-web-app-title" content="{{ $seo['site_name'] }}">
<meta name="theme-color" content="{{ $seo['theme_color'] }}">
<link rel="canonical" href="{{ $seo['url'] }}">

{{--
    ══════════ VERIFIKASI KEPEMILIKAN SITUS ══════════
    Nilainya dari .env (SEO_GOOGLE_VERIFICATION), bukan ditulis di sini:
    kodenya berbeda tiap properti Search Console, dan menaruhnya di kode
    berarti staging ikut mengaku sebagai produksi.

    Tag-nya tidak dicetak sama sekali bila kosong — meta verifikasi bernilai
    kosong dibaca Google sebagai klaim yang gagal, bukan sebagai ketiadaan.
--}}
@if (config('seo.google_verification'))
    <meta name="google-site-verification" content="{{ config('seo.google_verification') }}">
@endif
@if (config('seo.bing_verification'))
    <meta name="msvalidate.01" content="{{ config('seo.bing_verification') }}">
@endif

{{--
    ══════════ JANGKAUAN GEOGRAFIS ══════════
    Yang membuat "lowongan kerja Palembang" dan "kerja di Sumsel" menemukan
    situs ini. Bukan sinyal terkuat — yang terkuat tetap isi halamannya dan
    JobPosting di bawah — tapi ia menegaskan wilayahnya kepada perayap yang
    belum pernah melihat situs ini sama sekali.
--}}
<meta name="geo.region" content="ID-SS">
<meta name="geo.placename" content="{{ config('seo.geo_placename') }}">
<meta name="ICBM" content="{{ config('seo.geo_position') }}">
<meta name="geo.position" content="{{ str_replace(', ', ';', config('seo.geo_position')) }}">

{{--
    Jembatan ke sisi Vue. resources/js/utils/judulHalaman.js membaca meta ini
    supaya akhiran judul tab punya SATU sumber kebenaran (config/seo.php) dan
    tidak perlu ditulis ulang sebagai konstanta di JavaScript.
--}}
<meta name="app-site-name" content="{{ $seo['site_name'] }}">
<meta name="app-title-separator" content="{{ config('seo.title_separator', ' | ') }}">

{{-- ══════════ Open Graph — dipakai WhatsApp, Facebook, LinkedIn, Telegram ══════════ --}}
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
<meta property="og:url" content="{{ $seo['url'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
{{--
    WhatsApp rewel soal gambar: butuh URL ABSOLUT, berkas non-transparan, dan
    lebih suka ada width/height agar tidak perlu menebak rasio. og:image:secure_url
    ikut dicetak karena sebagian klien lama hanya membaca yang itu di HTTPS.
--}}
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:secure_url" content="{{ $seo['image'] }}">
<meta property="og:image:type" content="{{ $seo['image_type'] }}">
<meta property="og:image:width" content="{{ $seo['image_width'] }}">
<meta property="og:image:height" content="{{ $seo['image_height'] }}">
<meta property="og:image:alt" content="{{ $seo['image_alt'] }}">

{{-- ══════════ Twitter / X ══════════ --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<meta name="twitter:image:alt" content="{{ $seo['image_alt'] }}">
@if ($seo['twitter_site'] !== '')
    <meta name="twitter:site" content="{{ $seo['twitter_site'] }}">
    <meta name="twitter:creator" content="{{ $seo['twitter_site'] }}">
@endif

{{--
    ══════════ Data terstruktur (schema.org) ══════════
    Yang membuat hasil Google menampilkan nama + logo perusahaan, bukan sekadar
    baris biru. Tidak berpengaruh ke WhatsApp, tapi gratis dan tidak merugikan.
--}}
<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        // array_merge, BUKAN operator "+": pada larik, "+" adalah gabungan
        // BERDASARKAN KUNCI. Kedua sisi sama-sama berkunci 0,1,2 — sisi kiri
        // menang dan simpul dari controller (JobPosting, BreadcrumbList,
        // FAQPage) hilang tanpa satu pun galat. Halamannya cuma diam-diam
        // berhenti tampil sebagai hasil kaya.
        '@graph' => array_merge(array_values(
            array_filter([
                array_filter([
                    '@type' => 'Organization',
                    '@id' => $seo['home'] . '#organization',
                    'name' => $seo['organization_name'],
                    'url' => $seo['home'],
                    'logo' => $seo['organization_logo'],
                    'sameAs' => $seo['social_profiles'] ?: null,
                    // Nama lain yang dipakai orang saat mencari. Google
                    // memakainya untuk menyatukan "EVO", "PT EVO Group", dan
                    // nama tiap anak usaha ke satu entitas yang sama —
                    // tanpa ini ketiganya dianggap perusahaan berbeda.
                    'alternateName' => config('seo.alternate_names') ?: null,
                    'address' => array_filter([
                        '@type' => 'PostalAddress',
                        'addressLocality' => config('seo.geo_placename'),
                        'addressRegion' => config('seo.geo_region_name'),
                        'addressCountry' => 'ID',
                    ]),
                    'areaServed' => config('seo.area_served') ?: null,
                    'subOrganization' => collect(config('seo.sub_organizations', []))
                        ->map(fn ($n) => ['@type' => 'Organization', 'name' => $n])
                        ->values()->all() ?: null,
                ]),
                [
                    '@type' => 'WebSite',
                    '@id' => $seo['home'] . '#website',
                    'name' => $seo['site_name'],
                    'url' => $seo['home'],
                    'inLanguage' => 'id-ID',
                    'publisher' => ['@id' => $seo['home'] . '#organization'],
                    // Kotak pencarian langsung di hasil Google. Dipasang di
                    // SATU-SATUNYA simpul WebSite yang ada: dua WebSite dalam
                    // satu @graph membuat Google memilih sendiri mana yang
                    // dipakai, dan yang dipilih bisa jadi yang tanpa ini.
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => route('career.lowongan.semua') . '?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $seo['url'],
                    'url' => $seo['url'],
                    'name' => $seo['full_title'],
                    'description' => $seo['description'],
                    'isPartOf' => ['@id' => $seo['home'] . '#website'],
                    'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $seo['image']],
                ],
            ]),
        ), array_values($seo['json_ld'] ?? [])),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
