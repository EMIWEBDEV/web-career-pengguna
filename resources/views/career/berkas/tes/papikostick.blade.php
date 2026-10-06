{{--
    WEB CAREER — HASIL TES: Result Test PAPI Kostick

    DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/ujian-nilai-akhir-papikostick.blade.php).
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
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hasil Ujian Kandidat PAPI Kostick</title>
    <style>
        body { 
            font-family: 'Helvetica', Arial, sans-serif; 
            font-size: 11px; 
            padding: 10px 20px; 
            color: #000; 
            line-height: 1.3;
        }
        
        h1 {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 12px 0;
            text-transform: uppercase;
        }
        
        /* Tabel Biodata Top */
        .biodata-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 5px; 
        }
        .biodata-table td { 
            padding: 4px 0; 
            border: none; 
            vertical-align: top; 
        }
        .biodata-table .label { 
            width: 130px; 
            font-weight: bold; 
        }
        .biodata-table .colon { 
            width: 10px; 
            font-weight: bold; 
        }
        
        /* Garis Hitam Tebal */
        .thick-line {
            border-top: 2.5px solid #000;
            margin: 6px 0 8px 0;
        }

        /* Kotak Penjelasan Utama (kompak — agar grafik muat di halaman 1) */
        .penjelasan-box {
            border: 1px solid #000;
            padding: 7px 10px;
            margin-bottom: 10px;
            text-align: justify;
            font-size: 8.3px;
            line-height: 1.32;
        }
        .penjelasan-box .penjelasan-title {
            font-weight: bold;
            font-size: 9px;
            margin: 0 0 4px 0;
        }
        .penjelasan-box p {
            margin: 0 0 3px 0;
        }
        .penjelasan-box p:last-child {
            margin-bottom: 0;
        }

        /* Grafik Spider — ukuran STATIS, muat di halaman 1 */
        .chart-wrapper {
            text-align: center;
            margin-top: 4px;
            page-break-inside: avoid;
        }
        .chart-img {
            width: auto;
            height: 365px;     /* statis */
            max-width: 100%;
        }

        /* Tabel Rincian & Styling Halaman Selanjutnya */
        .page-break { page-break-before: always; }
        
        /* Aturan Mencegah Elemen Terpotong di PDF */
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Kotak Concern Area */
        .concern-container {
            border: 1px solid #000;
            padding: 12px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .concern-layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .concern-layout-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .concern-box-color {
            width: 16px; 
            height: 16px; 
            background-color: #FFF2CC; 
            border: 1px solid #000;
            margin-top: 1px;
        }

        /* TABEL HASIL */
        .hasil-bagian-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            border: 1px solid #000;
            page-break-inside: avoid; 
            table-layout: fixed; /* PENAMBAHAN KUNCI: Mencegah kolom besar sebelah */
        }
        
        .hasil-bagian-table th, .hasil-bagian-table td { 
            border: 1px solid #000; 
            padding: 6px 8px; 
            vertical-align: middle; 
            word-wrap: break-word; /* Mencegah teks meluber jika kepanjangan */
        }
        .hasil-bagian-table th { 
            color: #fff; 
            text-align: center; 
            padding: 8px;
        }

        /* Hilangkan garis vertikal di tengah Kode-Aspek dan Skor-Result */
        .no-border-right { border-right: none !important; }
        .no-border-left { border-left: none !important; }
        
        .bg-header-utama { background-color: #B45F06; font-weight: bold; }
        .bg-header-sub { background-color: #c46f16; font-weight: bold; }

        .text-center { text-align: center !important; }
        .font-bold { font-weight: 900; }
        .text-uppercase { text-transform: uppercase; }
        /* ── Header & Footer — DISALIN PERSIS dari template TIU ── */
        .header-table {
            width: 100%;
            border: none;
            margin-bottom: 25px;
        }
        .header-table td {
            border: none;
            vertical-align: middle;
        }
        .header-table .logo {
            width: 80px;
        }
        .header-table .company-details {
            text-align: right;
        }
        .company-details h4, .company-details p {
            margin: 0;
        }
        footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            text-align: center;
            font-size: 9px;
            color: #777;
        }
        .pagenum:before {
            content: counter(page);
        }
        .bg-title {
            background-color: #0b5394;
            color: white;
            padding: 10px 15px;
            text-align: center;
            border-radius: 4px 4px 0 0;
            margin-bottom: 0;
        }
        .bg-title h4 {
            margin: 0;
            font-size: 12px;
            font-weight: normal;
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
    $namaReport = $lap['nama_tes'] ?: 'Result Test PAPI Kostick';
    $watermark = $watermark ?? null;
    $pelanggaran = null;
@endphp
    @php
        use Carbon\Carbon;
        $tanggalTes = !empty($kandidat['Waktu_Akhir']) ? Carbon::parse($kandidat['Waktu_Akhir'])->translatedFormat('d F Y') : '-';
    @endphp

    <header>
        <table class="header-table">
            <tr>
                <td><img src="{{ $watermark }}" alt="Logo" class="logo"></td>
                <td class="company-details">
                    <h4>Evo Group</h4>
                    <p>Jl. Sapta Marga No.21, Bukit Sangkal, Kec. Kalidoni, Kota Palembang, Sumatera Selatan 30114</p>
                    <p>Website: www.evonusabersaudara.co.id | Email: evonusabersaudara.co.id</p>
                </td>
            </tr>
        </table>
    </header>

    <footer>
         <div class="bg-title">
             <h4>Dokumen Ini Bersifat <strong>RAHASIA</strong>, Dilarang Mencetak Dan Menyebarluaskan Tanpa Seizin HC EVO Group</h4>
        </div>
    </footer>

    <main>
        <h1>RESULT TEST PAPI KOSTICK</h1>

        <table class="biodata-table">
            <tr>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td>{{ $kandidat['Nama_Kandidat'] ?? '-' }}</td>
            </tr>
            @if($kandidat['isEksternal'])
                <tr>
                    <td class="label">Posisi Dilamar</td>
                    <td class="colon">:</td>
                    <td>{{ $kandidat['Posisi_Dilamar'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Level</td>
                    <td class="colon">:</td>
                    <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td>
                </tr>
            @else
                <tr>
                    <td class="label">Departemen</td>
                    <td class="colon">:</td>
                    <td>{{ $kandidat['nama_sub_divisi'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Level</td>
                    <td class="colon">:</td>
                    <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Tanggal Tes</td>
                <td class="colon">:</td>
                <td>{{ $tanggalTes }}</td>
            </tr>
        </table>

        <div class="thick-line"></div>

        <div class="penjelasan-box">
            <p class="penjelasan-title">PENJELASAN :</p>
            <p><strong>PAPIKOSTICK</strong> sebuah pengukuran kepribadian yang dirancang khusus untuk memperoleh kecenderungan perilaku dan preferensi yang sesuai dengan tempat kerja. Terdapat 2 (dua) elemen besar yang diukur dengan alat tes ini, yaitu peran dan kebutuhan</p>
            <p>Peran menilai persepsi seseorang tentang situasi kerja seseorang, sedangkan kebutuhan menilai preferensi untuk berperilaku dengan cara tertentu. Laporan hasil tes disampaikan dalam bentuk visual berupa cakram <i>(spider graph)</i></p>
            <p><strong>Peran</strong> menilai persepsi seseorang tentang situasi kerja seseorang, sedangkan <strong>kebutuhan</strong> menilai preferensi untuk berperilaku dengan cara tertentu. Laporan hasil tes disampaikan dalam bentuk visual berupa cakram <i>(spider graph)</i></p>
            <p>Profil dari <strong>Papikostick</strong> akan menghasilkan skor dari 20 aspek yang diukur. Skor bergerak dari rentang 0-9, di mana skor 0 adalah skor terendah dan skor 9 adalah skor tertinggi.</p>
        </div>

        @if(!empty($chartImage))
            <div class="chart-wrapper">
                <img src="{{ $chartImage }}" class="chart-img" alt="PAPI Kostick Chart">
            </div>
        @else
            <div style="text-align: center; margin-top: 30px; border: 1px dashed #aaa; padding: 20px;">
                <i>Grafik tidak tersedia.</i>
            </div>
        @endif

        <div class="page-break"></div>

        <div class="concern-container">
            <table class="concern-layout-table">
                <tr>
                    <td style="width: 25px;">
                        <div class="concern-box-color"></div>
                    </td>
                    <td style="text-align: justify; line-height: 1.4;">
                        <strong>Kotak Warna Kuning</strong> merupakan aspek dengan <i>Concern Area</i>. Menunjukkan aspek dengan skor di luar rentang optimal yang berpotensi memengaruhi efektivitas kerja. Perlu pendalaman lebih lanjut dan pertimbangan dalam konteks tuntutan jabatan.
                    </td>
                </tr>
            </table>
        </div>

        @if(!empty($papiData) && count($papiData) > 0)
            @foreach($papiData as $kategori)
                <table class="hasil-bagian-table">
                    <thead>
                        <tr>
                            <th colspan="4" class="bg-header-utama text-uppercase">
                                {{ $kategori['nama_kategori'] }}
                            </th>
                        </tr>
                        <tr>
                            <th colspan="2" class="bg-header-sub text-uppercase font-bold" style="width: 50%;">ASPEK</th>
                            <th colspan="2" class="bg-header-sub text-uppercase font-bold" style="width: 50%;">RESULT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kategori['aspek_list'] as $aspek)
                            <tr style="background-color: {{ $aspek['bg_interpretasi'] }};">
                                <td class="text-center font-bold no-border-right" style="width: 8%;">{{ $aspek['kode'] }}</td>
                                <td class="no-border-left" style="width: 50%;">{{ $aspek['nama_aspek'] }}</td>
                                <td class="text-center font-bold no-border-right" style="width: 8%;">{{ $aspek['nilai'] }}</td>
                                <td class="no-border-left" style="text-align: justify; width: 50%;">{{ $aspek['interpretasi'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @else
            <table class="hasil-bagian-table">
                <tr>
                    <td class="text-center">Data penilaian tidak ditemukan.</td>
                </tr>
            </table>
        @endif

        
    </main>
</body>
</html>