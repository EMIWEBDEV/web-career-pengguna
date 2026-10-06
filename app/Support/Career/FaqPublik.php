<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — sumber data FAQ untuk halaman PUBLIK.
 *
 * SATU PINTU untuk dua tempat yang menampilkan FAQ:
 *   - accordion di landing page (cuplikan pilihan admin) → landing()
 *   - halaman /karir/faq (seluruh pertanyaan per kategori) → semua()
 * Digabung di sini supaya keduanya tidak pernah berbeda pendapat soal FAQ mana
 * yang "aktif" dan bagaimana bentuk payload-nya.
 *
 * Read-only: penulisan penghitung dilihat/membantu ada di FaqPublikController.
 *
 * SETIAP query dibungkus try/catch dan mengembalikan array kosong bila gagal —
 * sama seperti timInfoRows() di CareerLandingController. Halaman karir publik
 * tidak boleh tumbang hanya karena tabel FAQ bermasalah; paling buruk section
 * FAQ-nya tidak muncul.
 */
class FaqPublik
{
    private const TABEL = 'N_WEB_CAREERS_Master_Faq';

    private const TABEL_KATEGORI = 'N_WEB_CAREERS_Master_Faq_Kategori';

    /**
     * Cuplikan untuk accordion landing: hanya yang ditandai admin
     * (Flag_Tampil_Landing = 'Y'), urut sesuai Urutan.
     */
    public function landing(int $limit = 6): array
    {
        try {
            return DB::table(self::TABEL)
                ->where('Flag_Tampil_Landing', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->orderBy('Urutan')
                ->orderBy('Id_Master_Faq')
                ->limit($limit)
                ->get(['Id_Master_Faq', 'Slug', 'Ikon', 'Pertanyaan', 'Jawaban_Ringkas', 'Jawaban_Detail'])
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Faq),
                    'slug' => $r->Slug,
                    'ikon' => $r->Ikon ?: 'bi-question-circle-fill',
                    'pertanyaan' => $r->Pertanyaan,
                    'jawaban' => $r->Jawaban_Ringkas,
                    // Landing tidak pernah merender detail, tapi perlu tahu ADA
                    // detailnya supaya bisa menawarkan "baca selengkapnya"
                    // hanya pada pertanyaan yang memang punya penjelasan panjang.
                    'punyaDetail' => trim((string) $r->Jawaban_Detail) !== '',
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat FAQ landing: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Seluruh FAQ aktif + kategorinya untuk halaman /karir/faq.
     *
     * @return array{kategori: array, faq: array}
     */
    public function semua(): array
    {
        try {
            $faq = DB::table(self::TABEL)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->orderBy('Urutan')
                ->orderBy('Id_Master_Faq')
                ->get([
                    'Id_Master_Faq', 'Faq_Kategori_Id', 'Slug', 'Ikon', 'Pertanyaan',
                    'Jawaban_Ringkas', 'Jawaban_Detail', 'Jumlah_Membantu', 'Jumlah_Tidak_Membantu',
                ])
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Faq),
                    // Kategori dikirim sebagai KODE, bukan id — klien memakainya
                    // untuk mengelompokkan & menggulir ke section.
                    'kategoriId' => $r->Faq_Kategori_Id ? (int) $r->Faq_Kategori_Id : null,
                    'slug' => $r->Slug,
                    'ikon' => $r->Ikon ?: 'bi-question-circle-fill',
                    'pertanyaan' => $r->Pertanyaan,
                    'jawaban' => $r->Jawaban_Ringkas,
                    // Sudah disaring HtmlBersih saat disimpan; klien menyaring
                    // sekali lagi dengan DOMPurify sebagai lapisan kedua.
                    'jawabanDetail' => trim((string) $r->Jawaban_Detail) !== '' ? $r->Jawaban_Detail : null,
                    // Teks polos detail ikut dikirim supaya pencarian di halaman
                    // FAQ juga menjangkau isi penjelasan panjang, bukan cuma
                    // pertanyaan & ringkasan.
                    'cari' => HtmlBersih::keTeks($r->Jawaban_Detail),
                    'membantu' => (int) $r->Jumlah_Membantu,
                    'tidakMembantu' => (int) $r->Jumlah_Tidak_Membantu,
                ])
                ->values();

            $kategori = DB::table(self::TABEL_KATEGORI)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->orderBy('Urutan')
                ->orderBy('Id_Master_Faq_Kategori')
                ->get(['Id_Master_Faq_Kategori', 'Kode', 'Nama', 'Deskripsi', 'Ikon'])
                ->map(fn ($r) => [
                    'id' => (int) $r->Id_Master_Faq_Kategori,
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'deskripsi' => $r->Deskripsi,
                    'ikon' => $r->Ikon ?: 'bi-collection-fill',
                    'jumlah' => $faq->where('kategoriId', (int) $r->Id_Master_Faq_Kategori)->count(),
                ])
                // Kategori yang belum punya pertanyaan tidak perlu tampil sebagai
                // section kosong di halaman publik.
                ->filter(fn ($k) => $k['jumlah'] > 0)
                ->values();

            // FAQ tanpa kategori (atau kategorinya sedang dinonaktifkan) tetap
            // harus bisa dibaca — kumpulkan di pseudo-kategori "Lainnya" alih-alih
            // menghilang tanpa jejak.
            $idKategoriTampil = $kategori->pluck('id')->all();
            $jumlahLainnya = $faq
                ->filter(fn ($f) => $f['kategoriId'] === null || ! in_array($f['kategoriId'], $idKategoriTampil, true))
                ->count();

            if ($jumlahLainnya > 0) {
                $kategori->push([
                    'id' => 0,
                    'kode' => 'LAINNYA',
                    'nama' => 'Lainnya',
                    'deskripsi' => 'Pertanyaan lain seputar karier di EVO Group.',
                    'ikon' => 'bi-three-dots',
                    'jumlah' => $jumlahLainnya,
                ]);
            }

            // Normalisasi: FAQ yang kategorinya tidak tampil dipindahkan ke id 0
            // supaya klien cukup mengelompokkan berdasarkan kategoriId.
            $faq = $faq->map(function ($f) use ($idKategoriTampil) {
                if ($f['kategoriId'] === null || ! in_array($f['kategoriId'], $idKategoriTampil, true)) {
                    $f['kategoriId'] = 0;
                }

                return $f;
            });

            return [
                'kategori' => $kategori->values()->all(),
                'faq' => $faq->values()->all(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat FAQ publik: ' . $e->getMessage());

            return ['kategori' => [], 'faq' => []];
        }
    }
}
