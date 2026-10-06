{{--
    WEB CAREER — HASIL TES: DISC Assessment Report

    DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/ujian-nilai-akhir-disc.blade.php).
    Seluruh isinya — kerangka HTML, kop berlogo, kaki "DOKUMEN RAHASIA",
    tera air, dan CSS — dibawa apa adanya supaya halaman ini identik dengan PDF yang
    diterbitkan CAT. Satu-satunya tambahan adalah blok pembongkar variabel
    tepat sesudah <body>, yang menyiapkan payload API untuk templat ini.

    Laporan ini dirender jadi PDF-nya SENDIRI (renderLaporanTes) lalu
    digabungkan di tingkat PDF oleh FPDI — bukan disisipkan ke dalam
    dokumen rancangan. Itu sebabnya kerangka HTML dan elemen berposisi
    tetap di sini aman: tidak ada halaman lain yang bisa ditumpanginya.

    JANGAN DISUNTING LANGSUNG. Berkas ini dihasilkan ulang oleh
    `php artisan berkas:salin-templat-tes`; suntingan tangan akan tertimpa.
    Perbaikan tampilan dilakukan di CAT, lalu perintah itu dijalankan ulang.
--}}
{{--
    Laporan hasil tes DISC.

    KERANGKANYA SENGAJA SAMA dengan laporan TIU & Kraeplin: watermark, kop
    berlogo, judul biru, tabel biodata, kepala tabel biru, kaki "RAHASIA",
    dan bagian pelanggaran dari partial bersama. Laporan satu peserta harus
    terlihat satu keluarga apa pun alat tesnya.

    Urutan isi: biodata + tipe personality -> Komposisi DISC (donat + tabel
    dimensi) -> Grafik DISC -> Strength -> Weakness -> pelanggaran.

    Rincian jawaban per nomor sengaja TIDAK dicetak di sini: laporan ini untuk
    membaca hasil, bukan memeriksa skoring. Yang butuh menelusuri jawaban bisa
    membuka tab Jawaban Peserta atau lembar Excel yang memuatnya lengkap.

    Grafik berupa PNG base64. Bila laporan diminta dari layar, gambarnya
    datang dari Highcharts; bila dari antrean (tanpa peramban), digambar di
    server oleh DiscChartRenderer. dompdf tidak menjalankan JavaScript, jadi
    grafik tidak mungkin dirender di dalam blade ini.
--}}
<!DOCTYPE html> 
<html>
<head>
    <meta charset="utf-8">
    <title>Hasil DISC</title>
    <style>
    body { font-family: 'Helvetica', Arial, sans-serif; font-size: 10px; padding: 5px; color: #333; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { border: 1px solid #ddd; padding: 5px; text-align: left; vertical-align: middle; }

    .text-center { text-align: center !important; }
    .align-middle { vertical-align: middle !important; }
    .font-bold { font-weight: bold; }
    .text-muted { color: #666; }
    .small { font-size: 9px; }

    .header-table { width: 100%; border: none; margin-bottom: 14px; }
    .header-table td { border: none; vertical-align: middle; padding: 2px; }
    .header-table .logo { width: 80px; }
    .company-details { text-align: right; }
    .company-details h4, .company-details p { margin: 0; }

    /* Kaki halaman polos: garis tipis + teks abu, tanpa bidang berwarna.
       Palang biru selebar halaman menarik perhatian ke bawah, padahal isinya
       cuma penanda kerahasiaan yang perlu ada tapi tak perlu dibaca duluan. */
    footer { position: fixed; bottom: -24px; left: 0; right: 0; height: 40px; text-align: center; }
    .kaki {
        border-top: 1px solid #e5e7eb; padding-top: 6px;
        font-size: 8.5px; color: #94a3b8; letter-spacing: .3px;
    }
    .kaki strong { color: #64748b; }

    .bg-title { background-color: #0b5394; color: white; padding: 8px 10px; text-align: center; border-radius: 4px 4px 0 0; margin-bottom: 0; }

    /* Judul biru dan tabel di bawahnya harus tetap satu kesatuan. Tanpa ini
       dompdf bisa memutus di antaranya sehingga judul tertinggal di halaman
       sebelumnya dan isinya seolah menumpang di atas bagian lain. */
    .seksi { page-break-inside: avoid; margin-bottom: 10px; }
    .seksi table { margin-bottom: 0; }
    .bg-title h6 { margin: 0; font-size: 14px; font-weight: normal; }

    thead.bg-table-head th {
        background-color: #0b5394 !important; color: white; text-align: center;
        vertical-align: middle; font-size: 10px; font-weight: bold; border: 1px solid #ddd !important;
    }

    .tes-intelegensi-table {
        margin-top: 0; margin-bottom: 12px !important;
        border-left: 1px solid #ddd; border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd; border-top: none;
    }
    .tes-intelegensi-table td { border: none; padding: 2px; }
    .biodata-inner-table td { padding: 4px 6px; }
    .bg-biodata { width: 1%; white-space: nowrap; font-weight: bold; }
    .kolom-titikdua { width: 10px; text-align: center; }

    .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: -1000; opacity: 0.08; width: 60%; }

    /* Kotak Tipe Personality — bentuk mengikuti dokumen acuan. Berdiri di
       ujung kanan kepala laporan, sejajar dengan biodata. */
    .tipe-wrap { width: 100%; margin: 0; border-collapse: collapse; }
    .tipe-wrap td, .tipe-wrap th { border: 1px solid #333; text-align: center; }
    .tipe-hd { background: #4a86c8; color: #fff; font-weight: bold; font-size: 10px; padding: 5px; letter-spacing: .6px; }
    .tipe-kode { font-size: 20px; font-weight: bold; padding: 7px 5px 2px; border-bottom: none !important; }
    .tipe-nama { font-size: 9.5px; padding: 0 6px 8px; border-top: none !important; color: #333; }

    /* Padding pindah ke pembungkus gambar supaya keterangan dan baris
       "cara membaca" di bawahnya bisa menempel penuh ke tepi kotak. */
    .box-grafik { border: 1px solid #ddd; padding: 0; text-align: center; margin-bottom: 15px; }
    .box-grafik .gbr { padding: 8px 8px 4px; }
    /* Tanpa max-height/object-fit: dompdf tidak mengenal object-fit, dan
       batas tinggi memotong gambar alih-alih mengecilkannya. Gambar tiga
       panel sudah berbanding lebar ~3:1, jadi lebar penuh sudah pas. */
    .box-grafik img { width: 100%; height: auto; }

    /* Bulat, bukan angka. Nomor urut menyiratkan peringkat — butir 1 lebih
       kuat daripada butir 5 — padahal butir interpretasi DISC setara. */
    ul.bt { margin: 0; padding-left: 18px; list-style-type: disc; }
    ul.bt li { margin-bottom: 4px; text-align: justify; }
    .kosong { color: #999; font-style: italic; }

    /* ── Tabel dimensi di sebelah donat ─────────────────────────────────
       Tinggi tiap baris disamakan lewat padding tetap, dan seluruh selnya
       rata tengah menegak. Tanpa ini baris "Dominance" (tiga baris teks)
       jauh lebih jangkung daripada tetangganya, sehingga kotak warnanya
       tidak lagi sejajar dengan namanya. */
    .dim-tbl { margin: 0; border: none; }
    .dim-tbl td { padding: 6px 8px; vertical-align: middle; }
    .dim-kode {
        width: 30px; text-align: center; vertical-align: middle !important;
        color: #fff; font-weight: bold; font-size: 15px; padding: 6px 2px !important;
    }
    .dim-nm-t { font-size: 11px; font-weight: bold; color: #1f2937; }
    .dim-nm-k { font-size: 8.5px; color: #6b7280; letter-spacing: .2px; }
    .dim-nm-d { font-size: 8.5px; color: #9ca3af; }
    .dim-angka { text-align: center; font-weight: bold; font-size: 13px; color: #1f2937; }

    /* Catatan sempit di kaki kotak komposisi — satu kalimat, bukan paragraf. */
    .nb-sel {
        background: #f8fafc; font-size: 8.5px; color: #6b7280;
        padding: 6px 9px !important; line-height: 1.5;
    }

    /* ── Keterangan di bawah grafik ─────────────────────────────────────
       Meniru baris penjelas di layar hasil tes, dipendekkan jadi satu
       kalimat per panel supaya laporan tetap padat. */
    .ket-grafik { margin: 0; border: none; table-layout: fixed; }
    .ket-grafik td {
        border: none; border-top: 1px solid #e5e7eb;
        width: 33.33%; padding: 7px 9px; vertical-align: top; text-align: center;
    }
    .ket-kode { font-size: 10px; font-weight: bold; color: #0b5394; letter-spacing: .5px; }
    .ket-judul { font-size: 9px; color: #1f2937; }
    .ket-isi { font-size: 8.5px; color: #6b7280; line-height: 1.5; }

    .baca {
        margin: 0; padding: 7px 10px; background: #f8fafc; border-top: 1px solid #e5e7eb;
        font-size: 8.5px; color: #4b5563; line-height: 1.6; text-align: left;
    }
    .baca b { color: #1f2937; }
    .kotak-w {
        display: inline-block; width: 8px; height: 8px; margin: 0 2px 0 6px;
        border: 1px solid #fff;
    }
    </style>
</head>

<body>
@php
    /* Disisipkan oleh berkas:salin-templat-tes — satu-satunya bagian berkas
       ini yang BUKAN salinan dari CAT. Membongkar payload endpoint
       api/v1/web-careers/laporan-tes menjadi variabel yang sama persis dengan
       yang dikirim controller CAT ke templat ini. */
    $data = $lap['data'] ?? [];
    $kandidat = $data['kandidat'] ?? [];
    $domain = collect($data['domain'] ?? [])->map(fn ($r) => (object) $r);
    $summaryList = collect($data['summary_list'] ?? [])->map(function ($r) {
        $r['Norma_Referensi'] = collect($r['Norma_Referensi'] ?? []);

        return $r;
    });
    $papiData = $data['papi_data'] ?? [];
    $h = $data;
    $chartImage = $data['chart_image'] ?? null;
    $donutImage = $data['donut_image'] ?? null;
    $namaReport = $lap['nama_tes'] ?: 'DISC Assessment Report';
    $watermark = $watermark ?? null;
    $pelanggaran = null;
@endphp
    <div class="watermark">
        <img src="{{ $watermark }}" width="100%" alt="Watermark Logo">
    </div>

    <header>
        <table class="header-table">
            <tr>
                <td><img src="{{ $watermark }}" alt="Logo" class="logo"></td>
                <td class="company-details">
                    <h4>Evo Group</h4>
                    <p>Jl. Sapta Marga No.21, Bukit Sangkal, Kec. Kalidoni, Kota Palembang</p>
                    <p>Website: www.evonusabersaudara.co.id</p>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <div class="kaki">
            Dokumen Ini Bersifat <strong>RAHASIA</strong> &middot; HC EVO Group
        </div>
    </footer>

    <main>
        @php
            $ident   = $h['identitas'] ?? [];
            $ring    = $h['ringkasan'] ?? [];
            $profil  = $h['profil'] ?? [];
            $dimensi = collect($h['dimensi'] ?? [])->keyBy('kode');
            $acuan   = $profil['sumber'] ?? 'CHANGE';

            // Baris tabel dimensi mengikuti MASTER: kode mana, urutannya, dan
            // warnanya semua dari N_HRIS_KANDIDAT_DISC_Dimensi (sudah terurut
            // kolom Urutan saat dirakit di DiscController::hasilLengkap).
            // Dimensi netral seperti X tidak punya baris — ia tidak menambah
            // skor dimensi mana pun.
            $dimUtama = collect($h['dimensi'] ?? [])
                ->filter(fn ($d) => ($d['dihitung'] ?? true))
                ->pluck('kode')
                ->all();

            // Tipe personality = kode profil dieja jadi nama dimensinya,
            // mis. "CS" -> "Compliance & Steadiness".
            $huruf = collect(str_split((string) ($profil['kode'] ?? '')))
                ->filter(fn ($x) => isset($dimensi[$x]));
            $tipeNama = $huruf->map(fn ($x) => $dimensi[$x]['nama'])->implode(' & ');
        @endphp

        {{-- Judul = NAMA TES apa adanya, mis. "DISC - SEMUA LEVEL". Nama
             laporan dari jendela Cetak sengaja tidak dipakai: isinya sering
             mengulang nama kandidat yang sudah tercetak persis di bawah
             kotak ini. Tetap dipakai sebagai cadangan bila indikatornya
             kebetulan tak bernama. --}}
        <div class="bg-title">
            <h6>{{ strtoupper($h['identitas']['nama_tes'] ?: $namaReport) }}</h6>
        </div>

        <table class="tes-intelegensi-table">
            <tr>
                <td style="padding: 5px; vertical-align: top;">
                    <table class="biodata-inner-table">
                        <tr>
                            <td class="bg-biodata">Nama Kandidat</td>
                            <td class="kolom-titikdua">:</td>
                            <td>{{ $kandidat['Nama_Kandidat'] ?? '-' }}</td>
                        </tr>
                        @if (!empty($kandidat['isEksternal']))
                            <tr>
                                <td class="bg-biodata">Posisi Dilamar</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['Posisi_Dilamar'] }}</td>
                            </tr>
                        @else
                            <tr>
                                <td class="bg-biodata">Departemen</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['nama_sub_divisi'] }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="bg-biodata">Level</td>
                            <td class="kolom-titikdua">:</td>
                            <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="bg-biodata">Tanggal Tes</td>
                            <td class="kolom-titikdua">:</td>
                            <td>{{ $kandidat['Tanggal_Tes'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>

                {{-- Tipe personality berdiri di ujung KANAN kepala laporan,
                     sejajar biodata — hal pertama yang dicari pembaca. --}}
                <td style="width: 210px; padding: 5px; vertical-align: top;">
                    <table class="tipe-wrap">
                        <tr><td class="tipe-hd">TIPE PERSONALITY</td></tr>
                        <tr><td class="tipe-kode">{{ $profil['kode'] ?: '-' }}</td></tr>
                        <tr><td class="tipe-nama">({{ $tipeNama ?: 'Belum tersedia' }})</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- 1. Komposisi DISC — donat + tipe personality + tabel dimensi,
               semuanya dalam SATU kotak. --}}
        <div class="seksi">
        <div class="bg-title"><h6>KOMPOSISI DISC — GRAFIK {{ $acuan }}</h6></div>
        <table class="table table-bordered">
            <tr>
                @if (!empty($donutImage))
                    {{-- Donat rata TENGAH menegak terhadap tabel di sebelahnya:
                         keduanya kini berbagi satu garis tengah, bukan yang satu
                         melayang di tengah sementara yang lain menempel ke atas. --}}
                    {{-- Donat sengaja dibuat sedikit LEBIH PENDEK daripada tabel
                         di sebelahnya. dompdf menempelkan tabel bersarang ke
                         tepi atas selnya betapa pun vertical-align-nya, jadi
                         kalau donatnya lebih jangkung, sisa ruangnya menganga
                         sebagai pita kosong di kaki tabel. --}}
                    <td style="width: 30%; text-align: center; padding: 14px 12px; vertical-align: middle;">
                        <img src="{{ $donutImage }}" style="width: 92%;" alt="Komposisi DISC">
                    </td>
                @endif
                <td style="padding: 0; vertical-align: middle;">
                    <table class="dim-tbl">
                        <thead class="bg-table-head">
                            <tr>
                                <th width="9%">Kode</th>
                                <th>Dimensi</th>
                                <th width="15%">Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dimUtama as $k)
                                @php
                                    $d = $dimensi[$k] ?? [];
                                    $t = collect($h['grafik'][$acuan] ?? [])->firstWhere('dimensi', $k);
                                @endphp
                                <tr>
                                    {{-- Warna sel dari master dimensi; abu netral hanya
                                         bila baris masternya memang belum diberi warna. --}}
                                    <td class="dim-kode" style="background: {{ $d['warna'] ?: '#94a3b8' }};">{{ $k }}</td>
                                    <td>
                                        <span class="dim-nm-t">{{ $d['nama'] ?? $k }}</span>
                                        @if (!empty($d['kata_kunci']))
                                            <br><span class="dim-nm-k">{{ $d['kata_kunci'] }}</span>
                                        @endif
                                        @if (!empty($d['deskripsi']))
                                            <br><span class="dim-nm-d">{{ $d['deskripsi'] }}</span>
                                        @endif
                                    </td>
                                    <td class="dim-angka">{{ $t['skor_mentah'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="nb-sel" colspan="{{ empty($donutImage) ? 1 : 2 }}">
{{-- Kalimat ini SENGAJA tidak dipotong baris di sekitar tag <b>: dompdf
                         membuang spasi (termasuk &nbsp;) yang mendahului tag inline di dalam
                         sel tabel. Tiap huruf tebal karena itu berdiri di AWAL baris, langsung setelah <br> tanpa spasi di antaranya. --}}
                    <b>Skor</b> = skor mentah dimensi pada grafik {{ $acuan }}.<br><b>Porsi lingkaran</b> = jarak tiap dimensi dari yang terendah pada grafik {{ $acuan }} di halaman ini &mdash; dimensi yang menonjol karena itu terlihat jelas lebih besar.
                </td>
            </tr>
        </table>

        </div>

        {{-- 2. Grafik DISC --}}
        <div class="seksi">
        <div class="bg-title"><h6>GRAFIK DISC</h6></div>
        <div class="box-grafik">
            <div class="gbr">
                @if (!empty($chartImage))
                    <img src="{{ $chartImage }}" alt="Grafik DISC">
                @else
                    <div style="padding: 40px; color: #999; font-style: italic;">
                        Grafik tidak dapat ditampilkan. Seluruh angkanya ada di tabel di atas.
                    </div>
                @endif
            </div>

            {{-- Keterangan tiga panel — kalimatnya SAMA dengan yang tampil di
                 layar hasil tes, dipendekkan jadi satu baris per panel. Grafik
                 tanpa keterangan memaksa pembaca menebak beda Most, Least, dan
                 Change; ketiganya mengukur hal berbeda, bukan tiga versi angka
                 yang sama. --}}
            <table class="ket-grafik">
                <tr>
                    <td>
                        <span class="ket-kode">MOST</span>
                        <span class="ket-judul">&middot; Public Self</span><br>
                        <span class="ket-isi">Citra yang ditampilkan di hadapan orang lain.</span>
                    </td>
                    <td>
                        <span class="ket-kode">LEAST</span>
                        <span class="ket-judul">&middot; Private Self</span><br>
                        <span class="ket-isi">Diri inti yang muncul saat berada di bawah tekanan.</span>
                    </td>
                    <td>
                        <span class="ket-kode">CHANGE</span>
                        <span class="ket-judul">&middot; Perceived Self</span><br>
                        <span class="ket-isi">Gaya alamiah &mdash; selisih Most terhadap Least.</span>
                    </td>
                </tr>
            </table>

            <p class="baca">
                Tinggi tiap titik terbaca sejajar dengan skala di tepi kiri. Garis mendatar tipis
                di tengah adalah titik nol &mdash; di atasnya dimensi menonjol, di bawahnya tidak.
                Angka pastinya ada pada tabel Komposisi DISC di atas.
                Warna titik mengikuti dimensinya:
                @foreach ($dimUtama as $k)<span class="kotak-w" style="background: {{ $dimensi[$k]['warna'] ?: '#94a3b8' }};"></span><b>{{ $k }}</b>@if (!$loop->last)<span>,</span>@endif @endforeach
            </p>
        </div>

        </div>

        {{-- 3. Kartu kekuatan & kelemahan.

               Dimulai di HALAMAN BARU. Halaman pertama menjawab "angkanya
               berapa" (komposisi + grafik), halaman kedua menjawab "artinya
               apa" — dan tanpa pemisah ini, panjang butir interpretasi yang
               berbeda antarprofil membuat grafik kadang terdorong ke halaman
               dua, kadang tidak. --}}
        <div class="seksi" style="page-break-before: always;">
        <div class="bg-title"><h6>STRENGTH — KEKUATAN</h6></div>
        <table class="table table-bordered">
            <tr><td style="padding: 9px;">
                @if (!empty($h['kekuatan']))
                    <ul class="bt">@foreach ($h['kekuatan'] as $t)<li>{{ $t }}</li>@endforeach</ul>
                @else
                    <span class="kosong">Belum ada butir kekuatan untuk profil ini.</span>
                @endif
            </td></tr>
        </table>

        </div>

        <div class="seksi">
        <div class="bg-title"><h6>WEAKNESS — KELEMAHAN</h6></div>
        <table class="table table-bordered">
            <tr><td style="padding: 9px;">
                @if (!empty($h['kelemahan']))
                    <ul class="bt">@foreach ($h['kelemahan'] as $t)<li>{{ $t }}</li>@endforeach</ul>
                @else
                    <span class="kosong">Belum ada butir kelemahan untuk profil ini.</span>
                @endif
            </td></tr>
        </table>

        </div>

        
    </main>
</body>
</html>
