{{--
    WEB CAREER — HASIL TES: Result Test Kraepelin

    DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/ujian-nilai-akhir-kraeplin.blade.php).
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
    <title>Hasil Kraeplin</title>
    <style>
    body {
        font-family: 'Helvetica', Arial, sans-serif;
        font-size: 10px;
        padding: 5px;
        color: #333;
    }

    /* === [MAIN TABLE STYLE] === */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    th, td {
        border: 1px solid #ddd;
        /* [ADJUSTMENT] Padding diperkecil agar tidak terlalu tinggi (Excel-like) */
        padding: 5px; 
        text-align: left;
        vertical-align: middle; /* Rata tengah secara vertikal */
    }
    
    .text-center { text-align: center !important; }
    .align-middle { vertical-align: middle !important; }
    .font-bold { font-weight: bold; }
    .text-muted { color: #666; }
    .small { font-size: 9px; }

    /* === [HEADER & FOOTER STYLE] === */
    .header-table {
        width: 100%;
        border: none;
        margin-bottom: 25px;
    }
    .header-table td {
        border: none;
        vertical-align: middle;
        padding: 2px;
    }
    .header-table .logo { width: 80px; }
    .company-details { text-align: right; }
    .company-details h4, .company-details p { margin: 0; }
    
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

    /* === [TITLE STYLE] === */
    .bg-title {
        background-color: #0b5394;
        color: white;
        padding: 8px 10px;
        text-align: center;
        border-radius: 4px 4px 0 0; 
        margin-bottom: 0;
    }
    .bg-title h6 { margin: 0; font-size: 14px; font-weight: normal; }

    /* === [TABLE HEADER STYLE] === */
    thead.bg-table-head th {
        background-color: #0b5394 !important;
        color: white;
        text-align: center;
        vertical-align: middle;
        font-size: 10px;
        font-weight: bold;
        border: 1px solid #ddd !important;
    }

    /* === [BIODATA STYLE] === */
    .tes-intelegensi-table {
        margin-top: 0;
        margin-bottom: 20px !important; 
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        border-top: none;
    }
    .tes-intelegensi-table td { border: none; padding: 2px; }
    .biodata-inner-table td { padding: 4px 6px; }
    .bg-biodata { width: 1%; white-space: nowrap; font-weight: bold; }
    .kolom-titikdua { width: 10px; text-align: center; }

    /* === [NESTED TABLE STYLE (Tabel di dalam Norma)] === */
    .nested-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        border: none; /* Hilangkan border luar tabel nested */
    }
    .nested-table td {
        border-top: none;
        border-left: none;
        border-right: none;
        /* Border bawah manual agar antar baris norma ada garis */
        border-bottom: 1px solid #ddd; 
        padding: 4px;
        text-align: center;
        font-size: 9px;
    }
    /* Menghilangkan border bawah pada baris terakhir nested table agar tidak double dengan border main table */
    .nested-table tr:last-child td {
        border-bottom: none;
    }
    /* Garis pemisah antara Skor dan Klasifikasi */
    .border-right-custom {
        border-right: 1px solid #ddd !important;
    }

    /* === [BADGE STYLE] === */
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px; /* Membuat bentuk pill/lonjong */
        font-size: 9px;
        font-weight: bold;
        color: #333;
        text-align: center;
        min-width: 60px;
        border: 1px solid rgba(0,0,0,0.1); /* Sedikit border halus */
    }

    .watermark {
        position: fixed;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        z-index: -1000;
        opacity: 0.08;
        width: 60%;
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
    $namaReport = $lap['nama_tes'] ?: 'Result Test Kraepelin';
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
         <div class="bg-title" style="font-size: 9px; padding: 5px;">
             Dokumen Ini Bersifat <strong>RAHASIA</strong> - HC EVO Group
        </div>
    </footer>

    <main>
        <div class="bg-title">
            <h6>{{ strtoupper($namaReport) }}</h6>
        </div>

        <table class="tes-intelegensi-table">
            <tr>
                <td style="padding: 5px;">
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
                            <td>{{ $kandidat['Tanggal_Tes'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="bg-table-head">
                    <tr>
                        <th rowspan="2" width="5%">No</th>
                        <th rowspan="2" width="30%">Aspek Pengukuran</th>
                        <th rowspan="2" width="10%">Nilai</th>
                        <th colspan="2" width="40%">Norma</th>
                        <th rowspan="2" width="15%">Kategori</th>
                    </tr>
                    <tr>
                        <th width="20%">Skor</th>
                        <th width="20%">Klasifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryList as $index => $item)
                        <tr>
                            <td class="text-center align-middle">
                                <strong>{{ $index + 1 }}</strong>
                            </td>

                            <td class="align-middle">
                                <strong style="font-size: 11px;">{{ $item['Nama_Aspek'] }}</strong><br />
                                <span class="text-muted small" style="line-height: 1.2; display: block; margin-top: 3px;">
                                    {{ $item['Deskripsi_Aspek'] }}
                                </span>
                            </td>

                            <td class="text-center align-middle font-bold" style="font-size: 11px;">
                                {{ $item['Nilai'] }}
                            </td>

                            <td colspan="2" style="padding: 0; vertical-align: top;">
                                <table class="nested-table">
                                    <tbody>
                                        @foreach($item['Norma_Referensi'] as $norma)
                                            <tr style="background-color: {{ $norma['Warna_Bg'] }};">
                                                <td width="50%" class="border-right-custom">
                                                    {{ $norma['Skor'] }}
                                                </td>
                                                <td width="50%">
                                                    {{ $norma['Klasifikasi'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>

                            <td class="text-center align-middle">
                                <span class="badge" 
                                      style="background-color: {{ $item['Warna'] }};">
                                    {{ $item['Keterangan'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                Tidak ada data summary tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
             <div style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                @if(!empty($chartImage))
                    <img src="{{ $chartImage }}" style="width: 100%; max-height: 350px; object-fit: contain;" alt="Grafik Kraeplin">
                @else
                    <div style="padding: 50px; color: #999; font-style: italic;">
                        Grafik tidak dapat ditampilkan (Koneksi ke server grafik gagal).
                    </div>
                @endif
            </div>

        
    </main>
</body>
</html>