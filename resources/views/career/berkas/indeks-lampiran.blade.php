{{--
    WEB CAREER — INDEKS DOKUMEN TERLAMPIR (rancangan halaman 17).

    Disalin 1:1: judul 25px, tabel berbingkai membulat dengan kepala #f1f5f9
    dan baris berselang #f8fafc, catatan dokumen berbatang kiri, lalu empat
    kartu ringkasan di dasar bidang isi.

    DI SINILAH TOTAL HALAMAN DIUMUMKAN. Halaman lampiran digabung apa adanya
    tanpa kop maupun nomor, jadi tanpa angka di kartu ini pembaca tak punya
    cara memastikan berkas yang diterimanya sudah utuh.

    STATUSNYA JUJUR: 'ADA' hanya untuk berkas yang benar-benar terbaca dan
    halamannya sudah terhitung; yang gagal ditandai 'TIDAK TERBACA' dan
    digantikan halaman penanda. Menulis 'ADA' untuk keduanya membuat indeks
    ini berbohong tentang isi dokumennya sendiri.
--}}
@php
    $terbaca = collect($lampiran)->where('gagal', false)->count();
    $gagal = count($lampiran) - $terbaca;

    // Rancangan memuat delapan baris pada satu halaman. Lebih dari itu tabelnya
    // menembus kartu ringkasan di bawah, jadi sisanya diringkas satu baris.
    $maks = 13;
    $tampil = array_slice($lampiran, 0, $maks);
    $sisa = max(0, count($lampiran) - count($tampil));
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    @include('career.berkas.partial-kop', ['bab' => 'Lampiran'])

    <div class="isi">
        <div style="padding-bottom:14px;border-bottom:1px solid #e9edf3;">
            <p style="font-size:25px;font-weight:bold;line-height:1.16;letter-spacing:-0.5px;color:#0f172a;">Indeks Dokumen Terlampir</p>
            <p style="font-size:9px;line-height:1;letter-spacing:0.9px;text-transform:uppercase;color:#94a3b8;padding-top:6px;">{{ count($lampiran) }} Dokumen</p>
        </div>

        @if ($tampil)
            <div style="border:1px solid #e9edf3;border-radius:8px;margin-top:20px;">
                <table style="width:100%;">
                    <tr>
                        <td style="width:32px;padding:10px 0 10px 17px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">No</td>
                        <td style="padding:10px 13px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">Jenis Dokumen</td>
                        <td style="padding:10px 13px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">Nama File</td>
                        <td style="width:44px;padding:10px 13px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;text-align:right;">Hal</td>
                        <td style="width:58px;padding:10px 17px 10px 13px;background:#f1f5f9;border-bottom:1px solid #e9edf3;font-size:6.5px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#475569;text-align:right;">Status</td>
                    </tr>
                    @foreach ($tampil as $i => $l)
                        @php $zebra = $i % 2 === 1 ? 'background:#f8fafc;' : ''; @endphp
                        <tr>
                            <td style="width:32px;padding:11px 0 11px 17px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:9.5px;font-weight:bold;line-height:1.5;color:#cbd5e1;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="pth" style="padding:11px 13px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:10.5px;font-weight:bold;line-height:1.5;color:#0f172a;">{{ $l['label'] }}</td>
                            <td class="pth" style="padding:11px 13px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:9px;line-height:1.5;color:#475569;">{{ \Illuminate\Support\Str::limit($l['nama'], 46) }}</td>
                            <td style="width:44px;padding:11px 13px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:9px;line-height:1.5;color:#94a3b8;text-align:right;">{{ $l['halaman'] ?: '—' }}</td>
                            <td style="width:58px;padding:11px 17px 11px 13px;{{ $zebra }}border-bottom:1px solid #f1f5f9;font-size:8.5px;font-weight:bold;line-height:1.5;letter-spacing:0.5px;text-align:right;color:{{ $l['gagal'] ? '#b91c1c' : '#10b981' }};">{{ $l['gagal'] ? 'GAGAL' : 'ADA' }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

            @if ($sisa > 0)
                <p style="font-size:10px;line-height:1.6;color:#94a3b8;padding-top:11px;">+{{ $sisa }} dokumen lain turut dilampirkan pada halaman berikutnya.</p>
            @endif
        @else
            <div style="border:1px solid #e9edf3;border-radius:8px;margin-top:20px;padding:20px;background:#f8fafc;">
                <p style="font-size:11px;color:#94a3b8;">Tidak ada dokumen yang dilampirkan pada berkas ini.</p>
            </div>
        @endif

        <div style="margin-top:22px;padding:16px 18px;border-left:2px solid {{ $gagal > 0 ? '#b91c1c' : '#334155' }};background:#f8fafc;">
            <p style="font-size:7px;font-weight:bold;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Catatan Dokumen</p>
            <p style="font-size:11px;line-height:1.7;color:#475569;padding-top:8px;">
                @if ($gagal > 0)
                    <strong>{{ $gagal }} dokumen</strong> tidak dapat digabungkan — umumnya karena terkunci sandi atau
                    memakai format yang belum didukung. Masing-masing digantikan satu halaman penanda, dan berkas
                    aslinya tetap tersimpan pada sistem rekrutmen.
                @else
                    Biodata kandidat EVO Group — memuat data pribadi, digunakan hanya untuk keperluan proses rekrutmen
                    dan seleksi karyawan serta dilarang disebarkan di luar keperluan itu. Dihasilkan otomatis pada
                    {{ $dicetak ?? '—' }}; tidak memerlukan tanda tangan.
                @endif
            </p>
        </div>

        {{-- Empat kartu ringkasan, dipatok ke dasar bidang isi seperti
             rancangan. Ditempatkan dengan `top`, bukan `bottom`: dompdf tidak
             menghitung `bottom` untuk elemen mutlak di dalam kotak
             ber-overflow, dan kartunya hilang tanpa jejak. 947px adalah tinggi
             bidang isi (1123 − 92 − 84); 62px tinggi kartunya. --}}
        <table style="position:absolute;left:0;top:885px;width:674px;">
            <tr>
                @php
                    $kartu = [
                        ['Total Dokumen', str_pad(count($lampiran), 2, '0', STR_PAD_LEFT), '#0f172a', 16],
                        ['Dapat Ditampilkan', $terbaca . ' / ' . count($lampiran), $gagal === 0 ? '#10b981' : '#a37a2c', 16],
                        ['Total Halaman', (string) ($totalHalaman ?: '—'), '#0f172a', 16],
                        ['Dicetak', (string) ($dicetak ?? '—'), '#0f172a', 11],
                    ];
                @endphp
                @foreach ($kartu as $i => $c)
                    <td style="width:25%;padding-right:{{ $i === 3 ? 0 : 10 }}px;">
                        <div style="padding:13px 15px;border:1px solid #e9edf3;border-radius:8px;">
                            <p style="font-size:7px;font-weight:bold;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">{{ $c[0] }}</p>
                            <p class="pth" style="font-size:{{ $c[3] }}px;font-weight:bold;line-height:1.45;color:{{ $c[2] }};padding-top:6px;">{{ $c[1] }}</p>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    @include('career.berkas.partial-kaki')
</div>
