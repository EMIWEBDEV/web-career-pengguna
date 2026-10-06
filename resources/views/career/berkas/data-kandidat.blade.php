{{--
    WEB CAREER — DATA KANDIDAT (rancangan halaman 03).

    Disalin 1:1: kepala bernama besar + foto 3×4 di kanan, garis navy 2px,
    lalu dua kolom — sidebar 184px (data pribadi, kontak, kontak darurat,
    status lamaran) dan kolom kanan (ringkasan lamaran, pendidikan, alamat).

    BINGKAI FOTO MENGIKUTI BENTUK GAMBARNYA, BUKAN SEBALIKNYA.
    Rancangan memberi kotak 3×4 di kanan nama. Tapi foto verifikasi yang
    tersimpan sudah dipotong LINGKARAN oleh LaporanKandidat (rasio 1:1), dan
    menjejalkannya ke kotak 3×4 menarik lingkaran itu jadi lonjong — wajahnya
    gepeng. Karena itu bingkainya yang menyesuaikan: bulat untuk foto bulat,
    kotak 3×4 untuk foto persegi panjang. Lihat blok fotonya di bawah.

    BAGIAN YANG KOSONG TIDAK DICETAK. Rancangan menampilkan agama, status
    pernikahan, dan kontak darurat karena kandidat contohnya mengisi semua;
    formulir lain tidak menanyakannya. Judul bagian tanpa isi terbaca seperti
    data yang hilang, jadi tiap baris digerbangi keberadaannya.
--}}
@php
    $K = $d['kandidat'];
    $L = $d['lamaran'];
    // Jawaban formulir yang dipanen — NIK, agama, alamat, kontak darurat, dan
    // kesiapan kerja tidak punya kolomnya sendiri di tabel mana pun. Lihat
    // LaporanKandidat::panen().
    $P = $d['panen'] ?? [];

    $ada = fn ($v) => trim((string) $v) !== '';
    $atau = fn ($v, $c = '—') => $ada($v) ? $v : $c;

    // Foto persegi lebih dulu; bila hanya versi bulat yang tersedia ia tetap
    // dipakai — bingkainya membulat mengikuti, jadi tidak ada sudut menyembul.
    $foto = $K['fotoAsli'] ?? $K['foto'] ?? null;
    $fotoBulat = empty($K['fotoAsli']) && ! empty($K['fotoBulat']);

    $status = strtoupper((string) ($L['status'] ?? ''));
    $warnaStatus = match ($status) {
        'LULUS', 'DITERIMA' => '#0d9668',
        'GUGUR', 'DITOLAK' => '#b91c1c',
        default => '#a37a2c',
    };

    // Urutan & isinya mengikuti rancangan halaman 03.
    $pribadi = array_values(array_filter([
        ['NIK', $K['nik'] ?? $P['nik'] ?? null],
        ['Tanggal Lahir', $K['tglLahir'] ?? null],
        ['Jenis Kelamin', $K['jkel'] ?? null],
        ['Agama', $P['agama'] ?? null],
        ['Status Pernikahan', $P['pernikahan'] ?? null],
    ], fn ($r) => $ada($r[1])));

    $darurat = array_values(array_filter([
        [$P['daruratNama'] ?? 'Kontak Darurat', collect([$P['daruratHubungan'] ?? null, $P['daruratHp'] ?? null])->filter()->implode(' · ')],
    ], fn ($r) => $ada($r[1])));

    $alamat = array_values(array_filter([
        ['Alamat Lengkap (Sesuai KTP)', $P['alamatKtp'] ?? null],
        ['Alamat Domisili Saat Ini', $P['alamatDomisili'] ?? null],
    ], fn ($r) => $ada($r[1])));

    $kesiapan = $P['kesiapan'] ?? [];
    $tambahan = $P['tambahan'] ?? [];

    // Ketersediaan & ekspektasi jadi dua kartu bersanding, seperti rancangan
    // halaman 05.
    $kartu = array_values(array_filter([
        ['Ketersediaan Mulai Bekerja', $P['mulaiKerja'] ?? null],
        ['Ekspektasi Gaji', $ada($P['ekspektasiGaji'] ?? null) && is_numeric(preg_replace('/\D/', '', (string) $P['ekspektasiGaji']))
            ? 'Rp ' . number_format((float) preg_replace('/\D/', '', (string) $P['ekspektasiGaji']), 0, ',', '.')
            : ($P['ekspektasiGaji'] ?? null)],
    ], fn ($r) => $ada($r[1])));

    $kontak = array_values(array_filter([
        ['Email', $K['email'] ?? null],
        ['No. Handphone (WA)', $K['hp'] ?? null],
        ['Kode Lamaran', $K['kodeLamaran'] ?? null],
    ], fn ($r) => $ada($r[1])));

    $lamaran = array_values(array_filter([
        ['Posisi Dilamar', $L['posisi'] ?? null],
        ['Program', $L['program'] ?? null],
        ['Departemen', $L['departemen'] ?? null],
        ['Level', $L['level'] ?? null],
        ['Penempatan', $L['lokasi'] ?? null],
        ['Tanggal Melamar', $tglLamar ?? null],
    ], fn ($r) => $ada($r[1])));

    $pendidikan = array_values(array_filter([
        ['Jenjang Pendidikan', $K['jenjang'] ?? null],
        ['Jurusan / Fakultas', $K['jurusan'] ?? null],
        ['Status Kemahasiswaan', $K['statusStudi'] ?? null],
        ['Nama Kampus', $K['kampus'] ?? null],
    ], fn ($r) => $ada($r[1])));

    $adaPendidikan = $ada($K['kampus'] ?? null) || $ada($K['jurusan'] ?? null);

    // ── TATA LETAK ────────────────────────────────────────────────────────
    //
    // Halaman ini TIDAK ikut diregangkan selanya, beda dari halaman formulir
    // dan riwayat. Blok-bloknya saling bertaut — foto, identitas, dan kontak
    // berbagi baris yang sama, dan menyisipkan jarak di antaranya memisahkan
    // hal yang justru harus terbaca sebagai satu kesatuan. Isinya pun hampir
    // selalu memenuhi kanvas, jadi tidak ada ruang yang perlu diisi.
    //
    // Yang tetap ditawarkan: ukuran teks & geser turun. Keduanya tidak
    // mengubah hubungan antar-blok, hanya keterbacaan dan posisi keseluruhan.
    $teks = App\Support\Career\TataLetakHalaman::ukuranTeks($setelHalaman ?? []);
    $geser = App\Support\Career\TataLetakHalaman::geserAtas($setelHalaman ?? [], 120);
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => 'Data Kandidat'])

    {{-- Geser BOLEH negatif: menaikkan isi merapatkannya ke kop supaya seluruh
         ruang kosong berkumpul jadi satu di kaki halaman, alih-alih terbelah
         tipis di atas dan bawah. `margin-top`, bukan `padding-top` -- padding
         tidak menerima nilai negatif. --}}
    <div class="isi" style="font-size:{{ $teks['nilai'] }}px;{{ $geser != 0 ? 'margin-top:' . $geser . 'px;' : '' }}">
        {{-- ── KEPALA ───────────────────────────────────────────────────
             Garisnya DIV tersendiri di bawah tabel, bukan border-bottom pada
             tabel: dompdf menggambar border tabel hanya selebar isi selnya,
             sehingga garis navy berhenti sebelum tepi kanan bidang. --}}
        <table style="width:674px;">
            <tr>
                {{-- Lebar kolom teks dipatok agar sisa ruangnya pasti cukup
                     untuk foto — tanpa itu nama panjang mendesak foto sampai
                     menempel ke tepi. --}}
                <td style="width:{{ 674 - ($fotoBulat ? 86 : 80) - 24 }}px;padding-right:24px;vertical-align:middle;">
                    <p class="pth" style="font-size:30px;font-weight:bold;line-height:1.08;letter-spacing:-0.75px;color:#0f172a;">{{ $atau($K['nama']) }}</p>
                    <p class="pth" style="font-size:12px;font-weight:bold;line-height:1.4;color:#a37a2c;padding-top:8px;text-transform:uppercase;">
                        {{ collect([$L['posisi'] ?? null, $L['level'] ?? null])->filter()->implode(' · ') ?: '—' }}
                    </p>
                    <p class="pth" style="font-size:10px;line-height:1.5;color:#475569;padding-top:11px;">
                        {{ collect([$K['email'] ?? null, $K['hp'] ?? null, $L['lokasi'] ?? null])->filter()->implode('   ·   ') }}
                    </p>
                </td>
                <td style="width:{{ $fotoBulat ? 86 : 80 }}px;vertical-align:middle;">
                    {{-- ── BINGKAI MENGIKUTI BENTUK FOTONYA ────────────────
                         LaporanKandidat memotong foto verifikasi jadi LINGKARAN
                         (236×236, rasio 1:1). Menjejalkannya ke kotak 3×4
                         seperti rancangan menarik lingkaran itu jadi lonjong
                         dan wajahnya gepeng — persis yang terjadi sebelumnya.

                         Jadi bingkainya ikut: foto bulat → cincin 86×86 dengan
                         border-radius penuh; foto persegi panjang → kotak 3×4
                         seperti rancangan. Keduanya sama tingginya di mata,
                         sehingga baris nama tetap seimbang.

                         Bila fotonya tidak ada, kotak berlabel "Foto 3×4" tetap
                         dicetak — di sini bingkai kosong justru benar, karena ia
                         menyatakan pasfoto memang belum diserahkan. --}}
                    @if ($foto && $fotoBulat)
                        <div style="width:86px;height:86px;border-radius:43px;border:1px solid #e9edf3;background:#f1f5f9;overflow:hidden;">
                            <img src="{{ $foto }}" alt="Pasfoto" style="width:86px;height:86px;border-radius:43px;">
                        </div>
                    @elseif ($foto)
                        <div style="width:80px;height:100px;border:1px solid #e9edf3;border-radius:5px;background:#f1f5f9;overflow:hidden;">
                            <img src="{{ $foto }}" alt="Pasfoto" style="width:80px;height:100px;">
                        </div>
                    @else
                        <div style="width:80px;height:100px;border:1px solid #e9edf3;border-radius:5px;background:#f1f5f9;">
                            <p style="font-size:6.5px;font-weight:bold;line-height:1.6;letter-spacing:0.7px;text-transform:uppercase;color:#94a3b8;text-align:center;padding-top:42px;">Foto<br>3×4</p>
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        {{-- Garis navy pemisah — BORDER satu sel tabel selebar penuh.

             Selnya diberi TINGGI NYATA lewat `line-height`, bukan dinolkan:
             dompdf tidak menggambar border pada sel yang tingginya nol, jadi
             `font-size:0` justru menghapus garisnya. Spasi-keras + line-height
             2px memberi sel tinggi minimum yang cukup untuk membawa border,
             tanpa menyisakan ruang yang terlihat. --}}
        <table style="width:674px;margin-top:16px;">
            <tr><td style="border-top:2px solid #0f172a;line-height:2px;font-size:2px;">&nbsp;</td></tr>
        </table>

        {{-- ── DUA KOLOM ──────────────────────────────────────────────── --}}
        <table style="width:674px;margin-top:18px;">
            <tr>
                {{-- SIDEBAR 184px --}}
                <td style="width:184px;padding-right:20px;border-right:1px solid #f1f5f9;">
                    @if ($pribadi)
                        @include('career.berkas.partial-tanda', ['label' => 'Data Pribadi'])
                        @foreach ($pribadi as $r)
                            <div style="padding-bottom:9px;">
                                <p class="k">{{ $r[0] }}</p>
                                <p class="pth v v-kecil">{{ $r[1] }}</p>
                            </div>
                        @endforeach
                        <div class="garis-tipis" style="margin:13px 0;"></div>
                    @endif

                    @if ($kontak)
                        @include('career.berkas.partial-tanda', ['label' => 'Kontak'])
                        @foreach ($kontak as $r)
                            <div style="padding-bottom:9px;">
                                <p class="k">{{ $r[0] }}</p>
                                <p class="pth v v-kecil">{{ $r[1] }}</p>
                            </div>
                        @endforeach
                        <div class="garis-tipis" style="margin:13px 0;"></div>
                    @endif

                    @if ($darurat)
                        @include('career.berkas.partial-tanda', ['label' => 'Kontak Darurat'])
                        @foreach ($darurat as $r)
                            <div style="padding-bottom:9px;">
                                <p class="k pth">{{ $r[0] }}</p>
                                <p class="pth v v-kecil">{{ $r[1] }}</p>
                            </div>
                        @endforeach
                        <div class="garis-tipis" style="margin:13px 0;"></div>
                    @endif

                    @include('career.berkas.partial-tanda', ['label' => 'Status Lamaran'])
                    <p style="font-size:12px;font-weight:bold;line-height:1.4;letter-spacing:0.7px;color:{{ $warnaStatus }};">{{ $atau($L['status']) }}</p>
                    @if ($ada($tglLamar ?? null))
                        <p style="font-size:10px;line-height:1.55;color:#94a3b8;padding-top:6px;">Melamar {{ $tglLamar }}</p>
                    @endif

                    @if ($ada($L['gugurDi'] ?? null))
                        <div style="padding-top:9px;">
                            <p class="k">Gugur Di Tahap</p>
                            <p class="pth v v-kecil">{{ $L['gugurDi'] }}</p>
                        </div>
                    @endif

                    {{-- Daftar bercentang "Pengalaman & Aktivitas" — rancangan
                         halaman 03. Sumbernya pertanyaan ya/tidak yang dipanen;
                         yang dijawab "Ya" bercentang hijau, sisanya abu. --}}
                    @if ($tambahan)
                        <div class="garis-tipis" style="margin:13px 0;"></div>
                        @include('career.berkas.partial-tanda', ['label' => 'Pengalaman & Aktivitas'])
                        @foreach (array_slice($tambahan, 0, 5) as $r)
                            @php $ya = strtolower($r['nilai']) === 'ya'; @endphp
                            <table style="margin-bottom:7px;">
                                <tr>
                                    <td style="width:14px;">
                                        <div style="width:14px;height:14px;border-radius:4px;background:{{ $ya ? '#e3f7ef' : '#f1f5f9' }};text-align:center;font-size:8.5px;font-weight:bold;line-height:14px;color:{{ $ya ? '#0d9668' : '#cbd5e1' }};">{{ $ya ? 'v' : '-' }}</div>
                                    </td>
                                    <td class="pth" style="padding-left:8px;font-size:10px;line-height:1.4;color:#475569;">
                                        {{ \Illuminate\Support\Str::of($r['label'])->replaceMatches('/^apakah\s+/i', '')->replace('?', '')->ucfirst() }}
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    @endif
                </td>

                {{-- KOLOM KANAN --}}
                <td style="padding-left:22px;">
                    @if ($lamaran)
                        @include('career.berkas.partial-tanda', ['label' => 'Ringkasan Lamaran'])
                        <table style="padding-bottom:16px;border-bottom:1px solid #f1f5f9;">
                            @foreach (array_chunk($lamaran, 2) as $pasang)
                                <tr>
                                    @foreach ($pasang as $r)
                                        <td style="width:50%;padding:0 20px 12px 0;">
                                            <p class="k">{{ $r[0] }}</p>
                                            <p class="pth v" style="text-transform:uppercase;">{{ $r[1] }}</p>
                                        </td>
                                    @endforeach
                                    @if (count($pasang) === 1)<td style="width:50%;"></td>@endif
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if ($adaPendidikan)
                        <div style="margin-top:16px;">
                            @include('career.berkas.partial-tanda', ['label' => 'Pendidikan'])
                        </div>

                        <table>
                            <tr>
                                <td style="width:24px;padding-top:4px;">
                                    <div style="width:10px;height:10px;border-radius:5px;background:#d4a93a;"></div>
                                </td>
                                <td>
                                    {{-- LEBAR TABEL DISEBUT DALAM PIKSEL. Tanpa itu
                                         dompdf menyusutkan tabel ke lebar isinya dan
                                         "IPK 3.78" menempel pada nama kampus alih-alih
                                         rata kanan — sebab yang sama dengan kop halaman
                                         yang pernah tercetak menyambung.

                                         423 = bidang isi 674 - sidebar 184 - padding
                                         kanannya 20 - garis 1 - padding kiri kolom 22
                                         - kolom titik 24. Angka yang lebih besar
                                         mendorong isi keluar kanvas: sempat dipakai
                                         650, dan IPK jatuh sampai ke area kaki. --}}
                                    <table style="width:423px;">
                                        <tr>
                                            <td style="padding-right:14px;">
                                                <p class="pth" style="font-size:13.5px;font-weight:bold;line-height:1.35;color:#0f172a;">{{ $atau($K['kampus']) }}</p>
                                            </td>
                                            @if ($ada($K['ipk'] ?? null))
                                                <td style="width:78px;text-align:right;vertical-align:top;">
                                                    <p style="font-size:10.5px;font-weight:bold;line-height:1.4;color:#475569;white-space:nowrap;">IPK {{ $K['ipk'] }}</p>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                    <p class="pth" style="font-size:11px;line-height:1.5;color:#475569;padding-top:4px;">
                                        {{ collect([$K['jenjang'] ?? null, $K['jurusan'] ?? null])->filter()->implode(' · ') ?: '—' }}
                                    </p>
                                    <p class="pth" style="font-size:9.5px;line-height:1.5;letter-spacing:0.5px;color:#94a3b8;padding-top:4px;text-transform:uppercase;">
                                        {{ collect([$K['statusStudi'] ?? null, $K['tahunLulus'] ?? null])->filter()->implode(' · ') }}
                                    </p>
                                </td>
                            </tr>
                        </table>

                        @if ($pendidikan)
                            <table style="margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;">
                                @foreach (array_chunk($pendidikan, 2) as $pasang)
                                    <tr>
                                        @foreach ($pasang as $r)
                                            <td style="width:50%;padding:12px 20px 0 0;">
                                                <p class="k">{{ $r[0] }}</p>
                                                <p class="pth v">{{ $r[1] }}</p>
                                            </td>
                                        @endforeach
                                        @if (count($pasang) === 1)<td style="width:50%;"></td>@endif
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    @endif

                    @if ($alamat)
                        <div style="margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9;">
                            @include('career.berkas.partial-tanda', ['label' => 'Alamat'])
                            @foreach ($alamat as $r)
                                <div style="padding-bottom:11px;">
                                    <p class="k">{{ $r[0] }}</p>
                                    <p class="pth" style="font-size:11px;line-height:1.55;color:#0f172a;padding-top:4px;">{{ $r[1] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($kartu)
                        <div style="margin-top:14px;">
                            <table>
                                <tr>
                                    @foreach ($kartu as $i => $c)
                                        <td style="width:{{ 100 / count($kartu) }}%;padding-right:{{ $i === count($kartu) - 1 ? 0 : 10 }}px;">
                                            <div style="padding:13px 15px;border:1px solid #e9edf3;border-radius:8px;background:#fbfcfd;">
                                                <p class="k">{{ $c[0] }}</p>
                                                <p class="pth" style="font-size:15px;font-weight:bold;line-height:1.25;color:#0f172a;padding-top:6px;">{{ $c[1] }}</p>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </div>
                    @endif

                    {{-- Kesiapan kerja: deret pertanyaan "Bersedia …" dalam satu
                         kotak berbingkai, seperti rancangan halaman 03. --}}
                    @if ($kesiapan)
                        <div style="margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9;">
                            @include('career.berkas.partial-tanda', ['label' => 'Kesiapan Penempatan & Kerja'])
                            <div style="border:1px solid #e9edf3;border-radius:7px;">
                                <table style="width:100%;">
                                    @foreach ($kesiapan as $i => $r)
                                        @php $garis = $i < count($kesiapan) - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; @endphp
                                        <tr>
                                            <td class="pth" style="padding:10px 15px;font-size:11px;line-height:1.5;color:#475569;{{ $garis }}">{{ $r['label'] }}</td>
                                            <td style="width:52px;padding:10px 15px;text-align:right;font-size:9.5px;font-weight:bold;line-height:1;letter-spacing:0.6px;color:{{ strtolower($r['nilai']) === 'ya' ? '#10b981' : '#94a3b8' }};{{ $garis }}">{{ strtoupper($r['nilai']) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
{{-- Penutup `.isi` ikut ditunda -- lihat catatan yang sama di
     formulir.blade.php. --}}
@if (empty($berlanjut))
    </div>
@endif

{{-- Penutup ditunda bila halaman ini masih akan ditumpangi sambungan —
     lihat catatan yang sama di formulir.blade.php. --}}
@if (empty($berlanjut))
    @include('career.berkas.partial-kaki')
</div>
@endif
