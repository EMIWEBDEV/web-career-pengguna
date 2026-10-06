{{--
    WEB CAREER — HALAMAN CV KANDIDAT.

    Menggantikan halaman "cetak tiap formulir apa adanya". Isinya disusun
    SusunCv dari SELURUH formulir yang diisi kandidat — satu untuk Rekrutmen,
    dua untuk MT — dan tiap fakta hanya muncul sekali.

    ── DUA MODE HALAMAN ─────────────────────────────────────────────────────
      dua-kolom  Kolom kiri 206px untuk fakta ringkas yang disapu cepat
                 (pendidikan, kemampuan, keluarga); kolom utama 404px untuk
                 riwayat yang benar-benar dinilai (pengalaman, organisasi,
                 sertifikasi). Itu cara CV dibaca: mata menyapu kiri untuk
                 verifikasi, lalu berhenti di kanan untuk menilai.
      penuh      Selebar 674px — deret pernyataan dan daftar dokumen. Satu
                 pertanyaan satu baris, dibaca dengan menyapu kolom jawaban;
                 dipaksa ke kolom sempit, pertanyaannya patah dan kolom
                 jawabannya hilang.

    Perakit yang menentukan modenya — lihat RakitBerkasSeleksi::potongCv().

    ── YANG TIDAK DIPUTUSKAN DI SINI ────────────────────────────────────────
    Bagian mana yang tampil dan isian mana yang dicetak: keduanya sudah
    disaring perakit mengikuti centang admin.
--}}
@php
    $modeCv = $mode ?? 'penuh';

    // Tinggi halaman dua kolom ditentukan lajur TERTINGGI, bukan jumlah
    // keduanya — keduanya berdiri berdampingan.
    $ukur = function ($daftar, $lebar) {
        return collect($daftar)->map(function ($b) use ($lebar) {
            if ($b['baris']) {
                return ['jenis' => 'timeline', 'jumlah' => max(1, collect($b['baris'])
                    ->sum(fn ($r) => count($r['isian']['baris'] ?? [])))];
            }

            // Kolom sempit mencetak SATU isian per baris, kolom lebar dua.
            // Membaginya dua di mana-mana membuat kolom kiri dikira separuh
            // tingginya — lihat $sempit di partial-cv-bagian.blade.php.
            $perBaris = $lebar < 300 ? 1 : 2;

            return ['jenis' => 'baris', 'jumlah' => max(1, (int) ceil(count($b['isian']) / $perBaris)) + 1];
        })->all();
    };

    // ── KOLOM MANA YANG MENENTUKAN TINGGI ────────────────────────────────
    //
    // Yang dipakai adalah kolom TERTINGGI, dan tingginya diukur dengan
    // penaksir yang sama dengan yang dipakai perakit memotong halaman
    // (RakitBerkasSeleksi::taksirBagianCv) — bukan dengan menghitung jumlah
    // bagiannya.
    //
    // Ini pernah salah: dulu yang dipilih kolom dengan bagian TERBANYAK.
    // Kolom kiri berisi empat bagian pendek terbaca "lebih tinggi" daripada
    // kolom utama berisi dua riwayat panjang, jadi tinggi halaman ditaksir
    // dari kolom yang keliru — dan halaman menyisakan 276px kosong di kaki.
    if ($modeCv === 'dua-kolom') {
        $R = App\Support\Career\RakitBerkasSeleksi::class;

        $tinggiKiri = collect($kiri ?? [])
            ->sum(fn ($b) => $R::taksirBagianCv($b, $R::LEBAR_KIRI));
        $tinggiUtama = collect($utama ?? [])
            ->sum(fn ($b) => $R::taksirBagianCv($b, $R::LEBAR_UTAMA));

        $blokUkur = $tinggiUtama >= $tinggiKiri
            ? $ukur($utama ?? [], $R::LEBAR_UTAMA)
            : $ukur($kiri ?? [], $R::LEBAR_KIRI);

        // Ekor lajur penuh berdiri DI BAWAH kedua kolom, jadi tingginya
        // menambah — bukan bersaing. Kalau tidak ikut dihitung, mesin tata
        // letak mengira halaman masih longgar lalu meregangkan selanya, dan
        // barisan terakhir terdorong keluar kanvas.
        $blokUkur = array_merge(
            $blokUkur,
            $ukur($ekor ?? [], App\Support\Career\SusunCv::LEBAR_LAJUR['penuh']),
        );
    } else {
        $blokUkur = $ukur($bagian ?? [], App\Support\Career\SusunCv::LEBAR_LAJUR['penuh']);
    }

    // Judul badan halaman sudah dihapus — tidak ada blok judul untuk diukur.

    // `lanjutan` menahan pemusatan vertikal — halaman sambungan mulai dari
    // atas. Harus sepakat dengan RakitBerkasSeleksi::tataHalaman(), yang
    // menghitung angka yang sama untuk panel kanan.
    $tata = App\Support\Career\TataLetakHalaman::hitung(
        $blokUkur,
        // Halaman CV tidak pernah dipusatkan — lihat catatan di
        // RakitBerkasSeleksi::tataHalaman().
        ($setelHalaman ?? []) + ['lanjutan' => true],
    );
    $teks = App\Support\Career\TataLetakHalaman::ukuranTeks($setelHalaman ?? []);
    $geser = App\Support\Career\TataLetakHalaman::geserAtas($setelHalaman ?? [], $tata);

    $L = App\Support\Career\SusunCv::LEBAR_LAJUR;
@endphp

@if (empty($sambung))
<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => $judulBab ?? 'Data Kandidat'])

    <div class="isi" style="font-size:{{ $teks['nilai'] }}px;{{ $geser != 0 ? 'margin-top:' . $geser . 'px;' : '' }}">
@else
    <div style="margin-top:{{ App\Support\Career\TataLetakHalaman::SELA_SAMBUNG }}px;padding-top:{{ App\Support\Career\TataLetakHalaman::SELA_SAMBUNG - 14 }}px;border-top:1px solid #e9edf3;">
@endif

        {{-- ── TANPA JUDUL BADAN HALAMAN ─────────────────────────────────
             Kop atas sudah menyebut bab-nya ("DATA KANDIDAT") dan halaman
             sebelumnya sudah memuat nama kandidat besar-besar. Judul ketiga
             di badan halaman — plus baris "disusun dari N formulir" — hanya
             mengulang keduanya, dan mendorong isi turun ~76px sehingga jarak
             kop ke isi menganga.

             Halaman ini langsung mulai dengan isinya. --}}

        @if ($modeCv === 'dua-kolom')
            {{-- Lebar kedua kolom & selokannya disebut piksel: dompdf tidak
                 menghormati persen pada tabel berposisi mutlak. --}}
            <table style="width:674px;margin-top:4px;">
                <tr>
                    {{-- Padding & garis ADA DI LUAR `width` menurut dompdf, jadi
                         lebar sel = isi + padding + border. Totalnya:
                         206+16+1 (kiri) + 404+15 (kanan) = 642, muat di 674.
                         Angkanya dari SusunCv::LEBAR_LAJUR — jangan ditulis
                         ulang di sini, lihat catatan pada konstanta itu. --}}
                    <td style="width:{{ $L['kiri'] }}px;padding-right:16px;border-right:1px solid #f1f5f9;vertical-align:top;">
                        @foreach ($kiri ?? [] as $b)
                            <div style="margin-top:{{ $loop->first ? 0 : $tata['sela'] }}px;">
                                @include('career.berkas.partial-cv-bagian', [
                                    'b' => $b,
                                    'lebar' => $L['kiri'],
                                    'atur' => $atur ?? [],
                                ])
                            </div>
                        @endforeach
                    </td>

                    <td style="width:{{ $L['utama'] }}px;padding-left:15px;vertical-align:top;">
                        @foreach ($utama ?? [] as $b)
                            <div style="margin-top:{{ $loop->first ? 0 : $tata['sela'] }}px;">
                                @include('career.berkas.partial-cv-bagian', [
                                    'b' => $b,
                                    'lebar' => $L['utama'],
                                    'atur' => $atur ?? [],
                                ])
                            </div>
                        @endforeach
                    </td>
                </tr>
            </table>

            {{-- ── EKOR LAJUR PENUH ─────────────────────────────────────────
                 Bagian lajur penuh yang masih muat di kaki halaman dua kolom
                 ini. Tanpanya, halaman dua kolom yang isinya pendek berhenti
                 di tengah kertas sementara halaman berikutnya hanya memuat
                 satu bagian — dua halaman setengah kosong berturut-turut.
                 Perakit yang memutuskan mana yang ikut; lihat potongCv(). --}}
            @foreach ($ekor ?? [] as $b)
                @php $jarakBagian = (int) ((($atur ?? [])[$b['kunci']] ?? [])['jarakAtas'] ?? 0); @endphp

                <div style="margin-top:{{ $tata['sela'] + $jarakBagian }}px;">
                    @include('career.berkas.partial-cv-bagian', [
                        'b' => $b,
                        'lebar' => $L['penuh'],
                        'atur' => $atur ?? [],
                    ])
                </div>
            @endforeach

        @else
            @foreach ($bagian ?? [] as $b)
                @php $jarakBagian = (int) ((($atur ?? [])[$b['kunci']] ?? [])['jarakAtas'] ?? 0); @endphp

                <div style="margin-top:{{ ($loop->first && empty($lanjutan) ? 16 : $tata['sela']) + $jarakBagian }}px;">
                    @include('career.berkas.partial-cv-bagian', [
                        'b' => $b,
                        'lebar' => $L['penuh'],
                        'atur' => $atur ?? [],
                    ])
                </div>
            @endforeach
        @endif

@if ((empty($sambung) && empty($berlanjut)) || ! empty($tutupHalaman))
    </div>
@endif

@if ((empty($sambung) && empty($berlanjut)) || ! empty($tutupHalaman))
    @include('career.berkas.partial-kaki')
</div>
@endif
