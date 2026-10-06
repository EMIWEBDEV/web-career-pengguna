{{--
    WEB CAREER — HASIL TES: Hasil Tes

    DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/ujian-nilai-akhir.blade.php).
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

        .bg-title {
            background-color: #0b5394;
            color: white;
            padding: 10px 15px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 15px;
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
        .bg-biodata {
            background-color: #f2f2f2;
            font-weight: bold;
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
    $namaReport = $lap['nama_tes'] ?: 'Hasil Tes';
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
            'pass', 'lulus' => '#28a745',
            'pass (at risk)' => '#ffc107',
            'reject', 'tidak lulus' => '#dc3545',
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
        <div class="bg-title">
             <h6>{{ $namaReport }} - {{ $kandidat['Nama_Tes'] }}</h6>
        </div>
    </header>

    <footer>
         <div class="bg-title">
             <h4>Dokumen Ini Bersifat <strong>RAHASIA</strong>, Dilarang Mencetak Dan Menyebarluaskan Tanpa Seizin HC EVO Group</h4>
        </div>
    </footer>

    <main>
        <table>
            <tr>
                <td style="width:25%;" class="bg-biodata">Nama Kandidat</td>
                <td style="width:75%;">{{ $kandidat['Nama_Kandidat'] ?? '-' }}</td>
            </tr>
            @if($kandidat['isEksternal'])
                <tr>
                    <td class="bg-biodata">Job Level</td>
                    <td>{{ $kandidat['Data_Jabatan'] ?? 'Staff' }}</td> 
                </tr>
                <tr>
                    <td class="bg-biodata">Job Position</td>
                    <td>{{ $kandidat['Posisi_Dilamar'] ?? '-' }}</td>
                </tr>
            @else
                <tr>
                    <td class="bg-biodata">Job Title</td>
                    <td>{{ $kandidat['Data_Jabatan'] ?? '-' }}</td> 
                </tr>
            @endif
            <tr>
                <td class="bg-biodata">Tanggal Tes</td>
                <td>{{ $tanggalTes }}</td>
            </tr>
            <tr>
                <td class="bg-biodata">Durasi Pengerjaan</td>
                <td>{{ $durasiFormatted }}</td>
            </tr>
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
                        <td style="background-color: {{ $row->Warna ?? '#eee' }}; color:black; width: 60px;" class="text-center font-bold">{{ (float) $row->Score_Peserta }}</td>
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
            <h6>SUMMARY & FINAL RESULT</h6>
        </div>
        <table>
            <tr>
                <td style="width:25%;" class="font-bold bg-biodata">NILAI AMBANG BATAS (BASELINE)</td>
                <td style="width:25%;" class="text-center font-bold">{{ (float) $kandidat['Ambang_Batas_Nilai'] ?? 0 }}%</td>
                <td rowspan="2" style="width:50%; background-color: {{ $statusColor }}; color:white; text-align:center; vertical-align:middle; font-size: 20px;" class="font-bold">
                    {{ strtoupper($kandidat['Status_Kelulusan'] ?? 'N/A') }}
                </td>
            </tr>
            <tr>
                <td class="font-bold bg-biodata">NILAI AKHIR (RESULT)</td>
                <td class="text-center font-bold">{{ (float) $kandidat['Total_Nilai'] ?? 0 }}%</td>
            </tr>
          @if($showSummaryBlock)
                <tr>
                    <td colspan="3" style="text-align:justify; padding-top: 15px;">
                        <strong style="margin-bottom: 5px; display: block;">Catatan:</strong>
                        {!! $kandidat['Summary'] ?? 'Tidak ada catatan tambahan.' !!}
                    </td>
                </tr>
            @endif
        </table>

        
    </main>
</body>
</html>