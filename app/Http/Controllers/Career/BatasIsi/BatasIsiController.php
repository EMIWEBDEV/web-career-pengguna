<?php

namespace App\Http\Controllers\Career\BatasIsi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\AlurKolom;
use App\Support\Career\BatasIsi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — JADWAL PENGISIAN FORMULIR TAHAP, dari worklist.
 *
 *   program()     pasang jadwal satu kolom tahap sebuah program (sekali);
 *   perpanjang()  perpanjang batas akhir kolom itu — hanya maju, tak pernah mundur;
 *   lepas()       lepas batas waktu kolom itu — sesudah Master Alur mematikan jadwalnya;
 *   ubah()        EDIT jadwal kolom (boleh mundur) — KHUSUS SUPERADMIN, alasan wajib;
 *   riwayatKolom() jejak perubahan jadwal satu kolom (siapa, kapan, lama → baru);
 *   kandidat()    kandidat terpilih: atur jadwal (yang belum) / perpanjang (yang sudah)
 *                 / lepas (tahap yang di Master Alur sudah tanpa jadwal);
 *   riwayat()     jejak perubahan jadwal satu tahap kandidat (drawer).
 *
 * Keputusan user: sesudah dijadwalkan, jadwal TIDAK bisa disunting — hanya
 * diperpanjang, disesuaikan berkala. Pengecualiannya SUPERADMIN (kasus salah
 * klik): boleh mengedit, dengan alasan wajib dan tercatat. Aturannya di
 * App\Support\Career\BatasIsi; controller ini menjaga hak & lingkup lalu meneruskan.
 */
class BatasIsiController extends Controller
{
    private const PAGE = 'pelamarPage';

    /** Batas atas perpanjangan sekali jalan — sama dengan pemilih di layar. */
    private const MAKS_HARI = 60;

    private const MAKS_JAM = 72;

    private const PESAN_PERPANJANG = [
        'nilai.required_unless' => 'Isi berapa lama perpanjangannya.',
        'nilai.required_if' => 'Isi berapa lama perpanjangannya.',
        'nilai.max' => 'Perpanjangan sekali jalan paling lama '.self::MAKS_HARI.' hari atau '.self::MAKS_JAM.' jam — ulangi bila perlu lebih.',
        'sampai.required_if' => 'Pilih tanggal & jam batasnya.',
        'sampai.after' => 'Batasnya sudah lewat — pilih waktu yang akan datang.',
    ];

    /**
     * POST /api/v1/karir/batas-isi/program — PASANG jadwal kolom.
     *
     * Berlaku untuk SELURUH kandidat program di tahap itu, jadi hanya akun tanpa
     * batas lingkup PIC yang boleh — rekruter yang memegang sebagian loker
     * memakai "Jadwal" pada kandidat terpilih.
     */
    public function program(Request $request)
    {
        if ($tolak = $this->tolakBelumSiap()) {
            return $tolak;
        }

        $data = $request->validate([
            'programId' => 'required|string|max:64',
            'kodeTahap' => 'required|string|max:30',
            'buka' => 'required|date_format:Y-m-d H:i:s',
            'batas' => 'required|date_format:Y-m-d H:i:s|after:buka|after:now',
        ], [
            'buka.required' => 'Isi kapan formulir dibuka.',
            'batas.required' => 'Isi batas akhirnya.',
            'batas.after' => 'Batas akhir harus sesudah waktu formulir dibuka dan belum lewat.',
        ]);
        if ($galat = self::galatBukaLampau(Carbon::parse($data['buka']))) {
            return ResponseHelper::error($galat, 422);
        }

        [$programId, $kolom, $galat] = $this->kolomProgram($data['programId'], $data['kodeTahap']);
        if ($galat) {
            return $galat;
        }

        $buka = Carbon::parse($data['buka']);
        $batas = Carbon::parse($data['batas']);
        $oleh = session('career_auth.nama', 'ADMIN');

        try {
            $r = BatasIsi::pasangProgram($programId, $data['kodeTahap'], $buka, $batas, $oleh, session('career_auth.id'));
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] gagal pasang kolom: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan jadwal.', 500);
        }

        if ($r === null) {
            return ResponseHelper::error('Jadwal kolom ini sudah diatur — jadwal hanya bisa diperpanjang.', 409);
        }

        Log::channel('web_career')->info(sprintf(
            '[JADWAL] program #%d tahap %s: %s s/d %s oleh %s (%d kandidat).',
            $programId, $data['kodeTahap'], $buka->format('Y-m-d H:i'), $batas->format('Y-m-d H:i'), $oleh, $r['diubah']
        ));

        $pesan = "Jadwal {$kolom['label']}: dibuka ".BatasIsi::teks($buka).', batas '.BatasIsi::teks($batas).". {$r['diubah']} kandidat mengikuti.";
        if ($r['lebihLambat'] > 0) {
            $pesan .= " {$r['lebihLambat']} kandidat yang batas pribadinya lebih lambat tidak diubah.";
        }

        return ResponseHelper::success($r, $pesan);
    }

    /** POST /api/v1/karir/batas-isi/program/perpanjang — batas akhir kolom maju. */
    public function perpanjang(Request $request)
    {
        if ($tolak = $this->tolakBelumSiap()) {
            return $tolak;
        }

        $data = $request->validate([
            'programId' => 'required|string|max:64',
            'kodeTahap' => 'required|string|max:30',
            'cara' => 'required|in:HARI,JAM,SAMPAI',
            'nilai' => 'required_unless:cara,SAMPAI|nullable|integer|min:1|max:'.self::maksNilai($request->input('cara')),
            'sampai' => 'required_if:cara,SAMPAI|nullable|date_format:Y-m-d H:i:s|after:now',
        ], self::PESAN_PERPANJANG);

        [$programId, $kolom, $galat] = $this->kolomProgram($data['programId'], $data['kodeTahap']);
        if ($galat) {
            return $galat;
        }

        $oleh = session('career_auth.nama', 'ADMIN');

        try {
            $r = BatasIsi::perpanjangProgram(
                $programId, $data['kodeTahap'], $data['cara'],
                isset($data['nilai']) ? (int) $data['nilai'] : null,
                ! empty($data['sampai']) ? Carbon::parse($data['sampai']) : null,
                $oleh, session('career_auth.id'),
            );
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] gagal perpanjang kolom: '.$e->getMessage());

            return ResponseHelper::error('Gagal memperpanjang jadwal.', 500);
        }

        Log::channel('web_career')->info(sprintf(
            '[JADWAL] program #%d tahap %s diperpanjang %s → %s oleh %s (%d kandidat).',
            $programId, $data['kodeTahap'], $r['lama']->format('Y-m-d H:i'), $r['baru']->format('Y-m-d H:i'), $oleh, $r['diubah']
        ));

        return ResponseHelper::success(
            ['batas' => $r['baru']->format('Y-m-d H:i:s'), 'diubah' => $r['diubah']],
            "Batas {$kolom['label']} diperpanjang sampai ".BatasIsi::teks($r['baru']).". {$r['diubah']} kandidat ikut maju.",
        );
    }

    /**
     * POST /api/v1/karir/batas-isi/program/lepas — lepas batas waktu satu kolom.
     *
     * Hanya bila Master Alur tahap itu sudah "Tanpa jadwal": kandidat yang masih
     * terikat jadwal lama (snapshot) bisa mengisi tanpa batas waktu, dan jadwal
     * kolomnya dihapus. Selama tahapnya masih berjadwal, jalannya perpanjang.
     */
    public function lepas(Request $request)
    {
        if ($tolak = $this->tolakBelumSiap()) {
            return $tolak;
        }

        $data = $request->validate([
            'programId' => 'required|string|max:64',
            'kodeTahap' => 'required|string|max:30',
        ]);

        [$programId, $kolom, $galat] = $this->kolomProgram($data['programId'], $data['kodeTahap']);
        if ($galat) {
            return $galat;
        }

        if (BatasIsi::berjadwal(BatasIsi::modeSekarang($programId, $data['kodeTahap']))) {
            return ResponseHelper::error(
                'Tahap ini masih berjadwal di Master Alur. Ubah ke "Tanpa jadwal" dulu bila batas waktunya ingin dilepas — selama masih berjadwal, jadwalnya hanya bisa diperpanjang.',
                422,
            );
        }

        $oleh = session('career_auth.nama', 'ADMIN');

        try {
            $r = BatasIsi::lepasProgram($programId, $data['kodeTahap'], $oleh, session('career_auth.id'));
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] gagal lepas kolom: '.$e->getMessage());

            return ResponseHelper::error('Gagal melepas batas waktu.', 500);
        }

        Log::channel('web_career')->info(sprintf(
            '[JADWAL] program #%d tahap %s dilepas oleh %s (%d kandidat, jadwal kolom %s).',
            $programId, $data['kodeTahap'], $oleh, $r['dilepas'], $r['jadwalDihapus'] ? 'dihapus' : 'tidak ada'
        ));

        return ResponseHelper::success(
            $r,
            "Batas waktu {$kolom['label']} dilepas — {$r['dilepas']} kandidat kini bisa mengisi formulir tanpa batas waktu."
                .($r['jadwalDihapus'] ? ' Jadwal kolomnya dihapus.' : ''),
        );
    }

    /**
     * POST /api/v1/karir/batas-isi/program/ubah — EDIT jadwal kolom.
     *
     * KHUSUS SUPERADMIN, untuk kasus salah klik: waktu dibuka & batas akhir
     * boleh maju maupun mundur. Alasan wajib; tercatat di riwayat kolom dan di
     * riwayat setiap kandidat yang ikut berubah.
     */
    public function ubah(Request $request)
    {
        if ($tolak = $this->tolakBelumSiap() ?? $this->tolakBukanSuperadmin()) {
            return $tolak;
        }

        $data = $request->validate([
            'programId' => 'required|string|max:64',
            'kodeTahap' => 'required|string|max:30',
            'buka' => 'required|date_format:Y-m-d H:i:s',
            'batas' => 'required|date_format:Y-m-d H:i:s|after:buka',
            'alasan' => 'required|string|min:5|max:300',
        ], [
            'buka.required' => 'Isi kapan formulir dibuka.',
            'batas.required' => 'Isi batas akhirnya.',
            'batas.after' => 'Batas akhir harus sesudah waktu formulir dibuka.',
            'alasan.required' => 'Tulis alasan pengeditan — tercatat di riwayat.',
            'alasan.min' => 'Alasan terlalu pendek — tulis sebabnya, mis. "salah pilih tanggal".',
        ]);

        [$programId, $kolom, $galat] = $this->kolomProgram($data['programId'], $data['kodeTahap']);
        if ($galat) {
            return $galat;
        }

        $buka = Carbon::parse($data['buka']);
        $batas = Carbon::parse($data['batas']);
        $oleh = session('career_auth.nama', 'ADMIN');

        try {
            $r = BatasIsi::ubahProgram($programId, $data['kodeTahap'], $buka, $batas, $data['alasan'], $oleh, session('career_auth.id'));
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] gagal edit kolom: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengedit jadwal.', 500);
        }

        Log::channel('web_career')->warning(sprintf(
            '[JADWAL] SUPERADMIN %s mengedit jadwal program #%d tahap %s: %s s/d %s → %s s/d %s (%d kandidat). Alasan: %s',
            $oleh, $programId, $data['kodeTahap'], $r['bukaLama'] ?? '-', $r['batasLama'], $buka->format('Y-m-d H:i'), $batas->format('Y-m-d H:i'),
            $r['diubah'], $data['alasan']
        ));

        $pesan = "Jadwal {$kolom['label']} diedit: dibuka ".BatasIsi::teks($buka).', batas '.BatasIsi::teks($batas).". {$r['diubah']} kandidat mengikuti.";
        if ($r['langsungLewat'] > 0) {
            $pesan .= " Perhatian: batasnya sudah lewat — {$r['langsungLewat']} kandidat langsung terkunci.";
        }

        return ResponseHelper::success($r, $pesan);
    }

    /** GET /api/v1/karir/batas-isi/program/riwayat — jejak perubahan jadwal satu kolom. */
    public function riwayatKolom(Request $request)
    {
        if (! BatasIsi::siap()) {
            return ResponseHelper::success([], 'Belum ada riwayat.');
        }

        $data = $request->validate([
            'programId' => 'required|string|max:64',
            'kodeTahap' => 'required|string|max:30',
        ]);

        $programId = Hashids::decode($data['programId'])[0] ?? null;
        $program = $programId ? DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first() : null;
        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        if (! $program || ($izin && ! in_array($program->Kategori, $izin, true))) {
            return ResponseHelper::error('Program tidak ditemukan.', 404);
        }

        $teks = fn ($v) => $v ? BatasIsi::teks($v) : null;
        $baris = BatasIsi::riwayatKolom((int) $programId, $data['kodeTahap'])->map(fn ($r) => [
            'aksi' => $r->Aksi,
            'bukaLama' => $teks($r->Buka_Lama),
            'bukaBaru' => $teks($r->Buka_Baru),
            'batasLama' => $teks($r->Batas_Lama),
            'batasBaru' => $teks($r->Batas_Baru),
            'jumlah' => $r->Jumlah_Kandidat !== null ? (int) $r->Jumlah_Kandidat : null,
            'alasan' => $r->Alasan,
            'oleh' => $r->Created_By,
            'peran' => $r->Created_By_Role,
            'at' => (string) $r->Created_At,
        ]);

        return ResponseHelper::success($baris, 'Riwayat jadwal kolom');
    }

    /**
     * POST /api/v1/karir/batas-isi/kandidat — satuan maupun massal.
     *
     *   ATUR          untuk yang BELUM berjadwal (waktu dibuka opsional + batas);
     *   HARI/JAM/SAMPAI  perpanjang yang SUDAH berjadwal — hanya maju;
     *   LEPAS         lepas batas waktu — hanya tahap yang di Master Alur sudah
     *                 "Tanpa jadwal";
     *   UBAH          KHUSUS SUPERADMIN: tetapkan waktu dibuka & batas apa adanya
     *                 (boleh mundur), alasan wajib.
     *
     * Hanya kandidat dalam kategori & lingkup PIC pengguna ini yang disentuh;
     * sisanya dihitung sebagai dilewati, bukan menggagalkan semuanya.
     */
    public function kandidat(Request $request)
    {
        if ($tolak = $this->tolakBelumSiap()) {
            return $tolak;
        }

        $ubah = $request->input('cara') === 'UBAH';
        if ($ubah && ($tolak = $this->tolakBukanSuperadmin())) {
            return $tolak;
        }

        $data = $request->validate([
            'tahapIds' => 'required|array|min:1|max:500',
            'tahapIds.*' => 'required|string|max:64',
            'cara' => 'required|in:ATUR,HARI,JAM,SAMPAI,LEPAS,UBAH',
            'buka' => 'nullable|date_format:Y-m-d H:i:s',
            // Superadmin yang mengedit boleh menetapkan batas yang sudah lewat
            // (menutup formulir lebih awal); jalan lain tidak.
            'sampai' => 'required_if:cara,ATUR,SAMPAI,UBAH|nullable|date_format:Y-m-d H:i:s'.($ubah ? '' : '|after:now'),
            'nilai' => 'required_if:cara,HARI,JAM|nullable|integer|min:1|max:'.self::maksNilai($request->input('cara')),
            'alasan' => ($ubah ? 'required|string|min:5' : 'nullable|string').'|max:300',
        ], self::PESAN_PERPANJANG + [
            'alasan.required' => 'Tulis alasan pengeditan — tercatat di riwayat.',
            'alasan.min' => 'Alasan terlalu pendek — tulis sebabnya, mis. "salah pilih tanggal".',
        ]);

        $buka = in_array($data['cara'], ['ATUR', 'UBAH'], true) && ! empty($data['buka']) ? Carbon::parse($data['buka']) : null;
        $sampai = ! empty($data['sampai']) ? Carbon::parse($data['sampai']) : null;
        // UBAH superadmin sengaja boleh ke masa lalu — membetulkan jadwal yang salah.
        if ($data['cara'] === 'ATUR' && $buka && ($galat = self::galatBukaLampau($buka))) {
            return ResponseHelper::error($galat, 422);
        }
        if ($buka && $sampai && ! $sampai->gt($buka)) {
            return ResponseHelper::error('Batas akhir harus sesudah waktu formulir dibuka.', 422);
        }

        $ids = collect($data['tahapIds'])->map(fn ($h) => Hashids::decode($h)[0] ?? null)->filter()->values()->all();
        $boleh = $this->dalamLingkup($ids);
        $oleh = session('career_auth.nama', 'ADMIN');

        try {
            $r = BatasIsi::aturKandidat(
                $boleh, $data['cara'], $buka, $sampai,
                isset($data['nilai']) ? (int) $data['nilai'] : null,
                $data['alasan'] ?? null, $oleh, session('career_auth.id'),
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] gagal atur kandidat: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan jadwal.', 500);
        }

        $dilewati = $r['dilewati'] + (count($data['tahapIds']) - count($boleh));
        Log::channel('web_career')->info(sprintf('[JADWAL] %s untuk %d kandidat oleh %s (%d dilewati).', $data['cara'], $r['diubah'], $oleh, $dilewati));

        if ($r['diubah'] === 0 && $data['cara'] === 'LEPAS') {
            return ResponseHelper::error(
                'Tidak ada yang dilepas — tahapnya masih berjadwal di Master Alur, kandidat tidak terikat jadwal, atau sudah mengirim formulir.',
                422,
            );
        }

        if ($r['diubah'] === 0 && $ubah) {
            return ResponseHelper::error(
                'Tidak ada yang diedit — kandidat terpilih tidak terikat jadwal (tahapnya tanpa jadwal di Master Alur) atau sudah mengirim formulir.',
                422,
            );
        }

        if ($r['diubah'] === 0) {
            return ResponseHelper::error(
                $data['cara'] === 'ATUR'
                    ? 'Tidak ada yang diubah — kandidat terpilih sudah berjadwal (jadwalnya hanya bisa diperpanjang), tahapnya tanpa jadwal di Master Alur, atau tidak sedang mengisi formulir.'
                    : 'Tidak ada yang diperpanjang — kandidat terpilih belum berjadwal, batas barunya tidak lebih lambat, atau tidak sedang mengisi formulir.',
                422,
            );
        }

        $kata = ['ATUR' => 'dijadwalkan', 'LEPAS' => 'dilepas dari batas waktu', 'UBAH' => 'diedit jadwalnya'][$data['cara']] ?? 'diperpanjang';

        return ResponseHelper::success(
            ['diubah' => $r['diubah'], 'dilewati' => $dilewati],
            "{$r['diubah']} kandidat {$kata}.".($dilewati ? " {$dilewati} dilewati." : ''),
        );
    }

    /** GET /api/v1/karir/batas-isi/riwayat/{id} — jejak perubahan jadwal satu tahap. */
    public function riwayat(string $id)
    {
        if (! BatasIsi::siap()) {
            return ResponseHelper::success([], 'Belum ada riwayat.');
        }

        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId || ! $this->dalamLingkup([$realId])) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $baris = DB::table(BatasIsi::T_RIWAYAT)
            ->where('Lamaran_Tahap_Id', $realId)
            ->orderByDesc('Id_Batas_Riwayat')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'bukaBaru' => ($r->Buka_Baru ?? null) ? BatasIsi::teks($r->Buka_Baru) : null,
                'lama' => $r->Batas_Lama ? BatasIsi::teks($r->Batas_Lama) : null,
                'baru' => $r->Batas_Baru ? BatasIsi::teks($r->Batas_Baru) : null,
                'sumber' => $r->Sumber,
                'alasan' => $r->Alasan,
                'oleh' => $r->Created_By,
                'at' => (string) $r->Created_At,
            ]);

        return ResponseHelper::success($baris, 'Riwayat jadwal');
    }

    /**
     * Hari yang sudah lewat tidak bisa dipilih sebagai waktu formulir dibuka
     * (masukan user 2 Okt 2026) — cermin kalendernya (sebelumHariIni di
     * Pelamar.vue). Jam yang sudah lewat HARI INI tetap boleh: artinya
     * "dibuka sekarang", dan itulah isian bawaannya (waktuKini).
     */
    private static function galatBukaLampau(Carbon $buka): ?string
    {
        return $buka->lt(now()->startOfDay())
            ? 'Waktu formulir dibuka tidak boleh di hari yang sudah lewat — pilih hari ini atau sesudahnya.'
            : null;
    }

    private static function maksNilai(mixed $cara): int
    {
        return $cara === 'JAM' ? self::MAKS_JAM : self::MAKS_HARI;
    }

    /** Mengedit jadwal (boleh mundur) hanya untuk SUPERADMIN — keputusan user. */
    private function tolakBukanSuperadmin()
    {
        return session('career_auth.role') === 'SUPERADMIN'
            ? null
            : ResponseHelper::error('Mengedit jadwal hanya bisa dilakukan superadmin. Admin memakai Perpanjang.', 403);
    }

    private function tolakBelumSiap()
    {
        return BatasIsi::siap()
            ? null
            : ResponseHelper::error('Fitur jadwal pengisian belum aktif (skrip basis data belum dijalankan).', 409);
    }

    /**
     * Program + kolom yang boleh dijadwalkan pengguna ini.
     *
     * @return array{0:int|null, 1:array|null, 2:\Illuminate\Http\JsonResponse|null}
     */
    private function kolomProgram(string $programHash, string $kodeTahap): array
    {
        $programId = Hashids::decode($programHash)[0] ?? null;
        $program = $programId ? DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first() : null;
        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        if (! $program || ($izin && ! in_array($program->Kategori, $izin, true))) {
            return [null, null, ResponseHelper::error('Program tidak ditemukan.', 404)];
        }

        if (AksesService::picDiizinkan(self::PAGE) !== null) {
            return [null, null, ResponseHelper::error(
                'Jadwal kolom berlaku untuk seluruh kandidat program, sementara akses Anda terbatas pada loker tertentu. '
                    .'Pakai tombol "Jadwal" pada kandidat terpilih.',
                403,
            )];
        }

        // Kolomnya dibaca seperti yang digambar papan (AlurKolom), dan boleh
        // tidaknya dijadwalkan ditentukan Master Alur — aturan yang sama dengan
        // `bolehBatas` di papan, bukan posisi tahap.
        $alurProgramId = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->value('Id_Master_Alur');
        $alur = $alurProgramId ? (int) $alurProgramId : null;
        $kolom = collect(AlurKolom::susun(AlurKolom::alurDipakai((int) $programId, $alur), $alur))->firstWhere('kode', $kodeTahap);

        if (! $kolom || empty($kolom['formulir'])) {
            return [null, null, ResponseHelper::error('Tahap ini bukan tahap pengisian formulir, jadi tidak bisa dijadwalkan.', 422)];
        }

        $adaJadwal = DB::table(BatasIsi::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->exists();
        if (! BatasIsi::kolomBolehDijadwal($kolom, $adaJadwal, BatasIsi::adaKandidatBerjadwal((int) $programId, $kodeTahap))) {
            return [null, null, ResponseHelper::error(
                'Tahap ini tanpa jadwal di Master Alur. Pilih "Dijadwalkan admin" atau "Otomatis" pada tahapnya dulu.',
                422,
            )];
        }

        return [(int) $programId, $kolom, null];
    }

    /**
     * Saring Id_Lamaran_Tahap ke yang boleh disentuh pengguna ini: kategori
     * program & lingkup PIC loker — aturan yang sama dengan papan worklist.
     *
     * @param  int[]  $ids
     * @return int[]
     */
    private function dalamLingkup(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        $pic = AksesService::picDiizinkan(self::PAGE);

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->whereIn('t.Id_Lamaran_Tahap', $ids)
            ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
            ->when($pic !== null, fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']))
            ->pluck('t.Id_Lamaran_Tahap')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
