{{--
    WEB CAREER — BERKAS SELEKSI KANDIDAT (dokumen induk).

    Merangkai seluruh halaman BUATAN SISTEM. Hasil psikotes dari CAT dan
    lampiran yang diunggah TIDAK dirakit di sini: keduanya digabungkan di
    tingkat PDF oleh GabungBerkas, apa adanya — tanpa kop, kaki, bingkai,
    maupun nomor halaman.

    ── KENAPA HALAMAN PSIKOTES TIDAK DIBINGKAI ──────────────────────────────
    Rancangan menampilkannya di dalam bingkai berkop karena di sana isinya
    hanya tangkapan layar. Yang sebenarnya datang dari CAT adalah laporan PDF
    utuh yang sudah punya kop, tera air, dan bilah kerahasiaannya sendiri —
    membingkainya lagi menghasilkan dua kerangka bertumpuk.

    ── TIAP SEKSI SATU HALAMAN PENUH ────────────────────────────────────────
    Kanvasnya berukuran tetap (794×1123) dan isinya berposisi mutlak, jadi
    tidak ada aliran antar-halaman: perakit sudah memotong isi yang panjang
    menjadi beberapa seksi sebelum sampai ke sini.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berkas Seleksi Kandidat — {{ $d['kandidat']['nama'] ?? '' }}</title>
    @include('career.berkas.gaya')
</head>
<body>

{{-- ── PEMUTUS HALAMAN TIDAK DIBUNGKUS DIV ─────────────────────────────
     Dulu tiap seksi dibungkus <div class="hal-putus">. Pembungkus itu DIHAPUS
     karena menghalangi penggabungan: seksi yang menumpang halaman pendahulunya
     harus melanjutkan aliran DI DALAM `.isi` milik pendahulu, dan div terpisah
     membuatnya mustahil.

     Pemutusnya kini menempel pada `.hal` di tiap blade, lewat $putusHalaman.
     Seksi bertanda `sambung` tidak punya `.hal` sama sekali — ia hanya
     mencetak isinya. Perakit sudah memastikan muat: lihat
     RakitBerkasSeleksi::sambungHalaman(). --}}
@foreach ($seksi as $i => $s)
    @php $putusHalaman = $i > 0 && empty($s['sambung']); @endphp
        @switch ($s['jenis'])
            @case ('SAMPUL')
                @include('career.berkas.sampul', ['judulSampul' => $s['judul'] ?? null, 'putusHalaman' => $putusHalaman])
                @break

            @case ('PEMISAH')
                @include('career.berkas.pemisah', [
                    'judul' => $s['judul'],
                    'aksen' => $s['aksen'] ?? '#a37a2c',
                    'noHalaman' => $s['no'] ?? null,
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            {{-- `setelHalaman` = penyesuaian tata letak yang ditetapkan admin
                 untuk halaman INI (kerapatan, sela, ukuran teks, geser). Kosong
                 berarti sistem menghitung sendiri — lihat TataLetakHalaman. --}}
            @case ('DATA_KANDIDAT')
                @include('career.berkas.data-kandidat', [
                    'noHalaman' => $s['no'] ?? null,
                    'setelHalaman' => $s['setelHalaman'] ?? [],
                    'sambung' => ! empty($s['sambung']),
                    'tanpaRegang' => ! empty($s['tanpaRegang']),
                    'berlanjut' => ! empty($s['berlanjut']),
                    'tutupHalaman' => ! empty($s['tutupHalaman']),
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            @case ('PENGALAMAN')
                @include('career.berkas.pengalaman', [
                    'noHalaman' => $s['no'] ?? null,
                    'atur' => $s['atur'] ?? [],
                    'setelHalaman' => $s['setelHalaman'] ?? [],
                    'sambung' => ! empty($s['sambung']),
                    'tanpaRegang' => ! empty($s['tanpaRegang']),
                    'berlanjut' => ! empty($s['berlanjut']),
                    'tutupHalaman' => ! empty($s['tutupHalaman']),
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            {{-- CV — halaman yang disusun dari SELURUH formulir kandidat.
                 Menggantikan cetak-tiap-formulir; lihat SusunCv. --}}
            @case ('CV')
                @include('career.berkas.cv', [
                    // `mode` menentukan halaman dua kolom atau lajur penuh;
                    // `kiri` & `utama` hanya terisi pada mode dua kolom.
                    // Ketiganya WAJIB dioper — tanpa itu blade jatuh ke mode
                    // penuh dengan $bagian kosong, dan halamannya tercetak
                    // hanya berisi judul.
                    'mode' => $s['mode'] ?? 'penuh',
                    'kiri' => $s['kiri'] ?? [],
                    'utama' => $s['utama'] ?? [],
                    'bagian' => $s['bagian'] ?? [],
                    // Bagian lajur penuh yang menumpang di kaki halaman dua
                    // kolom. Ikut WAJIB dioper: tanpa ini perakit sudah
                    // memindahkannya ke sini, tapi blade tidak mencetaknya —
                    // bagiannya lenyap dari dokumen tanpa jejak.
                    'ekor' => $s['ekor'] ?? [],
                    'lanjutan' => $s['lanjutan'] ?? false,
                    'jumlahFormulir' => $s['jumlahFormulir'] ?? 1,
                    'judulBab' => $s['judulBab'] ?? 'Data Kandidat',
                    'noHalaman' => $s['no'] ?? null,
                    'atur' => $s['atur'] ?? [],
                    'setelHalaman' => $s['setelHalaman'] ?? [],
                    'sambung' => ! empty($s['sambung']),
                    'tanpaRegang' => ! empty($s['tanpaRegang']),
                    'berlanjut' => ! empty($s['berlanjut']),
                    'tutupHalaman' => ! empty($s['tutupHalaman']),
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            @case ('FORMULIR')
                @include('career.berkas.formulir', [
                    'form' => $s['form'],
                    'bagian' => $s['bagian'],
                    'lanjutan' => $s['lanjutan'] ?? false,
                    'noHalaman' => $s['no'] ?? null,
                    'judul' => $s['judul'] ?? null,
                    'jarakAtas' => $s['jarakAtas'] ?? 0,
                    'atur' => $s['atur'] ?? [],
                    'setelHalaman' => $s['setelHalaman'] ?? [],
                    'sambung' => ! empty($s['sambung']),
                    'tanpaRegang' => ! empty($s['tanpaRegang']),
                    'berlanjut' => ! empty($s['berlanjut']),
                    'tutupHalaman' => ! empty($s['tutupHalaman']),
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            @case ('HASIL_MANUAL')
                @include('career.berkas.hasil-manual', ['seksi' => $s, 'noHalaman' => $s['no'] ?? null, 'putusHalaman' => $putusHalaman])
                @break

            @case ('INDEKS_LAMPIRAN')
                @include('career.berkas.indeks-lampiran', [
                    'lampiran' => $s['lampiran'],
                    'noHalaman' => $s['no'] ?? null,
                    'putusHalaman' => $putusHalaman,
                ])
                @break

            @case ('PENUTUP')
                @include('career.berkas.penutup', ['putusHalaman' => $putusHalaman, 'temaPenutup' => $s['tema'] ?? 'navy'])
                @break
        @endswitch
@endforeach

</body>
</html>
