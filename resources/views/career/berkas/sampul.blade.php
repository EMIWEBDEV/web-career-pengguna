{{--
    WEB CAREER — SAMPUL (rancangan halaman 01).

    Disalin 1:1 dari EVO Candidate Selection E-Book.dc.html. Angka-angka di
    bawah (left:74px, top:224px, top:548px, top:790px) datang langsung dari
    sana dan hanya benar pada kanvas 794×1123.

    PERBEDAAN YANG DISENGAJA — dan semuanya karena dompdf:
      • box-shadow pada titik lini masa → digambar sebagai lingkaran kedua
        yang lebih besar dan lebih pucat di belakangnya.
      • Garis penyambung antar titik: rancangan memakai flex:1 di dalam baris
        titik. Di sini ia jadi sel tabel berlatar #e2e8f0 setinggi 1px, dengan
        `font-size:0` supaya sel tak berisi teks tidak ikut meninggi.
      • Gradien garis di bawah judul → satu warna padat.
    Warna, ukuran huruf, jarak huruf, dan koordinatnya tidak diubah.
--}}
@php
    $K = $d['kandidat'];
    $L = $d['lamaran'];

    $ada = fn ($v) => trim((string) $v) !== '';

    // Nama dipatok 30px seperti rancangan, tapi mengecil bila tak muat pada
    // lebar 430px. Rancangan memakai satu nama; data memberi yang mana saja.
    $nama = $K['nama'] ?: '—';
    $kataTerpanjang = max(array_map('mb_strlen', preg_split('/\s+/', $nama) ?: ['']) ?: [1]);
    $ukuranNama = (int) max(17, min(30, floor(430 / (0.56 * max(1, $kataTerpanjang)))));

    $tahap = $d['tahap'] ?? [];
    // Rancangan menampilkan enam titik. Lebih dari itu tulisannya mengecil
    // sampai tak terbaca, jadi sisanya diringkas jadi satu penanda.
    $tampil = array_slice($tahap, 0, 6);
    $sisa = max(0, count($tahap) - count($tampil));
    $lebarTitik = count($tampil) > 0 ? (100 / count($tampil)) : 100;
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    {{-- Pita emas 6px di kepala halaman. --}}
    <div style="position:absolute;left:0;top:0;width:794px;height:6px;background:#d4a93a;"></div>

    {{-- Cincin hias di sudut kanan atas. Lingkaran digambar sebagai DIV
         ber-border-radius: dompdf membulatkan DIV dengan benar. --}}
    <div style="position:absolute;right:-190px;top:-170px;width:560px;height:560px;border:1px solid #eef2f7;border-radius:280px;"></div>
    <div style="position:absolute;right:-96px;top:-104px;width:380px;height:380px;border:1px solid #f4f7fa;border-radius:190px;"></div>
    {{-- Lingkaran emas pucat. Rancangan menaruhnya di bottom:170px sebagai
         latar di BELAKANG isi; dompdf tidak menyusun lapisan seperti peramban,
         jadi ia digambar lebih dulu di berkas ini — apa pun yang datang
         sesudahnya tercetak di atasnya. --}}
    <div style="position:absolute;right:-120px;top:563px;width:390px;height:390px;border-radius:195px;background:#fdfaf2;"></div>

    {{-- ── KEPALA: lambang + penanda dokumen resmi ────────────────────── --}}
    <table style="position:absolute;left:74px;top:70px;width:646px;">
        <tr>
            <td style="width:60%;">
                @if (! empty($logo))
                    <img src="{{ $logo }}" alt="EVO Group" style="height:62px;">
                @endif
            </td>
            <td style="width:40%;text-align:right;">
                <p style="font-size:8.5px;font-weight:bold;line-height:1;letter-spacing:1.7px;text-transform:uppercase;color:#94a3b8;">Dokumen Resmi Rekrutmen</p>
                <p style="font-size:8.5px;font-weight:bold;line-height:1.7;letter-spacing:1.1px;color:#cbd5e1;padding-top:6px;">EVO GROUP</p>
                {{-- Tiga garis mengecil — rancangan: 16px, 6px, 3px. --}}
                <table style="margin-top:14px;margin-left:auto;">
                    <tr>
                        <td style="width:16px;padding-right:6px;"><span style="display:block;border-top:1px solid #d4a93a;"></span></td>
                        <td style="width:6px;padding-right:6px;"><span style="display:block;border-top:1px solid #e4cd94;"></span></td>
                        <td style="width:3px;"><span style="display:block;border-top:1px solid #f2e6c9;"></span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── JUDUL BESAR ────────────────────────────────────────────────── --}}
    <div style="position:absolute;left:74px;top:224px;width:646px;">
        <table style="margin-bottom:24px;">
            <tr>
                <td style="width:44px;padding-top:4px;"><span style="display:block;border-top:2px solid #d4a93a;"></span></td>
                <td style="padding-left:12px;font-size:9px;font-weight:bold;line-height:1;letter-spacing:2.3px;text-transform:uppercase;color:#a37a2c;">EVO Group · Seleksi Kandidat</td>
            </tr>
        </table>

        @php
            // Judul besar boleh ditulis ulang admin. Dipecah per kata, dan kata
            // TERAKHIR berwarna emas — pola yang sama dengan "BERKAS SELEKSI
            // KANDIDAT" pada rancangan, tapi tetap benar untuk judul apa pun.
            //
            // Ukurannya dihitung dari kata terpanjang: dipatok 72px, judul
            // seperti "REKAPITULASI" akan meluber melewati tepi kanan.
            $kataJudul = preg_split('/\s+/', trim($judulSampul ?? '')) ?: [];
            $kataJudul = array_values(array_filter($kataJudul));

            if (! $kataJudul) {
                $kataJudul = ['BERKAS', 'SELEKSI', 'KANDIDAT'];
            }

            $terpanjang = max(array_map('mb_strlen', $kataJudul));
            $ukuranJudul = (int) max(30, min(72, floor(646 / (0.60 * max(1, $terpanjang)))));
        @endphp

        <div style="font-size:{{ $ukuranJudul }}px;font-weight:bold;line-height:0.96;letter-spacing:-2.5px;color:#0f172a;">
            @foreach ($kataJudul as $kata)
                @if ($loop->last)
                    <span style="color:#a37a2c;">{{ mb_strtoupper($kata) }}</span>
                @else
                    {{ mb_strtoupper($kata) }}<br>
                @endif
            @endforeach
        </div>

        {{-- Gradien rancangan jadi satu warna padat — dompdf tak mengenal gradient. --}}
        <div style="width:150px;border-top:1px solid #d4a93a;margin-top:26px;"></div>
    </div>

    {{-- ── IDENTITAS KANDIDAT ─────────────────────────────────────────── --}}
    <div style="position:absolute;left:74px;top:548px;width:646px;">
        {{-- LEBAR TABEL & KOLOM DISEBUT DALAM PIKSEL, bukan persen.
             dompdf tidak selalu menghormati persen pada tabel berposisi mutlak:
             kolomnya menyusut ke lebar isi, dan posisi yang panjang
             ("SPV FINISH GOOD · OFFICER") meluber sampai menabrak kode lamaran
             di sebelahnya. Dengan piksel, batas kedua kolom pasti.

             `padding-right` pada kolom kiri adalah selokan pemisah: tanpa itu
             huruf terakhir posisi menempel persis pada huruf pertama kode. --}}
        <table style="width:646px;">
            <tr>
                <td style="width:420px;padding-right:26px;vertical-align:bottom;">
                    <p class="pth" style="font-size:{{ $ukuranNama }}px;font-weight:bold;line-height:1.12;letter-spacing:-0.6px;color:#0f172a;">{{ $nama }}</p>
                    <p class="pth" style="font-size:13px;line-height:1.4;color:#a37a2c;padding-top:9px;">
                        {{ collect([$L['posisi'] ?? null, $L['level'] ?? null])->filter()->implode(' · ') ?: '—' }}
                    </p>
                </td>
                <td style="width:200px;text-align:right;vertical-align:bottom;">
                    <p style="font-size:7px;font-weight:bold;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Kode Lamaran</p>
                    {{-- `white-space:nowrap` — kode lamaran tidak boleh patah di
                         tengah; ia satu penanda utuh yang dipakai menelusuri
                         berkas. --}}
                    <p style="font-size:15px;font-weight:bold;line-height:1.3;color:#0f172a;padding-top:6px;white-space:nowrap;">{{ $K['kodeLamaran'] ?: '—' }}</p>
                </td>
            </tr>
        </table>

        @php
            $ringkas = array_values(array_filter([
                ['Tanggal Melamar', $tglLamar ?? null],
                ['Departemen', $L['departemen'] ?? null],
                ['Program', $L['program'] ?? null],
                ['Penempatan', $L['lokasi'] ?? null],
            ], fn ($r) => trim((string) $r[1]) !== ''));
            $lebarRingkas = count($ringkas) > 0 ? (100 / count($ringkas)) : 100;
        @endphp

        @if ($ringkas)
            <table style="margin-top:24px;padding:18px 0;border-top:1px solid #e9edf3;border-bottom:1px solid #e9edf3;">
                <tr>
                    @foreach ($ringkas as $r)
                        <td style="width:{{ $lebarRingkas }}%;padding:18px 10px 18px 0;">
                            <p style="font-size:7px;font-weight:bold;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">{{ $r[0] }}</p>
                            <p class="pth" style="font-size:11px;line-height:1.4;color:#0f172a;padding-top:3px;">{{ $r[1] }}</p>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    </div>

    {{-- ── TAHAPAN SELEKSI ────────────────────────────────────────────── --}}
    @if ($tampil)
        <div style="position:absolute;left:74px;top:790px;width:646px;">
            <p style="font-size:9px;font-weight:bold;line-height:1;letter-spacing:1.8px;text-transform:uppercase;color:#334155;margin-bottom:22px;">Tahapan Seleksi</p>

            <table>
                <tr>
                    @foreach ($tampil as $i => $t)
                        @php
                            $selesai = $t['status'] === 'SELESAI';
                            $jalan = $t['status'] === 'BERJALAN';

                            // Warna titik dari STATUS, bukan urutan: tahap bisa
                            // dilewati, ditahan, atau diulang.
                            $isi = $selesai ? '#10b981' : ($jalan ? '#d4a93a' : '#ffffff');
                            $cincin = $selesai ? '#e3f7ef' : ($jalan ? '#f9efd5' : '#ffffff');
                            $tepi = $selesai ? '#10b981' : ($jalan ? '#d4a93a' : '#cbd5e1');

                            $warnaLabel = ($selesai || $jalan) ? '#0f172a' : '#94a3b8';
                            $warnaWaktu = ($selesai || $jalan) ? '#94a3b8' : '#cbd5e1';

                            $lencanaLatar = $selesai ? '#e8f8f2' : ($jalan ? '#faf1dc' : '#f1f5f9');
                            $lencanaTeks = $selesai ? '#0d9668' : ($jalan ? '#a37a2c' : '#94a3b8');
                            $lencanaLabel = $selesai ? 'Selesai' : ($jalan ? 'Berjalan' : 'Belum');

                            $terakhir = $i === count($tampil) - 1;
                        @endphp
                        <td style="width:{{ $lebarTitik }}%;">
                            {{-- Titik + garis penyambung. Cincin digambar sebagai
                                 lingkaran pucat 21px yang menaungi titik 13px —
                                 pengganti box-shadow yang tak dikenal dompdf.

                                 Garisnya memenuhi sisa lebar kolom dan berhenti
                                 di titik terakhir, seperti pada rancangan. --}}
                            {{-- Titik & garis ditempatkan SECARA MUTLAK di dalam
                                 kotak setinggi 21px.

                                 Dua cara yang lebih sederhana sudah dicoba dan
                                 gagal di dompdf: sel tabel kosong di samping
                                 titik dirender bertinggi nol sehingga garisnya
                                 lenyap, dan margin negatif untuk menumpuk titik
                                 di atas garis tidak dihormati. Posisi mutlak
                                 tidak bergantung pada keduanya. --}}
                            <div style="position:relative;height:21px;">
                                @unless ($terakhir)
                                    <div style="position:absolute;left:21px;right:0;top:10px;height:1px;background:#e2e8f0;font-size:0;line-height:0;"></div>
                                @endunless
                                <div style="position:absolute;left:0;top:0;width:21px;height:21px;border-radius:11px;background:{{ $cincin }};">
                                    <div style="width:13px;height:13px;border-radius:7px;background:{{ $isi }};border:1px solid {{ $tepi }};margin:3px 0 0 3px;"></div>
                                </div>
                            </div>

                            <p class="pth" style="font-size:10px;font-weight:bold;line-height:1.35;color:{{ $warnaLabel }};padding:13px 14px 0 0;">{{ $t['label'] }}</p>
                            <p style="font-size:7.5px;line-height:1.5;letter-spacing:0.4px;color:{{ $warnaWaktu }};padding:5px 14px 0 0;">
                                {{ $t['diputusAt'] ? \Carbon\Carbon::parse($t['diputusAt'])->translatedFormat('d M Y H:i') : ($jalan ? 'Belum dijadwalkan' : 'Menunggu tahap') }}
                            </p>
                            <div style="padding-top:7px;">
                                <span style="display:inline-block;padding:3px 8px;border-radius:8px;background:{{ $lencanaLatar }};font-size:7px;font-weight:bold;line-height:1;letter-spacing:0.7px;text-transform:uppercase;color:{{ $lencanaTeks }};">{{ $lencanaLabel }}</span>
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>

            @if ($sisa > 0)
                <p style="font-size:8px;color:#94a3b8;padding-top:12px;">+{{ $sisa }} tahap berikutnya tercantum pada bagian Hasil Seleksi.</p>
            @endif
        </div>
    @endif

    {{-- ── KAKI SAMPUL ──────────────────────────────────────────────────
         Garis pemisah digambar sebagai DIV tersendiri, bukan border-top pada
         tabel: dompdf menempatkan border tabel di atas kotak PADDING-nya,
         sehingga garisnya melayang jauh di atas isinya dan kaki terlihat
         patah dari dokumen.

         `vertical-align: middle` pada kedua sel wajib disebut — aturan global
         berkas ini memasang `top`, dan tanpanya "20 Halaman" melorot ke dasar
         sel sementara lencana di sebelahnya duduk di tengah. --}}
    <div style="position:absolute;left:74px;bottom:62px;width:646px;">
        <div style="height:1px;background:#e9edf3;font-size:0;line-height:0;"></div>

        <table style="width:646px;margin-top:18px;">
            <tr>
                <td style="width:60%;vertical-align:middle;">
                    {{-- Titiknya DIV, bukan karakter ●: bulatan itu tidak ada di
                         font inti Helvetica dan tercetak sebagai huruf acak.
                         Lencananya DIV berlebar tetap, bukan tabel
                         inline-block: dompdf menolak `display:inline-block`
                         pada <table>. --}}
                    <div style="width:126px;padding:6px 12px;border:1px solid #efdfb8;border-radius:10px;background:#fdf9ef;">
                        <table>
                            <tr>
                                <td style="width:5px;padding-right:8px;vertical-align:middle;">
                                    <div style="width:5px;height:5px;border-radius:3px;background:#d4a93a;"></div>
                                </td>
                                <td style="font-size:8.5px;font-weight:bold;line-height:1;letter-spacing:1.5px;text-transform:uppercase;color:#a37a2c;white-space:nowrap;">Dokumen Rahasia</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width:40%;text-align:right;vertical-align:middle;font-size:8.5px;line-height:1;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">
                    {{-- Jumlah halaman SELURUH berkas, termasuk lampiran yang
                         digabung sesudah dompdf selesai — karena itu angkanya
                         dihitung di perakit, bukan dari counter(page). --}}
                    {{ $totalHalaman ? $totalHalaman . ' Halaman' : '' }}
                </td>
            </tr>
        </table>
    </div>
</div>
