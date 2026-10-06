<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * BIODATA KANDIDAT → HRIS REKRUTMEN (HCLearn).
 *
 * Menyusun muatan biodata untuk satu akun kandidat, memakai peta di
 * N_WEB_CAREERS_Master_Sinkron_Hris. Penjelasan panjang tentang kenapa petanya
 * berupa tabel — dan bukan daftar di dalam kode — ada di
 * docs/N_WEB_CAREERS_Master_Sinkron_Hris_2026-08-05.sql.
 *
 * SATU ATURAN YANG MENENTUKAN SEGALANYA
 *
 *   "tidak ditanyakan"          → field TIDAK dikirim, kolom di HRIS utuh
 *   "ditanyakan, lalu kosong"   → field dikirim sebagai null, kolom DIKOSONGKAN
 *
 * Perbedaannya bukan soal kerapian. Kandidat mengisi banyak formulir sepanjang
 * satu proses seleksi, dan tiap formulir menanyakan hal yang berbeda. Bila
 * "tidak ditanyakan" ikut terbaca sebagai kosong, satu formulir singkat di
 * tahap akhir akan menghapus alamat, tanggal lahir, dan pendidikan yang sudah
 * diisi lengkap di formulir pendaftaran — tanpa satu pun jejak.
 */
class SinkronHrisService
{
    /** Peta kolom HRIS → sumber nilainya. */
    public static function peta(): \Illuminate\Support\Collection
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Sinkron_Hris')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get();
    }

    /**
     * Susun muatan biodata untuk satu akun kandidat.
     *
     * @return array{Biodata: array<string,mixed>, Nama: ?string, Email: ?string}|null
     *         null bila akunnya tidak ada.
     */
    public static function bentukPayload(int $userId): ?array
    {
        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->first();
        if (! $user) {
            return null;
        }

        // Pengisian formulir, TERBARU DULU. Yang dibaca bukan "pengisian
        // terakhir" melainkan "pengisian terakhir YANG MEMUAT field ini" —
        // formulir tahap akhir yang cuma menanyakan tiga hal tidak boleh
        // menghapus sepuluh hal lain yang pernah diisi kandidat.
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Id_Users', $userId)
            ->orderByDesc('Waktu_Kirim')
            ->orderByDesc('Id_Formulir_Pengisian')
            ->get(['Jawaban_Json']);

        $jawabanPer = $pengisian
            ->map(fn ($p) => json_decode($p->Jawaban_Json ?: '{}', true) ?: [])
            ->all();

        $lamaran = self::lamaranTerbaru($userId);

        $biodata = [];

        foreach (self::peta() as $m) {
            $kunci = array_values(array_filter(array_map('trim', explode('|', (string) $m->Field_Key))));

            $hasil = match (strtoupper((string) $m->Sumber)) {
                'PROFIL' => self::dariProfil($user, $kunci),
                'LAMARAN' => self::dariLamaran($lamaran, $kunci),
                default => self::dariFormulir($jawabanPer, $kunci),
            };

            // [false, null] = tidak tercakup sumbernya → JANGAN dikirim.
            if ($hasil[0] === false) {
                continue;
            }

            $biodata[$m->Kolom_Hris] = $hasil[1];
        }

        return [
            'Biodata' => $biodata,
            // Dikirim terpisah supaya CAT bisa MEMBUATKAN baris calon bila
            // kandidat ini belum punya — pendaftaran ke HCLearn bersifat
            // best-effort, jadi akun tanpa Kode_Calon memang mungkin ada.
            'Nama' => $user->Nama,
            'Email' => $user->Email,
        ];
    }

    /** Lamaran terbaru + posisinya. Null bila kandidat belum pernah melamar. */
    private static function lamaranTerbaru(int $userId): ?object
    {
        return DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as p', 'p.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Program as g', 'g.Id_Program', '=', 'l.Program_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Waktu_Lamar')
            ->orderByDesc('l.Id_Lamaran')
            ->select(['p.Posisi', 'g.Nama as ProgramNama'])
            ->first();
    }

    /**
     * @return array{0: bool, 1: mixed} [tercakup, nilai]
     */
    private static function dariProfil(object $user, array $kunci): array
    {
        foreach ($kunci as $k) {
            // property_exists, BUKAN sekadar isset: kolom yang ada tapi bernilai
            // NULL tetap "tercakup" — akun yang nomornya dihapus kandidat harus
            // ikut mengosongkan nomor di HRIS.
            if (property_exists($user, $k)) {
                return [true, self::kosongJadiNull($user->{$k})];
            }
        }

        return [false, null];
    }

    /**
     * @param  array<int,array>  $jawabanPer  jawaban tiap pengisian, TERBARU dulu
     * @return array{0: bool, 1: mixed}
     */
    private static function dariFormulir(array $jawabanPer, array $kunci): array
    {
        foreach ($jawabanPer as $jawaban) {
            foreach ($kunci as $k) {
                if (! array_key_exists($k, $jawaban)) {
                    continue;
                }

                $v = $jawaban[$k];
                if (is_array($v)) {
                    $v = implode(', ', array_filter($v, fn ($x) => $x !== null && $x !== ''));
                }

                // Formulir INI menanyakannya — jawabannya berlaku, termasuk
                // bila kandidat mengosongkannya. Pencarian berhenti di sini
                // supaya jawaban lama tidak menutupi jawaban baru.
                return [true, self::kosongJadiNull($v)];
            }
        }

        return [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private static function dariLamaran(?object $lamaran, array $kunci): array
    {
        if (! $lamaran) {
            // Belum pernah melamar → posisinya memang belum ada jawabannya.
            // Mengirim kosong akan menghapus posisi yang barangkali sudah
            // diisi tim rekrutmen sendiri di HRIS.
            return [false, null];
        }

        foreach ($kunci as $k) {
            if (strtolower($k) === 'posisi') {
                return [true, self::kosongJadiNull($lamaran->Posisi ?: $lamaran->ProgramNama)];
            }
        }

        return [false, null];
    }

    /** '' dan '   ' diperlakukan sama dengan null — keduanya berarti "kosong". */
    private static function kosongJadiNull(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }

        return is_string($v) && trim($v) === '' ? null : $v;
    }
}
