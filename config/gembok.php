<?php

/*
|--------------------------------------------------------------------------
| GEMBOK — halaman bypass operasional (/ui/bypass/gembok)
|--------------------------------------------------------------------------
|
| Halaman ini menjalankan pembersihan penjadwalan TANPA melewati panel admin:
| tanpa login, tanpa peran, tanpa gerbang hak akses per-halaman. Itu memang
| gunanya — ia dipakai justru ketika jalur normal tidak bisa dipakai.
|
| Konsekuensinya satu-satunya yang memisahkan ia dari publik adalah kunci di
| bawah. Perlakukan kunci ini setara kredensial basis data, bukan sekadar
| "kata sandi halaman".
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | KUNCI PEMBUKA
    |----------------------------------------------------------------------
    |
    | Dibawa lewat ?gembok-key= pada kunjungan pertama. Buat kuncinya:
    |
    |     php -r "echo bin2hex(random_bytes(32));"
    |
    | KOSONG BERARTI TERTUTUP UNTUK SEMUA — bukan terbuka. Lihat catatan
    | "gagal tertutup" di App\Http\Middleware\GerbangGembok.
    |
    | Nilainya TIDAK PERNAH ditulis di berkas ini; ia tinggal di .env.
    */
    'secret' => env('GEMBOK_SECRET', ''),

    /*
    |----------------------------------------------------------------------
    | BERAPA LAMA TANDA MASUK BERLAKU (menit)
    |----------------------------------------------------------------------
    |
    | Sengaja jauh lebih pendek dari Log Viewer (720 menit). Log Viewer hanya
    | MEMBACA; halaman ini MENGHAPUS. Peramban yang ditinggal terbuka di meja
    | adalah tombol hapus massal yang menganggur.
    */
    'berlaku' => (int) env('GEMBOK_BERLAKU', 120),

    /*
    |----------------------------------------------------------------------
    | LINDUNGI JADWAL YANG SUDAH DIKERJAKAN?
    |----------------------------------------------------------------------
    |
    | true  = tolak penghapusan bila ada peserta berstatus mengerjakan/selesai
    |         (sama dengan pengaman di PenjadwalanController::destroy)
    | false = boleh, TAPI harus disertai konfirmasi paksa dari layar (lihat
    |         `paksa` pada permintaan hapus). Peringatannya tetap ditampilkan
    |         beserta jumlah orangnya — yang hilang bukan angka, melainkan
    |         jejak hasil ujian orang sungguhan.
    |
    | Bawaannya false: halaman ini memang untuk keadaan yang pengaman
    | normalnya justru jadi penghalang. Yang menahan bukan setelan, melainkan
    | ketikan konfirmasi di layar.
    */
    'lindungi_terpakai' => (bool) env('GEMBOK_LINDUNGI_TERPAKAI', false),

];
