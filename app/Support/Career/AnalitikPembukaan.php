<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — ANGKA SEBUAH TERBITAN.
 *
 * ══ APA YANG BISA DIHITUNG, DAN APA YANG TIDAK ══════════════════════════════
 *
 * Rancangan layarnya meminta empat kartu: Dilihat, Pelamar, Konversi, Lolos
 * Administrasi. Dua di antaranya TIDAK BISA dihitung dari basis data ini, dan
 * itu bukan soal kueri yang belum ditulis:
 *
 *   Dilihat    tidak ada satu pun tabel yang mencatat kunjungan halaman
 *              lowongan. Seluruh 114 tabel N_WEB_CAREERS_* sudah ditelusuri;
 *              tidak ada Pembukaan_View, tidak ada log kunjungan, tidak ada
 *              penghitung apa pun di sisi landing.
 *   Konversi   turunan dari Dilihat. Tanpa penyebutnya, angkanya tidak ada.
 *
 * Keduanya sengaja TIDAK diisi angka karangan. Angka yang tampak masuk akal
 * lebih berbahaya daripada kolom kosong: ia dibaca rapat, dikutip ke laporan,
 * dan dipakai memutuskan anggaran iklan — dan tidak seorang pun tahu angkanya
 * tidak pernah diukur. Bila pelacakan tayangan memang diinginkan, ia harus
 * dibangun lebih dulu; tempatnya sudah disiapkan di layar.
 *
 * Yang MENGGANTIKANNYA justru lebih menjawab pertanyaan pemilik terbitan:
 * berapa yang melamar, sedang di tahap mana, berapa yang lolos, dan kursi
 * mana yang belum terisi. Semuanya terukur, semuanya dari baris nyata.
 */
class AnalitikPembukaan
{
    /** Rentang hari yang boleh diminta layar. */
    public const RENTANG = [7, 14, 30];

    /**
     * Seluruh angka satu terbitan.
     *
     * @param  int  $pembukaanId  Id_Pembukaan
     * @param  int  $hari  panjang deret harian (7/14/30)
     */
    public static function untuk(int $pembukaanId, int $hari = 7): array
    {
        $hari = in_array($hari, self::RENTANG, true) ? $hari : 7;

        $pb = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $pembukaanId)->first();
        if (! $pb) {
            return self::kosong($hari);
        }

        // Satu pengambilan untuk seluruh perhitungan di bawah. Lamaran satu
        // terbitan jarang lebih dari beberapa ribu baris, dan mengambilnya
        // sekali jauh lebih murah daripada tujuh kueri agregat yang masing-
        // masing menyapu tabel yang sama.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Pembukaan_Id', $pembukaanId)
            ->get(['Id_Lamaran', 'Program_Posisi_Id', 'Status', 'Hasil_Akhir', 'Urutan_Tahap', 'Waktu_Lamar', 'Created_At']);

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $pb->Program_Id)
            ->get(['Id_Program_Posisi', 'Posisi', 'Level', 'Kuota', 'Terisi', 'Flag_Aktif', 'Mpp_Ref', 'Departemen', 'Lokasi', 'Status']);

        return [
            'ringkas' => self::ringkas($lamaran, $posisi),
            'deret' => self::deret($lamaran, $hari),
            // CORONG SELEKSI TINGKAT TERBITAN SUDAH TIDAK ADA.
            // Satu terbitan membawa banyak MPP, dan corong yang menjumlahkan
            // seluruhnya menjawab pertanyaan yang tidak dimiliki siapa pun:
            // "di tahap mana orang berguguran" hanya berarti bila ditanyakan
            // PER LOKER — tiap MPP punya alur, kuota, dan pelamar sendiri.
            // Corongnya kini hidup di panel detail loker, dalam bentuk piramida.
            'kuota' => self::kuota($posisi),
            'loker' => self::perLoker($lamaran, $posisi),
            // Matriks loker x hari untuk heatmap. Angkanya dari baris lamaran
            // yang sama, bukan dari deret acak: sel yang gelap memang loker
            // yang benar-benar diserbu pada hari itu.
            'heat' => self::heat($lamaran, $posisi, $hari),
            'sumber' => self::sumber($pb, $lamaran),
        ];
    }

    /** Kartu-kartu angka besar. */
    private static function ringkas($lamaran, $posisi): array
    {
        $total = $lamaran->count();
        $berjalan = $lamaran->where('Status', 'BERJALAN')->count();
        $lulus = $lamaran->where('Status', 'LULUS')->count();
        $gugur = $lamaran->where('Status', 'GUGUR')->count();

        $kuota = (int) $posisi->where('Flag_Aktif', '!=', 'N')->sum('Kuota');
        $terisi = (int) $posisi->where('Flag_Aktif', '!=', 'N')->sum('Terisi');

        return [
            'pelamar' => $total,
            'berjalan' => $berjalan,
            'lulus' => $lulus,
            'gugur' => $gugur,
            'kuota' => $kuota,
            'terisi' => $terisi,
            // Bagi nol dijaga: program tanpa kuota bukan program yang 100%
            // terisi, dan bukan pula galat yang boleh menjatuhkan halaman.
            'persenKuota' => $kuota > 0 ? round($terisi / $kuota * 100) : 0,
            // Rasio lolos dari yang SUDAH SELESAI diproses — bukan dari total.
            // Dibagi total, angkanya turun terus selama masih ada yang berjalan,
            // dan terbaca seperti mutu yang memburuk padahal belum diputus.
            'persenLulus' => ($lulus + $gugur) > 0 ? round($lulus / ($lulus + $gugur) * 100) : 0,
        ];
    }

    /**
     * Pelamar masuk per hari, plus pembanding periode sebelumnya.
     *
     * Deltanya dihitung, bukan ditulis tangan: N hari terakhir dibandingkan
     * dengan N hari sebelum itu. Lencana "+24%" yang tidak dihitung dari apa
     * pun adalah kebohongan kecil yang dibaca sebagai fakta.
     */
    private static function deret($lamaran, int $hari): array
    {
        $waktu = fn ($l) => $l->Waktu_Lamar ?: $l->Created_At;

        $hitung = [];
        foreach ($lamaran as $l) {
            $w = $waktu($l);
            if (! $w) {
                continue;
            }
            $tgl = substr((string) $w, 0, 10);
            $hitung[$tgl] = ($hitung[$tgl] ?? 0) + 1;
        }

        $titik = [];
        $kini = new \DateTimeImmutable('today');
        for ($i = $hari - 1; $i >= 0; $i--) {
            $d = $kini->modify("-{$i} day");
            $k = $d->format('Y-m-d');
            $titik[] = [
                'tanggal' => $k,
                'label' => $d->format('j/n'),
                'nilai' => (int) ($hitung[$k] ?? 0),
            ];
        }

        $periodeIni = array_sum(array_column($titik, 'nilai'));

        $periodeLalu = 0;
        for ($i = $hari * 2 - 1; $i >= $hari; $i--) {
            $periodeLalu += (int) ($hitung[$kini->modify("-{$i} day")->format('Y-m-d')] ?? 0);
        }

        return [
            'hari' => $hari,
            'titik' => $titik,
            'periodeIni' => $periodeIni,
            'periodeLalu' => $periodeLalu,
            // null = tidak ada pembanding. Nol dibandingkan nol bukan "naik 0%",
            // dan bukan pula "turun 100%" — ia memang belum bisa dibandingkan.
            'delta' => $periodeLalu > 0
                ? (int) round(($periodeIni - $periodeLalu) / $periodeLalu * 100)
                : null,
        ];
    }

    /**
     * PIRAMIDA SELEKSI SATU LOKER — berapa yang sampai di tiap tahap.
     *
     * ══ KENAPA PER LOKER, BUKAN PER TERBITAN ═══════════════════════════════
     *
     * Satu terbitan bisa membawa dua belas MPP dengan alur, kuota, dan tingkat
     * persaingan yang sama sekali berbeda. Corong yang menjumlahkan semuanya
     * menghasilkan satu bentuk rata-rata yang tidak menggambarkan satu loker
     * pun: MPP yang diserbu 200 orang menenggelamkan MPP yang dilamar tiga
     * orang, dan rekruter membaca "sehat" untuk posisi yang sebenarnya sepi.
     *
     * ══ BENTUK PIRAMIDA ════════════════════════════════════════════════════
     *
     * Tahap pertama di DASAR (paling lebar), tahap terakhir di PUNCAK (paling
     * sempit) — itulah bentuk yang benar bagi proses yang menyaring. Angkanya
     * "berapa yang SAMPAI di tahap ini", bukan berapa baris tahap yang dibuat:
     * baris dibuat untuk seluruh tahap sejak lamaran masuk, jadi menghitungnya
     * mentah-mentah menghasilkan piramida berbentuk balok.
     *
     * @return array{total: int, tahap: array}
     */
    public static function corongLoker(int $pembukaanId, int $posisiId): array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Pembukaan_Id', $pembukaanId)
            ->where('Program_Posisi_Id', $posisiId)
            ->get(['Id_Lamaran']);

        if ($lamaran->isEmpty()) {
            return ['total' => 0, 'tahap' => []];
        }

        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran'))
            ->orderBy('Urutan')
            ->get(['Lamaran_Id', 'Urutan', 'Label', 'Kode', 'Status', 'Hasil']);

        // ── PIRAMIDA ────────────────────────────────────────────────────────
        $per = [];
        foreach ($rows as $t) {
            $u = (int) $t->Urutan;
            $per[$u] ??= ['urutan' => $u, 'label' => $t->Label ?: $t->Kode, 'sampai' => 0];

            // Hanya tahap yang sudah DIJALANI — lihat catatan di atas.
            if ($t->Status !== 'MENUNGGU') {
                $per[$u]['sampai']++;
            }
        }
        ksort($per);

        $puncak = max(max(array_column($per, 'sampai') ?: [0]), 1);
        $tahap = array_values(array_map(function ($x) use ($puncak) {
            $x['persen'] = (int) round($x['sampai'] / $puncak * 100);

            return $x;
        }, $per));

        return ['total' => $lamaran->count(), 'tahap' => $tahap];
    }

    /** Cincin pemenuhan kuota. */
    private static function kuota($posisi): array
    {
        $aktif = $posisi->where('Flag_Aktif', '!=', 'N');
        $kuota = (int) $aktif->sum('Kuota');
        $terisi = (int) $aktif->sum('Terisi');

        return [
            'kuota' => $kuota,
            'terisi' => $terisi,
            'sisa' => max(0, $kuota - $terisi),
            'persen' => $kuota > 0 ? round($terisi / $kuota * 100) : 0,
        ];
    }

    /**
     * Loker mana yang paling diminati.
     *
     * Tidak ada di rancangan aslinya, tapi inilah pertanyaan yang muncul
     * begitu satu program membawa sepuluh loker: mana yang diserbu, dan mana
     * yang sepi dan perlu didorong.
     */
    private static function perLoker($lamaran, $posisi): array
    {
        $per = $lamaran->groupBy('Program_Posisi_Id')->map->count();
        $puncak = max($per->values()->all() ?: [1]);

        return $posisi->map(fn ($p) => [
            'id' => (int) $p->Id_Program_Posisi,
            'posisi' => $p->Posisi,
            'level' => $p->Level,
            // Nomor MPP ikut supaya layar bisa membuka detail MPP loker ini
            // tanpa perlu mencocokkan namanya sendiri.
            'mppRef' => $p->Mpp_Ref ?? null,
            'departemen' => $p->Departemen ?? null,
            'lokasi' => $p->Lokasi ?? null,
            'status' => $p->Status ?? null,
            'aktif' => ($p->Flag_Aktif ?? 'Y') !== 'N',
            'kuota' => (int) $p->Kuota,
            'terisi' => (int) $p->Terisi,
            'pelamar' => (int) ($per[$p->Id_Program_Posisi] ?? 0),
            'persen' => $puncak > 0 ? round(((int) ($per[$p->Id_Program_Posisi] ?? 0)) / $puncak * 100) : 0,
        ])->sortByDesc('pelamar')->values()->all();
    }

    /**
     * MATRIKS HEATMAP — pelamar per loker, per hari.
     *
     * Menggantikan grafik multi-garis yang ada di rancangan. Satu garis per
     * loker masih terbaca pada tiga loker; pada dua belas ia menjadi kusut
     * benang yang tidak menjawab apa pun. Heatmap menahan jumlah berapa pun:
     * mata langsung menemukan baris tergelap tanpa membaca satu label pun.
     *
     * Barisnya diurutkan dari yang paling banyak pelamarnya, jadi yang paling
     * perlu dilihat selalu berada di atas.
     */
    private static function heat($lamaran, $posisi, int $hari): array
    {
        // Mengikuti rentang yang diminta layar (7/14/30) — data yang sama
        // dipakai dua grafik sekaligus: heatmap DAN kolom bertumpuk "Pelamar
        // Masuk". Kerapatan 30 kolom diurus di sisi layar (angka di dalam sel
        // disembunyikan), bukan dengan memotong datanya di sini.

        $kini = new \DateTimeImmutable('today');
        $tanggal = [];
        for ($i = $hari - 1; $i >= 0; $i--) {
            $tanggal[] = $kini->modify("-{$i} day");
        }

        // [posisiId][Y-m-d] => jumlah
        $bucket = [];
        foreach ($lamaran as $l) {
            $w = $l->Waktu_Lamar ?: $l->Created_At;
            if (! $w || ! $l->Program_Posisi_Id) {
                continue;
            }
            $k = substr((string) $w, 0, 10);
            $bucket[(int) $l->Program_Posisi_Id][$k] = ($bucket[(int) $l->Program_Posisi_Id][$k] ?? 0) + 1;
        }

        $baris = $posisi->map(function ($p) use ($bucket, $tanggal) {
            $id = (int) $p->Id_Program_Posisi;
            $sel = array_map(fn ($d) => (int) ($bucket[$id][$d->format('Y-m-d')] ?? 0), $tanggal);

            return [
                'id' => $id,
                'posisi' => $p->Posisi,
                'mppRef' => $p->Mpp_Ref ?? null,
                'aktif' => ($p->Flag_Aktif ?? 'Y') !== 'N',
                'sel' => $sel,
                'total' => array_sum($sel),
            ];
        })->sortByDesc('total')->values()->all();

        $puncak = 0;
        foreach ($baris as $b) {
            $puncak = max($puncak, ...($b['sel'] ?: [0]));
        }

        return [
            'hari' => $hari,
            'kolom' => array_map(fn ($d) => $d->format('j/n'), $tanggal),
            'kolomPanjang' => array_map(fn ($d) => $d->format('D, j M'), $tanggal),
            'baris' => $baris,
            'puncak' => $puncak,
        ];
    }

    /**
     * SUMBER PELAMAR — daftar lengkap dari MASTER, dihitung dari jawaban nyata.
     *
     * ══ KENAPA DAFTARNYA DARI MASTER, BUKAN DARI JAWABAN SAJA ═══════════════
     *
     * Versi sebelumnya hanya menghitung nilai yang MUNCUL di jawaban, lalu
     * menampilkan apa adanya. Dua akibatnya buruk:
     *
     *   1. Kanal yang nol pelamar HILANG dari layar. Padahal justru itu yang
     *      perlu dilihat: "Job Fair tidak menghasilkan satu pun" adalah temuan,
     *      bukan ketiadaan data. Kanal yang menghilang terbaca seperti kanal
     *      yang tidak pernah ditawarkan.
     *   2. Urutan dan jumlah barisnya berubah-ubah antar terbitan, jadi dua
     *      terbitan tidak bisa dibandingkan sekilas.
     *
     * Sekarang daftarnya diambil dari OPSI FIELD di formulir pendaftaran —
     * itulah "master" sumber yang sesungguhnya di sistem ini: satu-satunya
     * tempat yang menetapkan pilihan apa saja yang boleh dijawab kandidat.
     * Seluruh opsi ditampilkan, termasuk yang bernilai nol.
     *
     * ══ SATU BUG LAMA IKUT TERTUTUP ════════════════════════════════════════
     *
     * Kunci yang dibaca dulu `sumber_info`. Kunci itu TIDAK PERNAH ADA — di
     * formulir namanya `sumber_informasi`. Jadi kartu Sumber Pelamar selalu
     * kosong sejak hari pertama, dan kekosongannya terbaca sebagai "belum ada
     * yang menjawab" padahal semua orang menjawab. Kuncinya kini tidak ditebak
     * lagi: ia dicari dari schema formulirnya sendiri.
     *
     * Larik KOSONG hanya bila formulirnya memang tidak punya pertanyaan sumber.
     */
    private static function sumber($pb, $lamaran): array
    {
        $field = self::fieldSumber($pb);
        if (! $field) {
            return [];
        }

        $kunci = $field['key'];
        $opsi = $field['opsi'];

        $hitung = [];
        foreach ($opsi as $o) {
            $hitung[$o] = 0;
        }

        $terjawab = 0;

        if ($lamaran->isNotEmpty()) {
            $isi = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran'))
                ->where('Sumber', 'PENDAFTARAN')
                ->whereNotNull('Waktu_Kirim')
                ->pluck('Jawaban_Json');

            foreach ($isi as $json) {
                $j = json_decode($json ?: '{}', true) ?: [];
                $nilai = $j[$kunci] ?? null;

                // Field pilihan-ganda menyimpan larik; tiap pilihan dihitung
                // sendiri-sendiri, bukan digabung jadi satu label gabungan yang
                // tidak cocok dengan opsi mana pun.
                foreach (is_array($nilai) ? $nilai : [$nilai] as $v) {
                    $v = trim((string) $v);
                    if ($v === '') {
                        continue;
                    }

                    $cocok = self::cocokkanOpsi($v, $opsi);
                    // Jawaban bebas-ketik yang di luar daftar dikumpulkan di
                    // satu baris, bukan dibuang: jumlahnya adalah petunjuk
                    // bahwa daftar opsinya perlu ditambah.
                    $kunciHitung = $cocok ?? 'Lainnya';
                    $hitung[$kunciHitung] = ($hitung[$kunciHitung] ?? 0) + 1;
                    $terjawab++;
                }
            }
        }

        // "Lainnya" hanya ditampilkan bila memang terisi dan bukan opsi resmi.
        if (($hitung['Lainnya'] ?? 0) === 0 && ! in_array('Lainnya', $opsi, true)) {
            unset($hitung['Lainnya']);
        }

        arsort($hitung);

        $out = [];
        foreach ($hitung as $label => $n) {
            $out[] = [
                'label' => $label,
                'jumlah' => $n,
                'persen' => $terjawab > 0 ? (int) round($n / $terjawab * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * Field "tahu lowongan dari mana" pada formulir pendaftaran program ini,
     * beserta daftar opsinya.
     *
     * Formulirnya TIDAK ditebak dari kategori. Ia ditelusuri lewat jalur yang
     * sama dengan yang benar-benar disodorkan ke kandidat:
     *
     *     program -> Alur_Kode -> tahap pertama -> Formulir_Kode -> versi terbit
     *
     * Cara lama (menebak dari kategori lewat Komponen_Kode) sudah tidak bisa
     * dipakai: seluruh formulir yang aktif sekarang dibuat lewat Master
     * Formulir dan Komponen_Kode-nya NULL, jadi pencarian itu selalu nihil dan
     * kartu Sumber Pelamar selalu kosong. Ia tetap dicoba paling akhir, untuk
     * pemasangan lama yang masih memakai formulir berkomponen.
     *
     * @return array{key: string, opsi: array<int, string>}|null
     */
    private static function fieldSumber($pb): ?array
    {
        $program = DB::table('N_WEB_CAREERS_Program')
            ->where('Id_Program', $pb->Program_Id)
            ->first(['Alur_Kode', 'Kategori']);

        $schema = null;

        if ($program && $program->Alur_Kode) {
            $kode = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
                ->where('a.Kode', $program->Alur_Kode)
                ->whereNotNull('t.Formulir_Kode')
                ->orderBy('t.Urutan')
                ->value('t.Formulir_Kode');

            if ($kode) {
                $schema = FormulirSchema::publishedByKode($kode)['schema'] ?? null;
            }
        }

        if (! $schema) {
            $schema = FormulirSchema::pendaftaranUntukKategori(
                (string) ($program->Kategori ?? 'REKRUTMEN'),
            )['schema'] ?? null;
        }

        return $schema ? self::cariFieldSumber($schema) : null;
    }

    /**
     * Telusuri schema formulir, kembalikan field pertanyaan sumber + opsinya.
     *
     * @return array{key: string, opsi: array<int, string>}|null
     */
    private static function cariFieldSumber(array $schema): ?array
    {
        foreach (($schema['langkah'] ?? []) as $langkah) {
            foreach (($langkah['bagian'] ?? []) as $bagian) {
                foreach (($bagian['field'] ?? []) as $f) {
                    $key = (string) ($f['key'] ?? '');
                    $label = mb_strtolower((string) ($f['label'] ?? ''));

                    // Dikenali dari kuncinya lebih dulu; labelnya jadi cadangan
                    // supaya formulir buatan admin yang memakai kunci lain tetap
                    // terbaca selama pertanyaannya masih pertanyaan yang sama.
                    $cocok = str_contains($key, 'sumber')
                        || str_contains($label, 'dari mana')
                        || str_contains($label, 'darimana');

                    if (! $cocok) {
                        continue;
                    }

                    $opsi = array_values(array_filter(array_map(
                        // Opsi boleh string polos atau objek {nilai,label}.
                        fn ($o) => is_array($o) ? trim((string) ($o['label'] ?? $o['nilai'] ?? '')) : trim((string) $o),
                        (array) ($f['opsi'] ?? []),
                    )));

                    if ($key !== '' && $opsi) {
                        return ['key' => $key, 'opsi' => $opsi];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Samakan jawaban dengan salah satu opsi master.
     *
     * Perbandingannya longgar (huruf besar-kecil, spasi ganda) karena jawaban
     * lama bisa tersimpan dengan ejaan yang sedikit berbeda dari opsi yang
     * berlaku sekarang — dan "Job Fair" versus "job fair" bukan dua kanal.
     */
    private static function cocokkanOpsi(string $nilai, array $opsi): ?string
    {
        $rapi = fn ($x) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $x)) ?? '');
        $cari = $rapi($nilai);

        foreach ($opsi as $o) {
            if ($rapi($o) === $cari) {
                return $o;
            }
        }

        return null;
    }

    private static function kosong(int $hari): array
    {
        return [
            'ringkas' => ['pelamar' => 0, 'berjalan' => 0, 'lulus' => 0, 'gugur' => 0, 'kuota' => 0, 'terisi' => 0, 'persenKuota' => 0, 'persenLulus' => 0],
            'deret' => ['hari' => $hari, 'titik' => [], 'periodeIni' => 0, 'periodeLalu' => 0, 'delta' => null],
            'kuota' => ['kuota' => 0, 'terisi' => 0, 'sisa' => 0, 'persen' => 0],
            'loker' => [],
            'heat' => ['kolom' => [], 'baris' => [], 'puncak' => 0],
            'sumber' => [],
        ];
    }
}
