{{--
    WEB CAREER — ISIAN FORMULIR KANDIDAT (rancangan halaman 04–05).

    Mengikuti gaya "Kesiapan Kerja & Informasi Tambahan": penanda bagian emas,
    kisi dua kolom untuk isian pendek, tabel berbingkai membulat untuk deret
    pertanyaan ya/tidak, dan kotak berbatang kiri untuk isian panjang.

    ── KELENGKAPAN DOKUMEN TIDAK DICETAK DI SINI ────────────────────────────
    Isian bertipe BERKAS dibuang dari halaman formulir: daftar dokumennya sudah
    punya tempat sendiri di bab Lampiran, lengkap dengan status dan jumlah
    halamannya. Mencetaknya dua kali — sekali sebagai lencana "ADA" di tengah
    jawaban, sekali lagi sebagai indeks — hanya membuat pembaca mengira ada dua
    daftar berbeda yang harus dicocokkan.

    ── PEMBABAKAN DARI SKEMA, BUKAN DARI DAFTAR DI SINI ─────────────────────
    Judul bagian datang dari skema yang DIBEKUKAN saat kandidat mengirim —
    bukan skema yang berlaku sekarang, karena pertanyaan bisa diganti setelah
    dijawab. Bagian yang tidak dicentang admin sudah disaring di perakit.

    ── SATU BAGIAN BISA MELUAP KE HALAMAN BERIKUTNYA ────────────────────────
    Kanvas rancangan bertinggi tetap, sedangkan jumlah pertanyaan tidak bisa
    ditebak. Perakit sudah memotong isian menjadi beberapa halaman; blade ini
    mencetak satu potongan saja dan menerima $lanjutan untuk menandai halaman
    sambungan.
--}}
@php
    $ada = fn ($v) => trim((string) $v) !== '';

    $yaTidak = function ($v) {
        $t = strtolower(trim((string) $v));

        return in_array($t, ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju', 'tidak', 't', 'false', '0', 'belum'], true);
    };
    $bernilaiYa = function ($v) {
        $t = strtolower(trim((string) $v));

        return in_array($t, ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju'], true);
    };
    $pendek = fn ($v) => mb_strlen(trim((string) $v)) <= 60;

    // ── TATA LETAK HALAMAN ────────────────────────────────────────────────
    //
    // Halaman formulir paling sering setengah kosong: satu bagian berisi tiga
    // pertanyaan mengisi seperempat kanvas, sisanya putih. Sela antar-bagian
    // karena itu dihitung dari isi yang benar-benar ada, bukan dipatok 20px.
    //
    // Blok diukur per BAGIAN, dengan `jumlah` = banyak isiannya: bagian berisi
    // sepuluh pertanyaan jelas setinggi sepuluh baris, bukan satu.
    $blokUkur = collect($bagian)
        ->map(fn ($b) => [
            'jenis' => 'baris',
            'jumlah' => max(1, (int) ceil(count($b['isian'] ?? []) / 2)) + 1,
        ])
        ->all();

    if (empty($lanjutan)) {
        array_unshift($blokUkur, ['jenis' => 'judul', 'jumlah' => 1]);
    }

    $tata = App\Support\Career\TataLetakHalaman::hitung($blokUkur, $setelHalaman ?? []);
    $teks = App\Support\Career\TataLetakHalaman::ukuranTeks($setelHalaman ?? []);
    $geser = App\Support\Career\TataLetakHalaman::geserAtas($setelHalaman ?? [], $tata);
@endphp

{{-- ── HALAMAN SENDIRI ATAU MENUMPANG ──────────────────────────────────
     Saat `$sambung` menyala, formulir ini menumpang kanvas seksi sebelumnya:
     tidak membuka `.hal`, tidak mencetak kop & kaki, dan tidak membuka `.isi`
     baru — `.isi` berposisi mutlak, jadi yang kedua bertumpuk tepat di atas
     yang pertama alih-alih menyambungnya. Lihat
     RakitBerkasSeleksi::sambungHalaman(). --}}
@if (empty($sambung))
<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => $form['label']])

    {{-- Geser BOLEH negatif: menaikkan isi merapatkannya ke kop supaya seluruh
         ruang kosong berkumpul jadi satu di kaki halaman, alih-alih terbelah
         tipis di atas dan bawah. `margin-top`, bukan `padding-top` -- padding
         tidak menerima nilai negatif. --}}
    <div class="isi" style="font-size:{{ $teks['nilai'] }}px;{{ $geser != 0 ? 'margin-top:' . $geser . 'px;' : '' }}">
@else
    {{-- Pemisah antar-seksi dalam satu halaman: garis tipis + jarak, supaya
         keduanya tetap terbaca sebagai dua bagian dokumen yang berbeda. --}}
    <div style="margin-top:{{ App\Support\Career\TataLetakHalaman::SELA_SAMBUNG }}px;padding-top:{{ App\Support\Career\TataLetakHalaman::SELA_SAMBUNG - 14 }}px;border-top:1px solid #e9edf3;">
@endif
        @if (empty($lanjutan))
            <div style="padding-bottom:14px;border-bottom:1px solid #e9edf3;{{ ($jarakAtas ?? 0) > 0 ? 'padding-top:' . (int) $jarakAtas . 'px;' : '' }}">
                {{-- Judul bisa ditulis ulang admin lewat Export Studio; label
                     bawaan datang dari master alur yang tidak selalu enak
                     dibaca di dokumen resmi. --}}
                <p class="pth" style="font-size:25px;font-weight:bold;line-height:1.16;letter-spacing:-0.5px;color:#0f172a;">{{ $judul ?? $form['label'] }}</p>
                <p style="font-size:9px;line-height:1;letter-spacing:0.9px;text-transform:uppercase;color:#94a3b8;padding-top:6px;">
                    {{-- Nama TAHAP jadi keterangan kecil, bukan judul: judul
                         besarnya adalah nama formulir yang dilihat kandidat.
                         "Formulir ini dikirim pada tahap apa" tetap berguna
                         untuk menelusuri berkas, tapi bukan identitasnya. --}}
                    Dikirim {{ $form['waktuKirim'] ? \Carbon\Carbon::parse($form['waktuKirim'])->translatedFormat('d F Y · H:i') : '—' }}@if (! empty($form['tahap'])) &nbsp;·&nbsp; Tahap {{ $form['tahap'] }}@endif
                </p>
            </div>
        @endif

        @foreach ($bagian as $b)
            @php
                // Isian berkas IKUT bila admin mencentangnya — dicetak sebagai
                // NAMA BERKAS saja (satu baris teks), bukan dokumennya. Berkas
                // aslinya tetap hanya ada di bab Lampiran; halaman formulir
                // cuma menyatakan bahwa ia diserahkan.
                $isi = collect($b['isian'] ?? [])
                    ->filter(fn ($f) => $ada($f['nilai']) || ! empty($f['baris']) || ! empty($f['berkas']));

                // Penyesuaian yang dikirim Export Studio untuk bagian ini.
                $setel = ($atur ?? [])[$b['kunci'] ?? ''] ?? [];
                $gayaBagian = $setel['gaya'] ?? 'timeline';
                $judulBagian = $setel['judul'] ?? ($b['judul'] ?? '');
                $jarakBagian = (int) ($setel['jarakAtas'] ?? 0);
            @endphp

            @continue ($isi->isEmpty())

            <div style="margin-top:{{ ($loop->first && empty($lanjutan) ? 18 : $tata['sela']) + $jarakBagian }}px;">
                @if ($ada($judulBagian))
                    @include('career.berkas.partial-tanda', ['label' => $judulBagian])
                @endif

                @php
                    $berulang = $isi->filter(fn ($f) => ! empty($f['baris']));
                    $datar = $isi->filter(fn ($f) => empty($f['baris']));

                    // Isian berkas dicetak sebagai NAMA BERKAS — satu baris
                    // teks di kisi dua kolom, sama seperti isian pendek lain.
                    $datar = $datar->map(function ($f) {
                        if (! empty($f['berkas'])) {
                            $f['nilai'] = $f['berkas']['nama'] ?? ($f['nilai'] ?: '—');
                        }

                        return $f;
                    });

                    // Deret pertanyaan ya/tidak dikumpulkan jadi satu tabel
                    // berbingkai seperti "Kesiapan Penempatan & Kerja".
                    $biner = $datar->filter(fn ($f) => empty($f['berkas']) && $yaTidak($f['nilai']))->values();
                    $panjang = $datar->filter(fn ($f) => empty($f['berkas']) && ! $yaTidak($f['nilai']) && ! $pendek($f['nilai']))->values();
                    $ringkas = $datar->filter(fn ($f) => ! empty($f['berkas']) || (! $yaTidak($f['nilai']) && $pendek($f['nilai'])))->values();
                @endphp

                {{-- ── BENTUK ISIAN PENDEK BISA DIPILIH ───────────────────
                     Bawaannya KISI dua kolom — label kecil di atas nilainya,
                     tanpa bingkai. Bagian seperti "Kesiapan Penempatan &
                     Kerja" yang isinya cuma dua angka penting terbaca lebih
                     baik sebagai KARTU: bingkai membuat angkanya berdiri
                     sebagai fakta, bukan tenggelam di antara isian lain.

                     Dipilih admin lewat panel kanan Export Studio
                     (`atur.<kunci bagian>.bentuk`), bukan ditebak dari jumlah
                     isian: "dua isian" tidak selalu berarti dua fakta penting,
                     dan menebaknya membuat dokumen berubah bentuk sendiri
                     ketika kandidat kebetulan mengosongkan satu jawaban. --}}
                @php $bentukRingkas = $setel['bentuk'] ?? 'kisi'; @endphp

                @if ($ringkas->isNotEmpty() && $bentukRingkas === 'kartu')
                    <table style="width:100%;">
                        @foreach ($ringkas->chunk(2) as $pasang)
                            <tr>
                                @foreach ($pasang as $i => $f)
                                    <td style="width:50%;padding:0 {{ $i === 0 && $pasang->count() > 1 ? 10 : 0 }}px 10px 0;vertical-align:top;">
                                        <div style="padding:13px 15px;border:1px solid #e9edf3;border-radius:8px;background:#fbfcfd;">
                                            <p class="k pth">{{ $f['label'] }}</p>
                                            <p class="pth" style="font-size:15px;font-weight:bold;line-height:1.25;color:#0f172a;padding-top:6px;">{{ $f['nilai'] ?: '—' }}</p>
                                        </div>
                                    </td>
                                @endforeach
                                @if ($pasang->count() === 1)<td style="width:50%;"></td>@endif
                            </tr>
                        @endforeach
                    </table>

                @elseif ($ringkas->isNotEmpty() && $bentukRingkas === 'baris')
                    {{-- Satu isian satu baris berbingkai — untuk bagian yang
                         labelnya panjang dan tidak muat di kisi dua kolom. --}}
                    <div style="border:1px solid #e9edf3;border-radius:7px;">
                        <table style="width:100%;">
                            @foreach ($ringkas as $i => $f)
                                @php $garis = $i < $ringkas->count() - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; @endphp
                                <tr>
                                    <td class="pth" style="width:45%;padding:10px 15px;font-size:10px;line-height:1.5;color:#94a3b8;{{ $garis }}">{{ $f['label'] }}</td>
                                    <td class="pth" style="padding:10px 15px;font-size:11px;font-weight:bold;line-height:1.5;color:#0f172a;{{ $garis }}">{{ $f['nilai'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>

                @elseif ($ringkas->isNotEmpty())
                    <table>
                        @foreach ($ringkas->chunk(2) as $pasang)
                            <tr>
                                @foreach ($pasang as $f)
                                    <td style="width:50%;padding:0 20px 12px 0;">
                                        <p class="k pth">{{ $f['label'] }}</p>
                                        <p class="pth v">{{ $f['nilai'] ?: '—' }}</p>
                                    </td>
                                @endforeach
                                {{-- Sel penyeimbang: baris ganjil tanpa ini
                                     membuat kolom terakhir melebar dua kali. --}}
                                @if ($pasang->count() === 1)<td style="width:50%;"></td>@endif
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if ($biner->isNotEmpty())
                    <div style="border:1px solid #e9edf3;border-radius:7px;margin-top:{{ $ringkas->isNotEmpty() ? 12 : 0 }}px;">
                        <table style="width:100%;">
                            @foreach ($biner as $i => $f)
                                <tr>
                                    <td class="pth" style="padding:10px 15px;font-size:11px;line-height:1.5;color:#475569;{{ $i < $biner->count() - 1 ? 'border-bottom:1px solid #f1f5f9;' : '' }}">{{ $f['label'] }}</td>
                                    <td style="width:60px;padding:10px 15px;text-align:right;font-size:9.5px;font-weight:bold;line-height:1;letter-spacing:0.6px;color:{{ $bernilaiYa($f['nilai']) ? '#10b981' : '#94a3b8' }};{{ $i < $biner->count() - 1 ? 'border-bottom:1px solid #f1f5f9;' : '' }}">{{ strtoupper($f['nilai']) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                @foreach ($panjang as $f)
                    <div style="margin-top:12px;">
                        <p class="k pth">{{ $f['label'] }}</p>
                        <div class="pth" style="margin-top:5px;padding:14px 18px;border-left:2px solid #d4a93a;background:#f8fafc;font-size:11px;line-height:1.7;color:#475569;">{{ $f['nilai'] }}</div>
                    </div>
                @endforeach

                {{-- Bagian berulang — bentuknya dipilih admin di Export Studio:
                     timeline (bawaan untuk riwayat bertanggal), tabel, atau
                     kartu. Lihat partial-berulang. --}}
                @foreach ($berulang as $f)
                    @include('career.berkas.partial-berulang', [
                        'isian' => $f,
                        'gaya' => $gayaBagian,
                    ])
                @endforeach
            </div>
        @endforeach
{{-- Penutup `.isi` ikut ditunda: `.isi` berposisi MUTLAK, jadi sambungan
     yang diletakkan sesudahnya jatuh di luar aliran dan menimpa isi induk
     alih-alih menyambungnya. Yang menutupnya adalah pihak yang sama dengan
     penutup kanvas di bawah. --}}
@if ((empty($sambung) && empty($berlanjut)) || ! empty($tutupHalaman))
    </div>
@endif

{{-- ── SIAPA YANG MENUTUP KANVAS ───────────────────────────────────────
     Halaman induk yang masih akan ditumpangi (`$berlanjut`) TIDAK menutup:
     kaki dan </div>-nya ditunda sampai sambungan terakhir selesai. Tanpa
     penundaan itu induk menutup dirinya lebih dulu, sambungan jatuh di LUAR
     kanvas, dan dompdf menerbitkannya sebagai halaman baru tanpa kop.

     Yang menutup adalah: halaman biasa (bukan sambungan, tidak berlanjut),
     atau sambungan TERAKHIR di halaman itu (`$tutupHalaman`). --}}
@if ((empty($sambung) && empty($berlanjut)) || ! empty($tutupHalaman))
    @include('career.berkas.partial-kaki')
</div>
@endif
