{{--
    WEB CAREER — SATU BAGIAN CV.

    Dipakai dua kali oleh cv.blade.php: sekali di dalam kolom (lebar 238 atau
    404px), sekali lagi selebar halaman (674px). Semua ukuran diturunkan dari
    $lebar supaya bentuknya sama persis di kedua tempat — dompdf tidak
    menghormati persen pada tabel bersarang, jadi tiap lebar harus disebut.

    @param  array  $b       satu bagian dari SusunCv
    @param  int    $lebar   lebar bidang tempatnya digambar (px)
    @param  array  $atur    penyesuaian per bagian dari Export Studio
--}}
@php
    $ada = fn ($v) => trim((string) $v) !== '';

    $biner = function ($v) {
        $t = mb_strtolower(trim((string) $v));

        return in_array($t, ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju',
            'tidak', 't', 'false', '0', 'belum'], true);
    };
    $bernilaiYa = function ($v) {
        $t = mb_strtolower(trim((string) $v));

        return in_array($t, ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju'], true);
    };
    $pendek = fn ($v) => mb_strlen(trim((string) $v)) <= 60;

    $setel = ($atur ?? [])[$b['kunci']] ?? [];
    $bentuk = $setel['bentuk'] ?? $b['bentuk'];
    $judulBagian = $setel['judul'] ?? $b['judul'];

    // Kolom sempit tidak muat kisi dua kolom — satu isian per baris.
    $sempit = $lebar < 300;
    $kolomIsi = $sempit ? 1 : 2;
    $lebarSel = $sempit ? $lebar : (int) floor($lebar / 2);
@endphp

@if ($ada($judulBagian))
    @include('career.berkas.partial-tanda', ['label' => $judulBagian])
@endif

@if ($b['baris'])
    {{-- Riwayat berulang — diserahkan ke partial-berulang supaya bentuk
         timeline / tabel / kartu-nya sama dengan halaman lain. --}}
    @foreach ($b['baris'] as $r)
        <div style="margin-top:{{ $loop->first ? 4 : 12 }}px;">
            @include('career.berkas.partial-berulang', [
                'isian' => $r['isian'],
                'gaya' => in_array($bentuk, ['timeline', 'tabel', 'kartu'], true) ? $bentuk : 'timeline',
                'judulBagian' => $judulBagian,
                'lebar' => $lebar,
            ])
        </div>
    @endforeach

@else
    @php
        $isi = collect($b['isian'])->filter(fn ($f) => $ada($f['nilai']) || ! empty($f['berkas']));

        $isi = $isi->map(function ($f) {
            if (! empty($f['berkas'])) {
                $f['nilai'] = $f['berkas']['nama'] ?? ($f['nilai'] ?: '—');
            }

            return $f;
        });

        $binerIsi = $isi->filter(fn ($f) => empty($f['berkas']) && $biner($f['nilai']))->values();
        $panjang = $isi->filter(fn ($f) => empty($f['berkas']) && ! $biner($f['nilai']) && ! $pendek($f['nilai']))->values();
        $ringkas = $isi->filter(fn ($f) => ! empty($f['berkas']) || (! $biner($f['nilai']) && $pendek($f['nilai'])))->values();
    @endphp

    @if ($ringkas->isNotEmpty() && $bentuk === 'kartu')
        <table style="width:{{ $lebar }}px;">
            @foreach ($ringkas->chunk($kolomIsi) as $pasang)
                <tr>
                    @foreach ($pasang as $i => $f)
                        <td style="width:{{ $lebarSel }}px;padding:0 {{ $i === 0 && $pasang->count() > 1 ? 10 : 0 }}px 9px 0;vertical-align:top;">
                            <div style="padding:10px 12px;border:1px solid #e9edf3;border-radius:8px;background:#fbfcfd;">
                                <p class="k pth">{{ $f['label'] }}</p>
                                <p class="pth" style="font-size:11.5px;font-weight:bold;line-height:1.3;color:#0f172a;padding-top:5px;">{{ $f['nilai'] ?: '—' }}</p>
                            </div>
                        </td>
                    @endforeach
                    @if ($pasang->count() < $kolomIsi)<td style="width:{{ $lebarSel }}px;"></td>@endif
                </tr>
            @endforeach
        </table>

    @elseif ($ringkas->isNotEmpty() && $bentuk === 'baris')
        <div style="border:1px solid #e9edf3;border-radius:7px;">
            <table style="width:{{ $lebar }}px;">
                @foreach ($ringkas as $i => $f)
                    @php $garis = $i < $ringkas->count() - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; @endphp
                    <tr>
                        <td class="pth" style="width:{{ (int) floor($lebar * 0.45) }}px;padding:8px 13px;font-size:10px;line-height:1.5;color:#94a3b8;{{ $garis }}">{{ $f['label'] }}</td>
                        <td class="pth" style="padding:8px 13px;font-size:10.5px;font-weight:bold;line-height:1.5;color:#0f172a;{{ $garis }}">{{ $f['nilai'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

    @elseif ($ringkas->isNotEmpty())
        <table style="width:{{ $lebar }}px;">
            @foreach ($ringkas->chunk($kolomIsi) as $pasang)
                <tr>
                    @foreach ($pasang as $f)
                        <td style="width:{{ $lebarSel }}px;padding:0 {{ $sempit ? 0 : 18 }}px 10px 0;">
                            <p class="k pth">{{ $f['label'] }}</p>
                            <p class="pth v">{{ $f['nilai'] ?: '—' }}</p>
                        </td>
                    @endforeach
                    @if ($pasang->count() < $kolomIsi)<td style="width:{{ $lebarSel }}px;"></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Deret ya/tidak: selalu tabel berbingkai. Dibaca dengan menyapu kolom
         jawaban di kanan, dan kisi dua kolom memaksa mata melompat. --}}
    @if ($binerIsi->isNotEmpty() && ($bentuk === 'ceklist' || $sempit))
        {{-- ── CEKLIST ───────────────────────────────────────────────────
             Penanda ya/tidak MENDAHULUI pertanyaannya, bukan berdiri di tepi
             seberang. Deret pernyataan dibaca sebagai satu daftar — yang
             dicari pembaca adalah "mana yang tidak", dan itu terjawab dengan
             menyapu satu kolom penanda, bukan dua kolom berjauhan.

             Penanda digambar sebagai DIV ber-border-radius: karakter bulat
             (U+25CF, U+2713) tidak ada di font inti Helvetica dan tercetak
             jadi huruf acak. --}}
        <table style="width:{{ $lebar }}px;margin-top:{{ $ringkas->isNotEmpty() ? 10 : 2 }}px;">
            @foreach ($binerIsi as $f)
                @php $ya = $bernilaiYa($f['nilai']); @endphp
                <tr>
                    <td style="width:15px;padding:0 7px 7px 0;vertical-align:top;">
                        <div style="width:13px;height:13px;border-radius:4px;background:{{ $ya ? '#e7f7f0' : '#f1f5f9' }};border:1px solid {{ $ya ? '#a7e3cb' : '#e2e8f0' }};">
                            <p style="font-size:8px;font-weight:bold;line-height:13px;text-align:center;color:{{ $ya ? '#0d9668' : '#94a3b8' }};">{{ $ya ? 'v' : '-' }}</p>
                        </div>
                    </td>
                    <td class="pth" style="padding:0 0 7px 0;font-size:9.5px;line-height:1.45;color:{{ $ya ? '#334155' : '#94a3b8' }};vertical-align:top;">{{ $f['label'] }}</td>
                </tr>
            @endforeach
        </table>

    @elseif ($binerIsi->isNotEmpty())
        <div style="border:1px solid #e9edf3;border-radius:7px;margin-top:{{ $ringkas->isNotEmpty() ? 10 : 0 }}px;">
            <table style="width:{{ $lebar }}px;">
                @foreach ($binerIsi as $i => $f)
                    @php $garis = $i < $binerIsi->count() - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''; @endphp
                    <tr>
                        <td class="pth" style="padding:8px 13px;font-size:10px;line-height:1.5;color:#475569;{{ $garis }}">{{ $f['label'] }}</td>
                        <td style="width:52px;padding:8px 13px;text-align:right;font-size:9px;font-weight:bold;line-height:1;letter-spacing:0.6px;color:{{ $bernilaiYa($f['nilai']) ? '#10b981' : '#94a3b8' }};{{ $garis }}">{{ mb_strtoupper($f['nilai']) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Uraian panjang: batang emas menandai ini kutipan kandidat, bukan
         keterangan sistem. --}}
    @foreach ($panjang as $f)
        <div style="margin-top:10px;padding:10px 13px;border-left:2px solid #d4a93a;background:#fbfcfd;">
            <p class="k pth">{{ $f['label'] }}</p>
            <p class="pth" style="font-size:10px;line-height:1.7;color:#334155;padding-top:5px;">{{ $f['nilai'] }}</p>
        </div>
    @endforeach
@endif
