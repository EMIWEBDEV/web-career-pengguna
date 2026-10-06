<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\HtmlBersih;
use App\Support\Career\Skrining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — SESI PHONE SCREENING yang benar-benar dijalankan rekruter.
 *
 * Satu aktivitas skrining di worklist = satu sesi di sini. Sesi menyimpan
 * jalannya wawancara (metode, berapa kali ditelepon, durasi), jawabannya, dan
 * ringkasan petugas.
 *
 * ── TIGA HAL YANG DIJAGA BERKAS INI ─────────────────────────────────────────
 *
 * 1. SESI MEMBEKUKAN PERTANYAANNYA SENDIRI.
 *    Saat sesi dibuka, seluruh pertanyaan versi terpakai disalin ke baris
 *    jawaban — label, tipe, opsi, bobot, penanda knockout. Menyunting master
 *    besok tidak menulis ulang sesi hari ini, bahkan bila baris masternya
 *    kelak terhapus.
 *
 * 2. SESI YANG SUDAH SELESAI TERKUNCI.
 *    Menyuntingnya lagi menuntut buka kunci, dan bukanya tercatat. Tanpa itu,
 *    skor yang sudah dipakai memutuskan kandidat bisa diubah diam-diam
 *    sesudah keputusannya diketuk.
 *
 * 3. KNOCKOUT MENANDAI, TIDAK MENGGUGURKAN.
 *    Hasilnya rekomendasi — palu tetap diketuk lewat mesin keputusan tahap,
 *    sepola MesinSyarat yang mengembalikan `lolos` lalu menyerahkan
 *    keputusannya ke pemanggil.
 */
class SkriningSesiController extends Controller
{
    /** Isi panel skrining satu aktivitas — pertanyaan, jawaban, keadaan sesi. */
    public function show(string $tesId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        // ── ID AKTIVITAS DATANG SEBAGAI HASHID ──────────────────────────
        //
        // Sepola seluruh rute /lamaran/sub-tes/* yang lain. Dulu parameter ini
        // bertipe `int`, dan PHP dengan senang hati mengubah "48o7" jadi 48 —
        // tanpa galat, tanpa peringatan yang sampai ke layar. Sesi skrining
        // lalu dibuka untuk AKTIVITAS ORANG LAIN, dan tidak ada satu pun tanda
        // bahwa itu terjadi.
        $realId = Hashids::decode($tesId)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $sub = $this->aktivitas((int) $realId);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $bentuk = Skrining::bentuk($sub, true);
        if (! $bentuk) {
            return ResponseHelper::error('Aktivitas ini bukan tahap skrining.', 422);
        }

        return ResponseHelper::success($bentuk + [
            'petunjuk' => $this->petunjuk($sub->Skrining_Kode ?? null),
        ], 'Panel skrining dimuat.');
    }

    /**
     * Buka sesi — sekali per aktivitas.
     *
     * Di sinilah pertanyaan dibekukan. Memanggilnya dua kali tidak membuat sesi
     * kedua: baris yang sudah ada dikembalikan apa adanya, karena indeks unik
     * bersaring UX_NWC_LmrSkr_Aktif memang cuma mengizinkan satu sesi hidup per
     * aktivitas, dan menabraknya akan berakhir sebagai galat basis data yang
     * tidak menjelaskan apa-apa ke rekruter.
     */
    public function mulai(Request $request, string $tesId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $realId = Hashids::decode($tesId)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }
        $tesId = (int) $realId;

        $sub = $this->aktivitas($tesId);
        if (! $sub || ! Skrining::untuk($sub->Tipe_Tahap_Kode ?? null)) {
            return ResponseHelper::error('Aktivitas ini bukan tahap skrining.', 404);
        }

        $ada = DB::table(Skrining::T_SESI)
            ->where('Lamaran_Tahap_Tes_Id', $tesId)
            ->whereNull('Arsip_At')
            ->first();

        if ($ada) {
            return ResponseHelper::success(
                ['id' => Hashids::encode($ada->Id_Lamaran_Skrining)],
                'Sesi sudah dibuka sebelumnya.'
            );
        }

        if (! $sub->Skrining_Kode) {
            return ResponseHelper::error(
                'Aktivitas ini belum terikat template skrining. Pasang dulu di Program Kegiatan.',
                422
            );
        }

        $pakai = Skrining::versiTerpakai($sub->Skrining_Kode, (int) $sub->Skrining_Versi);
        if (! $pakai) {
            return ResponseHelper::error(
                'Template "'.$sub->Skrining_Kode.'" v'.$sub->Skrining_Versi.' tidak ditemukan lagi di master.',
                422
            );
        }

        $data = $request->validate([
            'metode' => 'nullable|string|in:'.implode(',', Skrining::METODE),
            'kontakNomor' => 'nullable|string|max:40',
        ]);

        try {
            $id = DB::transaction(function () use ($sub, $tesId, $pakai, $data) {
                $id = DB::table(Skrining::T_SESI)->insertGetId([
                    'Lamaran_Id' => $sub->Lamaran_Id,
                    'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
                    'Lamaran_Tahap_Tes_Id' => $tesId,
                    'Skrining_Kode' => $pakai['kode'],
                    'Skrining_Versi' => $pakai['versi'],
                    'Nama_Snapshot' => $pakai['nama'],
                    'Status' => 'DRAF',
                    'Metode' => $data['metode'] ?? 'TELEPON',
                    // NOMOR BAWAAN DARI AKUN KANDIDAT.
                    //
                    // Nomor yang hendak dihubungi rekruter praktis selalu nomor
                    // yang didaftarkan kandidat sendiri. Membiarkannya kosong
                    // memaksa rekruter membuka tab lain, menyalin, lalu
                    // menempelkannya — setiap sesi, untuk data yang sudah ada di
                    // baris yang sama. Dan nomor yang diketik ulang adalah nomor
                    // yang bisa salah ketik.
                    //
                    // Tetap BISA DIGANTI: yang diisi cuma nilai awal, bukan
                    // kunci. Kandidat kerap memberi nomor lain saat dihubungi
                    // ("pakai nomor kantor saja"), dan nomor itulah yang benar
                    // dicatat sebagai yang dihubungi.
                    //
                    // Yang dikirim layar tetap didahulukan bila ada.
                    'Kontak_Nomor' => $data['kontakNomor'] ?? $this->nomorAkun((int) $sub->Lamaran_Id),
                    'Percobaan' => 0,
                    'Waktu_Mulai' => now(),
                    'Petugas' => session('career_auth.nama', 'ADMIN'),
                    'Petugas_Id' => session('career_auth.id'),
                    'Knockout_Flag' => 'T',
                ] + $this->capBuat(), 'Id_Lamaran_Skrining');

                $this->bekukanPertanyaan($id, $pakai['versiId']);

                return $id;
            });

            Log::channel('web_career')->info(sprintf(
                '[SKRINING] sesi dibuka: lamaran #%d aktivitas #%d, template %s v%d, oleh %s.',
                $sub->Lamaran_Id, $tesId, $pakai['kode'], $pakai['versi'], session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(['id' => $id], 'Sesi skrining dibuka.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuka sesi skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuka sesi.', 500);
        }
    }

    /**
     * Simpan jawaban + kepala sesi.
     *
     * Simpanan bersifat PARSIAL dan itu disengaja: wawancara telepon jarang
     * selesai dalam satu duduk, dan memaksa seluruh jawaban lengkap sebelum
     * boleh menyimpan berarti kehilangan semuanya begitu teleponnya putus.
     * Kelengkapan baru dituntut saat sesi diselesaikan.
     */
    public function simpan(Request $request, string $sesiId)
    {
        $sesi = $this->sesiTersunting($sesiId);
        if (! $sesi instanceof \stdClass) {
            return $sesi;
        }

        // Id nyata diambil dari BARIS yang sudah ditemukan, bukan diurai ulang
        // dari hash-nya. Satu penguraian per permintaan, dan tidak ada jalan
        // untuk keduanya berselisih.
        $sesiId = (int) $sesi->Id_Lamaran_Skrining;

        $data = $request->validate([
            'metode' => 'nullable|string|in:'.implode(',', Skrining::METODE),
            'kontakNomor' => 'nullable|string|max:40',
            'percobaan' => 'nullable|integer|min:0|max:99',
            'hasilKontak' => 'nullable|string|in:'.implode(',', Skrining::HASIL_KONTAK),
            'durasiMenit' => 'nullable|integer|min:0|max:1440',
            'rekomendasi' => 'nullable|string|in:'.implode(',', Skrining::REKOMENDASI),
            'ringkasanHtml' => 'nullable|string',
            'jawaban' => 'present|array',
            'jawaban.*.kode' => 'required|string|max:40',
            'jawaban.*.nilai' => 'nullable',
            'jawaban.*.catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $hasil = DB::transaction(function () use ($sesi, $sesiId, $data) {
                foreach ($data['jawaban'] as $j) {
                    $this->simpanSatuJawaban($sesiId, $j);
                }

                return $this->hitungUlang($sesi, $sesiId, $data);
            });

            return ResponseHelper::success($hasil, 'Tersimpan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /**
     * Selesaikan sesi — di sinilah kelengkapan baru dituntut.
     *
     * Pertanyaan wajib yang kosong ditolak DENGAN MENYEBUT MANA SAJA, bukan
     * satu per satu: rekruter yang baru menutup telepon tidak boleh disuruh
     * menebak-nebak lewat lima kali percobaan simpan.
     */
    public function selesaikan(Request $request, string $sesiId)
    {
        $sesi = $this->sesiTersunting($sesiId);
        if (! $sesi instanceof \stdClass) {
            return $sesi;
        }

        $sesiId = (int) $sesi->Id_Lamaran_Skrining;

        $data = $request->validate([
            'rekomendasi' => 'required|string|in:'.implode(',', Skrining::REKOMENDASI),
            'ringkasanHtml' => 'nullable|string',
            'jawaban' => 'present|array',
            'jawaban.*.kode' => 'required|string|max:40',
            'jawaban.*.nilai' => 'nullable',
            'jawaban.*.catatan' => 'nullable|string|max:1000',
        ]);

        $pakai = Skrining::versiTerpakai($sesi->Skrining_Kode, (int) $sesi->Skrining_Versi);

        try {
            $hasil = DB::transaction(function () use ($sesi, $sesiId, $data, $pakai) {
                foreach ($data['jawaban'] as $j) {
                    $this->simpanSatuJawaban($sesiId, $j);
                }

                $kurang = $this->wajibKosong($sesiId, $pakai['versiId'] ?? null);
                if ($kurang) {
                    // Dilempar supaya transaksinya batal — jawaban yang barusan
                    // masuk tetap tersimpan lewat panggilan `simpan` berikutnya,
                    // tapi sesinya tidak boleh terkunci setengah jadi.
                    throw new \RuntimeException(
                        'Belum lengkap: '.implode(', ', array_slice($kurang, 0, 5))
                        .(count($kurang) > 5 ? ' (+'.(count($kurang) - 5).' lagi)' : '')
                    );
                }

                $hasil = $this->hitungUlang($sesi, $sesiId, $data);

                DB::table(Skrining::T_SESI)->where('Id_Lamaran_Skrining', $sesiId)->update([
                    'Status' => 'SELESAI',
                    'Waktu_Selesai' => now(),
                    'Dikunci_At' => now(),
                ] + $this->capUbah());

                // ── AKTIVITASNYA IKUT DITUTUP ───────────────────────────
                //
                // Dulu yang ditutup hanya SESI-nya. Aktivitas di worklist tetap
                // berstatus BELUM, sehingga tombol "Catat Hasil" masih berdiri
                // di sebelah lencana "skrining selesai" — menawarkan mencatat
                // ulang sesuatu yang barusan dicatat lewat kuesionernya sendiri.
                // Lebih buruk lagi, tahapnya tidak pernah dievaluasi: rekomendasi
                // sudah ada, knockout sudah kena, tapi mesin keputusan tidak
                // pernah diberi tahu bahwa aktivitas ini rampung.
                //
                // Rekomendasi dipetakan ke hasil aktivitas dengan aturan yang
                // sama seperti "Catat Hasil":
                //   TIDAK_LANJUT / knockout -> GAGAL
                //   LANJUT / PERTIMBANGAN   -> LULUS
                // PERTIMBANGAN sengaja TIDAK menggugurkan: artinya "perlu dibahas",
                // bukan "tidak lolos", dan palunya tetap di tangan admin lewat
                // keputusan tahap.
                //
                // Aktivitas INFORMATIF tidak diberi verdict — sepola pintu
                // "Catat Hasil": perannya bahan pertimbangan, bukan penentu.
                $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->where('Id_Lamaran_Tahap_Tes', $sesi->Lamaran_Tahap_Tes_Id)
                    ->first();

                if ($sub && $sub->Flag_Selesai !== 'Y') {
                    $gagal = $data['rekomendasi'] === 'TIDAK_LANJUT' || ($hasil['knockout']['kena'] ?? false);

                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                        ->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)
                        ->update([
                            'Status' => 'SELESAI',
                            'Hasil' => $sub->Peran === 'INFORMATIF' ? null : ($gagal ? 'GAGAL' : 'LULUS'),
                            // Skor kuesioner ikut tersimpan sebagai nilai aktivitas,
                            // supaya rapor tes menampilkan angka yang sama dengan
                            // yang terbaca di panel skrining.
                            'Nilai' => $hasil['persen'] ?? $sub->Nilai,
                            'Flag_Selesai' => 'Y',
                            'Waktu_Selesai' => now(),
                        ] + $this->capUbah());

                    $hasil['outcome'] = app(\App\Support\Career\LamaranService::class)
                        ->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'))['outcome'] ?? null;
                }

                return $hasil;
            });

            Log::channel('web_career')->info(sprintf(
                '[SKRINING] sesi diselesaikan: #%d, rekomendasi %s, skor %s, knockout %s, oleh %s.',
                $sesiId, $data['rekomendasi'], $hasil['persen'] ?? '—',
                $hasil['knockout']['kena'] ? 'YA' : 'tidak', session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(
                $hasil,
                $hasil['knockout']['kena']
                    ? 'Sesi selesai — kandidat DITANDAI GUGUR: '.$hasil['knockout']['pesan']
                    : 'Sesi selesai dan dikunci.'
            );
        } catch (\RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyelesaikan skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyelesaikan sesi.', 500);
        }
    }

    /**
     * Buka kunci sesi yang sudah selesai.
     *
     * Alasannya WAJIB, dan dicatat. Membuka kunci berarti mengubah angka yang
     * mungkin sudah dipakai memutuskan nasib seseorang; yang menjaganya bukan
     * larangan, melainkan jejak.
     */
    public function bukaKunci(Request $request, string $sesiId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        // Tidak lewat sesiTersunting(): pintu ini justru MENUNTUT sesi yang
        // sudah terkunci, tepat kebalikan dari yang diizinkan penolong itu.
        $realId = Hashids::decode($sesiId)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sesi tidak valid.', 422);
        }
        $sesiId = (int) $realId;

        $sesi = DB::table(Skrining::T_SESI)->where('Id_Lamaran_Skrining', $sesiId)->whereNull('Arsip_At')->first();
        if (! $sesi) {
            return ResponseHelper::error('Sesi tidak ditemukan.', 404);
        }

        if ($sesi->Status !== 'SELESAI') {
            return ResponseHelper::error('Sesi ini memang belum terkunci.', 422);
        }

        $data = $request->validate(['alasan' => 'required|string|min:5|max:500']);

        DB::table(Skrining::T_SESI)->where('Id_Lamaran_Skrining', $sesiId)->update([
            'Status' => 'DRAF',
            'Dikunci_At' => null,
        ] + $this->capUbah());

        Log::channel('web_career')->warning(sprintf(
            '[SKRINING] KUNCI DIBUKA: sesi #%d (lamaran #%d), alasan "%s", oleh %s.',
            $sesiId, $sesi->Lamaran_Id, $data['alasan'], session('career_auth.nama', 'ADMIN')
        ));

        return ResponseHelper::success(null, 'Sesi dibuka kembali. Pembukaan ini tercatat di log.');
    }

    // ═══════════════════════════ PENOLONG ═══════════════════════════

    /**
     * Nomor HP dari AKUN kandidat — nilai awal kolom "Nomor dihubungi".
     *
     * Tersimpan di N_WEB_CAREERS_Users.No_Hp sudah berawalan kode negara
     * ("62812..."), bentuk yang sama dengan yang dipakai komponen TeleponNegara
     * di layar, jadi tidak perlu diolah lagi.
     *
     * Mengembalikan null bila akunnya belum mengisi nomor — kolomnya lalu
     * tampil kosong seperti sebelumnya, dan rekruter mengetiknya sendiri.
     */
    private function nomorAkun(int $lamaranId): ?string
    {
        $nomor = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $lamaranId)
            ->value('u.No_Hp');

        $nomor = trim((string) $nomor);

        return $nomor !== '' ? $nomor : null;
    }

    /** Aktivitas + tahapnya + lamarannya, dalam satu baris. */
    private function aktivitas(int $tesId): ?object
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as st')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as lt', 'lt.Id_Lamaran_Tahap', '=', 'st.Lamaran_Tahap_Id')
            ->where('st.Id_Lamaran_Tahap_Tes', $tesId)
            ->first(['st.*', 'lt.Lamaran_Id']);
    }

    /**
     * Sesi yang boleh disunting — atau tanggapan galat yang menjelaskan kenapa tidak.
     *
     * @return object|\Illuminate\Http\JsonResponse
     */
    /**
     * Sesi yang boleh disunting — parameternya HASH, bukan angka.
     *
     * Diuraikan di sini, satu tempat, bukan di tiap pintu: tiga endpoint
     * memanggilnya, dan penguraian yang ditulis tiga kali cepat atau lambat
     * berbeda di salah satunya.
     *
     * Hash yang tidak sah ditolak 422, bukan 404. Keduanya sama-sama menutup
     * pintu, tapi 404 menyatakan "tidak ada" — dan menyatakan sesuatu tidak
     * ada kepada orang yang menebak-nebak justru memberi tahu bahwa tebakan
     * lain mungkin berhasil.
     */
    private function sesiTersunting(string $sesiHash)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $sesiId = Hashids::decode($sesiHash)[0] ?? null;
        if (! $sesiId) {
            return ResponseHelper::error('Sesi tidak valid.', 422);
        }

        $sesi = DB::table(Skrining::T_SESI)->where('Id_Lamaran_Skrining', (int) $sesiId)->whereNull('Arsip_At')->first();

        if (! $sesi) {
            return ResponseHelper::error('Sesi tidak ditemukan.', 404);
        }

        if ($sesi->Status === 'SELESAI') {
            return ResponseHelper::error(
                'Sesi sudah selesai dan terkunci. Buka kunci dulu kalau memang perlu diperbaiki.',
                422
            );
        }

        if ($sesi->Status === 'BATAL') {
            return ResponseHelper::error('Sesi ini dibatalkan.', 422);
        }

        return $sesi;
    }

    /** Salin seluruh pertanyaan satu versi jadi baris jawaban kosong. */
    private function bekukanPertanyaan(int $sesiId, int $versiId): void
    {
        $tanya = DB::table(Skrining::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->orderBy('Urutan')
            ->get();

        foreach ($tanya as $t) {
            DB::table(Skrining::T_JAWAB)->insert([
                'Lamaran_Skrining_Id' => $sesiId,
                'Master_Skrining_Pertanyaan_Id' => $t->Id_Master_Skrining_Pertanyaan,
                'Urutan' => $t->Urutan,
                'Kode_Snapshot' => $t->Kode,
                'Label_Snapshot' => $t->Label,
                'Tipe_Snapshot' => $t->Tipe,
                'Seksi_Snapshot' => $t->Seksi,
                'Opsi_Snapshot' => $t->Opsi,
                'Skala_Min_Snapshot' => $t->Skala_Min,
                'Skala_Max_Snapshot' => $t->Skala_Max,
                'Bobot_Snapshot' => $t->Bobot,
                'Knockout_Snapshot' => $t->Flag_Knockout,
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama', 'ADMIN'),
                'Created_By_Id' => session('career_auth.id'),
            ]);
        }
    }

    /**
     * Simpan satu jawaban.
     *
     * Nilainya dihitung di SERVER dari opsi yang dibekukan, bukan diterima dari
     * layar. Skor yang dikirim klien adalah skor yang bisa dikarang klien — dan
     * angka ini ikut menentukan apakah seseorang lanjut atau tidak.
     */
    private function simpanSatuJawaban(int $sesiId, array $j): void
    {
        $baris = DB::table(Skrining::T_JAWAB)
            ->where('Lamaran_Skrining_Id', $sesiId)
            ->where('Kode_Snapshot', $j['kode'])
            ->first();

        // Kode yang tidak ada di sesi ini diabaikan diam-diam: itu artinya
        // layar mengirim pertanyaan dari versi lain, dan menuliskannya justru
        // akan merusak pembekuan yang sedang dijaga.
        if (! $baris) {
            return;
        }

        $nilaiMentah = $j['nilai'] ?? null;
        $opsi = Skrining::opsiArray($baris->Opsi_Snapshot ?? null);

        $skor = Skrining::nilaiJawaban($baris->Tipe_Snapshot, $nilaiMentah, $opsi);

        DB::table(Skrining::T_JAWAB)->where('Id_Lamaran_Skrining_Jawaban', $baris->Id_Lamaran_Skrining_Jawaban)->update([
            'Jawaban' => is_array($nilaiMentah) ? json_encode(array_values($nilaiMentah)) : $this->keTeks($nilaiMentah),
            'Jawaban_Teks' => $this->labelJawaban($nilaiMentah, $opsi),
            'Nilai' => $skor,
            'Catatan' => $j['catatan'] ?? null,
        ] + $this->capUbah());
    }

    /** Hitung ulang skor & knockout, lalu simpan ke kepala sesi. */
    private function hitungUlang(object $sesi, int $sesiId, array $data): array
    {
        $jawaban = DB::table(Skrining::T_JAWAB)->where('Lamaran_Skrining_Id', $sesiId)->get()->all();
        $skor = Skrining::hitungSkor($jawaban);

        $pakai = Skrining::versiTerpakai($sesi->Skrining_Kode, (int) $sesi->Skrining_Versi);
        $ko = $pakai
            ? Skrining::periksaKnockout($jawaban, $pakai['versiId'])
            : ['kena' => false, 'kode' => null, 'pesan' => null];

        $isi = [
            'Skor' => $skor['skor'],
            'Skor_Maks' => $skor['maks'],
            'Skor_Persen' => $skor['persen'],
            'Knockout_Flag' => $ko['kena'] ? 'Y' : 'T',
            'Knockout_Kode' => $ko['kode'],
            'Knockout_Pesan' => $ko['pesan'],
        ];

        foreach (
            [
                'metode' => 'Metode', 'kontakNomor' => 'Kontak_Nomor', 'percobaan' => 'Percobaan',
                'hasilKontak' => 'Hasil_Kontak', 'durasiMenit' => 'Durasi_Menit', 'rekomendasi' => 'Rekomendasi',
            ] as $dari => $ke
        ) {
            if (array_key_exists($dari, $data)) {
                $isi[$ke] = $data[$dari];
            }
        }

        if (array_key_exists('ringkasanHtml', $data)) {
            // Dibersihkan di server. Ringkasan ini dibaca orang lain di panel
            // worklist, dan HTML yang lolos apa adanya dari editor adalah jalan
            // masuk skrip yang paling gampang di seluruh modul ini.
            $html = HtmlBersih::saring($data['ringkasanHtml'] ?: null);
            $isi['Ringkasan_Html'] = $html;
            // Salinan teks polos ikut disimpan supaya pencarian dan ekspor tidak
            // perlu membongkar HTML tiap kali dibaca.
            $isi['Ringkasan'] = $html ? (HtmlBersih::keTeks($html) ?: null) : null;
        }

        DB::table(Skrining::T_SESI)->where('Id_Lamaran_Skrining', $sesiId)->update($isi + $this->capUbah());

        return ['skor' => $skor['skor'], 'maks' => $skor['maks'], 'persen' => $skor['persen'], 'knockout' => $ko];
    }

    /** Pertanyaan wajib yang masih kosong — labelnya, bukan kodenya. */
    private function wajibKosong(int $sesiId, ?int $versiId): array
    {
        if (! $versiId) {
            return [];
        }

        $wajib = DB::table(Skrining::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->where('Flag_Wajib', 'Y')
            ->pluck('Kode')
            ->all();

        if (! $wajib) {
            return [];
        }

        return DB::table(Skrining::T_JAWAB)
            ->where('Lamaran_Skrining_Id', $sesiId)
            ->whereIn('Kode_Snapshot', $wajib)
            ->where(fn ($q) => $q->whereNull('Jawaban')->orWhere('Jawaban', ''))
            ->orderBy('Urutan')
            ->pluck('Label_Snapshot')
            ->all();
    }

    /** Label terbaca dari sebuah jawaban — untuk laporan tanpa join. */
    private function labelJawaban($nilai, array $opsi): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $peta = [];
        foreach ($opsi as $o) {
            $peta[$o['nilai']] = $o['label'];
        }

        if (is_array($nilai)) {
            return mb_substr(implode(', ', array_map(fn ($v) => $peta[(string) $v] ?? (string) $v, $nilai)), 0, 1000);
        }

        return mb_substr($peta[(string) $nilai] ?? $this->keTeks($nilai), 0, 1000);
    }

    private function keTeks($nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        return is_scalar($nilai) ? (string) $nilai : json_encode($nilai);
    }

    private function petunjuk(?string $kode): ?string
    {
        return $kode
            ? DB::table(Skrining::T_MASTER)->where('Kode', $kode)->value('Petunjuk')
            : null;
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
}
