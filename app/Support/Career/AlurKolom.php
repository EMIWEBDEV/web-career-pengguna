<?php

namespace App\Support\Career;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — KOLOM PIPELINE: satu sumber kebenaran.
 *
 * ══ MASALAH YANG DIPECAHKAN ═════════════════════════════════════════════════
 *
 * Setiap lamaran MEMBEKUKAN alurnya saat orang melamar (Lamaran.Master_Alur_Id
 * + salinan tahapnya di Lamaran_Tahap). Itu benar dan memang disengaja.
 *
 * Tapi seluruh layar papan — worklist, monitoring, penjadwalan — dulu menyusun
 * kolomnya dari alur yang SEKARANG menempel di program, lalu menempatkan
 * kandidat memakai NOMOR URUT tahapnya. Dua anggapan yang dua-duanya rapuh:
 *
 *   1. "alur program sekarang = alur yang dijalani semua orang di program ini"
 *      Salah begitu admin mengarahkan program ke alur baru. Yang 50 orang
 *      berjalan tetap di alur lama, tapi papan menggambar kolom alur baru.
 *
 *   2. "tahap ke-3 di alur ini = tahap ke-3 yang dijalani kandidat"
 *      Salah begitu alur disunting di tempat. MasterAlurController sengaja
 *      MEMAKAI ULANG baris per Urutan (supaya lamaran berjalan tidak kehilangan
 *      rujukan) — jadi Id yang sama bisa berganti arti. Kandidat di "Psikotes"
 *      muncul di kolom "Wawancara User" tanpa satu galat pun.
 *
 * Akibatnya sama pada kedua kasus: DATANYA benar, PAPANNYA berbohong. Dan itu
 * lebih berbahaya daripada kesalahan yang terlihat — keputusan diambil dari
 * papan.
 *
 * ══ CARA KERJA ══════════════════════════════════════════════════════════════
 *
 * Kolom disusun dari alur yang BENAR-BENAR DIPAKAI lamaran di program itu
 * (union), bukan dari satu penunjuk di program. Kandidat ditempatkan menurut
 * KODE tahapnya — identitas, bukan nomor.
 *
 * Kode yang sama dari dua alur berbeda MENYATU jadi satu kolom. Itu memang yang
 * diinginkan: kalau dua alur sama-sama punya "PSIKOTES", bagi tim rekrutmen itu
 * satu tahap yang sama, dan memecahnya jadi dua kolom hanya memecah pekerjaan
 * yang sebenarnya satu.
 *
 * ══ YANG TIDAK BOLEH TERJADI ════════════════════════════════════════════════
 *
 * Tidak ada kandidat yang boleh hilang dari papan. Kalau kode tahapnya tak
 * cocok kolom mana pun (alur lama yang tahapnya sudah dihapus, data pra-mesin
 * tanpa Kode), ia jatuh ke kolom cadangan yang dibuat dari tahapnya sendiri —
 * bukan lenyap. Kandidat yang tak terlihat tidak akan pernah dikerjakan.
 */
final class AlurKolom
{
    /** Kode kolom cadangan untuk tahap yang tak dikenali alur mana pun. */
    public const KODE_LAINNYA = '__LAINNYA__';

    /**
     * Id alur yang BENAR-BENAR dipakai lamaran pada satu program,
     * digabung dengan alur yang sekarang menempel di program itu.
     *
     * Alur program tetap disertakan walau belum ada satu pelamar pun: papan
     * program yang baru dibuka harus tetap menggambarkan tahapannya, kalau
     * tidak admin melihat papan kosong tanpa kolom dan mengira alurnya belum
     * disetel.
     *
     * @return int[]
     */
    public static function alurDipakai(int $programId, ?int $alurProgramId): array
    {
        $dipakai = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Program_Id', $programId)
            ->whereNotNull('Master_Alur_Id')
            ->distinct()
            ->pluck('Master_Alur_Id')
            ->map(fn ($v) => (int) $v);

        if ($alurProgramId) {
            $dipakai->push((int) $alurProgramId);
        }

        return $dipakai->unique()->values()->all();
    }

    /**
     * Versi BANYAK PROGRAM sekaligus — untuk layar yang menggambar puluhan
     * funnel dalam satu muatan (Monitoring). Memanggil alurDipakai() di dalam
     * perulangan program berarti satu kueri per program, dan halaman yang
     * seluruh gunanya adalah "lihat semuanya sekaligus" justru jadi paling
     * lambat saat program bertambah.
     *
     * @param  int[]  $programIds
     * @param  array<int, int|null>  $alurProgram  [programId => alur sekarang]
     * @return array<int, int[]>  [programId => daftar Master_Alur_Id]
     */
    public static function alurDipakaiBanyak(array $programIds, array $alurProgram = []): array
    {
        $peta = [];

        if ($programIds) {
            $baris = DB::table('N_WEB_CAREERS_Lamaran')
                ->whereIn('Program_Id', $programIds)
                ->whereNotNull('Master_Alur_Id')
                ->distinct()
                ->get(['Program_Id', 'Master_Alur_Id']);

            foreach ($baris as $b) {
                $peta[(int) $b->Program_Id][] = (int) $b->Master_Alur_Id;
            }
        }

        foreach ($programIds as $pid) {
            if (! empty($alurProgram[$pid])) {
                $peta[(int) $pid][] = (int) $alurProgram[$pid];
            }
            $peta[(int) $pid] = array_values(array_unique($peta[(int) $pid] ?? []));
        }

        return $peta;
    }

    /**
     * Susun kolom pipeline dari beberapa alur sekaligus.
     *
     * @param  int[]  $alurIds
     * @param  int|null  $alurProgramId  alur yang sekarang dipasang di program —
     *                                   label & perilakunya yang dipakai saat
     *                                   dua alur mendefinisikan kode yang sama,
     *                                   karena itulah yang admin lihat di Master
     *                                   Alur saat ini.
     * @return array<int, array<string, mixed>>
     */
    public static function susun(array $alurIds, ?int $alurProgramId = null): array
    {
        if (! $alurIds) {
            return [];
        }

        $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->whereIn('Master_Alur_Id', $alurIds)
            ->orderBy('Urutan')
            ->get();

        if ($tahap->isEmpty()) {
            return [];
        }

        $tipe = self::masterTipe();
        $gabung = [];

        foreach ($tahap as $t) {
            $kode = (string) ($t->Kode ?: ('URUT-' . (int) $t->Urutan));
            $iniAlurProgram = $alurProgramId && (int) $t->Master_Alur_Id === (int) $alurProgramId;

            // Kolom yang sudah ada hanya DITIMPA oleh alur program sekarang.
            // Tanpa syarat itu, urutan pemrosesan alur menentukan label mana
            // yang menang — dan papan berganti label sendiri tanpa sebab yang
            // bisa dijelaskan ke siapa pun.
            if (isset($gabung[$kode]) && ! $iniAlurProgram) {
                // Tetap catat: kolom ini dipakai lebih dari satu alur.
                $gabung[$kode]['urutan'] = min($gabung[$kode]['urutan'], (int) $t->Urutan);
                $gabung[$kode]['alurIds'][] = (int) $t->Master_Alur_Id;

                continue;
            }

            $sebelumnya = $gabung[$kode] ?? null;

            $gabung[$kode] = self::bentukKolom($t, $tipe, $kode);
            $gabung[$kode]['alurIds'] = array_unique(array_merge(
                $sebelumnya['alurIds'] ?? [],
                [(int) $t->Master_Alur_Id],
            ));
            // Urutan pakai yang TERKECIL supaya kolom milik alur lama tidak
            // terlempar ke ujung kanan papan, jauh dari tahap sezamannya.
            $gabung[$kode]['urutan'] = $sebelumnya
                ? min($sebelumnya['urutan'], (int) $t->Urutan)
                : (int) $t->Urutan;
        }

        // Tandai kolom yang BUKAN milik alur program sekarang. Layar memakainya
        // untuk memberi keterangan "alur lama" — tanpa itu admin melihat kolom
        // asing di papannya dan mengira alurnya rusak.
        foreach ($gabung as $kode => $k) {
            $gabung[$kode]['alurLain'] = $alurProgramId
                ? ! in_array((int) $alurProgramId, $k['alurIds'], true)
                : false;
        }

        $hasil = array_values($gabung);

        // URUTAN PAPAN: nomor tahap dulu, lalu alur program SEBELUM alur lama.
        //
        // Tanpa syarat kedua, dua kolom bernomor sama diurutkan menurut abjad
        // labelnya — jadi kolom peninggalan bisa menyelinap di antara dua kolom
        // yang sedang dipakai, dan papan yang dibaca tim tiap hari berubah
        // susunannya karena alasan yang tidak berhubungan dengan pekerjaannya.
        //
        // Dengan ini, alur yang sekarang terbaca lurus dari kiri ke kanan, dan
        // kolom rombongan lama duduk tepat di sebelah tahap sezamannya.
        usort($hasil, fn ($a, $b) => [$a['urutan'], $a['alurLain'] ? 1 : 0, $a['label']]
            <=> [$b['urutan'], $b['alurLain'] ? 1 : 0, $b['label']]);

        return $hasil;
    }

    /**
     * Kolom mana yang menampung satu kandidat.
     *
     * URUTAN PENCARIAN — dari yang paling pasti ke yang paling menebak:
     *   1. Kode tahap  → identitas. Satu-satunya cara yang tetap benar setelah
     *                    alur disunting maupun diganti.
     *   2. Nomor urut  → HANYA bila tahapnya memang tidak punya Kode sama
     *                    sekali (baris pra-mesin). Lihat peringatan di bawah.
     *   3. Cadangan    → tidak ketemu; JANGAN dibuang.
     *
     * ══ KENAPA NOMOR URUT TIDAK DIPAKAI SEBAGAI CADANGAN UMUM ══
     *
     * Menggoda sekali: kalau kodenya tak ketemu, pakai saja nomornya. Tapi
     * justru di situ letak kesalahan yang ingin ditutup kelas ini.
     *
     * MasterAlurController memakai ULANG baris tahap per Urutan saat alur
     * disunting (supaya lamaran berjalan tak kehilangan rujukan). Jadi tahap
     * ke-4 yang dulu "FGD & Wawancara HR" bisa hari ini bernama "Wawancara
     * User" — Id dan nomornya sama, artinya berbeda.
     *
     * Kandidat yang snapshot-nya menyebut FGD lalu dicocokkan lewat nomor akan
     * mendarat di kolom Wawancara User: RAPI, MEYAKINKAN, DAN SALAH. Tidak ada
     * galat, tidak ada tanda tanya — hanya orang yang dinilai di tahap yang
     * bukan tahapnya.
     *
     * Punya Kode tapi tak cocok = tahapnya memang sudah tidak ada di alur mana
     * pun. Jawaban jujurnya "di luar alur", bukan tebakan bernomor. Kolom
     * cadangan menampilkannya dengan label aslinya, jadi tim tetap tahu persis
     * orang ini sedang di mana.
     *
     * @param  array<int, array<string, mixed>>  $kolom
     */
    public static function cocok(array $kolom, ?object $tahapKini): string
    {
        if (! $tahapKini) {
            return $kolom[0]['kode'] ?? self::KODE_LAINNYA;
        }

        $kode = (string) ($tahapKini->Kode ?? '');

        if ($kode !== '') {
            foreach ($kolom as $k) {
                if ($k['kode'] === $kode) {
                    return $kode;
                }
            }

            return self::KODE_LAINNYA;
        }

        $urutan = (int) ($tahapKini->Urutan ?? 0);
        if ($urutan > 0) {
            foreach ($kolom as $k) {
                if ((int) $k['urutan'] === $urutan) {
                    return $k['kode'];
                }
            }
        }

        return self::KODE_LAINNYA;
    }

    /**
     * Kolom cadangan untuk kandidat yang tahapnya tak dikenali alur mana pun.
     *
     * Dibuat dari tahap kandidat itu SENDIRI, bukan dari master — justru karena
     * masternya sudah tidak menjelaskan apa-apa. Labelnya memakai label yang
     * dibekukan di lamaran, jadi tetap terbaca sebagai tahap yang nyata.
     *
     * @param  Collection<int, object>  $tahapTakDikenal  baris Lamaran_Tahap
     * @return array<string, mixed>|null
     */
    public static function kolomCadangan(Collection $tahapTakDikenal): ?array
    {
        if ($tahapTakDikenal->isEmpty()) {
            return null;
        }

        $label = $tahapTakDikenal->pluck('Label')->filter()->unique();

        return [
            'kode' => self::KODE_LAINNYA,
            // Satu label saja → pakai apa adanya; lebih dari satu → nama umum,
            // karena menyebut salah satunya membuat yang lain tak terlihat.
            'label' => $label->count() === 1 ? (string) $label->first() : 'Tahap di luar alur',
            'urutan' => (int) $tahapTakDikenal->max('Urutan') + 1,
            'provider' => null,
            'tipe' => null,
            'tipeNama' => null,
            'talentPool' => false,
            'uploadHasil' => true,
            'wajibUpload' => false,
            'penawaran' => false,
            'tuntas' => false,
            'formulir' => false,
            'batasMode' => null,
            'batasHari' => null,
            'alurIds' => [],
            'alurLain' => true,
            // Penanda supaya layar bisa menjelaskan kolom ini apa adanya:
            // tahap yang alurnya sudah tidak ada lagi.
            'cadangan' => true,
        ];
    }

    /**
     * Perilaku tahap untuk SATU kandidat — snapshot dulu, master belakangan.
     *
     * Inilah yang membuat menyunting alur tidak lagi mengubah aturan orang yang
     * sedang menjalaninya. Kolom master tetap dikirim ke layar untuk menggambar
     * papan, tapi yang MENGIKAT satu kandidat adalah salinan miliknya sendiri.
     *
     * @param  object|null  $tahap  baris Lamaran_Tahap kandidat
     * @param  array<string, mixed>|null  $kolom  kolom master padanannya
     * @return array<string, bool>
     */
    public static function perilaku(?object $tahap, ?array $kolom): array
    {
        $ya = static fn ($v) => $v === 'Y';

        // `??` sengaja, BUKAN `?:` — 'T' adalah jawaban yang sah ("tidak"),
        // dan `?:` akan menganggapnya kosong lalu diam-diam jatuh ke master.
        return [
            'talentPool' => isset($tahap->Flag_Talent_Pool)
                ? $ya($tahap->Flag_Talent_Pool)
                : (bool) ($kolom['talentPool'] ?? false),
            'uploadHasil' => isset($tahap->Flag_Upload_Hasil)
                ? $ya($tahap->Flag_Upload_Hasil)
                : (bool) ($kolom['uploadHasil'] ?? false),
            'wajibUpload' => isset($tahap->Flag_Wajib_Upload)
                ? $ya($tahap->Flag_Wajib_Upload)
                : (bool) ($kolom['wajibUpload'] ?? false),
            'tuntas' => isset($tahap->Flag_Tuntas)
                ? $ya($tahap->Flag_Tuntas)
                : (bool) ($kolom['tuntas'] ?? false),
            // Penawaran BUKAN flag tahap melainkan sifat TIPE tahap, dan tipe
            // itu sendiri sudah dibekukan di Lamaran_Tahap.Tipe_Tahap_Kode.
            'penawaran' => isset($tahap->Tipe_Tahap_Kode)
                ? ($ya(self::masterTipe()[$tahap->Tipe_Tahap_Kode]->Flag_Penawaran ?? 'T'))
                : (bool) ($kolom['penawaran'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private static function bentukKolom(object $t, Collection $tipe, string $kode): array
    {
        $tt = $tipe[$t->Tipe_Tahap_Kode] ?? null;

        return [
            'kode' => $kode,
            'label' => $t->Label,
            'urutan' => (int) $t->Urutan,
            'provider' => $t->Provider,
            'tipe' => $t->Tipe_Tahap_Kode,
            'tipeNama' => $tt->Nama ?? null,
            'talentPool' => ($t->Flag_Talent_Pool ?? 'T') === 'Y',
            // Tipe yang seluruh gunanya memang dokumen (Background Check,
            // Reference Check, Tugas) selalu menerima unggahan, walau flag
            // per-tahapnya belum disetel admin.
            'uploadHasil' => ($t->Flag_Upload_Hasil ?? 'T') === 'Y'
                || ($tt->Flag_Upload_Hasil ?? 'T') === 'Y',
            'wajibUpload' => ($t->Flag_Wajib_Upload ?? 'T') === 'Y'
                || ($tt->Flag_Upload_Hasil ?? 'T') === 'Y',
            'penawaran' => ($tt->Flag_Penawaran ?? 'T') === 'Y',
            'tuntas' => ($t->Flag_Tuntas ?? 'T') === 'Y',
            // Tahap ini menuntut isian formulir kandidat — hanya kolom seperti
            // ini yang bisa diberi jadwal pengisian, dan hanya bila Master Alur
            // memberinya jadwal (BatasIsi::kolomBolehDijadwal).
            'formulir' => ($tt->Flag_Formulir ?? 'T') === 'Y',
            'batasMode' => $t->Batas_Mode ?? null,
            'batasHari' => isset($t->Batas_Hari) ? (int) $t->Batas_Hari : null,
            'alurIds' => [],
            'alurLain' => false,
            'cadangan' => false,
        ];
    }

    /**
     * Master Tipe Tahap (SELURUH kolom), dipetakan per Kode. Dibaca sekali per
     * permintaan — dan dipakai juga LamaranController::masterTipeTahap(), supaya
     * papan worklist tidak mengkueri tabel yang sama dua kali.
     */
    public static function masterTipe(): Collection
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->get()->keyBy('Kode');
    }
}
