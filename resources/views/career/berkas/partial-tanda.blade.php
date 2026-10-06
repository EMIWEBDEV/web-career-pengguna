{{--
    WEB CAREER — PENANDA BAGIAN: garis emas 13×2px + label huruf renggang.

    Rancangan menggambarnya dengan flex + gap:8px. Di sini jadi tabel dua sel;
    dompdf tidak mengenal flexbox.

    @param  string  $label  judul bagian — mis. "Data Pribadi"
--}}
<table class="tanda">
    <tr>
        <td class="garis"><span></span></td>
        <td class="teks">{{ $label }}</td>
    </tr>
</table>
