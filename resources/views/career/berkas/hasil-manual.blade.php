{{--
    WEB CAREER — RINGKASAN PENILAIAN AKTIVITAS MANUAL (rancangan halaman 14–15).

    Disalin 1:1: judul 27px, baris meta bergaris bawah, kartu besar
    border-radius 14px berisi empat kotak nilai, lalu catatan asesor, dan kaki
    bertanda tangan penilai.

    ── HALAMAN INI OPSIONAL, DAN ITU DISENGAJA ───────────────────────────────
    Bawaannya FGD & wawancara hanya menyertakan BERKAS yang diunggah asesor —
    itulah dokumen resminya. Ringkasan ini memuat hal yang tidak ada di berkas
    tersebut (nilai, kategori, dan catatan yang diketik di sistem), jadi ia
    ditawarkan sebagai centang tersendiri di Export Studio: dicetak bila
    diminta, dilewati bila tidak.

    SATU BLADE UNTUK SEMUA TIPE. Yang membedakan FGD, wawancara, MCU, dan
    phone screening hanya judul dan sebutan penilainya — strukturnya identik.
    Satu blade per tipe berarti tujuh berkas yang harus disunting berbarengan
    setiap kali rancangannya bergeser, dan tipe tahap baru yang dibuat lewat
    layar tidak akan punya bladenya sama sekali.
--}}
@php
    $a = $seksi['aktivitas'];
    $aksen = $seksi['aksen'] ?? '#f59e0b';

    $ada = fn ($v) => trim((string) $v) !== '';

    $sebutan = match ((string) $a->Tipe_Tahap_Kode) {
        'INTERVIEW' => ['judul' => 'Hasil Penilaian Wawancara', 'aktor' => 'Pewawancara'],
        'TES_OFFLINE_MANUAL' => ['judul' => 'Hasil Penilaian ' . ($a->Label ?: 'Tes'), 'aktor' => 'Asesor'],
        'PHONE_SCREEN' => ['judul' => 'Hasil Phone Screening', 'aktor' => 'Perekrut'],
        'MCU' => ['judul' => 'Hasil Medical Check-Up', 'aktor' => 'Penyedia'],
        'REFERENCE_CHECK' => ['judul' => 'Hasil Reference Check', 'aktor' => 'Pemeriksa'],
        'BACKGROUND_CHECK' => ['judul' => 'Hasil Background Check', 'aktor' => 'Pemeriksa'],
        'NEGOTIATION' => ['judul' => 'Hasil Negosiasi Penawaran', 'aktor' => 'Perekrut'],
        'ADMIN_SCREENING' => ['judul' => 'Hasil Seleksi Administrasi', 'aktor' => 'Pemeriksa'],
        default => ['judul' => 'Hasil ' . ($a->Label ?: 'Penilaian'), 'aktor' => 'Penilai'],
    };

    $hasil = strtoupper((string) $a->Hasil);
    $warnaHasil = match ($hasil) {
        'LULUS' => '#0d9668',
        'GAGAL' => '#b91c1c',
        default => '#0f172a',
    };

    $nilai = $a->Nilai !== null
        ? rtrim(rtrim(number_format((float) $a->Nilai, 2, ',', '.'), '0'), ',')
        : null;

    // Catatan_Html ditulis lewat editor kaya dan sudah dibersihkan saat
    // disimpan; Catatan adalah teks polos versi lama.
    $catatan = $ada($a->Catatan_Html) ? $a->Catatan_Html : null;
    $catatanPolos = ! $catatan && $ada($a->Catatan) ? $a->Catatan : null;

    $meta = array_values(array_filter([
        ['Tahap', $seksi['induk'] ?? null],
        ['Aktivitas', $a->Label ?? null],
        ['Tanggal Pelaksanaan', $a->Jadwal_Mulai ? \Carbon\Carbon::parse($a->Jadwal_Mulai)->translatedFormat('d F Y') : null],
        ['Lokasi', $a->Jadwal_Lokasi_Nama ?? null],
    ], fn ($r) => trim((string) $r[1]) !== ''));

    $kotak = array_values(array_filter([
        $nilai !== null ? ['Nilai Akhir', $nilai, '#0f172a'] : null,
        $ada($a->Nilai_Teks) ? ['Kategori', $a->Nilai_Teks, '#0d9668'] : null,
        $ada($a->Mcu_Status) ? ['Status MCU', $a->Mcu_Status, '#0f172a'] : null,
        $ada($a->Hasil) ? ['Rekomendasi', $a->Hasil, $warnaHasil] : null,
    ]));
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => 'Hasil Seleksi · ' . ($a->Label ?: 'Penilaian')])

    <div class="isi">
        <p style="font-size:27px;font-weight:bold;line-height:1.16;letter-spacing:-0.55px;color:#0f172a;">{{ $sebutan['judul'] }}</p>

        @if ($meta)
            <table style="margin-top:16px;padding-bottom:18px;border-bottom:1px solid #f1f5f9;">
                <tr>
                    @foreach ($meta as $r)
                        <td style="padding:0 26px 18px 0;">
                            <p style="font-size:8px;font-weight:bold;line-height:1;letter-spacing:1.3px;text-transform:uppercase;color:#94a3b8;">{{ $r[0] }}</p>
                            <p class="pth" style="font-size:12px;line-height:1.5;color:#0f172a;padding-top:5px;">{{ $r[1] }}</p>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        <div style="margin-top:22px;padding:26px 28px;border:1px solid #e2e8f0;border-radius:14px;">
            @if ($kotak)
                <table style="margin-bottom:20px;">
                    <tr>
                        @foreach ($kotak as $i => $c)
                            <td style="width:{{ 100 / count($kotak) }}%;padding-right:{{ $i === count($kotak) - 1 ? 0 : 10 }}px;">
                                <div style="padding:13px 15px;border:1px solid #e9edf3;border-radius:10px;background:#fbfcfd;">
                                    <p style="font-size:7px;font-weight:bold;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">{{ $c[0] }}</p>
                                    <p class="pth" style="font-size:17px;font-weight:bold;line-height:1.15;color:{{ $c[2] }};padding-top:8px;">{{ $c[1] }}</p>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endif

            @if ($catatan || $catatanPolos)
                <p style="font-size:9px;font-weight:bold;line-height:1;letter-spacing:1.8px;text-transform:uppercase;color:{{ $aksen }};padding-bottom:10px;">Ringkasan Penilaian</p>
                <div class="isi-kaya pth">
                    @if ($catatan)
                        {!! $catatan !!}
                    @else
                        {!! nl2br(e($catatanPolos)) !!}
                    @endif
                </div>
            @else
                <p style="font-size:11.5px;line-height:1.7;color:#94a3b8;">
                    Penilai tidak mencantumkan catatan tertulis untuk aktivitas ini.
                    @if (! empty($seksi['berkas']))
                        Berkas penilaian yang diunggah disertakan pada halaman berikutnya.
                    @endif
                </p>
            @endif
        </div>

        {{-- Kaki bidang isi: keterangan + nama penilai. Ditempatkan dengan
             `top`, bukan `bottom`: dompdf tidak menghitung `bottom` untuk
             elemen mutlak di dalam kotak ber-overflow. --}}
        <table style="position:absolute;left:0;top:880px;width:674px;padding-top:14px;border-top:1px solid #f1f5f9;">
            <tr>
                <td style="width:62%;padding-top:14px;font-size:10.5px;line-height:1.6;color:#94a3b8;">
                    Catatan ditulis langsung oleh {{ strtolower($sebutan['aktor']) }} pada sistem rekrutmen EVO Group dan ditampilkan apa adanya.
                </td>
                <td style="width:38%;padding-top:14px;text-align:right;">
                    <p style="font-size:8px;font-weight:bold;line-height:1;letter-spacing:1.3px;text-transform:uppercase;color:#94a3b8;">{{ $sebutan['aktor'] }}</p>
                    <p class="pth" style="font-size:11.5px;font-weight:bold;line-height:1.5;color:#0f172a;padding-top:6px;">{{ $a->Lanjut_By ?: ($a->Updated_By ?: '—') }}</p>
                </td>
            </tr>
        </table>
    </div>

    @include('career.berkas.partial-kaki')
</div>

<style>
    /* Isi dari editor kaya — ukurannya dipaksa supaya satu catatan bergaya
       bebas tidak mengubah rupa seluruh halaman. */
    .isi-kaya { font-size: 12.5px; line-height: 1.75; color: #334155; }
    .isi-kaya p { margin: 0 0 10px; }
    .isi-kaya ul, .isi-kaya ol { margin: 0 0 14px; padding-left: 20px; }
    .isi-kaya li { margin-bottom: 7px; padding-left: 4px; }
    .isi-kaya strong, .isi-kaya b { font-weight: bold; color: #0f172a; }
    .isi-kaya h1, .isi-kaya h2, .isi-kaya h3 { font-size: 13px; margin: 12px 0 7px; color: #0f172a; }
    /* Gambar sisipan dibatasi lebar kolom — foto ponsel bisa 4000px. */
    .isi-kaya img { max-width: 100%; }
</style>
