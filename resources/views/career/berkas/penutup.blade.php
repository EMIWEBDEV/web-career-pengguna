{{--
    WEB CAREER — PENUTUP (rancangan halaman 21).

    Disalin 1:1: latar navy, dua bingkai bersarang (emas 46px, putih pucat
    54px), pita emas 6px di atas DAN di bawah, tiga cincin konsentris, lambang
    di 150px, judul "Terima kasih." 92px, dan kaki bergaris emas.

    PERBEDAAN YANG DISENGAJA — semuanya karena dompdf:
      • linear-gradient & radial-gradient → warna padat #0f172a. Gradiennya
        sangat halus (#1e293b → #0f172a → #080e1c) dan cahaya emasnya hanya
        18% opasitas; sebagai warna padat perbedaannya nyaris tak terlihat,
        sementara gradien palsu berupa gambar akan menggemukkan berkas.
      • transform:translate → koordinat dihitung manual.
      • font-weight 300 pada "Terima" → dompdf hanya mengenal normal & bold;
        dipakai normal, dan "kasih." tetap bold seperti rancangan.
--}}
@php
    /*
     * ── TEMA WARNA HALAMAN PENUTUP ────────────────────────────────────────
     *
     * Bawaannya NAVY, sesuai rancangan. Admin boleh menggantinya jadi EMAS
     * lewat Export Studio — dan seluruh warna lain ikut menyesuaikan, bukan
     * hanya latarnya: teks, cincin, bingkai, dan pita harus tetap terbaca di
     * atas latar terang.
     *
     * Diturunkan dari SATU pilihan, bukan dipilih satu per satu. Membiarkan
     * admin menyetel tiap warna terpisah menghasilkan kombinasi yang tidak
     * terbaca — teks emas di atas latar emas — dan itu bukan keluwesan,
     * melainkan cara gagal yang mahal.
     */
    $tema = ($temaPenutup ?? 'navy') === 'emas'
        ? [
            'latar' => '#d4a93a',
            'cincinLuar' => '#e0bb60',   // lebih terang dari latar
            'cincinTengah' => '#dcb452',
            'cakram' => '#c99c2c',       // sedikit lebih gelap: tetap terbaca
            'bingkaiLuar' => '#8a6620',
            'bingkaiDalam' => '#dcb452',
            'pita' => '#0f172a',         // pita jadi navy — kebalikannya
            'aksen' => '#0f172a',
            'aksenPucat' => '#8a6620',
            'judul' => '#241a05',
            'redup' => '#5c4a1c',
            'garisKaki' => '#a37a2c',
            'tepiLencana' => '#8a6620',
        ]
        : [
            'latar' => '#0f172a',
            'cincinLuar' => '#2a2f45',
            'cincinTengah' => '#32374c',
            'cakram' => '#161f36',
            'bingkaiLuar' => '#4a4133',
            'bingkaiDalam' => '#1c2438',
            'pita' => '#d4a93a',
            'aksen' => '#d4a93a',
            'aksenPucat' => '#6b5623',
            'judul' => '#ffffff',
            'redup' => '#a8afbd',
            'garisKaki' => '#463d2c',
            'tepiLencana' => '#4e4330',
        ];
@endphp

<div @class(['hal', 'hal-putus' => $putusHalaman ?? false]) style="background:{{ $tema['latar'] }};">
    {{-- Cincin konsentris. Koordinatnya dihitung dari titik tengah kanvas
         (397, 561) dikurangi separuh ukurannya — pengganti translate(-50%). --}}
    <div style="position:absolute;left:-123px;top:41px;width:1040px;height:1040px;border:1px solid {{ $tema['cincinLuar'] }};border-radius:520px;"></div>
    <div style="position:absolute;left:17px;top:181px;width:760px;height:760px;border:1px solid {{ $tema['cincinTengah'] }};border-radius:380px;"></div>
    <div style="position:absolute;left:157px;top:321px;width:480px;height:480px;border-radius:240px;background:{{ $tema['cakram'] }};"></div>

    {{-- Dua bingkai bersarang. --}}
    <div style="position:absolute;left:46px;top:46px;width:700px;height:1029px;border:1px solid {{ $tema['bingkaiLuar'] }};"></div>
    <div style="position:absolute;left:54px;top:54px;width:684px;height:1013px;border:1px solid {{ $tema['bingkaiDalam'] }};"></div>

    {{-- Pita emas atas & bawah. --}}
    <div style="position:absolute;left:0;top:0;width:794px;height:6px;background:{{ $tema['pita'] }};"></div>
    <div style="position:absolute;left:0;top:1117px;width:794px;height:6px;background:{{ $tema['pita'] }};"></div>

    @if (! empty($logo))
        <div style="position:absolute;left:0;top:150px;width:794px;text-align:center;">
            <img src="{{ $logo }}" alt="EVO Group" style="height:58px;">
        </div>
    @endif

    {{-- Blok tengah. Rancangan memusatkannya dengan translateY(-50%); di sini
         posisinya dihitung sekali dan dipatok. --}}
    <div style="position:absolute;left:88px;top:330px;width:618px;text-align:center;">
        @include('career.berkas.partial-hias', ['warna' => $tema['aksen'], 'lebar' => 52, 'pucat' => $tema['aksenPucat']])

        <p style="font-size:9px;font-weight:bold;line-height:1;letter-spacing:3px;text-transform:uppercase;color:{{ $tema['aksen'] }};padding-top:38px;">Akhir Dokumen</p>

        {{-- DUA PARAGRAF, BUKAN SATU DENGAN <br>.

             Pada teks 92px di dalam kotak berposisi mutlak, dompdf salah
             menghitung tinggi baris kedua: "kasih." tercetak di y=94 — 306px
             di bawah "Terima" — sehingga halaman penutup terlihat pecah,
             judulnya di tengah dan sepotong kata terdampar di kaki.

             Dua paragraf dengan tinggi baris yang disebut eksplisit membuat
             tiap baris berdiri sendiri dan posisinya bisa dihitung. --}}
        <p style="font-size:92px;line-height:96px;letter-spacing:-4px;color:{{ $tema['judul'] }};padding-top:32px;">Terima</p>
        <p style="font-size:92px;line-height:96px;letter-spacing:-4px;font-weight:bold;color:{{ $tema['judul'] }};">kasih.</p>

        {{-- Kalimat "Terima kasih atas waktu dan perhatian..." DIHAPUS:
             judul besar di atas sudah mengatakannya, dan mengulangnya dalam
             kalimat panjang membuat halaman penanda akhir terbaca seperti
             surat. --}}

        <div style="padding-top:56px;">
            @include('career.berkas.partial-hias', ['warna' => $tema['aksen'], 'lebar' => 52, 'pucat' => $tema['aksenPucat']])
        </div>
    </div>

    {{-- Kaki. --}}
    {{-- `top:`, BUKAN `bottom:` — dompdf tidak menghitung `bottom` untuk
         elemen mutlak di dalam kotak ber-overflow. Dengan `bottom:100px`
         kakinya terdorong ke koordinat NEGATIF dan tercetak di luar kanvas:
         70 potongan teks hilang dari halaman penutup. Sebab yang sama dengan
         kaki halaman biasa — lihat `.kaki` di gaya.blade.php.
         1123 - 100 - 78 (tinggi blok kaki) = 945. --}}
    <table style="position:absolute;left:88px;top:945px;width:618px;padding-top:22px;border-top:1px solid {{ $tema['garisKaki'] }};">
        <tr>
            <td style="width:60%;padding-top:22px;">
                <p style="font-size:9px;font-weight:bold;line-height:1;letter-spacing:2px;text-transform:uppercase;color:{{ $tema['aksen'] }};">Dokumen Rahasia</p>
                <p style="font-size:8.5px;line-height:1.8;letter-spacing:1.2px;text-transform:uppercase;color:{{ $tema['redup'] }};padding-top:9px;">Untuk Keperluan Rekrutmen &amp; Seleksi</p>
            </td>
            <td style="width:40%;text-align:right;padding-top:22px;">
                {{-- Titiknya DIV, bukan karakter ●: bulatan itu tidak ada di
                     font inti Helvetica dan tercetak sebagai huruf acak. --}}
                {{-- Lencananya DIV berlebar tetap, bukan tabel inline-block:
                     dompdf menolak `display:inline-block` pada <table>. --}}
                <div style="width:150px;margin-left:auto;padding:7px 14px;border:1px solid {{ $tema['tepiLencana'] }};border-radius:11px;">
                    <table>
                        <tr>
                            <td style="width:5px;padding-right:8px;vertical-align:middle;">
                                <div style="width:5px;height:5px;border-radius:3px;background:{{ $tema['aksen'] }};"></div>
                            </td>
                            <td style="font-size:8.5px;font-weight:bold;line-height:1;letter-spacing:1.5px;text-transform:uppercase;color:{{ $tema['aksen'] }};white-space:nowrap;">EVO Group · {{ now()->year }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
</div>
