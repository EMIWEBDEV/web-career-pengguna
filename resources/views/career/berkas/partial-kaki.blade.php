{{--
    WEB CAREER — KAKI HALAMAN (rancangan: data-ftr).

    "LMR-XXXX · Nama Kandidat" di kiri, nomor halaman "03 / 21" di kanan.

    NOMOR HALAMAN TIDAK DICETAK DI SINI — sengaja.

    Berkas ini digabung dengan lampiran dan laporan psikotes yang punya
    penomorannya SENDIRI: laporan PAPI mencetak "1 | P a g e", berkas pindaian
    membawa nomor dari dokumen asalnya. Dua sistem penomoran pada satu lembar
    membuat pembaca harus menebak mana yang berlaku.

    Jumlah halaman diumumkan sekali di SAMPUL, dan itu sudah cukup untuk
    memastikan berkas yang diterima lengkap.
--}}
<table class="kaki">
    <tr>
        <td>{{ mb_strtoupper(($d['kandidat']['kodeLamaran'] ?? '') . ' · ' . \Illuminate\Support\Str::limit($d['kandidat']['nama'] ?? '', 42)) }}</td>
    </tr>
</table>
