<?php

namespace App\Http\Controllers\Career\Integrasi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\SinkronHrisService;
use Illuminate\Http\Request;

/**
 * MASTER KOLOM SINKRON BIODATA — dibaca CAT/HCLearn.
 *
 * KENAPA ADA
 * Saat menerima PATCH biodata, CAT memeriksa tiap kolom terhadap master kolom
 * sinkron sebelum menyimpannya — pengaman yang benar, supaya kiriman tak bisa
 * menulis kolom sembarangan. Masalahnya masternya, `N_WEB_CAREERS_Master_Sinkron_Hris`,
 * hidup di database WEB CAREERS dan tidak pernah ada di database HCLearn. Kueri
 * CAT ke tabel itu tidak menemukan apa pun, sehingga SETIAP kolom dianggap
 * "belum terdaftar" dan seluruh biodata ditolak 422 — dengan payload yang
 * sebenarnya sudah lengkap dan benar.
 *
 * Endpoint ini memberi CAT satu-satunya salinan yang sah, langsung dari
 * pemiliknya. Menyalin tabelnya ke database CAT juga bisa, tapi salinan itu
 * akan menyimpang diam-diam begitu master di sini bertambah — dan gejalanya
 * kembali persis seperti sekarang: kolom baru ditolak tanpa alasan yang jelas.
 *
 * TANPA LOGIN, server-ke-server. Guardnya sama dengan webhook hasil ujian:
 * header `X-WC-Secret`. Isinya bukan rahasia — hanya nama kolom dan labelnya —
 * tapi tetap ditutup supaya daftar ini tidak jadi peta struktur data bagi siapa
 * pun yang menemukan alamatnya.
 *
 * `Field_Key` SENGAJA TIDAK IKUT. Itu urusan Web Careers menemukan nilainya di
 * formulirnya sendiri; CAT hanya perlu tahu kolom mana yang boleh ditulis.
 */
class SinkronHrisController extends Controller
{
    /** GET /api/v1/webhook/master-sinkron-hris */
    public function index(Request $request)
    {
        $secret = (string) config('hclearn.callback_secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-WC-Secret'))) {
            return ResponseHelper::error('Secret tidak valid.', 401);
        }

        $kolom = SinkronHrisService::peta()
            ->map(fn ($m) => [
                'Kolom_Hris' => $m->Kolom_Hris,
                'Label' => $m->Label,
                // PROFIL | FORMULIR | LAMARAN — dari mana Web Careers mengambil
                // nilainya. Tidak dipakai untuk memutuskan boleh-tidaknya
                // menyimpan; disertakan agar layar master di CAT bisa
                // menerangkan asal tiap kolom tanpa menebak.
                'Sumber' => $m->Sumber,
                'Keterangan' => $m->Keterangan,
                'Urutan' => (int) $m->Urutan,
            ])
            ->values();

        return ResponseHelper::success($kolom, 'Master kolom sinkron biodata');
    }
}
