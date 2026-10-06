{{--
    WEB CAREER — HASIL TES: TIU — General Reasoning Test

    DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/ujian-nilai-akhir-tiu.blade.php).
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
    <title>Hasil Ujian Kandidat</title>
    <style>
    body {
        font-family: 'Helvetica', Arial, sans-serif;
        font-size: 10px;
        padding: 5px;
        color: #333;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        page-break-inside: auto;
    }
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px 10px;
        text-align: left;
        vertical-align: top;
    }
    main {
        padding: 0 10px;
    }
    .text-center { text-align: center !important; }
    .font-bold { font-weight: bold; }
    .keterangan-domain {
        margin-top: 2px;
        font-size: 10px;
        color: #555;
    }

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
    .document-id {
        background-color: #f2f2f2;
        padding: 5px;
        font-size: 10px;
        text-align: center;
        margin-bottom: 20px;
        border: 1px solid #ddd;
    }

    /* === [PERUBAHAN 1] === */
    .bg-title {
        background-color: #0b5394;
        color: white;
        padding: 10px 15px;
        text-align: center;
        /* Sudut atas melengkung, sudut bawah rata agar nempel */
        border-radius: 4px 4px 0 0; 
        margin-bottom: 0; /* Dihapus agar nempel ke tabel */
    }
    .bg-title h6 {
        margin: 0;
        font-size: 14px;
        font-weight: normal;
    }
    .bg-title h4 {
        margin: 0;
        font-size: 12px;
        font-weight: normal;
    }
 
    thead.bg-table-head th {
        background-color: #0b5394 !important;
        color: white;
        text-align: center;
    }
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: -1000;
        opacity: 0.08;
        width: 60%;
    }
    .disclaimer {
        margin-top: 25px;
        font-size: 9px;
        color: #666;
        text-align: justify;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }

    /* === GAYA YANG DISESUAIKAN UNTUK BLOK UTAMA === */

    /**
     * 1. Container Utama (Bordered)
     */
    /* === [PERUBAIKAN 2] === */
    .tes-intelegensi-table {
        margin-top: 0; /* Nempel ke bg-title */
        margin-bottom: 5px; 
        page-break-inside: avoid;
        /* Hanya border luar kiri, kanan, bawah */
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        border-top: none; /* Border atas hilang */
    }
    
    /* === [PERBAIKAN 3] === */
    /* Menghilangkan border antar sel <td> di tabel utama */
    .tes-intelegensi-table > tbody > tr > td {
        border: none;
    }

    /* 2. Kolom Kiri (Biodata) */
    .tes-intelegensi-table .keterangan-utama {
        width: 70%; 
        vertical-align: top;
        padding: 0; 
    }

    /* 3. Kolom Kanan (Kotak Hasil) */
    .tes-intelegensi-table .hasil-box {
        width: 30%; 
        vertical-align: top;
        padding: 8px;
        /* Beri border kiri manual agar terpisah, jika diinginkan */
        /* border-left: 1px solid #ddd; */ /* <-- Hapus komentar ini jika ingin ada garis pemisah */
    }

    /* === STYLING BARU UNTUK TABEL BIODATA DI DALAM === */
    .biodata-inner-table {
        width: 100%;
        margin: 0; 
        border: none; 
    }
    .biodata-inner-table td {
        border: none !important; /* WAJIB: Hapus border dari sel */
        padding: 8px 10px; 
        vertical-align: middle;
    }
    
    /* === [PERUBAHAN CSS BIODATA "MEPET"] === */
    .biodata-inner-table .bg-biodata {
        width: 1%; /* Trik "mepet" */
        white-space: nowrap; /* Mencegah label terpotong */
    }
    /* ============================================== */

    /* Style untuk kolom titik dua (:) */
    .biodata-inner-table .kolom-titikdua {
        width: 15px; /* Lebar tetap */
        text-align: center;
        padding-left: 2px;
        padding-right: 2px;
    }
    /* ============================================== */


    /* 4. Tabel Kecil "Hasil" di dalam Kolom Kanan */
    .hasil-table {
        width: 100%;
        margin-bottom: 0;
        border: 1px solid #aaa;
    }
    .hasil-table th, 
    .hasil-table td {
        border: 1px solid #aaa;
        padding: 6px;
        text-align: center;
        vertical-align: middle;
    }
    .hasil-table thead th {
        background-color: #f2f2f2;
        font-weight: bold;
        color: #333;
    }

   /* === [BARU] GAYA UNTUK BADGE STATUS === */
.cell-badge-status {
    padding: 8px !important; /* Memberi ruang di dalam sel */
    text-align: center;
    vertical-align: middle;
}

.status-badge {
    display: inline-block;  /* Kunci untuk membuat badge */
    padding: 6px 15px;      /* Padding 6px atas/bawah, 15px kiri/kanan (tidak mepet) */
    border-radius: 6px;     /* "Jangan terlalu melengkung" (sedikit melengkung) */
    font-weight: bold;
    font-size: 12px;
    color: white;           /* Asumsi warna status Anda gelap */
    text-align: center;
    min-width: 80px;        /* Agar lebar badge konsisten */
}
/* ==================================== */
    
    /* 6. Gaya untuk Teks Ambang Batas */
    .ambang-batas {
        font-size: 9px;
        color: #555;
        line-height: 1.4;
        text-align: left !important;
        padding-left: 10px !important;
    }

    .bg-hasilnya {
        background-color: #4a86e8 !important; /* Warna biru yang Anda minta */
        color: white !important; /* Teks putih agar kontras */
    }

    /* === [PERUBAHAN CSS KETERANGAN "MEPET"] === */
    /* 7. Keterangan di Bawah (Wrapper) */
    .keterangan-bawah-no-border {
        padding: 0; /* Hapus padding agar tabel baru pas */
        margin-bottom: 15px;
    }
    /* ========================================= */

    /* === [CSS BARU UNTUK BAGIAN I, II, III] === */
    .hasil-bagian-table {
        width: 100%;
        border: none; /* Tanpa border luar */
        margin-top: 10px;
    }
    .hasil-bagian-table tbody {
        /* Beri jarak antar bagian */
        border-bottom: 1px solid #eee;
    }
    .hasil-bagian-table tbody:last-child {
        border-bottom: none;
    }

    .hasil-bagian-table td {
        border: none; /* Tanpa border internal */
        padding: 4px 8px; /* Padding "mepet" (kecil) */
        vertical-align: top;
    }

    .bagian-header .bagian-title {
        font-weight: bold;
        font-size: 11px;
        color: #0b5394; /* Biru tua */
    }
    .bagian-header .bagian-skor {
        text-align: right;
        font-weight: bold;
        font-size: 11px;
    }
    .bagian-skor span {
        margin-left: 10px; /* Jarak antar skor */
    }
    .skor-persen { color: #0b5394; }
    .skor-angka { color: #333; }
    .skor-kategori-avg { color: #e69138; } /* Oranye */
    .skor-kategori-high { color: #008000; } /* Hijau */

    .bagian-desk-label {
        font-weight: bold;
        font-size: 9px;
        color: #555;
        padding-top: 8px !important;
    }
    .bagian-desk-text {
        font-size: 10px;
        color: #333;
        padding-top: 2px !important;
        padding-bottom: 10px !important; /* Jarak bawah */
        text-align: justify;
    }
    /* ======================================================== */
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
    $namaReport = $lap['nama_tes'] ?: 'TIU — General Reasoning Test';
    $watermark = $watermark ?? null;
    $pelanggaran = null;
@endphp
    <div class="watermark">
        <img src="{{ $watermark }}" width="100%" alt="Watermark Logo">
    </div>

    @php
        use Carbon\Carbon;
        $tanggalTes = !empty($kandidat['Waktu_Akhir']) ? Carbon::parse($kandidat['Waktu_Akhir'])->translatedFormat('d F Y') : '-';
        $durasiFormatted = 'Data tidak lengkap';
        if (!empty($kandidat['Waktu_Mulai_Akses']) && !empty($kandidat['Waktu_Selesai_Akses'])) {
            $mulai = Carbon::parse($kandidat['Waktu_Mulai_Akses']);
            $selesai = Carbon::parse($kandidat['Waktu_Selesai_Akses']);
            $durasiFormatted = $mulai->diffForHumans($selesai, true);
        }
        $statusKelulusan = strtolower($kandidat['Status_Kelulusan'] ?? '');
        $statusColor = match($statusKelulusan) {
            'pass', 'lulus' => '#198754',
            'pass (at risk)' => '#ffc107',
            'reject' => '#E06666',
            'tidak lulus' => '#dc3545',
            default => '#6c757d',
        };
        $kodeSoalMap = [
            'BE' => 'BUSINESS ETHICS', 'IA' => 'INTEGRITY ATTITUDE', 'WB' => 'WORK BACKGROUND',
            'SU' => 'SUBSTANCE USE', 'VQ' => 'VALIDITY FAKE QUESTION',
        ];
        $documentId = 'RES-' . date('Ymd') . '-' . ($kandidat['id'] ?? rand(1000, 9999));
        $showSummaryBlock = $domain->some(fn($item) => $item->Flag_Interpretation === 'Y');
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
        <div class="bg-title">
            <h6>{{ $kandidat['Nama_Tes'] }}</h6>
        </div>

        <table class="tes-intelegensi-table">
            <tr>
                <td class="keterangan-utama">
                    
                    <table class="biodata-inner-table">
                        <tr>
                            <td class="bg-biodata">Nama Kandidat</td>
                            <td class="kolom-titikdua">:</td>
                            <td>{{ $kandidat['Nama_Kandidat'] ?? '-' }}</td>
                        </tr>
                        @if($kandidat['isEksternal'])
                            <tr>
                                <td class="bg-biodata">Posisi Dilamar</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['Posisi_Dilamar'] ?? '' }}</td> 
                            </tr>
                            <tr>
                                <td class="bg-biodata">Level</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td> 
                            </tr>
                        @else
                            <tr>
                                <td class="bg-biodata">Departemen</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['nama_sub_divisi'] ?? '-' }}</td> 
                            </tr>
                            <tr>
                                <td class="bg-biodata">Level</td>
                                <td class="kolom-titikdua">:</td>
                                <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td> 
                            </tr>
                        @endif
                        <tr>
                            <td class="bg-biodata">Tanggal Tes</td>
                            <td class="kolom-titikdua">:</td>
                            <td>{{ $tanggalTes }}</td>
                        </tr>
                    </table>
                </td>
                
                <td class="hasil-box">
                    <table class="hasil-table">
                        <thead>
                            <tr>
                                <th class="bg-hasilnya">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                           <tr>
                                <td class="cell-badge-status">
                                    <span class="status-badge" style="background-color: {{ $statusColor }};">
                                        {{ strtoupper($kandidat['Status_Kelulusan'] ?? 'N/A') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="ambang-batas">
                                    Ambang Batas Staff = 50 <br>
                                    Ambang Batas Supervisor = 65
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        <table style="margin-top: 20px;">
            <thead> 
                <tr>
                    <th rowspan="2" style="background-color: #0b5394; color: white; text-align: center; vertical-align: middle;">Aspek</th>
                    
                    <th colspan="4" style="background-color: #0b5394; color: white; text-align: center;">Kategori</th>
                </tr>
                <tr>
                    <th colspan="2" style="background-color: #e06666; color: white; text-align: center;">
                        Low
                    </th>
                    
                    <th style="background-color: #f6b26b; text-align: center;">
                        Average
                    </th>
                    
                    <th style="background-color: #6aa84f; color: white; text-align: center;">
                        High
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nilai Keseluruhan</td>
                    <td colspan="2" style="text-align: center;">0–49%</td>
                    <td style="text-align: center;">50–74%</td>
                    <td style="text-align: center;">75–100%</td>
                </tr>
                <tr>
                    <td>Kemampuan Verbal</td>
                    <td colspan="2" style="text-align: center;">0–46%</td>
                    <td style="text-align: center;">47–73%</td>
                    <td style="text-align: center;">74–100%</td>
                </tr>
                <tr>
                    <td>Kemampuan Numerikal</td>
                    <td colspan="2" style="text-align: center;">0–46%</td>
                    <td style="text-align: center;">47–69%</td>
                    <td style="text-align: center;">70–100%</td>
                </tr>
                <tr>
                    <td>Kemampuan Logika</td>
                    <td colspan="2" style="text-align: center;">0–41%</td>
                    <td style="text-align: center;">42–66%</td>
                    <td style="text-align: center;">67–100%</td>
                </tr>
            </tbody>
        </table>
        <div class="bg-title" style="margin-top: 25px; margin-bottom: 0; border-radius: 4px 4px 0 0;">
            <h6>RINCIAN SKOR</h6>
        </div>
        <table>
            <thead class="bg-table-head">
                <tr>
                    <th>Domain Penilaian</th>
                    <th class="text-center">Bobot</th>
                    <th class="text-center" colspan="2">Hasil (Skor & Grade)</th>
                    @if($showSummaryBlock)
                        <th>Interpretasi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($domain as $row)
                    @php $namaSoal = $kodeSoalMap[$row->Kode_Soal] ?? $row->Jenis_Soal; @endphp
                    <tr>
                        <td>
                            <span class="font-bold">{{ $namaSoal }}</span>
                            <p class="keterangan-domain">{{ $row->Keterangan ?? "" }}</p>
                        </td>
                        <td class="text-center">{{ (float) $row->Bobot_Penilaian }}%</td>
                        <td style="background-color: {{ $row->Warna ?? '#eee' }}; color:black; width: 60px;" class="text-center font-bold">{{ (float) $row->Score_Peserta }} %</td>
                        <td style="background-color: {{ $row->Warna ?? '#eee' }}; color:black; width: 100px;" class="text-center">{{ $row->Status_Interpretation }}</td>
                        @if($showSummaryBlock)
                            <td>{!! $row->Remaks ?? 'Belum ada interpretasi.' !!}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showSummaryBlock ? 5 : 4 }}" class="text-center">Data penilaian tidak ditemukan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="bg-title" style="margin-top: 25px; margin-bottom: 0; border-radius: 4px 4px 0 0;">
            <h6>FINAL RESULT</h6>
        </div>
        <table>
            <tr>
                <td class="font-bold bg-biodata">HASIL AKHIR</td>
                <td class="text-center font-bold">{{ (float) $kandidat['Total_Nilai'] ?? 0 }}%</td>
                    <td  style="background-color: {{ $kandidat['Final_Score_Warna'] }}; text-align:center; vertical-align:middle; font-size: 12px;" class="font-bold">
                    {{ strtoupper($kandidat['Final_Score_Keterangan'] ?? 'N/A') }}
                </td>
            </tr>
        
        </table>

        
    </main>
</body>
</html>