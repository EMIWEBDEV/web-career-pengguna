{{--
    WEB CAREER — BAGIAN BERULANG dalam tiga bentuk.

    Riwayat kerja, organisasi, dan sertifikasi semuanya tersimpan sebagai
    array-of-objek di jawaban formulir. Bentuk cetaknya dipilih admin lewat
    panel kanan Export Studio; bawaannya timeline untuk bagian bertanggal.

    ── KENAPA TIGA, BUKAN SATU ────────────────────────────────────────────
      timeline  riwayat berurutan waktu — titik emas + garis penghubung, dan
                periodenya jadi penanda di kanan. Bentuk yang paling terbaca
                untuk pengalaman kerja & pendidikan.
      tabel     daftar panjang tanpa uraian — mis. daftar kenalan di
                perusahaan. Timeline untuk daftar semacam itu hanya
                menyisakan titik-titik yang tidak menerangkan apa pun.
      kartu     entri yang kolomnya berbeda-beda antar baris. Tabel berkolom
                tetap akan penuh sel kosong; kartu menampung apa adanya.

    @param  array   $isian  satu isian berulang: {label, baris[][]}
    @param  string  $gaya   timeline | tabel | kartu
--}}
@php
    // Lebar bidang tempat daftar ini digambar. Halaman CV mengopernya
    // (kolom 238 / 404px); halaman lain memakai bidang isi penuh 674px.
    // Tanpa ini timeline dipatok 642px dan meluber keluar kolom.
    $lebarBidang = (int) ($lebar ?? 674);

    /*
     * ── NAMA BERKAS DIBUANG DI SATU TEMPAT ────────────────────────────────
     *
     * "Upload Sertifikat: logo.png" tidak menerangkan apa pun tentang
     * sertifikatnya — ia nama berkas, bukan isi riwayat. Dokumennya sendiri
     * sudah punya tempat di bab Lampiran, lengkap dengan status dan jumlah
     * halamannya; mencetak namanya di sini membuat riwayat terlihat seperti
     * daftar unggahan.
     *
     * Disaring pada $baris, BUKAN di dalam masing-masing gaya: tabel dan
     * kartu mencetak seluruh kolom apa adanya, jadi filter yang hanya
     * dipasang di timeline akan bocor begitu admin mengganti bentuknya.
     */
    // Ditulis sebagai perulangan biasa, BUKAN rantai collect()->reject() ber-
    // regex: versi sebelumnya memakai batas-kata di dalam pola, dan karakter
    // itu pernah tertulis sebagai BACKSPACE literal (0x08) alih-alih escape
    // regex -- polanya lalu tidak pernah cocok, dan seluruh kolom berkas lolos
    // ke cetakan tanpa ada yang menyadarinya. Perbandingan awalan lugas di
    // bawah tidak punya cara gagal seperti itu.
    $abaikan = ['upload', 'unggah', 'lampir', 'file', 'berkas', 'dokumen'];
    $baris = [];

    foreach ($isian['baris'] ?? [] as $r) {
        $bersih = [];

        foreach ($r as $sub) {
            if (! empty($sub['berkas'])) {
                continue;
            }

            $l = mb_strtolower(trim((string) ($sub['label'] ?? '')));
            $lewati = false;

            foreach ($abaikan as $kata) {
                if (str_starts_with($l, $kata)) {
                    $lewati = true;

                    break;
                }
            }

            if (! $lewati) {
                $bersih[] = $sub;
            }
        }

        if ($bersih) {
            $baris[] = $bersih;
        }
    }


    // Kolom yang paling sering muncul dipakai jadi kepala tabel & penanda
    // timeline. Diambil dari SELURUH baris, bukan baris pertama: baris pertama
    // bisa saja yang paling sedikit isinya.
    $labelUnik = collect($baris)
        ->flatMap(fn ($r) => collect($r)->pluck('label'))
        ->countBy()
        ->sortDesc()
        ->keys()
        ->all();

    $cocok = function (array $r, array $kata, array $lewati = []) {
        foreach ($r as $sub) {
            $l = mb_strtolower((string) $sub['label']);

            foreach ($lewati as $x) {
                if (str_contains($l, $x)) {
                    continue 2;
                }
            }

            foreach ($kata as $k) {
                if (str_contains($l, $k)) {
                    return $sub['nilai'];
                }
            }
        }

        return null;
    };

    $ada = fn ($v) => trim((string) $v) !== '';
@endphp

<div style="margin-top:12px;">
    @if ($ada($isian['label']) && $isian['label'] !== ($judulBagian ?? ''))
        <p class="k pth" style="padding-bottom:4px;">{{ $isian['label'] }}</p>
    @endif

    @if ($gaya === 'tabel')
        {{-- ── TABEL ──────────────────────────────────────────────────── --}}
        @php $kolom = array_slice($labelUnik, 0, 3); @endphp

        <div style="border:1px solid #e9edf3;border-radius:8px;margin-top:4px;">
            <table style="width:{{ $lebarBidang }}px;">
                <tr>
                    <td style="width:32px;padding:9px 0 9px 15px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">No</td>
                    @foreach ($kolom as $k)
                        <td class="pth" style="padding:9px 12px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">{{ $k }}</td>
                    @endforeach
                </tr>
                @foreach ($baris as $i => $r)
                    @php $zebra = $i % 2 === 1 ? 'background:#f8fafc;' : ''; @endphp
                    <tr>
                        <td style="width:32px;padding:10px 0 10px 15px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:9.5px;font-weight:bold;line-height:1.5;color:#cbd5e1;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        @foreach ($kolom as $j => $k)
                            @php $nilai = collect($r)->firstWhere('label', $k)['nilai'] ?? null; @endphp
                            <td class="pth" style="padding:10px 12px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:10px;line-height:1.5;color:{{ $j === 0 ? '#0f172a' : '#475569' }};{{ $j === 0 ? 'font-weight:bold;' : '' }}">{{ $nilai ?: '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>

    @elseif ($gaya === 'kartu')
        {{-- ── KARTU ──────────────────────────────────────────────────── --}}
        @foreach ($baris as $i => $r)
            {{-- Lebar disebut piksel di kedua tingkat tabel — lihat catatan
                 pada timeline di bawah. 642 = 674 - padding kotak 2×15 - 2px
                 garis; 616 = 642 - kolom nomor 26. --}}
            <div style="margin-top:7px;padding:13px 15px;border:1px solid #e9edf3;border-radius:8px;">
                <table style="width:{{ $lebarBidang - 32 }}px;">
                    <tr>
                        <td style="width:26px;font-size:9px;font-weight:bold;letter-spacing:0.4px;color:#a37a2c;vertical-align:top;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <table style="width:{{ $lebarBidang - 58 }}px;">
                                @foreach (collect($r)->chunk(2) as $pasang)
                                    <tr>
                                        @foreach ($pasang as $sub)
                                            <td style="width:50%;padding:0 14px 9px 0;">
                                                <p class="k pth">{{ $sub['label'] }}</p>
                                                <p class="pth v v-kecil">{{ $sub['nilai'] ?: '—' }}</p>
                                            </td>
                                        @endforeach
                                        @if ($pasang->count() === 1)<td style="width:50%;"></td>@endif
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach

    @else
        {{-- ── TIMELINE (bawaan) ──────────────────────────────────────────
             Titik emas + garis tegak penghubung. Garisnya digambar sebagai
             sel tabel berlatar selebar 1px — DIV kosong bertinggi penuh tidak
             dirender dompdf. --}}
        @foreach ($baris as $i => $r)
            @php
                $utama = $cocok($r, ['nama', 'perusahaan', 'instansi', 'tempat', 'lembaga', 'organisasi', 'sertifik', 'pelatihan'], ['jabatan', 'posisi', 'periode', 'tahun', 'tanggal']);
                $waktu = $cocok($r, ['periode', 'tahun', 'tanggal', 'mulai']);
                $kedua = $cocok($r, ['posisi', 'jabatan', 'peran', 'penyelenggara', 'bidang']);
                $uraian = $cocok($r, ['uraian', 'deskripsi', 'tugas', 'rincian', 'keterangan']);

                // Sisanya dicetak apa adanya supaya tak ada isian yang raib
                // hanya karena labelnya tidak tertebak di atas. Dicocokkan
                // lewat LABEL, bukan nilai: dua kolom berbeda bisa kebetulan
                // berisi teks yang sama (mis. periode & tanggal terbit), dan
                // membandingkan nilainya membuang salah satunya.
                $dipakai = collect([$utama, $kedua, $waktu, $uraian])->filter()->all();
                $lain = collect($r)->reject(function ($s) use (&$dipakai) {
                    $pos = array_search($s['nilai'], $dipakai, true);

                    if ($pos === false) {
                        return false;
                    }

                    unset($dipakai[$pos]);

                    return true;
                });
                $terakhir = $i === count($baris) - 1;
            @endphp

            <table style="width:{{ $lebarBidang }}px;">
                <tr>
                    {{-- Kolom titik + garis tegak.

                         TINGGI GARIS DITAKSIR DARI ISI ENTRI, bukan dipatok.
                         dompdf tidak meninggikan sel kosong mengikuti sel
                         tetangganya, jadi tingginya harus disebut — dan angka
                         tetap 34px membuat entri berisi uraian panjang
                         menyisakan jeda menganga sebelum titik berikutnya,
                         sehingga rangkaiannya terlihat putus.

                         Taksirannya konservatif: kalau meleset, ia meleset ke
                         arah garis yang sedikit lebih pendek — yang terbaca
                         sebagai jeda tipis, bukan sebagai garis yang menabrak
                         titik di bawahnya. --}}
                    @php
                        $tinggiGaris = 18
                            + ($ada($kedua) ? 18 : 0)
                            + ($ada($uraian) ? 18 + (int) (mb_strlen((string) $uraian) / 78) * 17 : 0)
                            + ($lain->isNotEmpty() ? 16 : 0);
                    @endphp
                    <td style="width:22px;vertical-align:top;">
                        <div style="width:9px;height:9px;border-radius:5px;background:#d4a93a;margin-top:4px;"></div>
                        @unless ($terakhir)
                            <table style="width:9px;margin-top:3px;">
                                <tr>
                                    <td style="width:4px;font-size:0;line-height:0;">&nbsp;</td>
                                    <td style="width:1px;height:{{ $tinggiGaris }}px;background:#e9edf3;font-size:0;line-height:{{ $tinggiGaris }}px;">&nbsp;</td>
                                    <td style="width:4px;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        @endunless
                    </td>
                    <td style="padding:0 0 {{ $terakhir ? 0 : 14 }}px 10px;">
                        {{-- LEBAR DALAM PIKSEL, bukan `width:100%`.
                             Persen pada tabel BERSARANG tidak dihormati dompdf:
                             ia menyusutkan tabelnya ke lebar isi, dan periode
                             ("maret 2023") menempel pada nama entri alih-alih
                             rata kanan. 642 = bidang isi 674 - kolom titik 22
                             - padding kiri 10. --}}
                        <table style="width:{{ $lebarBidang - 32 }}px;">
                            <tr>
                                <td style="padding-right:12px;">
                                    <p class="pth" style="font-size:12px;font-weight:bold;line-height:1.35;color:#0f172a;">{{ $utama ?: '—' }}</p>
                                </td>
                                @if ($ada($waktu))
                                    <td style="width:{{ $lebarBidang < 460 ? 90 : 132 }}px;text-align:right;vertical-align:top;">
                                        <p class="pth" style="font-size:9px;font-weight:bold;line-height:1.5;letter-spacing:0.4px;color:#94a3b8;">{{ $waktu }}</p>
                                    </td>
                                @endif
                            </tr>
                        </table>

                        @if ($ada($kedua))
                            <p class="pth" style="font-size:10.5px;font-weight:bold;line-height:1.4;color:#a37a2c;padding-top:3px;">{{ $kedua }}</p>
                        @endif

                        @if ($ada($uraian))
                            <p class="pth" style="font-size:10px;line-height:1.65;color:#475569;padding-top:5px;">{{ $uraian }}</p>
                        @endif

                        @if ($lain->isNotEmpty())
                            <p class="pth" style="font-size:9.5px;line-height:1.6;color:#94a3b8;padding-top:4px;">
                                {{ $lain->map(fn ($s) => $s['label'] . ': ' . $s['nilai'])->implode('  ·  ') }}
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        @endforeach
    @endif
</div>
