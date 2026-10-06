{{--
    WEB CAREER — PENGALAMAN, ORGANISASI & SERTIFIKASI (rancangan halaman 04).

    Halaman ini TIDAK punya centangnya sendiri di Export Studio: isinya dipanen
    dari bagian berulang formulir yang sudah dicentang. Menawarkannya terpisah
    berarti satu sumber muncul sebagai dua pilihan, dan riwayat yang sama
    tercetak dua kali — sekali di sini, sekali di halaman formulirnya.

    Bentuk tiap blok mengikuti gaya yang dipilih admin untuk bagiannya
    (timeline / tabel / kartu) — lihat partial-berulang.

    SUMBERNYA DIKENALI DARI JUDUL BAGIAN, bukan daftar tetap. Perancang formulir
    menamai bagiannya bebas ("Riwayat Pengalaman Kerja / Magang", "Pengalaman
    Organisasi"), dan bagian yang tak dikenali tetap tercetak di halaman
    formulirnya sendiri — tidak ada yang hilang.
--}}
@php
    // Kumpulkan seluruh bagian berulang dari formulir yang tercetak, lengkap
    // dengan kunci bagiannya supaya gaya yang dipilih admin bisa dicari.
    $berulang = collect($d['formulir'] ?? [])
        ->flatMap(fn ($f) => collect($f['bagian'] ?? [])
            ->flatMap(fn ($b) => collect($b['isian'] ?? [])
                ->filter(fn ($i) => ! empty($i['baris']))
                ->map(fn ($i) => [
                    'judul' => $b['judul'] ?: $i['label'],
                    'kunci' => $b['kunci'] ?? '',
                    'isian' => $i,
                ])));

    $ada = fn ($v) => trim((string) $v) !== '';

    // ── TATA LETAK: SELA ANTAR-BLOK DIHITUNG, BUKAN DIPATOK ───────────────
    //
    // Kanvasnya setinggi 947px dan tidak menyusut. Halaman berisi dua entri
    // sertifikasi karena itu dulu mencetak dua entri di seperempat atas dan
    // menyisakan 60% kertas kosong. TataLetakHalaman menghitung sela yang
    // membagi sisa ruang itu; halaman yang isinya sudah penuh dikenali dan
    // tidak disentuh. Nilai yang ditetapkan admin di Export Studio menang
    // atas hitungan ini — lihat catatan di kelasnya.
    $blokUkur = $berulang
        ->map(fn ($b) => [
            'jenis' => (($atur ?? [])[$b['kunci']]['gaya'] ?? 'timeline'),
            'jumlah' => count($b['isian']['baris'] ?? []),
        ])
        ->prepend(['jenis' => 'judul', 'jumlah' => 1])
        ->all();

    $tata = App\Support\Career\TataLetakHalaman::hitung($blokUkur, $setelHalaman ?? []);
    $teks = App\Support\Career\TataLetakHalaman::ukuranTeks($setelHalaman ?? []);
    $geser = App\Support\Career\TataLetakHalaman::geserAtas($setelHalaman ?? [], $tata);
@endphp

{{-- ── HALAMAN SENDIRI ATAU MENUMPANG ──────────────────────────────────
     Saat `$sambung` menyala, seksi ini menumpang kanvas seksi sebelumnya:
     tidak membuka `.hal`, tidak mencetak kop & kaki (halaman induk sudah
     punya), dan tidak membuka `.isi` baru — `.isi` berposisi mutlak, jadi yang
     kedua akan bertumpuk tepat di atas yang pertama alih-alih menyambungnya.
     Yang tersisa hanya isinya, didahului garis pemisah. --}}
@if (empty($sambung))
<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => 'Data Kandidat'])

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
        <div style="padding-bottom:14px;border-bottom:1px solid #e9edf3;">
            <p style="font-size:25px;font-weight:bold;line-height:1.16;letter-spacing:-0.5px;color:#0f172a;">Pengalaman, Organisasi &amp; Sertifikasi</p>
        </div>

        @if ($berulang->isEmpty())
            <div style="margin-top:20px;padding:16px 18px;border-left:2px solid #cbd5e1;background:#f8fafc;">
                <p style="font-size:11px;line-height:1.7;color:#94a3b8;">Kandidat tidak mencantumkan riwayat pengalaman, organisasi, maupun sertifikasi.</p>
            </div>
        @endif

        @foreach ($berulang as $blok)
            @php
                $setel = ($atur ?? [])[$blok['kunci']] ?? [];
                $gayaBlok = $setel['gaya'] ?? 'timeline';
                $judulBlok = $setel['judul'] ?? $blok['judul'];
            @endphp

            <div style="margin-top:{{ $loop->first ? 18 : $tata['sela'] }}px;">
                @if ($ada($judulBlok))
                    @include('career.berkas.partial-tanda', ['label' => $judulBlok])
                @endif

                @include('career.berkas.partial-berulang', [
                    'isian' => $blok['isian'],
                    'gaya' => $gayaBlok,
                    'judulBagian' => $judulBlok,
                ])
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
