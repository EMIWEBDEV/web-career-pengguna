<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — PROFIL LENGKAP satu kartu Talent Pool.
 *
 * Halaman Talent Pool hanya menyimpan RINGKASAN (nama, posisi, skor, tag).
 * Begitu rekruter membuka kartunya, yang ia butuhkan justru tiga hal yang tidak
 * ada di sana: siapa orang ini, berkasnya lengkap atau tidak, dan apa saja yang
 * pernah ia lalui. Tanpa itu, "Tarik ke Lowongan" ditekan berdasarkan satu baris
 * catatan yang ditulis berbulan-bulan lalu.
 *
 * DIBACA SAAT DIBUKA, BUKAN DISALIN KE KARTU. Kartu Talent Pool sengaja tipis —
 * menyalin biodata ke dalamnya berarti data yang membeku: kandidat memperbarui
 * nomor teleponnya, kartunya tetap menyebut nomor lama. Yang dibekukan hanya
 * yang memang harus beku (skor, tahap asal); sisanya dibaca dari sumbernya.
 */
class ProfilTalenta
{
    /**
     * @return array{bio: array, berkas: array, riwayat: array}
     */
    public static function rakit(int $lamaranId, ?int $userId): array
    {
        return [
            // "Kode Kartu" di rancangan adalah nomor rekaan (TP-2026-0011);
            // sistem ini tidak pernah menerbitkannya. Yang NYATA dan dipakai
            // sehari-hari untuk merujuk kandidat adalah kode lamarannya —
            // itu yang tercetak di undangan dan di PDF biodata.
            'kodeLamaran' => DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaranId)->value('Kode'),
            'bio' => self::bio($lamaranId),
            'berkas' => self::berkas($lamaranId),
            // Seluruh lamaran ORANG INI, bukan hanya yang membuatnya masuk pool.
            // Kandidat yang sudah tiga kali melamar adalah informasi yang sama
            // pentingnya dengan skornya — dan itu tidak terlihat dari kartu.
            'riwayat' => $userId ? self::riwayat($userId) : [],
        ];
    }

    /**
     * Biodata — dari master kunci identitas dulu, lalu tebakan kunci jawaban.
     *
     * Formulirnya dirancang lewat layar, jadi kunci yang dipakai tiap program
     * bisa berbeda. Yang sudah didaftarkan di master dibaca lewat jalur resmi;
     * sisanya (alamat, agama, status kawin) belum punya kode master sehingga
     * dicari dari kunci jawabannya — dan bila memang tidak ditanyakan, hasilnya
     * null, bukan tebakan.
     */
    private static function bio(int $lamaranId): array
    {
        $p = LamaranService::dataKandidatEmail($lamaranId);
        $jawaban = self::jawabanGabungan($lamaranId);
        $label = self::labelSkema($lamaranId);

        // DICARI LEWAT LABEL, BUKAN HANYA KUNCI.
        //
        // Formulirnya dirancang lewat layar, dan yang merancang menamai kuncinya
        // sesuka hati: `v_alamat`, `almt_domisili`, `f14`. Mencocokkan kunci saja
        // membuat kolom biodata kosong pada formulir yang PERTANYAANNYA jelas
        // ada — dan yang membacanya menyimpulkan kandidat tidak mengisinya.
        // Yang stabil justru pertanyaannya: "Alamat Domisili" tetap berbunyi
        // begitu berapa kali pun kuncinya diganti.
        $cari = function (array $pola) use ($jawaban, $label) {
            foreach ($jawaban as $k => $v) {
                if (! is_string($v) || trim($v) === '') {
                    continue;
                }

                $jerami = (string) $k . ' ' . ($label[$k] ?? '');
                foreach ($pola as $p) {
                    if (preg_match($p, $jerami)) {
                        return trim($v);
                    }
                }
            }

            return null;
        };

        $tglLahir = $p['tglLahir'] ?? null;
        $tempat = $cari(['/tempat[_\s-]*lahir/i', '/^kota[_\s-]*lahir/i']);
        $ttl = trim(implode(', ', array_filter([
            $tempat,
            $tglLahir ? self::tanggal($tglLahir) : null,
        ])));

        return [
            // Master lebih dulu; bila kodenya belum didaftarkan, jatuh ke
            // pencarian label — itulah yang membuat NIK muncul pada formulir
            // yang menanyakannya meski `Master_Kunci_Identitas` belum punya
            // kode NIK.
            'nik' => $p['nik'] ?? $cari(['/\bn\.?i\.?k\b/i', '/nomor[_\s-]*(induk[_\s-]*kependudukan|ktp)/i', '/no[_\s-]*ktp/i']),
            'ttl' => $ttl !== '' ? $ttl : null,
            'gender' => $p['jkel'] ?? $cari(['/jenis[_\s-]*kelamin/i', '/\bgender\b/i']),
            'agama' => $cari(['/agama/i', '/religion/i']),
            'kawin' => $cari(['/status[_\s-]*(perkawinan|pernikahan|nikah|kawin)/i', '/^perkawinan$/i', '/marital/i']),
            'hp' => $p['hp'] ?? $cari(['/no[_\s-]*(hp|telp|telepon|wa)/i', '/nomor[_\s-]*(hp|telepon|whatsapp)/i', '/\bhandphone\b/i']),
            'alamat' => $cari(['/alamat/i', '/domisili/i', '/\baddress\b/i']),
            'jenjang' => $p['jenjang'] ?? $cari(['/jenjang/i', '/tingkat[_\s-]*pendidikan/i']),
            'kampus' => $p['kampus'] ?? $cari(['/(nama[_\s-]*)?(kampus|universitas|institusi|sekolah|perguruan)/i']),
            'jurusan' => $p['jurusan'] ?? $cari(['/jurusan/i', '/program[_\s-]*studi/i', '/\bprodi\b/i']),
            'ipk' => $p['ipk'] ?? $cari(['/\bipk\b/i', '/indeks[_\s-]*prestasi/i']),
            'tahunLulus' => $p['tahunLulus'] ?? $cari(['/tahun[_\s-]*lulus/i', '/tahun[_\s-]*kelulusan/i']),
            'statusStudi' => $p['statusStudi'] ?? $cari(['/status[_\s-]*(studi|mahasiswa|pendidikan)/i']),
            'pengalaman' => $cari(['/pengalaman[_\s-]*kerja/i', '/lama[_\s-]*pengalaman/i', '/^pengalaman$/i']),
        ];
    }

    /**
     * key => LABEL PERTANYAAN, dari seluruh skema beku lamaran ini.
     *
     * Dibaca dari snapshot, bukan skema yang berlaku sekarang: pertanyaannya
     * bisa sudah diganti nama setelah kandidat menjawab, dan yang harus
     * dicocokkan adalah kalimat yang benar-benar ia baca saat itu.
     */
    private static function labelSkema(int $lamaranId): array
    {
        $peta = [];

        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Formulir_Pengisian')
            ->pluck('Schema_Snapshot_Json');

        foreach ($rows as $json) {
            $skema = json_decode($json ?: '', true);
            if (! is_array($skema)) {
                continue;
            }
            foreach ($skema['langkah'] ?? [] as $langkah) {
                foreach ($langkah['bagian'] ?? [] as $bagian) {
                    foreach ($bagian['field'] ?? [] as $f) {
                        $key = (string) ($f['key'] ?? '');
                        if ($key !== '') {
                            $peta[$key] = (string) ($f['label'] ?? '');
                        }
                    }
                }
            }
        }

        return $peta;
    }

    /**
     * KELENGKAPAN BERKAS — diukur terhadap yang DIMINTA, bukan yang terkumpul.
     *
     * Menghitung "6 dari 6" dari baris berkas yang ada selalu menghasilkan 100%
     * dan tidak pernah memberi tahu apa pun. Yang berarti adalah selisihnya:
     * daftar berkas WAJIB diambil dari skema yang dibekukan saat formulir
     * dikirim (field bertipe file/foto), lalu dicocokkan dengan yang benar-benar
     * terunggah. Yang diminta tapi kosong ikut muncul — itulah gunanya.
     */
    private static function berkas(int $lamaranId): array
    {
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Formulir_Pengisian')
            ->get(['Id_Formulir_Pengisian', 'Schema_Snapshot_Json']);

        if ($pengisian->isEmpty()) {
            return [];
        }

        // Yang DIMINTA — key => label, dari skema beku tiap pengisian.
        $diminta = [];
        foreach ($pengisian as $g) {
            $skema = json_decode($g->Schema_Snapshot_Json ?: '', true);
            if (! is_array($skema)) {
                continue;
            }
            foreach ($skema['langkah'] ?? [] as $langkah) {
                foreach ($langkah['bagian'] ?? [] as $bagian) {
                    foreach ($bagian['field'] ?? [] as $f) {
                        $tipe = mb_strtolower((string) ($f['tipe'] ?? ''));
                        $key = (string) ($f['key'] ?? '');
                        if ($key !== '' && in_array($tipe, ['file', 'foto'], true)) {
                            $diminta[$key] = (string) ($f['label'] ?? $key);
                        }
                    }
                }
            }
        }

        // Yang TERUNGGAH ke tabel berkas.
        $ada = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $pengisian->pluck('Id_Formulir_Pengisian'))
            ->orderBy('Id_Formulir_Berkas')
            ->get(['Id_Formulir_Berkas', 'Field_Key', 'Nama_Asli', 'Ekstensi', 'Mime', 'Ukuran_Byte', 'Waktu_Unggah', 'Status_Verifikasi'])
            ->keyBy('Field_Key');

        // TIDAK SEMUA FOTO SINGGAH DI TABEL BERKAS.
        //
        // Field bertipe `foto` diambil lewat kamera dan sebagian formulir
        // menyimpannya langsung sebagai data URI di dalam Jawaban_Json — tidak
        // pernah menjadi baris berkas. Tanpa jalur ini, foto verifikasi yang
        // JELAS ADA di formulir tercetak "Belum diunggah", dan kelengkapan
        // berkasnya salah hitung setiap kali.
        $jawaban = self::jawabanGabungan($lamaranId);

        $keluar = [];

        foreach ($diminta as $key => $label) {
            $keluar[] = self::barisBerkas($label, $ada[$key] ?? null, $jawaban[$key] ?? null);
            unset($ada[$key]);
        }

        // Berkas yang tidak ada di skema mana pun (pengisian lama tanpa
        // snapshot) TETAP DITAMPILKAN — menyembunyikannya membuat berkas yang
        // sungguh-sungguh ada seolah tidak pernah diunggah.
        foreach ($ada as $key => $b) {
            $keluar[] = self::barisBerkas(self::labelDariKunci((string) $key), $b, null);
        }

        return $keluar;
    }

    /**
     * @param  mixed  $b      baris N_WEB_CAREERS_Formulir_Berkas, bila ada
     * @param  mixed  $isian  jawaban formulir untuk kunci yang sama — foto
     *                        kamera hidup di sini sebagai data URI
     */
    private static function barisBerkas(string $label, $b, $isian): array
    {
        // FOTO DI DALAM JAWABAN — dianggap ADA, dan bisa dilihat langsung.
        // Batas 3 MB ditegakkan sebelum ditanam: data URI raksasa membuat
        // seluruh balasan detail membengkak dan halamannya gagal termuat.
        if (! $b && is_string($isian) && str_starts_with($isian, 'data:image/')) {
            return [
                'nama' => $label,
                'tipe' => strtoupper(explode('/', explode(';', substr($isian, 11), 2)[0])[0] ?: 'IMG'),
                'rupa' => 'gambar',
                'ada' => true,
                'id' => null,
                'pratinjau' => strlen($isian) <= 3 * 1024 * 1024 ? $isian : null,
                'mime' => explode(';', substr($isian, 5), 2)[0],
                'ukuranByte' => null,
                'namaAsli' => null,
                'meta' => 'Tersimpan di dalam jawaban formulir',
            ];
        }

        // TERCATAT DI FORMULIR, TAPI BERKASNYA TIDAK ADA.
        //
        // Sebagian jawaban hanya menyimpan NAMA berkas yang dipilih kandidat
        // ("apple-1986660_1280.png") tanpa pernah menghasilkan baris berkas —
        // unggahannya putus di tengah jalan. Dua keadaan ini menuntut tindakan
        // yang berbeda: "belum diunggah" berarti minta kandidat mengunggah,
        // sementara ini berarti unggahannya GAGAL dan perlu ditelusuri. Kalau
        // keduanya sama-sama tertulis "KOSONG", tidak ada yang pernah tahu
        // bedanya.
        if (! $b && is_string($isian) && trim($isian) !== '') {
            return [
                'nama' => $label,
                'tipe' => strtoupper(pathinfo($isian, PATHINFO_EXTENSION) ?: '—'),
                'rupa' => 'hilang',
                'ada' => false,
                'id' => null,
                'pratinjau' => null,
                'mime' => null,
                'ukuranByte' => null,
                'namaAsli' => trim($isian),
                'meta' => 'Tercatat “' . \Illuminate\Support\Str::limit(trim($isian), 42) . '” — berkasnya tidak tersimpan',
            ];
        }

        $ext = $b ? strtoupper((string) ($b->Ekstensi ?: pathinfo((string) $b->Nama_Asli, PATHINFO_EXTENSION))) : '';
        $mime = strtolower((string) ($b->Mime ?? ''));

        // RUPA IKON MENGIKUTI BERKAS YANG SEBENARNYA.
        //
        // Mime dipercaya lebih dulu, ekstensi jadi cadangan: berkas yang
        // diunggah dari ponsel kerap kehilangan ekstensinya. Ikon gambar untuk
        // sebuah PDF membuat orang mengira ia bisa dilihat sekilas — lalu ia
        // menekannya dan mendapat unduhan.
        $rupa = 'lain';
        if ($b) {
            if (str_starts_with($mime, 'image/') || in_array($ext, ['JPG', 'JPEG', 'PNG', 'WEBP', 'HEIC', 'GIF'], true)) {
                $rupa = 'gambar';
            } elseif ($mime === 'application/pdf' || $ext === 'PDF') {
                $rupa = 'pdf';
            }
        } else {
            $rupa = 'kosong';
        }

        return [
            'nama' => $label,
            'tipe' => $ext !== '' ? $ext : ($b ? 'FILE' : '—'),
            'rupa' => $rupa,
            'ada' => (bool) $b,
            // Id ber-hash untuk membuka berkasnya lewat signed URL. Null bila
            // memang belum ada yang bisa dibuka — layar memakainya untuk
            // menentukan barisnya bisa ditekan atau tidak.
            'id' => $b ? \Vinkla\Hashids\Facades\Hashids::encode($b->Id_Formulir_Berkas) : null,
            'pratinjau' => null,
            // Mime & ukuran dibawa apa adanya: pratinjau memutuskan gambar atau
            // PDF dari keduanya, bukan menebak dari nama berkas.
            'mime' => $b->Mime ?? null,
            'ukuranByte' => $b ? (int) ($b->Ukuran_Byte ?? 0) : null,
            'namaAsli' => $b->Nama_Asli ?? null,
            'meta' => $b
                ? 'Diunggah ' . self::tanggal((string) $b->Waktu_Unggah) . ' · ' . self::ukuran((int) ($b->Ukuran_Byte ?? 0))
                : 'Belum diunggah',
        ];
    }

    /** Seluruh lamaran orang ini, terbaru dulu, berikut tahapannya. */
    private static function riwayat(int $userId): array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Id_Lamaran')
            ->limit(12)
            ->get([
                'l.Id_Lamaran', 'l.Kode', 'l.Status', 'l.Waktu_Lamar', 'l.Alasan_Gugur', 'l.Gugur_Di_Tahap',
                'p.Nama as ProgramNama', 'x.Posisi', 'x.Departemen',
            ]);

        if ($lamaran->isEmpty()) {
            return [];
        }

        $tahapPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran'))
            ->orderBy('Urutan')
            ->get(['Lamaran_Id', 'Urutan', 'Label', 'Status', 'Hasil', 'Skor', 'Diputus_At', 'Flag_Bypass', 'Hold_Flag', 'Masuk_Talent_Pool'])
            ->groupBy('Lamaran_Id');

        return $lamaran->map(function ($l) use ($tahapPer) {
            $tahapan = collect($tahapPer[$l->Id_Lamaran] ?? [])->map(fn ($t) => [
                // Nomor urut ikut dikirim: linimasa yang menyebut "tahap 5"
                // hanya berguna kalau nomornya sama dengan yang dilihat
                // kandidat di portalnya.
                'urutan' => (int) $t->Urutan,
                'nama' => $t->Label,
                'hasil' => self::hasilTahap($t),
                'nilai' => $t->Skor !== null ? (float) $t->Skor : null,
                'tgl' => $t->Diputus_At ? self::tanggal((string) $t->Diputus_At) : '—',
            ])->values()->all();

            return [
                'kode' => $l->Kode,
                'program' => $l->ProgramNama ?: '—',
                'posisi' => $l->Posisi ?: '—',
                'departemen' => $l->Departemen,
                'periode' => $l->Waktu_Lamar ? self::bulan((string) $l->Waktu_Lamar) : '—',
                'hasil' => self::hasilLamaran((string) $l->Status),
                'note' => $l->Alasan_Gugur
                    ?: ($l->Gugur_Di_Tahap ? 'Berhenti di tahap ' . $l->Gugur_Di_Tahap . '.' : 'Tidak ada catatan penutup untuk lamaran ini.'),
                'tahapan' => $tahapan,
            ];
        })->values()->all();
    }

    /**
     * Status satu tahap → kosakata yang dipakai layar.
     *
     * BYPASS DIPERIKSA LEBIH DULU: tahap yang dilewati lewat fast-track juga
     * berhasil "LULUS" di kolom Hasil, dan menampilkannya sebagai lulus membuat
     * rekruter mengira kandidat pernah mengerjakannya.
     */
    private static function hasilTahap($t): string
    {
        // TITIK PINDAH KE TALENT POOL — DIPERIKSA PALING AWAL.
        //
        // Inilah tahap yang menjelaskan kenapa orang ini ada di kolam, dan
        // dulu ia justru yang paling salah tampil: `Hasil` berbunyi
        // 'TALENT_POOL' yang bukan LULUS dan bukan GAGAL, sehingga jatuh ke
        // cabang terakhir dan tercetak "BELUM" — abu-abu, sama seperti tahap
        // yang memang belum pernah dijalani. Perjalanan kandidat lalu terbaca
        // seperti berhenti tanpa sebab.
        $hasil = strtoupper((string) ($t->Hasil ?? ''));
        if ($hasil === 'TALENT_POOL' || ($t->Masuk_Talent_Pool ?? 'T') === 'Y') {
            return 'TALENT_POOL';
        }

        if (($t->Flag_Bypass ?? 'T') === 'Y') {
            return 'SKIP';
        }

        if (in_array($hasil, ['LULUS', 'LOLOS'], true)) {
            return 'LULUS';
        }
        if (in_array($hasil, ['GAGAL', 'GUGUR', 'TIDAK_LULUS'], true)) {
            return 'GAGAL';
        }

        return strtoupper((string) ($t->Status ?? '')) === 'BERJALAN' ? 'BERJALAN' : 'BELUM';
    }

    private static function hasilLamaran(string $status): string
    {
        return match (strtoupper($status)) {
            'TALENT_POOL' => 'TALENT_POOL',
            'GUGUR', 'DITOLAK' => 'GUGUR',
            'LOLOS', 'DITERIMA' => 'LOLOS',
            default => 'BERJALAN',
        };
    }

    /** `pas_foto_resmi` → `Pas Foto Resmi`. Cadangan saat skemanya tak ada. */
    private static function labelDariKunci(string $key): string
    {
        return ucwords(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }

    private static function tanggal(string $v): string
    {
        try {
            return Carbon::parse($v)->format('d M Y');
        } catch (\Throwable $e) {
            return $v;
        }
    }

    private static function bulan(string $v): string
    {
        try {
            return Carbon::parse($v)->format('M Y');
        } catch (\Throwable $e) {
            return $v;
        }
    }

    private static function ukuran(int $byte): string
    {
        if ($byte <= 0) {
            return '—';
        }

        return $byte >= 1048576
            ? number_format($byte / 1048576, 1, ',', '.') . ' MB'
            : number_format($byte / 1024, 0, ',', '.') . ' KB';
    }

    /** Jawaban SELURUH pengisian lamaran, digabung; yang terbaru menang. */
    private static function jawabanGabungan(int $lamaranId): array
    {
        $gabung = [];

        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Formulir_Pengisian')
            ->pluck('Jawaban_Json');

        foreach ($rows as $json) {
            $isi = json_decode($json ?: '', true);
            if (is_array($isi)) {
                $gabung = array_merge($gabung, $isi);
            }
        }

        return $gabung;
    }
}
