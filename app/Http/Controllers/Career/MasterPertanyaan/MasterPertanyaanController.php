<?php

namespace App\Http\Controllers\Career\MasterPertanyaan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\BankPertanyaan;
use App\Support\Career\KodeOtomatis;
use App\Support\Career\MesinSyarat;
use App\Support\Career\Skrining;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER PERTANYAAN SKRINING: pustaka pertanyaan lintas template.
 *
 * Satu pertanyaan ditulis SEKALI di sini, lalu ditarik ke template mana pun
 * yang membutuhkannya. Tanpa ini, "Bersedia ditempatkan di luar kota?" ditulis
 * ulang di setiap template dengan redaksi yang sedikit berbeda dan daftar opsi
 * yang tidak persis sama — dan analitik lintas program jadi mustahil karena
 * tiga pertanyaan yang sebenarnya satu tercatat sebagai tiga hal berbeda.
 *
 * ── MENYUNTING DI SINI TIDAK MENGUBAH APA PUN DI TEMPLATE ───────────────────
 *
 * Template memegang SALINANNYA SENDIRI. Itu disengaja, dan bukan kelalaian
 * normalisasi: bank yang dirujuk hidup-hidup akan membuat satu suntingan di
 * sini mengubah arti seluruh sesi kandidat yang pernah memakainya.
 *
 * Yang bisa dilakukan sesudah menyunting adalah MENYEGARKAN draf template dari
 * bank — dan itu tindakan sadar yang dilakukan orang di halaman template,
 * bukan efek samping yang menjalar sendiri dari sini.
 *
 * Karena itu halaman ini menampilkan angka pemakaian: bukan sebagai peringatan
 * bahaya, tapi supaya orang tahu berapa template yang perlu ia segarkan
 * sesudah memperbaiki sebuah pertanyaan.
 */
class MasterPertanyaanController extends Controller
{
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-pertanyaan/masterPertanyaan',
            CareerShell::props('/master-pertanyaan', 'Master Pertanyaan Skrining')
        );
    }

    public function list(Request $request)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::success(
                ['rows' => [], 'siap' => false],
                'Skema bank pertanyaan belum dijalankan.'
            );
        }

        try {
            $data = $request->validate([
                'q' => 'nullable|string|max:120',
                'jf' => 'nullable|array', 'jf.*' => 'string|max:40',
                'level' => 'nullable|array', 'level.*' => 'string|max:40',
                'ct' => 'nullable|array', 'ct.*' => 'string|max:40',
                'komp' => 'nullable|array', 'komp.*' => 'string|max:40',
                'fase' => 'nullable|array', 'fase.*' => 'string|max:40',
                'jenis' => 'nullable|array', 'jenis.*' => 'string|max:24',
                'prioritas' => 'nullable|array', 'prioritas.*' => 'string|max:16',
                'halaman' => 'nullable|integer|min:1',
            ]);

            // BERHALAMAN sejak banknya berisi seribu lebih pertanyaan.
            // Menggambar seluruhnya sekaligus membuat halaman ini berhenti
            // bisa dipakai justru ketika isinya sudah cukup lengkap.
            // Terbaru lebih dulu. Yang dicari orang di halaman ini hampir
            // selalu yang baru saja ia tambahkan atau sunting — bukan yang
            // kebetulan berada di awal abjad kelompoknya.
            $hasil = BankPertanyaan::cari($data + ['urut' => 'baru'], (int) ($data['halaman'] ?? 1), 50);
            $pakai = BankPertanyaan::pemakaian();

            $rows = collect($hasil['rows'])
                ->map(fn ($r) => $r + ['pemakaian' => $pakai[$r['kode']] ?? ['template' => 0, 'terbit' => 0, 'ubahan' => 0]])
                ->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'total' => $hasil['total'],
                'halaman' => (int) ($data['halaman'] ?? 1),
                'perHalaman' => 50,
                'siap' => true,
                'siapTag' => BankPertanyaan::siapTag(),
                'konteks' => BankPertanyaan::KONTEKS,
                'tipe' => Skrining::TIPE,
                'tipeBeropsi' => Skrining::TIPE_BEROPSI,
                'tipeBerskala' => Skrining::TIPE_BERSKALA,
                'tipeBerskor' => Skrining::TIPE_BERSKOR,
                'operator' => MesinSyarat::OPERATOR,
                'tag' => BankPertanyaan::tag(false),
                'jenisPertanyaan' => BankPertanyaan::JENIS,
                'prioritasPertanyaan' => BankPertanyaan::PRIORITAS,
                'statusTinjau' => BankPertanyaan::STATUS_TINJAU,
                // Kelompok tumbuh dari isi tabel, bukan dari master tersendiri —
                // isinya belasan baris, dan satu halaman CRUD untuk itu tidak
                // sepadan dengan tambahan tempat orang harus mencari.
                'kelompok' => DB::table(BankPertanyaan::TABEL)
                    ->where('Konteks', 'SKRINING')->whereNotNull('Kelompok')
                    ->distinct()->orderBy('Kelompok')->pluck('Kelompok'),
                // Sub-kelompok ikut dikirim BESERTA kelompok induknya, bukan
                // sebagai daftar datar: "Penempatan" milik Ketersediaan tidak
                // ada gunanya ditawarkan saat kelompoknya Teknis IT.
                'subKelompok' => BankPertanyaan::siapTag()
                    ? DB::table(BankPertanyaan::TABEL)
                        ->where('Konteks', 'SKRINING')
                        ->whereNotNull('Sub_Kelompok')
                        ->distinct()->orderBy('Kelompok')->orderBy('Sub_Kelompok')
                        ->get(['Kelompok', 'Sub_Kelompok'])
                        ->map(fn ($r) => ['kelompok' => $r->Kelompok, 'sub' => $r->Sub_Kelompok])
                        ->values()
                    : [],
            ], 'Bank pertanyaan');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat bank pertanyaan: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    // ═══════════════════════════ TULIS ═══════════════════════════

    private function aturan(Request $request): array
    {
        return $request->validate([
            // Boleh kosong: yang kosong dibuatkan sistem dari redaksi dan
            // kelompoknya. Tetap diterima bila diisi — kode lama yang sudah
            // beredar harus bisa dipertahankan apa adanya saat disunting.
            'kode' => 'nullable|string|max:40|regex:/^[a-z0-9_]+$/',
            'konteks' => 'nullable|string|in:'.implode(',', BankPertanyaan::KONTEKS),
            'kelompok' => 'nullable|string|max:60',
            'tipe' => 'required|string|in:'.implode(',', Skrining::TIPE),
            'label' => 'required|string|max:500',
            'bantuan' => 'nullable|string|max:500',
            'opsi' => 'nullable|array',
            'opsi.*.nilai' => 'required_with:opsi|string|max:120',
            'opsi.*.label' => 'nullable|string|max:200',
            'opsi.*.skor' => 'nullable|numeric|min:-999|max:999',
            'skalaMin' => 'nullable|integer|min:0|max:100',
            'skalaMax' => 'nullable|integer|min:1|max:100',
            'labelMin' => 'nullable|string|max:100',
            'labelMax' => 'nullable|string|max:100',
            'bobot' => 'nullable|numeric|min:0|max:9999',
            'knockoutOperator' => 'nullable|string|in:'.implode(',', MesinSyarat::OPERATOR),
            'knockoutNilai' => 'nullable|string|max:500',
            'knockoutPesan' => 'nullable|string|max:500',
            'wajib' => 'nullable|boolean',
            'catatan' => 'nullable|boolean',
            'urutan' => 'nullable|integer|min:0|max:9999',
            'subKelompok' => 'nullable|string|max:60',
            'jenis' => 'nullable|string|in:'.implode(',', BankPertanyaan::JENIS),
            'prioritas' => 'nullable|string|in:'.implode(',', BankPertanyaan::PRIORITAS),
            'durasiDetik' => 'nullable|integer|min:5|max:1800',
            'jawabanDiharapkan' => 'nullable|string|max:1000',
            'redFlag' => 'nullable|string|max:1000',
            'pertanyaanLanjutan' => 'nullable|string|max:1000',
        ], [
            'kode.regex' => 'Kode hanya boleh huruf kecil, angka, dan garis bawah (mis. gaji_harapan).',
        ]);
    }

    /**
     * Kode yang AKAN dipakai, tanpa menyimpan apa pun.
     *
     * Sengaja tidak memesan kodenya. Pemesanan menuntut tempat penyimpanan
     * kode-yang-belum-jadi beserta cara membersihkannya saat orang menutup
     * jendela tanpa menyimpan — dan pembersihan yang gagal meninggalkan kode
     * yang terpakai selamanya oleh pertanyaan yang tidak pernah ada.
     *
     * Konsekuensinya kode yang tampil bisa berubah bila ada orang lain yang
     * menyimpan kode sama persis dalam hitungan detik itu juga. Kalau itu
     * terjadi, penyimpanannya sendiri yang menyesuaikan diri — bukan gagal.
     */
    public function pratinjauKode(Request $request)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $d = $request->validate([
            'label' => 'required|string|max:500',
            'kelompok' => 'nullable|string|max:60',
        ]);

        return ResponseHelper::success([
            'kode' => KodeOtomatis::pertanyaan($d['label'], $d['kelompok'] ?? null),
        ]);
    }

    /**
     * Periksa yang tidak tertangkap aturan baris-per-baris.
     *
     * Sama persis dengan yang berlaku di penyunting template — kalau berbeda,
     * pertanyaan yang lolos di sini akan ditolak saat ditarik ke template, dan
     * orang harus memperbaikinya di tempat yang bukan tempat ia menulisnya.
     */
    private function periksa(array $d): ?string
    {
        $beropsi = in_array($d['tipe'], Skrining::TIPE_BEROPSI, true);
        $berskala = in_array($d['tipe'], Skrining::TIPE_BERSKALA, true);
        $opsi = $d['opsi'] ?? [];

        if ($beropsi && count($opsi) < 2) {
            return 'Tipe '.$d['tipe'].' butuh minimal dua pilihan — satu pilihan bukan pertanyaan.';
        }

        if ($beropsi) {
            $nilai = array_column($opsi, 'nilai');
            if (count(array_unique($nilai)) !== count($nilai)) {
                return 'Ada dua pilihan bernilai sama.';
            }
        }

        if ($berskala && (($d['skalaMax'] ?? 0) <= ($d['skalaMin'] ?? 0))) {
            return 'Batas atas skala harus lebih besar dari batas bawah.';
        }

        if (($d['bobot'] ?? null) !== null && ! in_array($d['tipe'], Skrining::TIPE_BERSKOR, true)) {
            return 'Tipe '.$d['tipe'].' tidak dinilai angka oleh sistem, jadi bobotnya tidak akan terpakai.';
        }

        if (! empty($d['knockoutOperator']) && ($d['knockoutNilai'] ?? '') === '') {
            return 'Bawaan auto-gugur belum lengkap — isi nilai pembandingnya, atau kosongkan operatornya.';
        }

        return null;
    }

    public function store(Request $request)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $d = $this->aturan($request);
        $konteks = $d['konteks'] ?? 'SKRINING';

        if ($salah = $this->periksa($d)) {
            return ResponseHelper::error($salah, 422);
        }

        $otomatis = empty($d['kode']);

        if (! $otomatis && DB::table(BankPertanyaan::TABEL)->where('Konteks', $konteks)->where('Kode', $d['kode'])->exists()) {
            return ResponseHelper::error('Kode "'.$d['kode'].'" sudah ada di bank.', 422);
        }

        // ── KODE OTOMATIS TANPA KUNCI ─────────────────────────────────────
        //
        // Kodenya ditebak dari satu pembacaan indeks, lalu disisipkan. Bila dua
        // orang kebetulan menebak sama persis pada saat yang sama, indeks unik
        // menolak yang kedua dan tebakan diulang — bukan seluruh tabel yang
        // ditahan menunggu giliran. Tiga percobaan sudah jauh di atas peluang
        // tabrakan yang mungkin terjadi.
        for ($coba = 1; ; $coba++) {
            if ($otomatis) {
                $d['kode'] = KodeOtomatis::pertanyaan($d['label'], $d['kelompok'] ?? null);
            }

            try {
                $baruId = DB::table(BankPertanyaan::TABEL)->insertGetId($this->isi($d) + [
                    'Kode' => $d['kode'],
                    'Konteks' => $konteks,
                    'Flag_Sistem' => 'T',
                    'Flag_Aktif' => 'Y',
                ] + $this->capBuat(), 'Id_Master_Pertanyaan');

                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($otomatis && $coba < 3 && $this->kodeGanda($e)) {
                    continue;
                }

                Log::channel('web_career')->error('Gagal menambah pertanyaan bank: '.$e->getMessage());

                return ResponseHelper::error(
                    $this->kodeGanda($e) ? 'Kode "'.$d['kode'].'" sudah ada di bank.' : 'Gagal menyimpan.',
                    $this->kodeGanda($e) ? 422 : 500
                );
            } catch (\Throwable $e) {
                Log::channel('web_career')->error('Gagal menambah pertanyaan bank: '.$e->getMessage());

                return ResponseHelper::error('Gagal menyimpan.', 500);
            }
        }

        $this->catat('ditambahkan', $d['kode'].' — '.mb_substr($d['label'], 0, 60));

        return ResponseHelper::success(
            ['id' => (int) $baruId, 'kode' => $d['kode']],
            $otomatis
                ? 'Pertanyaan ditambahkan ke bank dengan kode '.$d['kode'].'.'
                : 'Pertanyaan ditambahkan ke bank.'
        );
    }

    public function update(Request $request, int $id)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $baris = DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Pertanyaan tidak ditemukan.', 404);
        }

        $d = $this->aturan($request);
        if ($salah = $this->periksa($d)) {
            return ResponseHelper::error($salah, 422);
        }

        // Kode tidak lagi dikirim formulir saat menyunting. Yang kosong berarti
        // "biarkan seperti semula" — bukan "buatkan yang baru": kode yang
        // berganti diam-diam memutus agregasi seluruh sesi yang sudah tercatat.
        if (empty($d['kode'])) {
            $d['kode'] = $baris->Kode;
        }

        // KODE BAWAAN SISTEM TIDAK BOLEH BERGANTI.
        //
        // Kode itulah yang menyatukan pertanyaan yang sama di template berbeda
        // saat hasilnya diagregasi. Menggantinya memutus penyatuan itu untuk
        // seluruh sesi yang sudah tercatat — tanpa satu pun pesan galat, karena
        // yang tersimpan di sesi memang salinannya.
        if (($baris->Flag_Sistem ?? 'T') === 'Y' && $d['kode'] !== $baris->Kode) {
            return ResponseHelper::error('Kode pertanyaan bawaan sistem tidak bisa diubah. Redaksinya boleh.', 422);
        }

        $konteks = $d['konteks'] ?? $baris->Konteks;

        if ($d['kode'] !== $baris->Kode
            && DB::table(BankPertanyaan::TABEL)->where('Konteks', $konteks)->where('Kode', $d['kode'])
                ->where('Id_Master_Pertanyaan', '<>', $id)->exists()) {
            return ResponseHelper::error('Kode "'.$d['kode'].'" sudah ada di bank.', 422);
        }

        try {
            DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->update(
                $this->isi($d) + ['Kode' => $d['kode'], 'Konteks' => $konteks] + $this->capUbah()
            );

            $this->catat('disunting', $d['kode'].' — '.mb_substr($d['label'], 0, 60));

            $pakai = BankPertanyaan::pemakaian()[$baris->Kode] ?? null;

            return ResponseHelper::success(
                null,
                $pakai && $pakai['template'] > 0
                    ? sprintf(
                        'Pertanyaan diperbarui. %d template memakai salinan lamanya — segarkan drafnya bila perlu ikut berubah.',
                        $pakai['template']
                    )
                    : 'Pertanyaan diperbarui.'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyunting pertanyaan bank: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /** Aktif ⇄ nonaktif. Yang nonaktif tak lagi ditawarkan saat menyusun template. */
    public function toggle(int $id)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $baris = DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Pertanyaan tidak ditemukan.', 404);
        }

        $baru = ($baris->Flag_Aktif ?? 'Y') === 'Y' ? 'N' : 'Y';

        DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)
            ->update(['Flag_Aktif' => $baru] + $this->capUbah());

        $this->catat($baru === 'Y' ? 'diaktifkan' : 'dinonaktifkan', $baris->Kode);

        return ResponseHelper::success(
            ['aktif' => $baru === 'Y'],
            $baru === 'Y'
                ? 'Pertanyaan diaktifkan.'
                : 'Pertanyaan dinonaktifkan. Template yang sudah memakainya tidak berubah.'
        );
    }

    /**
     * Salin satu pertanyaan bank jadi pertanyaan baru.
     *
     * Cara paling lazim menambah varian: "gaji harapan" versi manajer yang
     * opsinya sama tapi bawaan knockout-nya berbeda. Menyalin lebih pendek
     * daripada mengetik ulang lima belas opsi.
     */
    public function duplikat(Request $request, int $id)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $asal = DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->first();
        if (! $asal) {
            return ResponseHelper::error('Pertanyaan tidak ditemukan.', 404);
        }

        $d = $request->validate([
            'kode' => 'nullable|string|max:40|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:500',
        ]);

        if (empty($d['kode'])) {
            // Kelompok asalnya dipakai supaya salinan tetap masuk keluarga
            // awalan yang sama — `it_` tetap `it_`, bukan pindah ke `q_`.
            $d['kode'] = KodeOtomatis::pertanyaan($d['label'], $asal->Kelompok ?? null);
        } elseif (DB::table(BankPertanyaan::TABEL)->where('Konteks', $asal->Konteks)->where('Kode', $d['kode'])->exists()) {
            return ResponseHelper::error('Kode "'.$d['kode'].'" sudah ada di bank.', 422);
        }

        $isi = (array) $asal;
        unset(
            $isi['Id_Master_Pertanyaan'],
            $isi['Created_At'], $isi['Created_By'], $isi['Created_By_Id'],
            $isi['Updated_At'], $isi['Updated_By'], $isi['Updated_By_Id'],
        );
        $isi['Kode'] = $d['kode'];
        $isi['Label'] = $d['label'];
        // Salinan tidak mewarisi status bawaan sistem: yang dilindungi adalah
        // daftar bawaan itu sendiri, bukan tiap turunannya.
        $isi['Flag_Sistem'] = 'T';
        $isi['Flag_Aktif'] = 'Y';

        try {
            DB::table(BankPertanyaan::TABEL)->insert($isi + $this->capBuat());
            $this->catat('diduplikat', $asal->Kode.' → '.$d['kode']);

            return ResponseHelper::success(['kode' => $d['kode']], 'Pertanyaan disalin sebagai '.$d['kode'].'.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menduplikat pertanyaan bank: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyalin.', 500);
        }
    }

    /**
     * Hapus dari bank.
     *
     * Template yang sudah memakainya TIDAK ikut rusak — mereka memegang
     * salinannya. Yang hilang adalah asal-usulnya: sesudah ini tidak ada lagi
     * yang bisa dipakai menyegarkan, dan analitik lintas template kehilangan
     * rujukan pusatnya. Karena itu yang sudah dipakai ditolak, dan yang bawaan
     * sistem tidak bisa dihapus sama sekali.
     */
    public function destroy(int $id)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $baris = DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Pertanyaan tidak ditemukan.', 404);
        }

        if (($baris->Flag_Sistem ?? 'T') === 'Y') {
            return ResponseHelper::error('Pertanyaan bawaan sistem tidak bisa dihapus — nonaktifkan saja.', 422);
        }

        $pakai = BankPertanyaan::pemakaian()[$baris->Kode] ?? null;
        if ($pakai && $pakai['template'] > 0) {
            return ResponseHelper::error(
                'Pertanyaan ini dipakai '.$pakai['template'].' template — nonaktifkan saja, jangan dihapus.',
                422
            );
        }

        try {
            DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->delete();
            $this->catat('dihapus', $baris->Kode);

            return ResponseHelper::success(null, 'Pertanyaan dihapus dari bank.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menghapus pertanyaan bank: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus.', 500);
        }
    }

    /**
     * Pasang ulang tag sebuah pertanyaan — ganti seluruhnya, bukan tambal.
     *
     * Layar mengirim daftar utuh setiap kali disimpan. Mencabut satu tag dan
     * memasang dua lainnya dalam satu simpanan mustahil dinyatakan sebagai
     * beda-per-baris tanpa mengirim id bayangan yang harus dijaga dua sisi.
     *
     * Tag yang berasal dari BENIH tetap ikut terganti: sesudah seseorang
     * menyunting tag sebuah pertanyaan secara sadar, keputusannya yang berlaku
     * — bukan bawaan benihnya.
     */
    public function simpanTag(Request $request, int $id)
    {
        if (! BankPertanyaan::siapTag()) {
            return ResponseHelper::error('Skema tag belum dijalankan.', 409);
        }

        $baris = DB::table(BankPertanyaan::TABEL)->where('Id_Master_Pertanyaan', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Pertanyaan tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'tag' => 'present|array',
            'tag.*.dimensi' => 'required|string|in:'.implode(',', BankPertanyaan::DIMENSI),
            'tag.*.kode' => 'required|string|max:40',
        ]);

        $sah = DB::table(BankPertanyaan::T_TAG)->get(['Id_Master_Tag', 'Dimensi', 'Kode'])
            ->keyBy(fn ($t) => $t->Dimensi.'|'.$t->Kode);

        try {
            DB::transaction(function () use ($id, $data, $sah) {
                DB::table(BankPertanyaan::T_IKAT_TAG)->where('Master_Pertanyaan_Id', $id)->delete();

                $sudah = [];
                foreach ($data['tag'] as $t) {
                    $kunci = $t['dimensi'].'|'.$t['kode'];
                    // Tag asing diabaikan diam-diam, bukan ditolak: layar bisa
                    // mengirim tag yang barusan dinonaktifkan orang lain, dan
                    // menggagalkan seluruh simpanan karena satu baris usang
                    // hanya membuat orang kehilangan pekerjaannya.
                    if (! isset($sah[$kunci]) || isset($sudah[$kunci])) {
                        continue;
                    }
                    $sudah[$kunci] = true;

                    DB::table(BankPertanyaan::T_IKAT_TAG)->insert([
                        'Master_Pertanyaan_Id' => $id,
                        'Master_Tag_Id' => (int) $sah[$kunci]->Id_Master_Tag,
                        'Dimensi' => $t['dimensi'],
                        'Tag_Kode' => $t['kode'],
                        'Created_At' => now(),
                        'Created_By' => session('career_auth.nama', 'ADMIN'),
                        'Created_By_Id' => session('career_auth.id'),
                    ]);
                }
            });

            $this->catat('tag disunting', $baris->Kode.' — '.count($data['tag']).' tag');

            return ResponseHelper::success(null, count($data['tag']).' tag tersimpan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan tag pertanyaan: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan tag.', 500);
        }
    }

    /**
     * Pasang satu tag ke BANYAK pertanyaan sekaligus.
     *
     * Alasan keberadaannya: menandai empat puluh pertanyaan teknis sebagai
     * milik sebuah job family satu per satu adalah pekerjaan yang membuat
     * orang berhenti memakai tag sama sekali.
     */
    public function tagMassal(Request $request)
    {
        if (! BankPertanyaan::siapTag()) {
            return ResponseHelper::error('Skema tag belum dijalankan.', 409);
        }

        $data = $request->validate([
            'id' => 'required|array|min:1', 'id.*' => 'integer',
            'dimensi' => 'required|string|in:'.implode(',', BankPertanyaan::DIMENSI),
            'kode' => 'required|string|max:40',
            'aksi' => 'required|string|in:PASANG,LEPAS',
        ]);

        $tag = DB::table(BankPertanyaan::T_TAG)
            ->where('Dimensi', $data['dimensi'])->where('Kode', $data['kode'])->first();

        if (! $tag) {
            return ResponseHelper::error('Tag tidak ditemukan.', 404);
        }

        try {
            if ($data['aksi'] === 'LEPAS') {
                $n = DB::table(BankPertanyaan::T_IKAT_TAG)
                    ->whereIn('Master_Pertanyaan_Id', $data['id'])
                    ->where('Master_Tag_Id', $tag->Id_Master_Tag)
                    ->delete();

                return ResponseHelper::success(['jml' => $n], "Tag dilepas dari {$n} pertanyaan.");
            }

            $sudah = DB::table(BankPertanyaan::T_IKAT_TAG)
                ->whereIn('Master_Pertanyaan_Id', $data['id'])
                ->where('Master_Tag_Id', $tag->Id_Master_Tag)
                ->pluck('Master_Pertanyaan_Id')->all();

            $baru = array_values(array_diff($data['id'], $sudah));
            foreach ($baru as $pid) {
                DB::table(BankPertanyaan::T_IKAT_TAG)->insert([
                    'Master_Pertanyaan_Id' => (int) $pid,
                    'Master_Tag_Id' => (int) $tag->Id_Master_Tag,
                    'Dimensi' => $tag->Dimensi,
                    'Tag_Kode' => $tag->Kode,
                    'Created_At' => now(),
                    'Created_By' => session('career_auth.nama', 'ADMIN'),
                    'Created_By_Id' => session('career_auth.id'),
                ]);
            }

            $this->catat('tag massal', $tag->Kode.' → '.count($baru).' pertanyaan');

            return ResponseHelper::success(
                ['jml' => count($baru)],
                count($baru).' pertanyaan diberi tag '.$tag->Nama
                .(count($sudah) ? ' ('.count($sudah).' sudah punya).' : '.')
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menandai massal: '.$e->getMessage());

            return ResponseHelper::error('Gagal menandai.', 500);
        }
    }

    // ═══════════════════════════ PENOLONG ═══════════════════════════

    private function isi(array $d): array
    {
        $beropsi = in_array($d['tipe'], Skrining::TIPE_BEROPSI, true);
        $berskala = in_array($d['tipe'], Skrining::TIPE_BERSKALA, true);
        $ko = ! empty($d['knockoutOperator']);

        return [
            'Kelompok' => $d['kelompok'] ?? null,
            'Tipe' => $d['tipe'],
            'Label' => $d['label'],
            'Bantuan' => $d['bantuan'] ?? null,
            'Opsi' => $beropsi && ! empty($d['opsi']) ? json_encode(array_values($d['opsi'])) : null,
            'Skala_Min' => $berskala ? ($d['skalaMin'] ?? 1) : null,
            'Skala_Max' => $berskala ? ($d['skalaMax'] ?? 5) : null,
            'Label_Min' => $berskala ? ($d['labelMin'] ?? null) : null,
            'Label_Max' => $berskala ? ($d['labelMax'] ?? null) : null,
            'Bobot_Bawaan' => $d['bobot'] ?? null,
            'Knockout_Operator' => $ko ? $d['knockoutOperator'] : null,
            'Knockout_Nilai' => $ko ? ($d['knockoutNilai'] ?? null) : null,
            'Knockout_Pesan' => $ko ? ($d['knockoutPesan'] ?? null) : null,
            'Flag_Wajib_Bawaan' => ! empty($d['wajib']) ? 'Y' : 'T',
            'Flag_Catatan_Bawaan' => ! empty($d['catatan']) ? 'Y' : 'T',
            'Urutan' => $d['urutan'] ?? 0,
        ] + (BankPertanyaan::siapTag() ? [
            // Metadata rekrutmen. Ditambahkan hanya bila kolomnya memang ada —
            // lingkungan yang belum menjalankan skrip tag tetap bisa menyimpan
            // pertanyaan seperti biasa.
            'Sub_Kelompok' => $d['subKelompok'] ?? null,
            'Jenis' => $d['jenis'] ?? null,
            'Prioritas' => $d['prioritas'] ?? 'RECOMMENDED',
            'Durasi_Detik' => $d['durasiDetik'] ?? null,
            'Jawaban_Diharapkan' => $d['jawabanDiharapkan'] ?? null,
            'Red_Flag' => $d['redFlag'] ?? null,
            'Pertanyaan_Lanjutan' => $d['pertanyaanLanjutan'] ?? null,
        ] : []);
    }

    /**
     * Galat ini berarti kode tebakannya keburu dipakai orang lain — bukan
     * kegagalan yang perlu dilaporkan sebagai kerusakan. 2601/2627 adalah nomor
     * SQL Server untuk indeks unik dan batasan unik.
     */
    private function kodeGanda(\Throwable $e): bool
    {
        $pesan = $e->getMessage();

        return str_contains($pesan, '2601') || str_contains($pesan, '2627')
            || stripos($pesan, 'duplicate key') !== false;
    }

    private function capBuat(): array
    {
        return [
            'Created_At' => now(),
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ];
    }

    private function capUbah(): array
    {
        return [
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ];
    }

    private function catat(string $aksi, string $apa): void
    {
        Log::channel('web_career')->info(sprintf(
            '[BANK PERTANYAAN] %s: %s, oleh %s.',
            $aksi, $apa, session('career_auth.nama', 'ADMIN')
        ));
    }
}
