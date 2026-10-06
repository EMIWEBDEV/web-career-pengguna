{{--
    WEB CAREER — HIASAN "titik · garis · titik".

    Dipakai halaman pemisah bab dan penutup. Rancangan menggambarnya dengan
    flex + gap:9px; di sini jadi tabel bertepi otomatis supaya tetap terpusat
    di dompdf, yang tidak mengenal flexbox.

    @param  string  $warna   warna garis tengah
    @param  int     $lebar   panjang garis tengah (44 di pemisah, 52 di penutup)
    @param  string  $pucat   warna kedua titik — di rancangan warna yang sama
                             beropasitas .3/.45, di sini warna padat setara
--}}
<table style="margin-left:auto;margin-right:auto;">
    <tr>
        <td style="width:5px;padding-right:9px;">
            <div style="width:5px;height:5px;border-radius:3px;background:{{ $pucat }};"></div>
        </td>
        <td style="width:{{ $lebar }}px;padding-right:9px;">
            <div style="width:{{ $lebar }}px;height:2px;background:{{ $warna }};"></div>
        </td>
        <td style="width:5px;">
            <div style="width:5px;height:5px;border-radius:3px;background:{{ $pucat }};"></div>
        </td>
    </tr>
</table>
