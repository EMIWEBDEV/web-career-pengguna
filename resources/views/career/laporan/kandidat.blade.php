{{--
    WEB CAREER — BIODATA KANDIDAT (PDF).

    Mengikuti rancangan "Biodata Kandidat — SALNI" (docs/refrences/design):
    kertas putih, palet krem–emas, kop & kaki berjalan di tiap halaman.
    Halaman 1 berisi profil dua kolom; halaman berikutnya isian formulir;
    halaman terakhir dokumen & catatan.

    ── HURUFNYA INTER & JETBRAINS MONO, SAMA DENGAN RANCANGAN ─────────────────
    TTF-nya dibundel di resources/fonts/pdf (keduanya OFL) dan didaftarkan lewat
    @font-face. dompdf hanya mengenal DUA ragam per rumpun (normal & bold), jadi
    berat 500/600/800 di rancangan dipetakan ke salah satunya.

    ── PANJANGNYA TIDAK BISA DITEBAK, JADI TIDAK DITEBAK ──────────────────────
    Nama kandidat, nama posisi, dan nama program datang dari data — ada yang
    lima huruf, ada yang satu baris penuh. Ukuran huruf nama & posisi DIHITUNG
    dari panjangnya (lihat $ukuranNama di bawah), bukan dipatok satu angka:
    dipatok 42px, nama panjang menabrak foto; dipatok kecil, nama pendek
    terlihat seperti salah cetak. Kata tunggal yang panjang (alamat surel,
    nama berkas) dipatahkan dengan word-wrap, dan tiap sel yang isinya dari
    kandidat memakai `word-wrap: break-word`.

    ── PEMBABAKANNYA DATA, BUKAN DAFTAR DI SINI ───────────────────────────────
    Judul bagian diambil dari BAGIAN formulir yang dibekukan saat kandidat
    mengirim — lihat LaporanKandidat::petaSkema(). Formulirnya dirancang lewat
    layar, jadi menuliskan daftar judul di sini berarti setiap bagian baru akan
    menumpuk di "lain-lain" sampai ada yang ingat menyunting berkas ini.

    Bentuk cetak tiap isian ditentukan TIPE fieldnya, bukan namanya:
      consent                 → baris bercentang dua kolom
      pertanyaan + Ya/Tidak   → baris penuh berlencana
      currency                → kartu emas "Rp 5.000.000"
      textarea / teks panjang → sel selebar dua kolom
      bagian berulang         → kartu bernomor
      sisanya                 → kisi dua kolom

    ── BATASAN MESIN CETAK (dompdf 3.1.5) ─────────────────────────────────────
      • Tidak ada flexbox, grid, gradient, maupun box-shadow. Kisi rancangan
        dijadikan TABEL; gradien dijadikan satu warna padat.
      • Garis tipis digambar dengan BORDER, bukan div berlatar setinggi 1px:
        elemen tanpa isi kerap dirender bertinggi nol.
      • border-radius dipasang pada DIV, tidak pada tabel ber-border-collapse —
        di tabel, latar selnya tidak ikut terpotong dan sudutnya menyembul.
      • Foto BULAT dipotong di sisi server (LaporanKandidat::bulatkan), sebab
        dompdf tidak memotong gambar mengikuti border-radius.
      • Kop & kaki `position: fixed` — isinya SAMA di semua halaman. dompdf
        tidak bisa memberi halaman pertama kop yang berbeda tanpa menyalakan
        eksekusi PHP di dalam berkas, dan berkas ini dirakit dari isian
        kandidat. Karena itu kop bergaya halaman 2–4 dipakai di seluruh
        halaman; nomor halamannya asli dari counter(page).
      • Blok yang tak boleh terbelah diberi page-break-inside: avoid.

    Seluruh gambar berupa data URI — dompdf tidak diizinkan menembak URL
    (isRemoteEnabled mati), sebab sebagian isian datang dari kandidat.
--}}
@php
    $K = $d['kandidat'];
    $L = $d['lamaran'];

    $ada = fn ($v) => trim((string) $v) !== '';
    $fontDir = 'file://' . str_replace('\\', '/', resource_path('fonts/pdf'));


    // ── UKURAN NAMA & POSISI DIHITUNG DARI PANJANGNYA ──────────────────────
    // Lebar kolom kiri kepala ≈ 500px pada A4 bermargin rancangan. Dua
    // penjagaan sekaligus: panjang seluruhnya (berapa baris jadinya) dan KATA
    // TERPANJANG (satu kata tak bisa dipatah rapi, jadi ia yang menentukan
    // batas atas). Tanpa yang kedua, "MUHAMMADIYAH" tunggal tetap meluber
    // walau total karakternya sedikit.
    $nama = $K['nama'] ?: '—';
    $kataNama = max(array_map('mb_strlen', preg_split('/\s+/', $nama) ?: ['']) ?: [1]);
    $ukuranNama = (int) max(16, min(match (true) {
        mb_strlen($nama) <= 12 => 42,
        mb_strlen($nama) <= 18 => 36,
        mb_strlen($nama) <= 26 => 30,
        mb_strlen($nama) <= 36 => 25,
        mb_strlen($nama) <= 50 => 21,
        default => 18,
    }, floor(500 / (0.68 * max(1, $kataNama)))));

    $posisi = $L['posisi'] ?: $L['program'] ?: '—';
    $ukuranPosisi = mb_strlen($posisi) <= 42 ? 15 : (mb_strlen($posisi) <= 70 ? 13 : 11.5);

    $YA = ['ya', 'y', 'sudah', 'sesuai', 'benar', 'setuju', 'bersedia', 'ada', 'true', '1'];
    $TIDAK = ['tidak', 't', 'belum', 'tidak sesuai', 'tidak ada', 'false', '0'];
    $nadaJawab = function ($v) use ($YA, $TIDAK) {
        $s = mb_strtolower(trim((string) $v));
        return in_array($s, $YA, true) ? 'ya' : (in_array($s, $TIDAK, true) ? 'tidak' : null);
    };

    $rupiah = function ($v) {
        $angka = preg_replace('/[^0-9]/', '', (string) $v);
        return $angka !== '' ? 'Rp ' . number_format((float) $angka, 0, ',', '.') : trim((string) $v);
    };

    $warnaStatus = [
        'l-lolos' => '#16714f', 'l-jalan' => '#16714f', 'l-gugur' => '#b91c1c',
        'l-talent' => '#6d28d9', 'l-netral' => '#3d4351',
    ][$nadaHasil] ?? '#3d4351';

    $dokumen = collect($d['formulir'])->flatMap(fn ($f) => $f['dokumen'])
        ->unique(fn ($x) => $x['field'] . '|' . $x['nama'])->values();

    // ── RINGKASAN SAMPING: dicari lewat LABEL, dan boleh tidak ketemu ──────
    // Sidebar rancangan menampilkan Agama, Status Pernikahan, Jenis Institusi,
    // Program Studi — semuanya isian formulir yang TIDAK punya kode di master
    // kunci identitas. Dicocokkan dengan labelnya, dan barisnya cuma hilang
    // bila tak ketemu. Ini sengaja hanya untuk RINGKASAN: isian lengkapnya
    // tetap tercetak utuh di bagian formulir, jadi tebakan yang meleset di
    // sini tidak pernah menghilangkan data dari dokumen.
    $semuaIsian = collect($d['formulir'])->flatMap(fn ($f) => $f['isian'])
        ->filter(fn ($j) => ! $j['berkas'] && trim((string) $j['nilai']) !== '');
    $cariLabel = function (string $pola) use ($semuaIsian) {
        $j = $semuaIsian->first(fn ($x) => (bool) preg_match($pola, (string) $x['label']));
        return $j ? trim((string) $j['nilai']) : null;
    };

    // NIK — CADANGAN LEWAT ISIAN FORMULIR.
    //
    // N_WEB_CAREERS_Master_Kunci_Identitas belum punya baris ber-Kode 'NIK'
    // (yang ada baru FOTO, NAMA, TGL_LAHIR, JKEL, KAMPUS, JURUSAN, JENJANG,
    // IPK, SEMESTER, STATUS_STUDI, TAHUN_LULUS) — jadi $profil['nik'] selalu
    // kosong dan lencana NIK di kepala tidak pernah muncul. Dicari sendiri di
    // sini sampai kodenya didaftarkan di master; begitu didaftarkan, yang dari
    // master menang karena ia disebut lebih dulu.
    $nik = $ada($K['nik'] ?? null)
        ? $K['nik']
        : ($semuaIsian->first(fn ($x) => (bool) preg_match('/^nik$|^no_?ktp$|^nomor_?ktp$/i', (string) $x['key']))['nilai']
            ?? $cariLabel('/\bnik\b|no\.?\s*ktp|nomor\s*ktp/i'));

    $agama = $cariLabel('/\bagama\b/i');
    $statusNikah = $cariLabel('/pernikahan|perkawinan|\bnikah\b|marital/i');
    $jenisInstitusi = $cariLabel('/jenis\s*institusi/i');
    $prodi = $cariLabel('/program\s*studi|\bprodi\b/i');
    $noWa = $cariLabel('/whats\s*?app|\bwa\b/i');
    $dataSesuai = $cariLabel('/(data|identitas).*(sesuai|benar)|sudah\s*sesuai/i');

    $statusStudi = trim(($K['statusStudi'] ?? '') . ($ada($K['semester'] ?? null) ? ' · Semester ' . $K['semester'] : ''));

    /** Isian sebuah bagian → urutan blok siap cetak. */
    $susun = function (array $isian) use ($nadaJawab) {
        $blok = [];
        $antre = [];
        $centang = [];

        $buang = function () use (&$blok, &$antre, &$centang) {
            if ($antre) { $blok[] = ['jenis' => 'kisi', 'isi' => $antre]; $antre = []; }
            if ($centang) { $blok[] = ['jenis' => 'centang', 'isi' => $centang]; $centang = []; }
        };

        foreach ($isian as $j) {
            // Berkas punya tabelnya sendiri di halaman terakhir — nama file di
            // tengah kisi data pribadi hanya jadi derau.
            if ($j['berkas']) { continue; }

            if ($j['baris']) { $buang(); $blok[] = ['jenis' => 'kartu', 'isi' => $j]; continue; }

            $nilai = trim((string) $j['nilai']);
            if ($nilai === '') { continue; }

            $nada = $nadaJawab($nilai);

            if (($j['tipe'] ?? null) === 'consent') {
                if ($antre) { $blok[] = ['jenis' => 'kisi', 'isi' => $antre]; $antre = []; }
                $centang[] = ['j' => $j, 'nada' => $nada ?? 'ya'];
                continue;
            }

            // Ya/Tidak berlabel PANJANG itu sebuah pertanyaan — di rancangan ia
            // jadi baris penuh berlencana. Yang labelnya pendek ("Buta Warna")
            // tetap sepasang label-nilai biasa di dalam kisi.
            if ($nada !== null && mb_strlen($j['label']) > 32) {
                $buang(); $blok[] = ['jenis' => 'tanya', 'isi' => $j, 'nada' => $nada]; continue;
            }

            if ($centang) { $blok[] = ['jenis' => 'centang', 'isi' => $centang]; $centang = []; }
            // Isian panjang menempati DUA kolom di dalam kisi yang sama —
            // rancangan memakai `grid-column: span 2`, bukan blok terpisah.
            //
            // Ekspektasi gaji TETAP DI DALAM KISI, hanya diberi warna emas.
            // Dikeluarkan jadi blok tersendiri, ia memutus kisi dan memaksa
            // "Ketersediaan Mulai Bekerja" di atasnya melebar sendirian —
            // rancangan justru menaruh keduanya bersebelahan.
            $antre[] = $j + [
                'lebar' => (($j['tipe'] ?? null) === 'textarea' || mb_strlen($nilai) > 58) ? 2 : 1,
                'duit' => ($j['tipe'] ?? null) === 'currency',
            ];
        }

        $buang();

        return $blok;
    };

    /**
     * Sel-sel kisi → baris tabel dua kolom.
     *
     * Isian selebar dua kolom memutus baris supaya tidak ada sel yang
     * menggantung setengah — di dompdf, colspan yang tidak genap membuat
     * kolomnya bergeser di seluruh sisa tabel.
     */
    $barisKisi = function (array $sel) {
        $baris = [];
        $kini = [];
        foreach ($sel as $s) {
            if (($s['lebar'] ?? 1) === 2) {
                if ($kini) { $baris[] = $kini; $kini = []; }
                $baris[] = [$s];
                continue;
            }
            $kini[] = $s;
            if (count($kini) === 2) { $baris[] = $kini; $kini = []; }
        }
        if ($kini) { $baris[] = $kini; }

        return $baris;
    };

    $mono = fn ($j) => in_array($j['tipe'] ?? '', ['number', 'date', 'phone', 'tahun', 'currency'], true)
        || preg_match('/\bnik\b|no\.?\s*hp|handphone|telepon|whats|npwp|rekening/i', (string) $j['label']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Biodata Kandidat — {{ $nama }}</title>
    <style>
        @font-face { font-family: 'Inter'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/Inter-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Inter'; font-style: normal; font-weight: bold;   src: url('{{ $fontDir }}/Inter-Bold.ttf') format('truetype'); }
        @font-face { font-family: 'JetBrains Mono'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/JetBrainsMono-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'JetBrains Mono'; font-style: normal; font-weight: bold;   src: url('{{ $fontDir }}/JetBrainsMono-Bold.ttf') format('truetype'); }

        /* Margin atas & bawah menyediakan ruang untuk kop dan kaki `fixed`. */
        @page { margin: 84px 38px 62px; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 11.5px; line-height: 1.4;
            color: #14171f; margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; padding: 0; }
        /* Semua yang isinya dari kandidat boleh dipatahkan — tanpa ini satu
           alamat surel panjang menggeser seluruh kolom di sebelahnya. */
        .pth { word-wrap: break-word; }

        .m { font-family: 'JetBrains Mono', monospace; }
        .lbl {
            font-family: 'JetBrains Mono', monospace;
            font-size: 7.5px; font-weight: bold; letter-spacing: 1.2px;
            text-transform: uppercase; color: #7c8496; margin-bottom: 5px;
        }
        .lbl-t { color: #6e7686; }

        /* ── HALAMAN 1 — KEPALA ─────────────────────────────────────────── */
        .kepala { padding-bottom: 20px; border-bottom: 1px solid #e4e2da; }
        .kepala .kiri { padding-right: 26px; }
        .nama-besar {
            font-weight: bold; line-height: 1.05; letter-spacing: -0.032em;
            color: #14171f; word-wrap: break-word;
        }
        .posisi { margin-top: 11px; }
        .posisi .tik { width: 24px; padding-top: 7px; }
        .posisi .tik span { display: block; border-top: 3px solid #c9a227; }
        .posisi .teks { padding-left: 11px; font-weight: bold; color: #a8801e; line-height: 1.3; word-wrap: break-word; }
        /* ── LENCANA NIK ─────────────────────────────────────────────────────
           PADDING DIPASANG DI SEL, BUKAN DI TABEL. dompdf tidak menerapkan
           padding sebuah <table> ke area selnya — yang terjadi, angkanya
           menempel lalu menembus keempat tepi kotak.

           `vertical-align: middle` juga wajib disebut: aturan global di berkas
           ini memasang `top` untuk semua sel, sehingga label "NIK" dan garis
           pemisahnya melorot ke dasar kotak sementara angkanya di tengah. */
        .nik {
            width: auto; margin-top: 15px; border-radius: 9px;
            background: #fbf3de; border: 1px solid #d5bb7c; border-left: 3px solid #c9a227;
        }
        .nik td { vertical-align: middle; padding: 9px 0; }
        .nik .k {
            padding-left: 15px; font-family: 'JetBrains Mono', monospace;
            font-size: 8px; font-weight: bold; line-height: 19px;
            letter-spacing: 1.6px; color: #545c6b; white-space: nowrap;
        }
        /* Pemisah tegak digambar sebagai BORDER sebuah span setinggi tetap —
           sel kosong berlatar kerap dirender bertinggi nol oleh dompdf. */
        .nik .pemisah { width: 1px; padding: 9px 12px; }
        .nik .pemisah span { display: block; width: 0; height: 15px; border-left: 1px solid #c9a227; }
        .nik .v {
            padding-right: 15px; font-family: 'JetBrains Mono', monospace;
            font-size: 16px; font-weight: bold; line-height: 19px;
            letter-spacing: 0.96px; color: #a8801e; white-space: nowrap;
        }
        .chips { margin-top: 15px; }
        .chip {
            display: inline-block; margin: 0 5px 5px 0; padding: 5px 11px; border-radius: 6px;
            font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: bold;
            letter-spacing: 1.1px; text-transform: uppercase;
            background: #ffffff; border: 1px solid #d8d5ca; color: #3d4351;
        }
        .chip-gelap { background: #1b2333; border-color: #1b2333; color: #e7c55c; }
        .c-hijau  { background: #eff8f4; border-color: #a7cfbe; color: #16714f; }
        .c-merah  { background: #fdf2f2; border-color: #e6b8b8; color: #b91c1c; }
        .c-ungu   { background: #f5f2fd; border-color: #cbbdf0; color: #6d28d9; }
        .c-netral { background: #f4efe2; border-color: #ddd9cc; color: #6e7686; }
        .lamar {
            display: inline-block; margin: 0 0 5px 3px;
            font-family: 'JetBrains Mono', monospace; font-size: 10px;
            letter-spacing: 0.6px; color: #7c8496; white-space: nowrap;
        }

        .kepala .kanan { width: 1%; }
        /* UKURANNYA UKURAN ISI, BUKAN UKURAN LUAR.
           dompdf memakai `content-box` dan tidak mengenal `box-sizing`, jadi
           126px di sini berarti KOTAK ISI 126px — sementara fotonya cuma 112px.
           Selisih 14px itu jatuh semua ke kanan-bawah, dan lingkaran fotonya
           duduk melenceng ke kiri-atas di dalam cincinnya. Dipatok 112px,
           padding 3 + border 4 menumpuk jadi 126px total dan keduanya sepusat. */
        .cincin {
            width: 112px; height: 112px; border-radius: 63px;
            border: 4px solid #c9a227; background: #ffffff; padding: 3px;
        }
        .cincin img { width: 112px; height: 112px; display: block; }

        /* CADANGAN SAAT FOTONYA MASIH PERSEGI — lihat LaporanKandidat::bulatkan.
           Pemotongan bulat butuh GD; kalau ekstensinya tidak ada, yang tertanam
           foto asli apa adanya. Menyorongkannya ke dalam cincin bulat hanya
           memamerkan sudut-sudut yang menyembul; bingkai persegi membuatnya
           terbaca sebagai pilihan, bukan kegagalan. */
        .bingkai {
            width: 112px; height: 112px; border-radius: 10px;
            border: 3px solid #c9a227; background: #ffffff; padding: 3px;
        }
        .bingkai img { width: 112px; height: 112px; display: block; }
        .kode-foto {
            display: inline-block; margin-top: 11px; padding: 6px 12px; border-radius: 50px;
            background: #fbf1d6; border: 1px solid #e0cb96; white-space: nowrap;
            font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: bold;
            letter-spacing: 1.08px; color: #a8801e;
        }
        .tanpa-foto {
            width: 178px; padding: 16px 17px; border-radius: 12px;
            background: #ffffff; border: 1px solid #e0cb96;
        }
        .tanpa-foto .kode { font-family: 'JetBrains Mono', monospace; font-size: 17px; font-weight: bold; color: #a8801e; }
        .tanpa-foto .bawah { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #ddd9cc; }
        .tanpa-foto .v { font-size: 11px; font-weight: bold; color: #14171f; }

        /* ── HALAMAN 1 — DUA KOLOM ──────────────────────────────────────── */
        /* LEBAR DUA KOLOMNYA DIPATOK PERSEN, dan induknya `fixed` juga.
           Tabel ber-`table-layout: fixed` di dalam sel yang lebarnya belum
           pasti akan melebar sesuai isi terlebar — kisi Ringkasan Lamaran
           lantas menembus tepi kanan kertas. Persen dipakai, bukan piksel,
           supaya padding sel ikut terhitung di dalam lebar kolomnya. */
        .badan { padding-top: 20px; table-layout: fixed; }
        .badan .sisi { width: 33%; padding-right: 24px; }
        .badan .isi { width: 67%; border-left: 1px solid #e9e7e0; padding-left: 24px; }

        .sisi-judul { margin-bottom: 11px; }
        .sisi-judul .t {
            width: 1%; white-space: nowrap;
            font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: bold;
            letter-spacing: 1.8px; text-transform: uppercase; color: #d4a93a;
        }
        /* Garisnya EMAS MUDA, bukan emas penuh. Rancangan memakai gradien yang
           memudar jadi tembus pandang; dompdf tidak punya gradien, dan garis
           emas pekat sepanjang itu jauh lebih berat daripada yang dimaksud —
           ia malah menarik mata lebih dulu daripada judulnya sendiri. */
        .sisi-judul .g { padding-left: 8px; }
        .sisi-judul .g span { display: block; border-top: 1px solid #e0cb96; }
        .sisi-blok { margin-bottom: 19px; }
        .sisi-baris { margin-bottom: 10px; }
        .sisi-baris .v { font-size: 10px; font-weight: normal; color: #14171f; word-wrap: break-word; }
        .sisi-baris .v-m { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; }
        .sisi-baris .v-b { font-size: 11px; font-weight: bold; }
        .sisi-baris .v-e { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: bold; color: #a8801e; }
        .sisi-baris .ket { margin-top: 2px; font-size: 9.5px; color: #6e7686; }

        /* ── KARTU IPK & JENJANG ─────────────────────────────────────────────
           PADDING DI DIV PEMBUNGKUS, bukan di tabelnya — dompdf mengabaikan
           padding sebuah <table>, dan kartunya jadi seolah tanpa jarak sama
           sekali: "3.89" menembus garis bawah dan "S1" menempel tepi kanan.

           `line-height: 1` dari rancangan juga tidak bisa dipakai apa adanya:
           di peramban kotak barisnya boleh meluap tanpa akibat, di dompdf ia
           MEMOTONG glif angka yang tingginya melebihi baris. Dinaikkan ke 1.1.

           Latarnya diberi warna krem-emas (bukan putih seperti rancangan):
           kartu putih bertepi emas tipis di atas kolom yang juga putih nyaris
           tak terbaca sebagai kartu — ia hanya terlihat seperti garis nyasar. */
        .ipk {
            padding: 12px 14px; border-radius: 10px; background: #fcf7ea;
            border: 1px solid #e0cb96; margin-bottom: 11px;
        }
        .ipk td { vertical-align: bottom; padding: 0; }
        .ipk .n { font-size: 23px; font-weight: bold; letter-spacing: -0.46px; color: #a8801e; line-height: 1.1; }
        /* 2px menyamakan garis alas "S1" dengan "3.89": dua ukuran huruf yang
           kotaknya dirapatkan di bawah tidak otomatis sebaris alasnya. */
        .ipk .j { font-size: 14px; font-weight: bold; color: #14171f; line-height: 1.1; padding-bottom: 2px; }
        .ipk .kanan { text-align: right; width: 1%; white-space: nowrap; padding-left: 10px; }

        /* Baris "label kiri · nilai kanan" di sidebar Data Pribadi. */
        .pasangan { margin-bottom: 8px; }
        .pasangan .k { font-size: 10px; color: #6e7686; }
        .pasangan .v { text-align: right; font-size: 10px; font-weight: bold; color: #14171f; word-wrap: break-word; }
        .pasangan .v-m { font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: normal; }

        /* ── JUDUL BAGIAN ───────────────────────────────────────────────── */
        .sec { margin-bottom: 10px; page-break-after: avoid; }
        .sec td { vertical-align: middle; }
        .sec .b { width: 14px; }
        .sec .b span { display: block; border-top: 3px solid #c9a227; }
        .sec .t {
            padding-left: 10px; white-space: nowrap;
            font-size: 10.5px; font-weight: bold; letter-spacing: 2.1px;
            text-transform: uppercase; color: #14171f;
        }
        .sec .g { padding: 0 10px; }
        .sec .g span { display: block; border-top: 1px solid #dedbd0; }
        .sec .meta {
            width: 1%; white-space: nowrap; text-align: right;
            font-family: 'JetBrains Mono', monospace; font-size: 8.5px;
            letter-spacing: 0.85px; color: #7c8496; text-transform: uppercase;
        }
        .blok { margin-bottom: 17px; }

        /* ── KISI ────────────────────────────────────────────────────────────
           `table-layout: fixed` WAJIB. Tanpa itu colgroup diabaikan dan dompdf
           membagi lebar menurut isi: satu nama departemen panjang melebarkan
           kolomnya sendiri sampai kolom "Tanggal Melamar" di sebelahnya
           menyempit dan labelnya patah dua baris. */
        .kisi { table-layout: fixed; }
        .kisi td {
            border: 1px solid #e4e2da; padding: 10px 14px;
            background: #ffffff; page-break-inside: avoid;
        }
        .kisi td.tint { background: #f7f6f2; }
        .kisi .v { font-size: 11.5px; font-weight: bold; color: #14171f; line-height: 1.35; word-wrap: break-word; }
        .kisi .v-n { font-weight: normal; color: #3d4351; }
        .kisi .v-m { font-family: 'JetBrains Mono', monospace; font-weight: normal; color: #14171f; }
        .kisi .v-e { color: #a8801e; }

        /* ── BARIS "SEKILAS DATA DIRI" ──────────────────────────────────── */
        .daftar { border: 1px solid #e4e2da; border-radius: 11px; }
        .daftar .brs { padding: 9px 14px; border-bottom: 1px solid #edebe4; }
        .daftar .brs-1 { background: #f7f6f2; }
        .daftar .brs-akhir { border-bottom: 0; }
        /* Label tidak boleh patah — daftarnya berisi label pendek buatan kita
           sendiri, dan yang panjang justru nilainya. `width: 1%` menyerahkan
           seluruh sisa lebar ke kolom nilai. */
        .daftar .k { font-size: 10.5px; color: #6e7686; white-space: nowrap; width: 1%; padding-right: 12px; }
        .daftar .v { text-align: right; font-size: 11.5px; font-weight: bold; color: #14171f; word-wrap: break-word; }
        .daftar .v-m { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: normal; }

        /* ── KARTU & LENCANA ────────────────────────────────────────────── */
        .kotak { padding: 11px 14px; border: 1px solid #e4e2da; border-radius: 11px; page-break-inside: avoid; }
        .kotak + .kotak { margin-top: 9px; }
        .kotak .teks { font-size: 11.5px; line-height: 1.55; color: #14171f; word-wrap: break-word; }

        .darurat {
            padding: 12px 14px; border: 1px solid #e0cb96; border-left: 3px solid #c9a227;
            border-radius: 11px; background: #fcf7ea; page-break-inside: avoid;
        }
        .darurat .nm { font-size: 13px; font-weight: bold; color: #14171f; word-wrap: break-word; }
        .darurat .hub { margin-top: 3px; font-size: 10.5px; color: #6e7686; }
        .darurat .kanan { width: 1%; text-align: right; white-space: nowrap; padding-left: 16px; }
        .darurat .hp { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: bold; color: #a8801e; }

        .pil {
            display: inline-block; padding: 4px 10px; border-radius: 5px;
            font-family: 'JetBrains Mono', monospace; font-size: 8.5px; font-weight: bold;
            letter-spacing: 0.85px; text-transform: uppercase; white-space: nowrap;
        }
        .pil-ya { background: #fbf1d6; border: 1px solid #e0cb96; color: #a8801e; }
        .pil-tidak { background: #f4efe2; border: 1px solid #e4e2da; color: #6e7686; }

        .tanya { page-break-inside: avoid; }
        .tanya + .tanya { margin-top: 8px; }
        .tanya .isi { padding: 10px 14px; border: 1px solid #e4e2da; border-radius: 10px; }
        .tanya .isi.emas { border-color: #e0cb96; background: #fcf7ea; }
        .tanya .q { font-size: 11.5px; font-weight: bold; line-height: 1.35; color: #14171f; word-wrap: break-word; }
        .tanya .a { width: 1%; white-space: nowrap; text-align: right; padding-left: 14px; }

        .centang td.sel { padding: 0 4px 8px; }
        .centang td.s1 { padding-left: 0; }
        .centang td.s2 { padding-right: 0; }
        .centang .isi {
            padding: 10px 13px; border: 1px solid #e4e2da; border-radius: 9px;
            background: #ffffff; page-break-inside: avoid;
        }
        /* KOTAK CENTANG & NOMOR: DIPUSATKAN LEWAT SEL TABEL, BUKAN line-height.
           dompdf menaruh garis teks memakai tinggi baris tetapi TIDAK memusatkan
           glifnya di dalam kotak — centang dan angka "01" melorot ke sudut
           kotak, kadang keluar sama sekali. `vertical-align: middle` pada sel
           tabel adalah satu-satunya pemusatan yang dihormatinya. */
        .centang .cek { width: 17px; }
        .centang .cek .kk { width: 17px; height: 17px; border-radius: 5px; background: #a8801e; }
        .centang .cek .kk td {
            width: 17px; height: 17px; padding: 0; text-align: center; vertical-align: middle;
            color: #ffffff; font-size: 10px; font-weight: bold;
        }
        .centang .cek .kk.mati { background: #e4e2da; }
        .centang .cek .kk.mati td { color: #7c8496; }
        .centang .teks { padding-left: 11px; font-size: 10.5px; line-height: 1.4; color: #14171f; word-wrap: break-word; }
        .centang .ya { width: 1%; white-space: nowrap; padding-left: 12px; font-family: 'JetBrains Mono', monospace; font-size: 8.5px; font-weight: bold; letter-spacing: 0.85px; color: #a8801e; }

        /* Sel ekspektasi gaji di dalam kisi — angka besar terbaca + angka mentah
           kandidat kecil di bawahnya, supaya yang membaca cepat tidak salah
           menghitung nol. */
        .kisi td.duit { background: #fbf1d6; border-color: #d5bb7c; }
        .kisi td.duit .n { font-size: 19px; font-weight: bold; letter-spacing: -0.38px; color: #a8801e; line-height: 1.15; }
        .kisi td.duit .mentah { margin-top: 3px; font-family: 'JetBrains Mono', monospace; font-size: 8.5px; color: #7c8496; }

        .kartu {
            padding: 13px 15px; border: 1px solid #e4e2da; border-left: 3px solid #c9a227;
            border-radius: 11px; background: #ffffff; page-break-inside: avoid;
        }
        .kartu + .kartu { margin-top: 9px; }
        .no { width: 32px; height: 32px; border-radius: 8px; background: #1b2333; }
        .no td {
            width: 32px; height: 32px; padding: 0; text-align: center; vertical-align: middle;
            font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: bold; color: #e7c55c;
        }
        .kartu .kol { padding-left: 15px; }
        .kartu .kol .v { font-size: 11.5px; line-height: 1.4; color: #14171f; word-wrap: break-word; }

        /* ── DOKUMEN ────────────────────────────────────────────────────── */
        .dok th {
            background: #f7f6f2; padding: 9px 14px; text-align: left;
            font-family: 'JetBrains Mono', monospace; font-size: 7.5px; font-weight: bold;
            letter-spacing: 1.2px; color: #6e7686; text-transform: uppercase;
            border: 1px solid #e4e2da;
        }
        .dok td { padding: 9px 14px; border: 1px solid #e4e2da; border-top-color: #edebe4; page-break-inside: avoid; }
        .dok .jenis { font-size: 11px; font-weight: bold; color: #14171f; word-wrap: break-word; }
        .dok .file { font-family: 'JetBrains Mono', monospace; font-size: 9.5px; line-height: 1.5; color: #6e7686; word-wrap: break-word; }
        .dok .file a { color: #a8801e; text-decoration: none; }
        .dok .st {
            width: 78px; text-align: right; white-space: nowrap;
            font-family: 'JetBrains Mono', monospace; font-size: 8px; font-weight: bold;
            letter-spacing: 0.64px; text-transform: uppercase;
        }
        .catatan-kecil { margin-top: 8px; font-family: 'JetBrains Mono', monospace; font-size: 9px; color: #7c8496; }
        .kosong { color: #7c8496; font-size: 11px; font-style: italic; padding: 8px 0; }

        .putus {
            margin-top: 11px; padding: 11px 14px; border-radius: 10px; background: #fdf2f2;
            border: 1px solid #e6b8b8; border-left: 3px solid #b91c1c;
            color: #7f1d1d; font-size: 11px; page-break-inside: avoid;
        }
        .putus b { display: block; font-size: 7.5px; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 3px; }

        .nota {
            margin-top: 17px; padding: 15px 17px; border-radius: 11px;
            background: #ffffff; border: 1px solid #e0cb96; border-left: 3px solid #c9a227;
            page-break-inside: avoid;
        }
        .nota .j {
            font-family: 'JetBrains Mono', monospace; font-size: 8.5px; font-weight: bold;
            letter-spacing: 1.7px; color: #a8801e; text-transform: uppercase; margin-bottom: 8px;
        }
        .nota .t { font-size: 10px; line-height: 1.6; color: #6e7686; }
    </style>
</head>
<body>

{{-- KOP & KAKI tidak ada di sini — digambar ke kanvas oleh KopKakiLaporan
     sesudah dokumen tersusun, sebab halaman pertama memakai kop yang berbeda
     dan nomor halaman baru diketahui setelah semuanya selesai disusun. Margin
     @page di atas sudah menyediakan ruangnya. --}}

{{-- ═══════════════════ HALAMAN 1 — PROFIL ══════════════════════════════ --}}
<table class="kepala"><tr>
    <td class="kiri">
        <div class="nama-besar pth" style="font-size: {{ $ukuranNama }}px">{{ $nama }}</div>

        <table class="posisi"><tr>
            <td class="tik"><span></span></td>
            <td class="teks pth" style="font-size: {{ $ukuranPosisi }}px">{{ $posisi }}</td>
        </tr></table>

        @if ($ada($nik))
            <table class="nik"><tr>
                <td class="k">NIK</td>
                <td class="pemisah"><span></span></td>
                <td class="v">{{ $nik }}</td>
            </tr></table>
        @endif

        <div class="chips">
            @if ($ada($L['departemen']))<span class="chip chip-gelap pth">{{ $L['departemen'] }}</span>@endif
            @if ($ada($L['level']))<span class="chip pth">{{ $L['level'] }}</span>@endif
            @if ($ada($L['lokasi']))<span class="chip pth">{{ $L['lokasi'] }}</span>@endif
            <span class="chip {{ ['l-lolos' => 'c-hijau', 'l-jalan' => 'c-hijau', 'l-gugur' => 'c-merah', 'l-talent' => 'c-ungu'][$nadaHasil] ?? 'c-netral' }}">{{ $labelHasil }}</span>
            @if ($tglLamar)<span class="lamar">Melamar {{ $tglLamar }}</span>@endif
        </div>
    </td>

    {{-- FOTO HANYA BILA ADA — sumbernya dipilih berjenjang di
         LaporanKandidat::fotoKandidat (pas foto formulir dulu, baru foto
         verifikasi), dan tiap calon dicoba dibaca sampai ada yang benar-benar
         terambil. Tanpa foto, panelnya berganti isi jadi identitas lamaran,
         bukan bingkai kosong bertuliskan "tanpa foto". --}}
    <td class="kanan">
        @if ($K['foto'])
            <div class="{{ $K['fotoBulat'] ? 'cincin' : 'bingkai' }}"><img src="{{ $K['foto'] }}" alt=""></div>
            <div style="text-align: center"><span class="kode-foto">{{ $K['kodeLamaran'] }}</span></div>
        @else
            <div class="tanpa-foto">
                <div class="lbl lbl-t">Kode Lamaran</div>
                <div class="kode">{{ $K['kodeLamaran'] }}</div>
                {{-- PROGRAM & MELAMAR DITUMPUK, TIDAK BERSEBELAHAN.
                     Rancangan menaruhnya berdampingan karena contohnya pendek
                     ("EVO SQUAD"). Nama program sungguhan bisa satu kalimat;
                     berdampingan di kartu selebar 178px, ia terpaksa membungkus
                     lima baris sementara tanggal di sebelahnya tetap satu baris
                     — kartunya jadi tinggi sebelah dan garis bawahnya terlihat
                     rusak. Ditumpuk, keduanya tetap rapi berapa pun panjangnya. --}}
                <div class="bawah">
                    <div class="lbl">Program</div>
                    <div class="v pth">{{ $L['program'] ?: '—' }}</div>
                    <div style="margin-top: 10px">
                        <div class="lbl">Melamar</div>
                        <div class="v">{{ $tglLamar ?: '—' }}</div>
                    </div>
                </div>
            </div>
        @endif
    </td>
</tr></table>

<table class="badan"><tr>
    {{-- ── SIDEBAR ────────────────────────────────────────────────────── --}}
    <td class="sisi">
        <div class="sisi-blok">
            <table class="sisi-judul"><tr>
                <td class="t">Kontak</td>
                <td class="g"><span></span></td>
            </tr></table>
            @if ($ada($K['email']))
                <div class="sisi-baris"><div class="lbl">Email</div><div class="v pth">{{ $K['email'] }}</div></div>
            @endif
            @if ($ada($K['hp']))
                <div class="sisi-baris"><div class="lbl">Telepon</div><div class="v v-m pth">{{ $K['hp'] }}</div></div>
            @endif
            @if ($ada($noWa) && $noWa !== $K['hp'])
                <div class="sisi-baris"><div class="lbl">No. WhatsApp Terdaftar</div><div class="v v-m pth">{{ $noWa }}</div></div>
            @endif
            <div class="sisi-baris"><div class="lbl">Kode Lamaran</div><div class="v v-e pth">{{ $K['kodeLamaran'] }}</div></div>
        </div>

        @php
            $adaDidik = $ada($K['ipk']) || $ada($K['jenjang']) || $ada($K['kampus'])
                || $ada($K['jurusan']) || $ada($prodi) || $ada($statusStudi);
        @endphp
        @if ($adaDidik)
            <div class="sisi-blok">
                <table class="sisi-judul"><tr>
                    <td class="t">Pendidikan</td>
                    <td class="g"><span></span></td>
                </tr></table>

                @if ($ada($K['ipk']) || $ada($K['jenjang']))
                    <div class="ipk">
                        <table><tr>
                            <td>
                                <div class="lbl lbl-t">IPK</div>
                                <div class="n">{{ $K['ipk'] ?: '—' }}</div>
                            </td>
                            <td class="kanan">
                                <div class="lbl lbl-t">Jenjang</div>
                                <div class="j">{{ $K['jenjang'] ?: '—' }}</div>
                            </td>
                        </tr></table>
                    </div>
                @endif

                @if ($ada($K['kampus']))
                    <div class="sisi-baris">
                        <div class="lbl">Institusi</div>
                        <div class="v v-b pth">{{ $K['kampus'] }}</div>
                        @if ($ada($jenisInstitusi))<div class="ket pth">Jenis institusi: {{ $jenisInstitusi }}</div>@endif
                    </div>
                @endif
                @if ($ada($K['jurusan']))
                    <div class="sisi-baris"><div class="lbl">Jurusan / Fakultas</div><div class="v pth">{{ $K['jurusan'] }}</div></div>
                @endif
                @if ($ada($prodi))
                    <div class="sisi-baris"><div class="lbl">Program Studi</div><div class="v pth">{{ $prodi }}</div></div>
                @endif
                @if ($ada($statusStudi))
                    <div class="sisi-baris"><div class="lbl">Status Kemahasiswaan</div><div class="v pth">{{ $statusStudi }}</div></div>
                @endif
                @if ($ada($K['tahunLulus']))
                    <div class="sisi-baris"><div class="lbl">Tahun Lulus</div><div class="v pth">{{ $K['tahunLulus'] }}</div></div>
                @endif
            </div>
        @endif

        @php
            $pribadi = collect([
                ['Tanggal Lahir', $K['tglLahir'], true],
                ['Jenis Kelamin', $K['jkel'], false],
                ['Agama', $agama, false],
                ['Status Pernikahan', $statusNikah, false],
            ])->filter(fn ($r) => $ada($r[1]));
        @endphp
        @if ($pribadi->count())
            <div class="sisi-blok">
                <table class="sisi-judul"><tr>
                    <td class="t">Data Pribadi</td>
                    <td class="g"><span></span></td>
                </tr></table>
                @foreach ($pribadi as $r)
                    <table class="pasangan"><tr>
                        <td class="k">{{ $r[0] }}</td>
                        <td class="v {{ $r[2] ? 'v-m' : '' }} pth">{{ $r[1] }}</td>
                    </tr></table>
                @endforeach
            </div>
        @endif
    </td>

    {{-- ── KOLOM UTAMA ────────────────────────────────────────────────── --}}
    <td class="isi">
        <div class="blok">
            <table class="sec"><tr>
                <td class="b"><span></span></td>
                <td class="t">Ringkasan Lamaran</td>
                <td class="g"><span></span></td>
            </tr></table>
            {{-- Lebar kolom DIPATOK. Tanpa colgroup, dompdf membagi lebar
                 menurut isi — satu nama departemen panjang membuat kolom
                 sebelahnya menyempit sampai labelnya sendiri patah dua baris. --}}
            <table class="kisi">
                <colgroup><col style="width:33.34%"><col style="width:33.33%"><col style="width:33.33%"></colgroup>
                <tr>
                    <td class="tint" colspan="3">
                        <div class="lbl lbl-t">Posisi Dilamar</div>
                        <div class="v v-e pth" style="font-size: 14px">{{ $posisi }}</div>
                    </td>
                </tr>
                <tr>
                    <td><div class="lbl">Program</div><div class="v pth">{{ $L['program'] ?: '—' }}</div></td>
                    <td><div class="lbl">Departemen</div><div class="v v-n pth">{{ $L['departemen'] ?: '—' }}</div></td>
                    <td><div class="lbl">Level</div><div class="v v-n pth">{{ $L['level'] ?: '—' }}</div></td>
                </tr>
                <tr>
                    <td><div class="lbl">Penempatan</div><div class="v v-n pth">{{ $L['lokasi'] ?: '—' }}</div></td>
                    <td><div class="lbl">Status Lamaran</div><div class="v" style="color: {{ $warnaStatus }}">{{ $labelHasil }}</div></td>
                    <td><div class="lbl">Tanggal Melamar</div><div class="v v-n">{{ $tglLamar ?: '—' }}</div></td>
                </tr>
            </table>

            @if ($L['status'] === 'GUGUR' && ($ada($L['gugurDi']) || $ada($L['alasanGugur'])))
                <div class="putus">
                    <b>Berhenti di tahap {{ $L['gugurDi'] ?: '—' }}</b>
                    {{ $L['alasanGugur'] ?: 'Tanpa alasan tercatat.' }}
                </div>
            @endif
        </div>

        {{-- SEKILAS DATA DIRI — identitas yang paling sering dicari, ditarik
             ke halaman muka. Isian lengkapnya tetap tercetak utuh di halaman
             formulir; ini ringkasan, bukan penggantinya. --}}
        @php
            $sekilas = collect([
                ['Nama Lengkap', $nama, false],
                ['Email Terdaftar', $K['email'], true],
                ['No. WhatsApp Terdaftar', $noWa ?: $K['hp'], true],
                ['NIK', $nik, true],
                ['Agama', $agama, false],
                ['Status Pernikahan', $statusNikah, false],
                ['Apakah data di atas sudah sesuai?', $dataSesuai, false],
            ])->filter(fn ($r) => $ada($r[1]))->values();
        @endphp
        @if ($sekilas->count())
            <div class="blok">
                <table class="sec"><tr>
                    <td class="b"><span></span></td>
                    <td class="t">Sekilas Data Diri</td>
                    <td class="g"><span></span></td>
                </tr></table>
                <div class="daftar">
                    @foreach ($sekilas as $i => $r)
                        <table class="brs {{ $i === 0 ? 'brs-1' : '' }} {{ $loop->last ? 'brs-akhir' : '' }}"><tr>
                            <td class="k">{{ $r[0] }}</td>
                            <td class="v {{ $r[2] ? 'v-m' : '' }} pth">
                                @if ($nadaJawab($r[1]) !== null && mb_strlen($r[0]) > 24)
                                    <span class="pil pil-{{ $nadaJawab($r[1]) }}">{{ $r[1] }}</span>
                                @else
                                    {{ $r[1] }}
                                @endif
                            </td>
                        </tr></table>
                    @endforeach
                </div>
            </div>
        @endif
    </td>
</tr></table>

{{-- ══════════ ISIAN FORMULIR — berbabak mengikuti bagian formulirnya ════ --}}
@foreach ($d['formulir'] as $form)
    <div style="page-break-before: always"></div>
    @php $waktu = 'Dikirim ' . \Illuminate\Support\Str::of($form['waktuKirim'])->substr(0, 16); @endphp

    @forelse ($form['bagian'] as $bagian)
        <div class="blok">
            <table class="sec"><tr>
                <td class="b"><span></span></td>
                <td class="t">{{ $bagian['judul'] ?: $form['label'] }}</td>
                <td class="g"><span></span></td>
                {{-- Label formulir dipangkas: nama formulir datang dari master
                     dan bisa satu kalimat penuh, sementara meta ini berbagi
                     satu baris dengan judul bagiannya. --}}
                @if ($loop->first)<td class="meta">{{ \Illuminate\Support\Str::limit($form['label'], 38) }} &middot; {{ $waktu }}</td>@endif
            </tr></table>

            @foreach ($susun($bagian['isian']) as $blok)
                @if ($blok['jenis'] === 'kisi')
                    <table class="kisi">
                        <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
                        @foreach ($barisKisi($blok['isi']) as $baris)
                            <tr>
                                @foreach ($baris as $j)
                                    <td class="{{ ! empty($j['duit']) ? 'duit' : '' }}"
                                        @if (($j['lebar'] ?? 1) === 2 || count($baris) === 1) colspan="2" @endif>
                                        <div class="lbl {{ ! empty($j['duit']) ? 'lbl-t' : '' }}">{{ $j['label'] }}</div>
                                        @if (! empty($j['duit']))
                                            <div class="n">{{ $rupiah($j['nilai']) }}</div>
                                            @if ($rupiah($j['nilai']) !== trim((string) $j['nilai']))
                                                <div class="mentah">Input kandidat: {{ $j['nilai'] }}</div>
                                            @endif
                                        @else
                                            <div class="v {{ $mono($j) ? 'v-m' : '' }} pth">{{ $j['nilai'] }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>

                @elseif ($blok['jenis'] === 'tanya')
                    <div class="tanya">
                        <div class="isi {{ $blok['nada'] === 'ya' ? 'emas' : '' }}">
                            <table><tr>
                                <td class="q pth">{{ $blok['isi']['label'] }}</td>
                                <td class="a"><span class="pil pil-{{ $blok['nada'] }}">{{ $blok['isi']['nilai'] }}</span></td>
                            </tr></table>
                        </div>
                    </div>

                @elseif ($blok['jenis'] === 'centang')
                    <table class="centang">
                        @foreach (array_chunk($blok['isi'], 2) as $pasang)
                            <tr>
                                @foreach ($pasang as $i => $c)
                                    <td class="sel {{ $i === 0 ? 's1' : '' }} {{ $i === count($pasang) - 1 ? 's2' : '' }}"
                                        @if (count($pasang) === 1) colspan="2" @endif>
                                        <div class="isi">
                                            <table><tr>
                                                <td class="cek">
                                                <table class="kk {{ $c['nada'] === 'ya' ? '' : 'mati' }}"><tr>
                                                    <td>{{ $c['nada'] === 'ya' ? '✓' : '×' }}</td>
                                                </tr></table>
                                            </td>
                                                <td class="teks pth">{{ $c['j']['label'] }}</td>
                                            </tr></table>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>

                @elseif ($blok['jenis'] === 'kartu')
                    @foreach ($blok['isi']['baris'] as $n => $baris)
                        <div class="kartu">
                            <table><tr>
                                <td style="width: 32px">
                                    <table class="no"><tr><td>{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }}</td></tr></table>
                                </td>
                                <td class="kol">
                                    @foreach ($baris as $i => $kol)
                                        <div @if ($i) style="margin-top: 8px" @endif>
                                            <div class="lbl">{{ $kol['label'] }}</div>
                                            {{-- Sub-isian yang berupa BERKAS
                                                 dicetak sebagai tautan yang bisa
                                                 diklik dari dalam PDF. Nama berkas
                                                 sebagai teks mati tidak berarti
                                                 apa pun bagi pembaca dokumen. --}}
                                            <div class="v pth">
                                                @if (! empty($kol['tautan']))
                                                    <a href="{{ $kol['tautan'] }}">{{ $kol['nilai'] }}</a>
                                                @else
                                                    {{ $kol['nilai'] }}
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </td>
                            </tr></table>
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>
    @empty
        {{-- Dikatakan terang-terangan. Formulir yang terkirim kosong berbeda
             dari formulir yang tidak pernah ada, dan menghilangkannya diam-diam
             membuat dokumen terbaca seolah pertanyaannya memang tak diajukan. --}}
        <table class="sec"><tr>
            <td class="b"><span></span></td>
            <td class="t">{{ $form['label'] }}</td>
            <td class="g"><span></span></td>
            <td class="meta">{{ $waktu }}</td>
        </tr></table>
        <p class="kosong">Formulir ini terkirim tanpa isian.</p>
    @endforelse
@endforeach

{{-- ══════════════ HALAMAN TERAKHIR — DOKUMEN & CATATAN ═════════════════ --}}
{{-- Halaman baru HANYA bila memang ada dokumen. Dipaksa tanpa syarat,
     kandidat yang berkasnya tidak ikut tercetak mendapat satu halaman penuh
     berisi kotak catatan sendirian — dan halaman kosong di dokumen resmi
     terbaca seperti ada yang gagal dicetak. --}}
@if ($dokumen->count())
    <div style="page-break-before: always"></div>
    <div class="blok">
        <table class="sec"><tr>
            <td class="b"><span></span></td>
            <td class="t">Dokumen Terlampir</td>
            <td class="g"><span></span></td>
            <td class="meta">{{ $dokumen->count() }} Dokumen</td>
        </tr></table>

        {{-- Tiap baris bisa diklik DARI DALAM PDF. Tautannya bertanda tangan &
             berumur (lihat LaporanKandidat::tautanBerkas): pembaca PDF tidak
             membawa cookie sesi, jadi tautan ke rute admin biasa akan selalu
             mendarat di halaman login. --}}
        {{-- TANPA KOLOM STATUS. Yang tercatat di sana adalah status VERIFIKASI
             berkas oleh tim rekrutmen ("BELUM"/"TERSEDIA") — urusan internal
             yang tidak berarti apa pun bagi pembaca biodata, dan pada berkas
             yang diteruskan ke user interview justru terbaca seolah dokumen
             kandidatnya bermasalah. --}}
        <table class="dok" style="table-layout: fixed">
            <thead><tr>
                <th style="width: 168px">Jenis Dokumen</th>
                <th>Nama File</th>
            </tr></thead>
            <tbody>
                @foreach ($dokumen as $x)
                    <tr>
                        <td class="jenis">{{ $x['label'] ?? ucwords(str_replace(['_', '-'], ' ', $x['field'])) }}</td>
                        <td class="file">
                            @if ($x['tautan'])<a href="{{ $x['tautan'] }}">{{ $x['nama'] }}</a>@else{{ $x['nama'] }}@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="catatan-kecil">Tautan dokumen di dalam berkas ini berlaku 30 hari sejak dicetak.</div>
    </div>
@endif

<div class="nota">
    <div class="j">Catatan Dokumen</div>
    <div class="t">
        Biodata kandidat EVO Group — memuat data pribadi, digunakan hanya untuk keperluan
        proses rekrutmen dan seleksi karyawan serta dilarang disebarkan di luar keperluan itu.
        Dihasilkan otomatis pada {{ $d['dicetak'] }}; tidak memerlukan tanda tangan.
    </div>
</div>

</body>
</html>
