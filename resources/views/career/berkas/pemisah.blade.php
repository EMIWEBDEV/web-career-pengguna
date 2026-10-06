{{--
    WEB CAREER — PEMISAH BAB (rancangan halaman 06 & 16).

    Disalin 1:1: pita 6px berwarna aksen bab, tiga cincin konsentris di tengah,
    lambang di 132px, judul 64px yang dipecah dua baris, diapit hiasan
    titik-garis-titik, lalu penanda dokumen di bawah.

    WARNA AKSENNYA IKUT BAB, seperti pada rancangan: Hasil Seleksi memakai
    #a37a2c, Lampiran memakai #334155.
--}}
@php
    $aksen = $aksen ?? '#a37a2c';

    // Padanan pucat untuk cincin & titik hiasan. Rancangan memakai warna aksen
    // beropasitas 10% / 14% / 5% di atas kertas putih; nilai di bawah adalah
    // hasil pencampurannya, sebab dompdf tidak mengenal rgba().
    $emas = $aksen === '#a37a2c';
    $cincinLuar = $emas ? '#f0e9dd' : '#e8eaee';   // aksen 10%
    $cincinTengah = $emas ? '#e9dfcd' : '#dfe2e8'; // aksen 14%
    $cincinIsi = $emas ? '#fbf9f5' : '#f7f8f9';    // aksen 5%
    $titikPucat = $emas ? '#dcc9a4' : '#c2c9d3';

    $baris = preg_split('/\s+/', trim($judul)) ?: [$judul];

    // Judul dua kata dipecah dua baris seperti rancangan ("HASIL / SELEKSI").
    // Lebih dari dua kata dibiarkan mengalir — memaksa pemenggalan pada judul
    // panjang justru membuat barisnya timpang.
    $atas = count($baris) === 2 ? $baris[0] : $judul;
    $bawah = count($baris) === 2 ? $baris[1] : null;

    $terpanjang = max(mb_strlen($atas), mb_strlen((string) $bawah));
    $ukuran = (int) max(30, min(64, floor(640 / (0.62 * max(1, $terpanjang)))));
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false])>
    <div style="position:absolute;left:0;top:0;width:794px;height:6px;background:{{ $aksen }};"></div>

    {{-- Cincin konsentris. Rancangan memusatkannya pada bidang di BAWAH pita
         6px — titik tengahnya (397, 564,5), bukan tengah kertas. Koordinat kiri
         & atas dihitung dari sana dikurangi separuh ukurannya. --}}
    <div style="position:absolute;left:-73px;top:95px;width:940px;height:940px;border:1px solid {{ $cincinLuar }};border-radius:470px;"></div>
    <div style="position:absolute;left:57px;top:225px;width:680px;height:680px;border:1px solid {{ $cincinTengah }};border-radius:340px;"></div>
    <div style="position:absolute;left:182px;top:350px;width:430px;height:430px;border-radius:215px;background:{{ $cincinIsi }};"></div>

    @if (! empty($logo))
        <div style="position:absolute;left:0;top:132px;width:794px;text-align:center;">
            <img src="{{ $logo }}" alt="EVO Group" style="height:46px;">
        </div>
    @endif

    {{-- Blok judul dipusatkan pada lingkaran, bukan pada kertas: tingginya
         (2 hiasan + judul dua baris) dihitung lalu ditarik ke atas dari titik
         tengah. Rancangan memakai translateY(-50%), yang tak dikenal dompdf. --}}
    @php
        $tinggiJudul = ($ukuran * 1.02 * ($bawah ? 2 : 1)) + 68 + 4;
        $atasBlok = (int) round(564.5 - ($tinggiJudul / 2));
    @endphp
    <div style="position:absolute;left:60px;top:{{ $atasBlok }}px;width:674px;text-align:center;">
        @include('career.berkas.partial-hias', ['warna' => $aksen, 'lebar' => 44, 'pucat' => $titikPucat])

        {{-- Tiap baris jadi paragrafnya sendiri, dengan tinggi baris disebut
             dalam piksel. `<br>` di dalam teks sebesar ini pada kotak mutlak
             membuat dompdf salah menempatkan baris kedua: "DOKUMEN" tercetak
             277px di bawah "LAMPIRAN", dan halaman pembatas terlihat pecah. --}}
        @php $tinggiBaris = (int) round($ukuran * 1.02); @endphp
        <div style="padding:34px 0;">
            <p style="font-size:{{ $ukuran }}px;font-weight:bold;line-height:{{ $tinggiBaris }}px;letter-spacing:-2.2px;color:#0f172a;text-transform:uppercase;">{{ $atas }}</p>
            @if ($bawah)
                <p style="font-size:{{ $ukuran }}px;font-weight:bold;line-height:{{ $tinggiBaris }}px;letter-spacing:-2.2px;color:#0f172a;text-transform:uppercase;">{{ $bawah }}</p>
            @endif
        </div>

        @include('career.berkas.partial-hias', ['warna' => $aksen, 'lebar' => 44, 'pucat' => $titikPucat])
    </div>

    {{-- `top:`, BUKAN `bottom:`. dompdf tidak menghitung `bottom` pada elemen
         mutlak — barisnya tercetak di koordinat yang salah, dan halaman
         pembatas terlihat pecah: judulnya di tengah, satu kata lagi terlempar
         ke kaki. Angkanya dihitung dari tinggi kanvas: 1123 − 128 − 10. --}}
    <p style="position:absolute;left:0;top:985px;width:794px;text-align:center;font-size:8px;font-weight:bold;line-height:1;letter-spacing:2.4px;text-transform:uppercase;color:#94a3b8;">EVO Group · Berkas Seleksi Kandidat</p>

    @include('career.berkas.partial-kaki')
</div>
