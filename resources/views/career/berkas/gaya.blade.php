{{--
    WEB CAREER — GAYA BERSAMA BERKAS SELEKSI KANDIDAT.

    DISALIN 1:1 dari rancangan
    docs/coba-design/EVO Group Candidate Selection E-book (3)/EVO Candidate Selection E-Book.dc.html

    ── KANVASNYA 794 × 1123 PIKSEL, DAN ITU MENENTUKAN SEGALANYA ─────────────
    Rancangan menggambar tiap halaman sebagai kanvas A4 pada 96 dpi dengan
    elemen BERPOSISI MUTLAK — bukan aliran dokumen. Angka-angka di dalamnya
    (left:74px, top:224px, dan seterusnya) hanya benar bila kanvasnya berukuran
    sama persis. Karena itu @page dibuat 794×1123px bermargin nol, dan tiap
    halaman jadi satu kotak .hal berukuran penuh.

    Konsekuensinya kop & kaki TIDAK lagi digambar di kanvas dompdf: keduanya
    kini elemen biasa di dalam tiap halaman, persis seperti pada rancangan.

    ── HURUFNYA HELVETICA/ARIAL, WAJIB ──────────────────────────────────────
    Rancangan menulis font: … 'Helvetica','Arial',sans-serif di SETIAP elemen.
    Keduanya font inti PDF: selalu tersedia, tak perlu ditanam, tak pernah
    gagal termuat — dan itu penting untuk tata letak berposisi mutlak, yang
    akan meleset seluruhnya bila fontnya tergantikan.

    ── YANG TIDAK BISA DIPAKAI DI DOMPDF, DAN GANTINYA ──────────────────────
      • flex & grid       → TABEL dengan lebar kolom eksplisit
      • gradient          → satu warna padat (atau gambar bila perlu)
      • box-shadow        → dihapus; cincin di sekitar titik lini masa
                            digambar sebagai lingkaran kedua yang lebih besar
      • transform         → dihitung manual jadi koordinat mutlak
      • object-fit        → lebar/tinggi dihitung di PHP
    Sisanya — warna, ukuran huruf, jarak, border, border-radius pada DIV —
    disalin apa adanya.
--}}
<style>
    /* Kanvas rancangan: A4 pada 96 dpi. Margin nol karena setiap halaman
       menempatkan isinya sendiri secara mutlak. */
    @page { margin: 0; size: 794px 1123px; }

    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 11px;
        color: #0f172a;
        margin: 0;
        padding: 0;
    }

    /* Satu halaman = satu kanvas penuh. `position: relative` menjadikannya
       acuan bagi seluruh anak yang berposisi mutlak. */
    .hal {
        position: relative;
        width: 794px;
        height: 1123px;
        overflow: hidden;
        background: #fff;
    }
    .hal-putus { page-break-before: always; }

    table { border-collapse: collapse; }
    td, th { padding: 0; vertical-align: top; }

    p { margin: 0; }

    /* Isian kandidat boleh dipatahkan — satu alamat surel panjang tanpa ini
       menggeser seluruh kolom di sebelahnya. */
    .pth { word-wrap: break-word; }

    /* ── KOP & KAKI (rancangan: data-hdr / data-ftr) ──────────────────────
       Ukuran & warnanya disalin apa adanya:
       font:700 7px/1; letter-spacing:.16em; color:#b8c2d0 */
    /* LEBARNYA DISEBUT EKSPLISIT (674px = 794 - 60 - 60), bukan lewat
       `right: 60px`. dompdf tidak menurunkan lebar tabel dari pasangan
       left+right: tabelnya menyusut ke lebar isinya, kedua selnya merapat,
       dan kop terbaca sebagai satu kalimat sambung
       "EVO GROUP - BERKAS SELEKSI KANDIDATDATA KANDIDAT". */
    .kop {
        position: absolute; left: 60px; width: 674px; top: 40px;
        /* 14px, bukan 9: `line-height: 1` membuat huruf berdiri tepat setinggi
           badannya tanpa ruang di bawah, jadi jarak ke garis pemisah terlihat
           jauh lebih rapat daripada angka paddingnya. */
        padding-bottom: 14px; border-bottom: 1px solid #eef2f7;
    }
    .kop td {
        font-size: 7px; font-weight: bold; line-height: 1.5;
        letter-spacing: 1.1px; text-transform: uppercase; color: #b8c2d0;
        white-space: nowrap;
    }
    .kop .bab { text-align: right; font-size: 8px; color: #8a6620; }

    /* Sama seperti .kop: lebar eksplisit, kalau tidak nomor halaman menempel
       pada nama kandidat alih-alih rata kanan.

       `top`, BUKAN `bottom`: dompdf tidak menghitung `bottom` untuk elemen
       mutlak di dalam kotak ber-overflow - kakinya hilang tanpa jejak, dan
       nomor halaman ikut lenyap. 1123 - 38 - 21 (tinggi kaki) = 1064. */
    .kaki {
        position: absolute; left: 60px; width: 674px; top: 1060px;
        /* Sama seperti .kop — lihat catatan padding di sana. */
        padding-top: 14px; border-top: 1px solid #eef2f7;
    }
    .kaki td {
        font-size: 7px; font-weight: bold; line-height: 1.5;
        letter-spacing: 0.9px; text-transform: uppercase; color: #b8c2d0;
    }

    /* Bidang isi di antara kop dan kaki — rancangan: top:92px; bottom:84px. */
    /* Bidang isi di antara kop dan kaki.

       `top: 76px`, DIRAPATKAN dari 92: kop berakhir di sekitar y=66 (mulai 40
       + tinggi barisnya + padding 14 + garis), jadi 92 menyisakan jarak yang
       terukur 50-54px pada cetakan — terbaca sebagai kop yang mengambang
       jauh dari isinya. 76 memberi ~10px, cukup untuk memisahkan tanpa
       memutus keduanya.

       TINGGI_ISI di TataLetakHalaman IKUT BERUBAH mengikuti angka ini:
       1123 - 76 - 84 = 963. Keduanya harus sepakat, kalau tidak penggabungan
       halaman menghitung ruang yang tidak ada. */
    .isi { position: absolute; left: 60px; right: 60px; top: 76px; bottom: 84px; overflow: hidden; }

    /* ── PENANDA BAGIAN: garis emas 13×2 + label ──────────────────────────
       Rancangan menggambarnya dengan flex; di sini jadi tabel dua sel. */
    .tanda { margin-bottom: 10px; }
    .tanda .garis { width: 13px; padding-top: 3px; }
    .tanda .garis span { display: block; width: 13px; border-top: 2px solid #d4a93a; }
    .tanda .teks {
        padding-left: 8px;
        font-size: 8px; font-weight: bold; line-height: 1;
        letter-spacing: 1.3px; text-transform: uppercase; color: #334155;
    }

    /* Label kecil di atas nilai — font:700 7px/1; ls .14em; #94a3b8 */
    .k {
        font-size: 7px; font-weight: bold; line-height: 1;
        letter-spacing: 1px; text-transform: uppercase; color: #94a3b8;
    }
    .v { font-size: 11px; line-height: 1.4; color: #0f172a; padding-top: 3px; }
    .v-kecil { font-size: 10.5px; }

    .abu { color: #94a3b8; }
    .redup { color: #475569; }
    .emas { color: #a37a2c; }
    .hijau { color: #10b981; }
    .hijau-tua { color: #0d9668; }

    .garis-tipis { border-top: 1px solid #f1f5f9; height: 0; }
    .garis-tepi { border-top: 1px solid #e9edf3; height: 0; }
</style>
