{{--
    WEB CAREER — KOP HALAMAN (rancangan: data-hdr).

    "EVO Group · Berkas Seleksi Kandidat" di kiri, nama bab di kanan (emas
    tua #8a6620, sedikit lebih besar).

    @param  string  $bab  nama bab halaman ini — mis. "Data Kandidat"
--}}
{{-- Lebar kolom disebut eksplisit: tanpa itu dompdf merapatkan kedua sel
     sampai bersinggungan, dan kop terbaca sebagai satu kalimat panjang. --}}
<table class="kop">
    <tr>
        <td style="width:60%;">EVO Group · Berkas Seleksi Kandidat</td>
        <td class="bab" style="width:40%;">{{ $bab ?? '' }}</td>
    </tr>
</table>
