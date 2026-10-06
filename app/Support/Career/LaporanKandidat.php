<?php

namespace App\Support\Career;

use App\Support\Career\BerkasBaris;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — pengumpul data LAPORAN KANDIDAT (read-only).
 *
 * Satu tempat yang merakit seluruh isi laporan: biodata, foto verifikasi,
 * perjalanan tahap, hasil tes, dan jawaban formulir. Dipakai PDF maupun Excel,
 * supaya keduanya mustahil menampilkan angka yang berbeda untuk orang yang
 * sama — kalau masing-masing merakit sendiri, satu perubahan aturan hanya akan
 * terpasang di salah satunya dan tak ada yang menyadarinya sampai ada yang
 * membandingkan dua berkas.
 *
 * TIDAK menyentuh sesi login: dipanggil dari dalam antrean, tempat tidak ada
 * pengguna yang sedang masuk.
 */
class LaporanKandidat
{
    /**
     * FORMULIR MANA YANG BOLEH DICETAK.
     *
     * Satu lamaran bisa punya beberapa pengisian: MT mengumpulkan data dua kali
     * (pendaftaran, lalu kelengkapan data diri di tahap berikutnya).
     *
     * ── URUTANNYA KRONOLOGIS: TERLAMA DULU ────────────────────────────────
     *
     * Formulir pendaftaran diisi lebih dulu dan memuat identitas; formulir
     * tahap lanjutan menambah dan memperbarui. Dibaca dari yang terbaru,
     * dokumen membuka dengan pembaruan lalu mundur ke identitas dasar —
     * urutan yang memaksa pembaca melompat-lompat. Kronologi juga yang
     * dilihat admin saat menyusun ulang lewat drag: yang di atas memang yang
     * lebih dulu terjadi.
     *
     * SEMUANYA ditandai `utama` — bawaannya seluruh formulir ikut tercetak,
     * dan admin mematikan yang tidak perlu. Sebelumnya hanya yang terbaru,
     * dan itu diam-diam menyembunyikan jawaban pendaftaran dari dokumen.
     *
     * Hanya yang SUDAH DIKIRIM yang masuk: draf belum tentu benar, dan mencetak
     * setengah jawaban sebagai dokumen resmi lebih buruk daripada tidak mencetak.
     */
    public static function daftarFormulir(int $lamaranId): array
    {
        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            // Nama formulir yang SEBENARNYA — lihat catatan pada 'label'.
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as mf', 'mf.Id_Master_Formulir', '=', 'fp.Master_Formulir_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->whereNotNull('fp.Waktu_Kirim')
            ->orderBy('fp.Waktu_Kirim')
            ->select('fp.Id_Formulir_Pengisian', 'fp.Sumber', 'fp.Komponen_Kode', 'fp.Waktu_Kirim',
                't.Urutan as TahapUrutan', 't.Label as TahapLabel', 'mf.Nama as NamaFormulir')
            ->get();

        return $rows->values()->map(fn ($r) => [
            'id' => Hashids::encode($r->Id_Formulir_Pengisian),
            // ── NAMA FORMULIR, BUKAN NAMA TAHAP ──────────────────────────
            //
            // Dulu label diambil dari Lamaran_Tahap.Label, sehingga formulir
            // pendaftaran tercetak sebagai "Seleksi Administrasi" — itu nama
            // TAHAP tempat formulirnya dipakai, bukan nama formulirnya.
            // Kandidat yang mengisinya melihat "Formulir MT Pendaftaran" di
            // layar, dan berkas resmi yang menyebutnya lain membuat dua
            // dokumen tentang hal yang sama tidak bisa dicocokkan.
            //
            // Nama tahap tetap dibawa terpisah sebagai `tahap` — halaman
            // formulir memakainya sebagai keterangan kecil, karena "formulir
            // ini dikirim pada tahap apa" tetap informasi yang berguna.
            'label' => $r->NamaFormulir
                ?: ($r->TahapLabel ?: ($r->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap')),
            'tahap' => (string) ($r->TahapLabel ?? ''),
            'sumber' => $r->Sumber,
            'komponen' => $r->Komponen_Kode,
            'tahapUrutan' => (int) ($r->TahapUrutan ?? 0),
            'waktuKirim' => (string) $r->Waktu_Kirim,
            'utama' => true,
        ])->all();
    }

    /**
     * Rakit seluruh isi laporan.
     *
     * @param  int[]  $pengisianIds  formulir yang diminta; kosong = yang terbaru.
     */
    public static function rakit(int $lamaranId, array $pengisianIds = []): ?array
    {
        // ── SINGGAHAN SATU PERMINTAAN ─────────────────────────────────────
        //
        // Satu permintaan pratinjau memanggil rakit() dua kali dengan argumen
        // yang sama: sekali lewat petaHalaman() untuk panel Tata letak, sekali
        // lagi lewat jalankan() untuk merender. Tiap panggilan ~1,8 detik di
        // basis data — dan keduanya menanyakan hal yang persis sama.
        //
        // Kuncinya memuat DAFTAR PENGISIAN, bukan nomor lamaran saja: dua
        // pemanggil dengan pilihan formulir berbeda adalah dua pertanyaan
        // berbeda, dan menyinggahkannya pada nomor lamaran saja akan
        // memulangkan formulir yang salah untuk pemanggil kedua.
        //
        // Statis per proses: hidup selama satu permintaan HTTP / satu job
        // antrean, lalu ikut mati. Tidak ada basi yang bisa bertahan ke
        // permintaan berikutnya.
        static $singgah = [];

        $ids = array_values(array_unique(array_map('intval', $pengisianIds)));
        sort($ids);
        $kunciSinggah = $lamaranId . ':' . implode(',', $ids);

        if (array_key_exists($kunciSinggah, $singgah)) {
            return $singgah[$kunciSinggah];
        }

        $hasil = self::rakitSungguhan($lamaranId, $pengisianIds);

        // Batasi: satu job antrean bisa mencetak banyak lamaran berturut-turut,
        // dan tiap hasil rakit membawa foto kandidat beserta seluruh jawaban.
        if (count($singgah) > 8) {
            $singgah = [];
        }

        return $singgah[$kunciSinggah] = $hasil;
    }

    /** Isi sebenarnya rakit() — lihat singgahan di pemanggilnya. */
    private static function rakitSungguhan(int $lamaranId, array $pengisianIds = []): ?array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $lamaranId)
            ->select(
                'l.*',
                'u.Nama as UserNama', 'u.Email as UserEmail', 'u.No_Hp as UserHp',
                'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori',
                'x.Posisi', 'x.Level', 'x.Departemen', 'x.Lokasi',
            )
            ->first();

        if (! $lamaran) {
            return null;
        }

        $profil = LamaranService::dataKandidatEmail($lamaranId);
        $foto = self::fotoKandidat($lamaranId, $profil['fotoPath'] ?? null);

        $hasil = [
            'kandidat' => [
                // NAMA RESMI dari formulir lebih dulu, nama akun cadangan —
                // aturan yang sama dengan kartu worklist. Nama akun diketik
                // saat mendaftar dan kerap seadanya; dokumen resmi yang menyebut
                // kandidat dengan nama itu tidak cocok dengan KTP-nya sendiri.
                'nama' => self::namaResmi($lamaranId) ?: ($lamaran->UserNama ?: $lamaran->Created_By),
                'email' => $lamaran->UserEmail,
                'hp' => $profil['hp'] ?: $lamaran->UserHp,
                'kodeLamaran' => $lamaran->Kode,
                'nik' => $profil['nik'] ?? null,
                'tglLahir' => $profil['tglLahir'],
                'jkel' => $profil['jkel'],
                'kampus' => $profil['kampus'],
                'tahunLulus' => $profil['tahunLulus'],
                // Rincian pendidikan — dipakai sidebar CV. Formulir MT tidak
                // menanyakan tahun lulus (hanya status & semester berjalan),
                // jadi keduanya ikut supaya kolom pendidikan tidak kosong
                // hanya karena pertanyaannya memang tak pernah diajukan.
                'jurusan' => $profil['jurusan'] ?? null,
                'jenjang' => $profil['jenjang'] ?? null,
                'ipk' => $profil['ipk'] ?? null,
                'statusStudi' => $profil['statusStudi'] ?? null,
                'semester' => $profil['semester'] ?? null,
                // NULL bila memang tidak ada — dan tata letaknya menyesuaikan.
                // Bingkai kosong berlabel "foto" pada dokumen yang dibaca
                // direksi terbaca seperti berkas yang gagal dimuat.
                //
                // BENTUKNYA IKUT DIBAWA. Bingkai lingkaran hanya sah kalau
                // fotonya benar-benar sudah dipotong lingkaran; kalau GD tidak
                // tersedia dan yang tertanam masih persegi, cincin bulat justru
                // memamerkan ketidakcocokannya — tata letak beralih ke bingkai
                // persegi. Lihat fotoKandidat().
                'foto' => $foto['uri'] ?? null,
                'fotoBulat' => (bool) ($foto['bulat'] ?? false),
            ],
            'lamaran' => [
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->ProgramKategori,
                'posisi' => $lamaran->Posisi,
                'level' => $lamaran->Level,
                'departemen' => $lamaran->Departemen,
                'lokasi' => $lamaran->Lokasi,
                'status' => $lamaran->Status,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'waktuLamar' => (string) $lamaran->Waktu_Lamar,
                'gugurDi' => $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $lamaran->Alasan_Gugur,
            ],
            'tahap' => self::tahap($lamaranId),
            'formulir' => self::formulir($lamaranId, $pengisianIds),
            'dicetak' => now()->format('d M Y H:i'),
        ];

        // Jawaban formulir yang dipanen untuk halaman Data Kandidat. Dikerjakan
        // SESUDAH formulir dirakit supaya keduanya membaca sumber yang sama —
        // dan supaya panen tidak perlu menyentuh basis data lagi.
        $hasil['panen'] = self::panen($hasil['formulir']);

        return $hasil;
    }

    /**
     * Panen jawaban formulir untuk halaman DATA KANDIDAT.
     *
     * ── KENAPA DIPANEN, BUKAN DIBACA DARI KOLOM ──────────────────────────
     *
     * NIK, agama, status pernikahan, alamat, kontak darurat, dan kesiapan
     * kerja tidak punya kolomnya sendiri di tabel mana pun — semuanya jawaban
     * formulir yang dirancang lewat layar. Rancangan halaman 03 menampilkan
     * semuanya; tanpa dipanen, halaman itu hanya terisi sepertiga dan sisanya
     * kosong melompong.
     *
     * ── DICOCOKKAN DARI LABELNYA, DAN ITU DISENGAJA ──────────────────────
     *
     * Kunci field ditentukan perancang formulir dan berbeda antar program
     * ('nik', 'v_nik', 'identitas_nik'). Labelnya jauh lebih stabil karena ia
     * yang dibaca kandidat — "NIK" tetap "NIK" di formulir mana pun. Yang
     * tidak tertebak tidak hilang: ia tetap tercetak di halaman formulirnya
     * sendiri.
     *
     * @param  array  $formulir  hasil self::formulir()
     */
    private static function panen(array $formulir): array
    {
        // Seluruh isian dari semua formulir, didatarkan jadi label => nilai.
        $isian = [];

        foreach ($formulir as $f) {
            foreach ($f['bagian'] ?? [] as $b) {
                foreach ($b['isian'] ?? [] as $i) {
                    if (! empty($i['berkas']) || ! empty($i['baris'])) {
                        continue;
                    }

                    $nilai = trim((string) $i['nilai']);

                    if ($nilai === '') {
                        continue;
                    }

                    // Yang PERTAMA menang: formulir pendaftaran diisi lebih
                    // dulu dan memuat identitas; formulir tahap lanjutan
                    // umumnya mengulang sebagian pertanyaannya.
                    $isian[] = ['label' => (string) $i['label'], 'nilai' => $nilai, 'bagian' => (string) ($b['judul'] ?? '')];
                }
            }
        }

        $cari = function (array $kata, array $lewati = []) use ($isian) {
            foreach ($isian as $i) {
                $l = mb_strtolower($i['label']);

                foreach ($lewati as $x) {
                    if (str_contains($l, $x)) {
                        continue 2;
                    }
                }

                foreach ($kata as $k) {
                    if (str_contains($l, $k)) {
                        return $i['nilai'];
                    }
                }
            }

            return null;
        };

        // Deret pertanyaan kesediaan — dikenali dari kata kerjanya, bukan dari
        // judul bagiannya, supaya formulir yang menamai bagiannya lain tetap
        // terbaca.
        $kesiapan = collect($isian)
            ->filter(fn ($i) => preg_match('/^(bersedia|sanggup|siap)\b/i', $i['label']))
            ->map(fn ($i) => ['label' => $i['label'], 'nilai' => $i['nilai']])
            ->values()
            ->all();

        // Pertanyaan ya/tidak lain yang bukan kesediaan — mis. "Apakah
        // memiliki buta warna?". Dipisahkan supaya kartu kesiapan tidak
        // tercampur pertanyaan medis.
        $tambahan = collect($isian)
            ->filter(fn ($i) => str_starts_with(mb_strtolower($i['label']), 'apakah')
                && in_array(mb_strtolower($i['nilai']), ['ya', 'tidak'], true))
            ->map(fn ($i) => ['label' => $i['label'], 'nilai' => $i['nilai']])
            ->values()
            ->all();

        return [
            'nik' => $cari(['nik', 'nomor induk kependudukan']),
            'agama' => $cari(['agama']),
            'pernikahan' => $cari(['status pernikahan', 'status perkawinan']),
            'alamatKtp' => $cari(['alamat lengkap', 'alamat ktp', 'alamat sesuai']),
            'alamatDomisili' => $cari(['alamat domisili', 'domisili saat ini']),
            'daruratNama' => $cari(['nama kontak darurat', 'kontak darurat']),
            'daruratHubungan' => $cari(['hubungan dengan peserta', 'hubungan kontak']),
            'daruratHp' => $cari(['no handphone kontak darurat', 'telepon kontak darurat', 'hp kontak darurat']),
            'mulaiKerja' => $cari(['ketersediaan mulai', 'mulai bekerja', 'kesiapan mulai']),
            'ekspektasiGaji' => $cari(['ekspektasi gaji', 'gaji yang diharapkan', 'harapan gaji']),
            'kesiapan' => $kesiapan,
            'tambahan' => $tambahan,
        ];
    }

    /**
     * Nama resmi kandidat menurut FORMULIR.
     *
     * Pengisian dibaca urut dari yang PALING AWAL: formulir pendaftaran diisi
     * lebih dulu dan itulah yang memuat identitas; formulir tahap lanjutan
     * umumnya tidak menanyakan nama lagi.
     */
    private static function namaResmi(int $lamaranId): ?string
    {
        $adaSnapshot = FormulirSchema::punyaKolomPengisianSnapshot();

        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Formulir_Pengisian')
            ->get(array_merge(
                ['Jawaban_Json'],
                $adaSnapshot ? ['Schema_Snapshot_Json'] : [],
            ));

        foreach ($rows as $fp) {
            $nama = IdentitasKandidat::nama(
                json_decode($fp->Jawaban_Json ?: '{}', true) ?: [],
                $fp->Schema_Snapshot_Json ?? null,
            );
            if ($nama) {
                return $nama;
            }
        }

        return null;
    }

    /** Perjalanan tahap + hasil tiap aktivitasnya. */
    private static function tahap(int $lamaranId): array
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Urutan')
            ->get();

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahap->pluck('Id_Lamaran_Tahap')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        return $tahap->map(fn ($t) => [
            'urutan' => (int) $t->Urutan,
            'label' => $t->Label,
            'status' => $t->Status,
            'hasil' => $t->Hasil,
            'catatan' => $t->Catatan,
            'diputusAt' => (string) ($t->Diputus_At ?: ''),
            'diputusOleh' => $t->Diputus_By,
            'ditahan' => ($t->Hold_Flag ?? 'T') === 'Y',
            // Aktivitas INTERNAL tetap masuk: laporan ini dibaca TIM, bukan
            // kandidat. Justru background check & cek referensi yang paling
            // sering ditanyakan saat keputusan ditinjau ulang.
            'aktivitas' => collect($sub->get($t->Id_Lamaran_Tahap, []))->map(fn ($s) => [
                'label' => $s->Label,
                'peran' => $s->Peran,
                'status' => $s->Status,
                'hasil' => $s->Hasil,
                'nilai' => $s->Nilai !== null ? (float) $s->Nilai : null,
                'internal' => ! JadwalPrivat::terlihat($s),
                'catatan' => $s->Catatan,
                'mcuStatus' => $s->Mcu_Status,
                // Penggantian biaya (MCU): Diganti / Tidak diganti / Menunggu —
                // turunan hasilnya, null bila tipenya tak punya ketentuan biaya.
                'biaya' => BiayaAktivitas::status(
                    $s,
                    AlurKolom::masterTipe()->get($s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode),
                )['label'] ?? null,
            ])->values()->all(),
        ])->values()->all();
    }

    /** Jawaban formulir terpilih (atau yang terbaru bila tak ditentukan). */
    private static function formulir(int $lamaranId, array $pengisianIds): array
    {
        $q = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            // Nama formulir dari master — lihat catatan pada daftarFormulir().
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as mf', 'mf.Id_Master_Formulir', '=', 'fp.Master_Formulir_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->whereNotNull('fp.Waktu_Kirim')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel', 'mf.Nama as NamaFormulir');

        if ($pengisianIds) {
            // Dipilih admin → dicetak menurut KRONOLOGI, supaya jawaban lama
            // dan pembaruannya terbaca berurutan.
            $rows = $q->whereIn('fp.Id_Formulir_Pengisian', $pengisianIds)
                ->orderBy('fp.Waktu_Kirim')
                ->get();
        } else {
            // Tanpa pilihan: ambil YANG TERBARU saja. Mencetak semua secara
            // diam-diam membuat laporan satu kandidat membengkak jadi belasan
            // halaman berisi jawaban yang sudah ia perbarui sendiri.
            //
            // Hanya SATU orderBy di sini: SQL Server menolak kolom yang sama
            // muncul dua kali di ORDER BY, dan menambahkan urutan naik di bawah
            // (untuk kronologi) membuat kueri ini gagal total.
            $rows = $q->orderByDesc('fp.Waktu_Kirim')->limit(1)->get();
        }

        $berkas = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $rows->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        return $rows->map(function ($fp) use ($berkas, $lamaranId) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];
            $berkasIni = collect($berkas->get($fp->Id_Formulir_Pengisian, []));
            // LABEL, TIPE, dan PEMBABAKAN asli dari skema yang dibekukan saat
            // formulir dikirim.
            $peta = self::petaSkema($fp->Schema_Snapshot_Json ?? null);
            $label = $peta['label'];
            $urutan = array_flip(array_keys($label));

            $isian = collect($jawaban)
                // URUTAN MENGIKUTI FORMULIR, bukan urutan kunci di JSON.
                // Yang diisi kandidat berurut logis (identitas → alamat →
                // kontak darurat); JSON menyimpannya sesuai urutan tulis,
                // dan dokumen yang melompat-lompat memaksa pembaca mencari.
                // Kunci yang tak ada di skema didorong ke belakang.
                ->sortBy(fn ($v, $k) => $urutan[$k] ?? 9999)
                ->map(function ($v, $k) use ($berkasIni, $label, $peta, $lamaranId) {
                    $b = $berkasIni->firstWhere('Field_Key', $k);

                    return [
                        'key' => $k,
                        // Label ASLI dari skema formulir; hanya bila kuncinya
                        // tidak ada di sana barulah namanya dirapikan sendiri.
                        // Tanpa ini dokumen resmi berbunyi "V Nama", "Nik",
                        // "Alamat Ktp" — pertanyaan yang di layar berbunyi utuh
                        // dan benar tiba-tiba jadi singkatan di atas kertas.
                        'label' => $label[$k] ?? ucwords(str_replace(['_', '-'], ' ', $k)),
                        // Tipe field menentukan BENTUK CETAKNYA: persetujuan
                        // jadi baris bercentang, isian panjang jadi baris penuh,
                        // sisanya masuk kisi dua kolom. Tanpa ini semuanya
                        // tercetak sebagai satu daftar label-nilai yang rata.
                        'tipe' => $peta['tipe'][$k] ?? null,
                        'nilai' => self::nilaiTeks($v),
                        // Isian BERULANG (pengalaman kerja, riwayat sertifikasi)
                        // disimpan sebagai array of objek. Barisnya dipertahankan
                        // supaya bisa dicetak sebagai kartu bernomor, bukan satu
                        // paragraf panjang bertitik koma.
                        // $k ADALAH kunci bagian berulangnya — itulah yang
                        // dicocokkan ke Bagian_Key di tabel berkas.
                        'baris' => self::baris($v, $label, $berkasIni, $lamaranId, (string) $k),
                        // Isian berupa berkas dicetak sebagai ADA/TIDAK, bukan
                        // nama file — nama berkas tidak berarti apa pun di
                        // atas kertas, dan berkasnya sendiri tidak ikut tercetak.
                        'berkas' => $b ? self::berkasRingkas($b, $lamaranId) : null,
                    ];
                })->values()->all();

            return [
                // Id pengisiannya ikut dibawa supaya pemanggil bisa mencocokkan
                // hasil rakitan ini dengan pilihan yang dikirim layar — daftar
                // formulir memakai hashid dari id yang sama. Tanpa ini
                // pencocokannya terpaksa lewat label, yang tidak unik: satu
                // lamaran bisa punya dua pengisian berlabel "Formulir Tahap".
                'pengisianId' => (int) $fp->Id_Formulir_Pengisian,
                // Nama FORMULIR, bukan nama tahap — lihat daftarFormulir().
                'label' => $fp->NamaFormulir
                    ?: ($fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap')),
                'tahap' => (string) ($fp->TahapLabel ?? ''),
                'komponen' => $fp->Komponen_Kode,
                'waktuKirim' => (string) $fp->Waktu_Kirim,
                // Datar — dipakai ekspor Excel, yang memang ingin satu baris
                // satu fakta tanpa pembabakan.
                'isian' => $isian,
                // Berbabak — dipakai PDF. Judulnya diambil dari BAGIAN formulir
                // itu sendiri, jadi dokumen ikut berubah begitu perancang
                // menambah atau menamai ulang sebuah bagian.
                //
                // Tiap bagian membawa KUNCI yang sama dengan yang dipakai
                // Export Studio ('formulir.<hashid>.<md5 judul>') — itulah
                // pegangan yang menghubungkan pilihan admin di layar dengan
                // bagian yang dicetak di sini.
                'bagian' => self::bagi(
                    $isian,
                    $peta['bagian'],
                    'formulir.' . Hashids::encode((int) $fp->Id_Formulir_Pengisian) . '.',
                ),
                'dokumen' => $berkasIni->map(fn ($b) => [
                    'field' => $b->Field_Key,
                    // LABEL ASLI pertanyaannya, bukan kunci yang dirapikan.
                    // Tanpa ini tabel dokumen berbunyi "Dok Kk", "Dok Ktp" —
                    // singkatan internal yang tak pernah dilihat kandidat,
                    // sementara pertanyaannya di layar berbunyi "Kartu Keluarga".
                    //
                    // Bernomor bila berkas itu milik satu baris bagian berulang,
                    // supaya tiga sertifikat tidak tercetak sebagai tiga baris
                    // berjudul sama persis.
                    'label' => BerkasBaris::label(
                        $label[$b->Field_Key] ?? ucwords(str_replace(['_', '-'], ' ', (string) $b->Field_Key)),
                        $b->Baris_Index !== null ? (int) $b->Baris_Index : null,
                    ),
                    // Bisa diklik langsung dari dalam PDF — lihat tautanBerkas().
                    ...self::berkasRingkas($b, $lamaranId),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Tautan dokumen yang bisa diklik DARI DALAM PDF.
     *
     * Bertanda tangan (HMAC dari APP_KEY) dan berumur, bukan rute admin biasa:
     * pembaca PDF tidak membawa cookie sesi, jadi tautan ke rute admin akan
     * selalu mendarat di halaman login — tautan yang pasti gagal lebih buruk
     * daripada tidak ada tautan sama sekali.
     *
     * UMURNYA TERBATAS, dan itu disengaja. Laporan ini memuat data pribadi dan
     * kerap diteruskan lewat surel; tautan yang berlaku selamanya berarti
     * salinan PDF lama tetap membuka dokumen kandidat bertahun-tahun kemudian.
     * 30 hari cukup untuk satu putaran seleksi, sesudahnya laporan tinggal
     * dicetak ulang.
     *
     * Dibangun di ANTREAN, tempat tidak ada permintaan HTTP — jadi alamat
     * dasarnya diambil dari APP_URL. Bila APP_URL salah, tautannya menunjuk ke
     * host yang keliru; itu satu-satunya setelan yang harus benar di produksi.
     */
    private static function tautanBerkas(int $lamaranId, int $berkasId): ?string
    {
        if (! $berkasId) {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'career.laporan.berkas',
                now()->addDays(30),
                ['lamaran' => Hashids::encode($lamaranId), 'berkas' => Hashids::encode($berkasId)],
            );
        } catch (\Throwable $e) {
            // Laporan tanpa tautan masih berguna; laporan yang gagal terbit tidak.
            Log::channel('web_career')->warning('[LAPORAN] tautan berkas gagal dibuat: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Label, tipe, dan PEMBABAKAN dari skema yang dibekukan saat dikirim.
     *
     * Snapshot, BUKAN skema yang berlaku sekarang: pertanyaan bisa diganti
     * namanya setelah kandidat menjawab, dan mencetak jawaban lama di bawah
     * pertanyaan baru adalah cara paling halus menyampaikan hal yang keliru.
     *
     * Urutan kemunculannya ikut terjaga — dipakai mengurutkan isian di dokumen
     * sesuai urutan formulirnya.
     *
     * BAGIAN ikut dibaca karena judulnya ("Data Pribadi & Kontak", "Alamat",
     * "Kontak Darurat") adalah satu-satunya sumber pembabakan yang benar. Kalau
     * dokumen ini memakai daftar judul sendiri, bagian yang baru ditambahkan
     * lewat perancang formulir akan menumpuk di bagian "lain-lain" selamanya.
     *
     * @return array{label: array<string,string>, tipe: array<string,string>, bagian: list<array{judul:string,berulang:bool,keys:list<string>}>}
     */
    private static function petaSkema(?string $json): array
    {
        $peta = ['label' => [], 'tipe' => [], 'bagian' => []];

        $skema = json_decode($json ?: '', true);
        if (! is_array($skema)) {
            return $peta;
        }

        foreach ($skema['langkah'] ?? [] as $langkah) {
            $judulLangkah = trim((string) ($langkah['judul'] ?? ''));

            foreach ($langkah['bagian'] ?? [] as $bagian) {
                $keys = [];

                if (! empty($bagian['berulang'])) {
                    // Seluruh isi bagian berulang tersimpan di bawah SATU kunci
                    // penampung berisi array baris — sama seperti yang dipakai
                    // formulir di layar (lihat kunciBagian() di aturan.js).
                    $kunci = (string) ($bagian['key'] ?? '');
                    if ($kunci === '') {
                        $kunci = trim(preg_replace('/[^a-z0-9]+/', '_',
                            mb_strtolower((string) ($bagian['judul'] ?? 'bagian'))) ?: '', '_') ?: 'bagian';
                    }
                    $keys[] = $kunci;
                    $peta['label'][$kunci] ??= trim((string) ($bagian['judul'] ?? $kunci));

                    // Label kolom di dalam baris — dipakai menamai isi kartu.
                    // `??=` supaya tidak menimpa field tingkat atas bernama sama.
                    foreach ($bagian['field'] ?? [] as $f) {
                        if (! empty($f['key']) && ! empty($f['label'])) {
                            $peta['label'][$f['key']] ??= $f['label'];
                        }
                    }
                } else {
                    foreach ($bagian['field'] ?? [] as $f) {
                        if (empty($f['key'])) {
                            continue;
                        }
                        $keys[] = $f['key'];
                        if (! empty($f['label'])) {
                            $peta['label'][$f['key']] = $f['label'];
                        }
                        if (! empty($f['tipe'])) {
                            $peta['tipe'][$f['key']] = mb_strtolower((string) $f['tipe']);
                        }
                    }
                }

                if ($keys) {
                    $peta['bagian'][] = [
                        'judul' => self::judulBagian($judulLangkah, $bagian),
                        'berulang' => ! empty($bagian['berulang']),
                        'keys' => $keys,
                    ];
                }
            }
        }

        return $peta;
    }

    /**
     * Judul yang benar-benar dilihat kandidat saat mengisi.
     *
     * ── KENAPA BUKAN $bagian['judul'] SAJA ────────────────────────────────
     *
     * Formulir dinamis bersusun DUA tingkat: langkah → bagian. Yang tampil di
     * layar sebagai judul besar adalah LANGKAH ("Data Diri", "Status
     * Pendidikan", "Pendidikan", "Kesediaan", "Verifikasi"); "Bagian 1" hanya
     * nama bawaan wadah di dalamnya, dan perancang formulir tidak pernah
     * mengubahnya karena memang tidak pernah terlihat.
     *
     * Berkas seleksi dulu mengambil judul bagian itu apa adanya, sehingga
     * dokumen resmi mencetak "BAGIAN 1" empat kali berturut-turut — pembaca
     * tidak punya cara tahu bahwa keempatnya adalah data diri, status
     * pendidikan, pendidikan, dan kesediaan.
     *
     * Aturannya:
     *   • judul bagian BAWAAN ("Bagian 1", kosong) → judul LANGKAH
     *   • judul bagian punya nama sendiri          → judul BAGIAN apa adanya
     *
     * "Bawaan" dikenali dari polanya ("Bagian 1", "Section 2", kosong), bukan
     * dari daftar judul terlarang: perancang bebas menamainya apa saja, dan
     * yang ingin dibuang justru nama yang TIDAK pernah ia pilih sendiri.
     */
    private static function judulBagian(string $judulLangkah, array $bagian): string
    {
        $judulBagian = trim((string) ($bagian['judul'] ?? ''));

        if ($judulLangkah === '') {
            return $judulBagian;
        }

        $bawaan = $judulBagian === ''
            || (bool) preg_match('/^(bagian|section|grup|group)\s*\d*$/iu', $judulBagian);

        // Judul bagian yang bawaan tidak menerangkan apa pun — judul langkah
        // yang dipakai, dan itu justru yang dilihat kandidat di layar.
        if ($bawaan) {
            return $judulLangkah;
        }

        // Judul bagian yang PUNYA nama sendiri dipakai apa adanya, tanpa
        // ditempeli judul langkahnya. "Identitas & Pengalaman · B. Identitas"
        // dua kali lebih panjang tanpa menerangkan apa pun yang belum
        // dikatakan "B. Identitas" — dan judul panjang di dokumen cetak
        // memakan lebar yang seharusnya jadi ruang jawaban.
        return $judulBagian;
    }

    /**
     * Isian → dikelompokkan mengikuti bagian formulirnya.
     *
     * Kunci yang tak dikenali skema TIDAK dibuang, melainkan jatuh ke satu
     * bagian tak berjudul di paling belakang. Jawaban yang hilang diam-diam
     * dari dokumen resmi jauh lebih berbahaya daripada jawaban yang tampil
     * tanpa judul bagian — yang pertama tak seorang pun sadari.
     *
     * @param  list<array>  $isian
     * @param  list<array{judul:string,berulang:bool,keys:list<string>}>  $bagian
     */
    private static function bagi(array $isian, array $bagian, string $awalanKunci = ''): array
    {
        $sisa = collect($isian)->keyBy('key');
        $hasil = [];

        // Nomor urut bagian ikut membentuk kunci, BUKAN judulnya saja.
        //
        // Perancang formulir tidak wajib memberi judul yang unik — formulir
        // pendaftaran di produksi punya EMPAT bagian yang semuanya berjudul
        // "Bagian 1". Kunci berbasis judul membuat keempatnya berbagi kunci
        // yang sama, sehingga mencentang satu mematikan semuanya sekaligus.
        //
        // Indeksnya stabil selama skema yang dibekukan tidak berubah — dan
        // skema itu memang dibekukan saat kandidat mengirim.
        $urut = 0;

        foreach ($bagian as $b) {
            $ambil = [];
            foreach ($b['keys'] as $k) {
                if ($sisa->has($k)) {
                    $ambil[] = $sisa->get($k);
                    $sisa->forget($k);
                }
            }
            // Bagian yang seluruh pertanyaannya tidak terjawab (mis. tertutup
            // syarat "tampil_jika") tidak dicetak sebagai judul kosong.
            if ($ambil) {
                $hasil[] = [
                    'judul' => $b['judul'],
                    'berulang' => $b['berulang'],
                    'isian' => $ambil,
                    'kunci' => $awalanKunci . md5($urut . '|' . (string) $b['judul']),
                ];
            }

            $urut++;
        }

        if ($sisa->isNotEmpty()) {
            $hasil[] = [
                'judul' => '',
                'berulang' => false,
                'isian' => $sisa->values()->all(),
                'kunci' => $awalanKunci . md5($urut . '|'),
            ];
        }

        return $hasil;
    }

    /**
     * Jawaban bagian berulang → daftar baris siap cetak.
     *
     * Hanya array-of-objek yang diakui; daftar biasa (jawaban checkbox) bukan
     * baris dan tetap dicetak sebagai satu untai teks.
     *
     * @return ?list<list<array{label:string,nilai:string}>>
     */
    private static function baris(
        mixed $v,
        array $label,
        ?\Illuminate\Support\Collection $berkas = null,
        ?int $lamaranId = null,
        ?string $bagian = null,
    ): ?array {
        if (! is_array($v) || ! $v || ! array_is_list($v)) {
            return null;
        }

        $baris = [];
        // Indeks MENTAH, bukan posisi tampil: baris yang seluruh isinya kosong
        // tidak ikut dicetak, jadi keduanya bisa berbeda — dan yang dicocokkan
        // ke Baris_Index adalah yang mentah.
        foreach (array_values($v) as $ri => $r) {
            if (! is_array($r) || array_is_list($r)) {
                return null;
            }

            $isi = [];
            foreach ($r as $k => $nilai) {
                $teks = self::nilaiTeks($nilai, 1);
                if ($teks === '') {
                    continue;
                }

                // Sub-isian yang ternyata BERKAS dicetak sebagai tautan yang
                // bisa diklik dari dalam PDF, bukan nama berkas sebagai teks
                // mati. Dicocokkan lewat posisi barisnya; berkas lama yang
                // Baris_Index-nya null jatuh ke pencocokan nama.
                $tautan = null;
                if ($berkas && $lamaranId !== null) {
                    $b = $berkas->first(
                        fn ($x) => $x->Bagian_Key === $bagian
                            && $x->Baris_Index !== null
                            && (int) $x->Baris_Index === $ri
                            && $x->Field_Key === (string) $k
                    ) ?? $berkas->first(
                        fn ($x) => $x->Field_Key === (string) $k && $x->Nama_Asli === $teks
                    );

                    if ($b) {
                        $tautan = self::tautanBerkas($lamaranId, (int) $b->Id_Formulir_Berkas);
                    }
                }

                $isi[] = [
                    'label' => $label[$k] ?? ucwords(str_replace(['_', '-'], ' ', (string) $k)),
                    'nilai' => $teks,
                    'tautan' => $tautan,
                ];
            }

            if ($isi) {
                $baris[] = $isi;
            }
        }

        return $baris ?: null;
    }

    /**
     * Jawaban formulir → satu untai teks yang layak dicetak.
     *
     * ISIAN BERULANG BERISI ARRAY OF OBJECT. Pengalaman kerja, riwayat
     * pendidikan, dan daftar keahlian tersimpan sebagai
     * `[{posisi: …, perusahaan: …}, …]`. `implode()` di atasnya melempar
     * "Array to string conversion" — peringatan belaka di PHP, sehingga yang
     * tercetak bukan galat melainkan kata "Array" di tengah dokumen resmi yang
     * dibaca direksi. Persis kelas kekeliruan yang paling lama tak ketahuan.
     *
     * Bentuk cetaknya: tiap baris jadi satu kalimat "label: isi", dipisah titik
     * koma. Kedalamannya dibatasi — struktur yang lebih dalam dari itu hampir
     * pasti data rusak, dan menelusurinya terus hanya menghasilkan paragraf
     * yang tak seorang pun baca.
     */
    private static function nilaiTeks(mixed $v, int $dalam = 0): string
    {
        if (is_bool($v)) {
            return $v ? 'Ya' : 'Tidak';
        }

        if (! is_array($v)) {
            return trim((string) $v);
        }

        if ($dalam >= 3) {
            return '(data bersarang)';
        }

        $bagian = [];
        foreach ($v as $k => $isi) {
            $teks = self::nilaiTeks($isi, $dalam + 1);
            if ($teks === '') {
                continue;
            }
            // Kunci numerik tidak disebut: "1: Jakarta, 2: Bandung" hanya
            // menambah angka yang tidak berarti apa-apa bagi pembaca.
            $bagian[] = is_int($k) ? $teks : ucwords(str_replace(['_', '-'], ' ', (string) $k)) . ': ' . $teks;
        }

        return implode($dalam === 0 ? '; ' : ', ', $bagian);
    }

    /**
     * FOTO MANA YANG DIPAKAI DOKUMEN.
     *
     * Formulirnya DIRANCANG LEWAT LAYAR: kunci baru bisa lahir kapan saja
     * tanpa seorang pun menyunting kode ini. Karena itu tidak satu pun nama
     * kolom ditulis mati di sini — semuanya ditemukan dengan menelusuri apa
     * yang benar-benar tersimpan. Urutannya, dari yang paling layak dicetak:
     *
     *   1. MASTER (Kode='FOTO') — daftar kunci yang memang diakui admin.
     *   2. SKEMA yang dibekukan saat formulir dikirim — field bertipe `foto`
     *      lebih dulu, lalu field `file` yang pertanyaannya memang meminta
     *      pas foto. Ini yang membuat field pas foto BARU langsung terpakai.
     *   3. Berkas GAMBAR mana pun yang kuncinya berbunyi foto (bukan verifikasi).
     *   4. FOTO VERIFIKASI. Hampir pasti ada karena diminta sejak awal apply —
     *      tapi tujuannya memastikan orangnya, bukan dipandang, dan kebanyakan
     *      diambil seadanya dengan kamera depan. Karena itu paling belakang.
     *   5. Tidak ada → null, dan tata letaknya menyesuaikan (tanpa bingkai kosong).
     *
     * Di tiap langkah, TABEL BERKAS dicari lebih dulu lalu JAWABAN_JSON:
     * sebagian formulir menyimpan path — atau bahkan data URI hasil jepretan
     * kamera — di dalam jawaban, tidak pernah singgah di tabel berkas.
     *
     * @param  ?string  $cadangan  path foto verifikasi yang sudah ditemukan pemanggil
     * @return ?array{uri: string, bulat: bool}  `bulat` = sudah dipotong lingkaran;
     *                                           bila false, tata letaknya WAJIB
     *                                           memakai bingkai persegi.
     */
    private static function fotoKandidat(int $lamaranId, ?string $cadangan): ?array
    {
        // DICOBA SATU PER SATU SAMPAI ADA YANG BENAR-BENAR TERBACA.
        //
        // Baris di tabel berkas bukan jaminan berkasnya masih ada: di data
        // sekarang pun ada pas foto yang barisnya lengkap tapi objeknya sudah
        // tidak ada di bucket. Kalau yang seperti itu langsung dianggap "foto
        // kandidat", dokumennya terbit tanpa foto padahal foto verifikasinya
        // masih utuh — dan tak ada galat yang memberi tahu siapa pun.
        foreach (self::calonFoto($lamaranId, $cadangan) as $path) {
            if ($foto = self::fotoDataUri($path)) {
                return $foto;
            }
        }

        return null;
    }

    /**
     * Seluruh calon foto, terurut dari yang paling layak dicetak.
     *
     * @return list<string>
     */
    private static function calonFoto(int $lamaranId, ?string $cadangan): array
    {
        // Pengisian TERBARU didahulukan di seluruh langkah: kandidat yang
        // memperbarui pas fotonya di tahap lanjutan berarti yang lama sudah
        // tidak ia akui. Draf tidak disaring di sini — foto yang sudah
        // terunggah tetap fotonya, sekalipun formulirnya belum dikirim ulang.
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderByDesc('Waktu_Kirim')
            ->orderByDesc('Id_Formulir_Pengisian')
            ->get(['Id_Formulir_Pengisian', 'Jawaban_Json', 'Schema_Snapshot_Json']);

        if ($pengisian->isEmpty()) {
            return array_values(array_filter([$cadangan]));
        }

        $ids = $pengisian->pluck('Id_Formulir_Pengisian')->all();
        $urut = array_flip($ids);

        // HANYA BERKAS GAMBAR. Tanpa saringan ini, formulir yang menamai kunci
        // ijazahnya "foto_ijazah" akan menaruh PDF di dalam bingkai pas foto —
        // dompdf memuatnya sebagai gambar rusak, bukan sebagai galat, jadi yang
        // sampai ke pembaca adalah kotak kosong tanpa ada yang tahu kenapa.
        $berkas = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $ids)
            ->whereNotNull('Path_File')
            ->get(['Formulir_Pengisian_Id', 'Field_Key', 'Path_File', 'Mime', 'Ekstensi'])
            ->filter(fn ($b) => self::berkasGambar($b))
            ->sortBy(fn ($b) => $urut[$b->Formulir_Pengisian_Id] ?? 9999)
            ->values();

        $calon = [];
        $kumpulkan = function (array $kunci) use ($berkas, $pengisian, &$calon) {
            foreach ($kunci as $k) {
                if ($path = $berkas->firstWhere('Field_Key', $k)->Path_File ?? null) {
                    $calon[] = $path;
                }
                foreach ($pengisian as $p) {
                    $jawaban = json_decode($p->Jawaban_Json ?: '{}', true) ?: [];
                    if ($nilai = self::nilaiGambar($jawaban[$k] ?? null)) {
                        $calon[] = $nilai;
                    }
                }
            }
        };

        $kumpulkan(DB::table('N_WEB_CAREERS_Master_Kunci_Identitas')
            ->where('Kode', 'FOTO')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->pluck('Field_Key')->all());

        $kumpulkan(self::kunciFotoSkema($pengisian));

        foreach ($berkas as $b) {
            if (self::berbunyiPasFoto((string) $b->Field_Key)) {
                $calon[] = $b->Path_File;
            }
        }

        if ($cadangan) {
            $calon[] = $cadangan;
        }

        return array_values(array_unique($calon));
    }

    /**
     * Kunci pas foto MENURUT SKEMA yang dibekukan saat formulir dikirim.
     *
     * Tiga lapis, dan URUTANNYA YANG PENTING:
     *
     *   1. field `foto` yang BUKAN foto verifikasi — pas foto sungguhan.
     *   2. field `file` yang pertanyaannya berbunyi pas foto, sebab sebagian
     *      formulir memakai unggahan berkas biasa, bukan tipe khusus.
     *   3. field `foto` yang justru foto verifikasi.
     *
     * Lapis ketiga terpisah karena foto verifikasi di sistem ini DIAMBIL LEWAT
     * KAMERA, jadi ia juga bertipe `foto`. Bila ketiganya disatukan, formulir
     * yang menanyakan foto verifikasi lebih dulu (dan itu urutan yang wajar,
     * ia diminta sejak awal apply) akan membuat swafoto seadanya menang atas
     * pas foto resmi — tanpa satu pun galat, dan hanya ketahuan kalau ada yang
     * membandingkan dokumennya dengan formulir aslinya.
     *
     * Dibaca dari SNAPSHOT, bukan skema yang berlaku sekarang: field foto bisa
     * saja sudah dihapus dari formulir, sementara fotonya masih tersimpan.
     */
    private static function kunciFotoSkema(\Illuminate\Support\Collection $pengisian): array
    {
        $pas = [];
        $file = [];
        $verifikasi = [];

        foreach ($pengisian as $p) {
            $skema = json_decode($p->Schema_Snapshot_Json ?: '', true);
            if (! is_array($skema)) {
                continue;
            }

            foreach ($skema['langkah'] ?? [] as $langkah) {
                foreach ($langkah['bagian'] ?? [] as $bagian) {
                    foreach ($bagian['field'] ?? [] as $f) {
                        $key = (string) ($f['key'] ?? '');
                        if ($key === '') {
                            continue;
                        }

                        $tipe = strtolower((string) ($f['tipe'] ?? ''));
                        $sebutan = $key . ' ' . ($f['label'] ?? '');

                        if ($tipe === 'foto') {
                            self::berbunyiPasFoto($sebutan) ? $pas[] = $key : $verifikasi[] = $key;
                        } elseif ($tipe === 'file' && self::berbunyiPasFoto($sebutan)) {
                            $file[] = $key;
                        }
                    }
                }
            }
        }

        return array_values(array_unique([...$pas, ...$file, ...$verifikasi]));
    }

    /**
     * Keterangan satu berkas untuk PEMBACA DI LAYAR, bukan cuma untuk PDF.
     *
     * Dulu yang dikirim hanya nama, status, dan tautan — cukup bagi dokumen
     * cetak, yang tidak pernah menampilkan berkasnya sendiri. Panel berkas di
     * layar butuh dua hal lagi sebelum bisa menggambar apa pun: apakah ini
     * gambar (ditaruh di <img>) atau PDF (ditaruh di <iframe>), dan berapa
     * besarnya. Tanpa keduanya panel hanya bisa menawarkan tautan unduh —
     * persis keluhan yang membuat berkas "tidak muncul" padahal ada.
     *
     * Tautannya bertanda tangan dan TANPA SESI, jadi bisa langsung dipasang
     * sebagai src; lihat tautanBerkas().
     */
    private static function berkasRingkas(object $b, int $lamaranId): array
    {
        $ext = ltrim(strtolower((string) ($b->Ekstensi ?: pathinfo((string) $b->Path_File, PATHINFO_EXTENSION))), '.');
        $mime = strtolower(trim((string) ($b->Mime ?? '')));

        return [
            'nama' => $b->Nama_Asli,
            'status' => $b->Status_Verifikasi,
            'tautan' => self::tautanBerkas($lamaranId, (int) $b->Id_Formulir_Berkas),
            'ext' => strtoupper($ext),
            'isImage' => self::berkasGambar($b),
            'isPdf' => $mime === 'application/pdf' || $ext === 'pdf',
            'ukuran' => (int) ($b->Ukuran_Byte ?? 0),
            'waktu' => (string) ($b->Waktu_Unggah ?? ''),
        ];
    }

    /**
     * Berkas ini gambar atau bukan.
     *
     * `Mime` yang menentukan bila terisi — nama berkas datang dari kandidat dan
     * bisa berbunyi apa saja. Ekstensi hanya dipakai untuk baris lama yang
     * ditulis sebelum kolom Mime ada.
     */
    private static function berkasGambar(object $b): bool
    {
        $mime = strtolower(trim((string) ($b->Mime ?? '')));
        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        $ext = strtolower((string) ($b->Ekstensi ?: pathinfo((string) $b->Path_File, PATHINFO_EXTENSION)));

        return in_array(ltrim($ext, '.'), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /** Pertanyaan/kunci ini meminta PAS FOTO — dan bukan foto verifikasi. */
    private static function berbunyiPasFoto(string $teks): bool
    {
        $t = mb_strtolower($teks);

        // Foto verifikasi punya jalurnya sendiri di paling belakang; kalau ia
        // ikut tertangkap di sini, ia akan menang atas pas foto yang sebenarnya.
        if (str_contains($t, 'verif') || str_contains($t, 'selfie') || str_contains($t, 'swafoto')) {
            return false;
        }

        return str_contains($t, 'foto') || str_contains($t, 'photo');
    }

    /** Nilai jawaban yang berupa gambar: data URI tertanam, atau path berkas. */
    private static function nilaiGambar(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }

        $v = trim($v);
        if ($v === '') {
            return null;
        }

        return str_starts_with($v, 'data:image/') || preg_match('/\.(jpe?g|png|webp)$/i', $v)
            ? $v
            : null;
    }

    /**
     * Foto verifikasi → data URI, supaya IKUT TERCETAK.
     *
     * dompdf mengambil gambar jarak jauh lewat permintaan HTTP-nya sendiri, dan
     * berkas kita ada di bucket berwenang — permintaan itu akan ditolak, lalu
     * fotonya hilang tanpa galat apa pun. Ditanam sebagai data URI supaya
     * dokumennya berdiri sendiri.
     *
     * Kegagalan mengembalikan null, bukan melempar: laporan tanpa foto masih
     * berguna, laporan yang gagal terbit tidak.
     */
    private static function fotoDataUri(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        // Sudah tertanam sejak dari jawaban formulir (jepretan kamera). Tidak
        // ada yang perlu diambil dari GCS — batas ukurannya tetap ditegakkan,
        // sebab base64 raksasa membuat PDF-nya gagal dirender. Tetap dipotong
        // bulat seperti yang dari GCS: dua sumber yang sama-sama foto kandidat
        // tidak boleh tampil dengan bentuk yang berbeda.
        if (str_starts_with($path, 'data:image/')) {
            if (strlen($path) > 4 * 1024 * 1024) {
                return null;
            }

            $bytes = base64_decode(explode(',', $path, 2)[1] ?? '', true);
            $bulat = $bytes !== false && $bytes !== '' ? self::bulatkan($bytes) : null;

            return $bulat
                ? ['uri' => $bulat, 'bulat' => true]
                : ['uri' => $path, 'bulat' => false];
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if (! $disk->exists($path)) {
                return null;
            }

            $isi = $disk->get($path);
            // Batas aman: foto verifikasi seharusnya beberapa ratus KB. Yang
            // jauh lebih besar hampir pasti salah unggah, dan menanamnya
            // sebagai base64 membuat PDF-nya membengkak & gagal dirender.
            if (strlen($isi) > 3 * 1024 * 1024) {
                return null;
            }

            if ($bulat = self::bulatkan($isi)) {
                return ['uri' => $bulat, 'bulat' => true];
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');

            return ['uri' => 'data:' . $mime . ';base64,' . base64_encode($isi), 'bulat' => false];
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] foto verifikasi gagal dimuat: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Foto → PNG BULAT dengan latar tembus pandang, dipotong tengah.
     *
     * Rancangannya memakai foto lingkaran ber-`fit: cover`. dompdf tidak
     * memotong gambar mengikuti border-radius — yang keluar tetap persegi —
     * jadi pemotongannya dikerjakan di sini, bukan diserahkan ke CSS.
     * Sekaligus menyelesaikan `cover`: potret dan lanskap sama-sama dipotong
     * dari TENGAH, bukan dipenyet jadi persegi.
     *
     * Ukurannya dipatok 236 px — dua kali ukuran cetak (118 px) supaya tetap
     * tajam saat dokumen dicetak, tanpa menanam foto 1,4 MB apa adanya.
     *
     * Mengembalikan null bila GD tidak ada atau gambarnya tak terbaca; pemanggil
     * lalu memakai gambar aslinya. Foto persegi masih jauh lebih baik daripada
     * dokumen yang gagal terbit.
     */
    private static function bulatkan(string $isi): ?string
    {
        // KEGAGALANNYA DICATAT, TIDAK DIAM-DIAM.
        //
        // Bentuk fotonya ditentukan di sini, dan kalau lolos tanpa suara satu-
        // satunya petunjuk adalah pas foto persegi di dalam bingkai — yang
        // terbaca sebagai salah rancang, bukan sebagai ekstensi yang hilang.
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            Log::channel('web_career')->warning(
                '[LAPORAN] GD tidak tersedia — pas foto tercetak PERSEGI. '
                . 'Pasang ekstensi gd di lingkungan ini agar potongan bulatnya jalan.'
            );

            return null;
        }

        try {
            $asli = @imagecreatefromstring($isi);
            if (! $asli) {
                // Nyaris selalu format yang GD-nya dibangun tanpa dukungan itu
                // (WEBP paling sering), bukan berkas rusak.
                Log::channel('web_career')->warning(sprintf(
                    '[LAPORAN] pas foto tak terbaca GD (%d KB, jenis: %s) — tercetak PERSEGI.',
                    strlen($isi) / 1024,
                    (@getimagesizefromstring($isi)['mime'] ?? null) ?: 'tidak dikenali'
                ));

                return null;
            }

            $lw = imagesx($asli);
            $lt = imagesy($asli);
            $sisi = min($lw, $lt);
            // Potong dari tengah secara mendatar, tapi agak ke ATAS secara
            // tegak: pada pas foto, wajah ada di sepertiga atas — memotong
            // tepat di tengah kerap memenggal dahi.
            $x = (int) (($lw - $sisi) / 2);
            $y = (int) min(max(0, ($lt - $sisi) / 4), $lt - $sisi);

            $n = 236;
            $keluar = imagecreatetruecolor($n, $n);
            imagealphablending($keluar, false);
            imagesavealpha($keluar, true);
            imagefilledrectangle($keluar, 0, 0, $n, $n, imagecolorallocatealpha($keluar, 0, 0, 0, 127));
            imagealphablending($keluar, true);
            imagecopyresampled($keluar, $asli, 0, 0, $x, $y, $n, $n, $sisi, $sisi);
            imagedestroy($asli);

            // Di luar lingkaran dijadikan tembus pandang, dan tepinya dihaluskan.
            // imageellipse tidak bisa "menghapus" ke alfa dan imageantialias
            // tidak berlaku pada alfa, jadi dikerjakan per piksel.
            //
            // TIGA LAPIS, dari luar ke dalam:
            //   • di luar jari-jari      → tembus pandang penuh
            //   • pita selebar 1,2 px    → alfa sebanding jarak, supaya tepinya
            //     tidak bergerigi. Dipotong per baris seperti sebelumnya, tepi
            //     lingkarannya bertangga — dan pada cetakan itu terlihat.
            //   • pita tipis di dalamnya → digelapkan samar sebagai GARIS TEPI.
            //     Pas foto berlatar putih (dan itu mayoritas) kalau tidak diberi
            //     garis akan menyatu dengan kertas: yang tampak cuma cincin emas
            //     mengambang dengan wajah menggantung di tengahnya.
            imagealphablending($keluar, false);
            $r = $n / 2;
            $halus = 1.2;   // lebar pita penghalus (px)
            $garis = 2.4;   // tebal garis tepi dalam (px)

            for ($by = 0; $by < $n; $by++) {
                $dy = $by + 0.5 - $r;
                for ($bx = 0; $bx < $n; $bx++) {
                    $dx = $bx + 0.5 - $r;
                    $d = sqrt($dx * $dx + $dy * $dy);

                    if ($d <= $r - $halus - $garis) {
                        continue;   // bagian dalam, biarkan apa adanya
                    }

                    if ($d >= $r) {
                        imagesetpixel($keluar, $bx, $by, 0x7F000000);

                        continue;
                    }

                    $c = imagecolorat($keluar, $bx, $by);
                    $cr = ($c >> 16) & 0xFF;
                    $cg = ($c >> 8) & 0xFF;
                    $cb = $c & 0xFF;

                    // Makin dekat tepi, makin gelap — 0 di pangkal pita, 0,22
                    // tepat di tepi. Cukup untuk membatasi, terlalu tipis untuk
                    // terbaca sebagai coretan.
                    $pekat = 0.22 * min(1, max(0, ($d - ($r - $halus - $garis)) / $garis));
                    $cr = (int) ($cr * (1 - $pekat) + 0x14 * $pekat);
                    $cg = (int) ($cg * (1 - $pekat) + 0x17 * $pekat);
                    $cb = (int) ($cb * (1 - $pekat) + 0x1F * $pekat);

                    // Alfa hanya berlaku di pita terluar.
                    $tutup = min(1, max(0, ($r - $d) / $halus));
                    $a = (int) round(127 * (1 - $tutup));

                    imagesetpixel($keluar, $bx, $by, ($a << 24) | ($cr << 16) | ($cg << 8) | $cb);
                }
            }
            imagesavealpha($keluar, true);

            ob_start();
            imagepng($keluar, null, 8);
            $png = (string) ob_get_clean();
            imagedestroy($keluar);

            return $png !== '' ? 'data:image/png;base64,' . base64_encode($png) : null;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] foto gagal dibulatkan: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Logo perusahaan sebagai data URI — alasan sama dengan foto di atas.
     *
     * DIPERKECIL DULU, DAN ITU BUKAN SOAL UKURAN BERKAS.
     *
     * Berkas aslinya 3051×2998 piksel; di dokumen ia tampil setinggi 46 poin.
     * dompdf tidak peduli seberapa kecil ia digambar — seluruh piksel tetap
     * diurai untuk setiap kemunculan. Satu halaman sampul yang memuatnya
     * memakan 8 detik, dan halaman pemisah bab memuatnya lagi.
     *
     * Hasil perkecilannya disimpan di cache proses: satu dokumen memakai logo
     * yang sama di sampul, tiap pemisah bab, dan penutup.
     */
    public static function logoDataUri(): ?string
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache ?: null;
        }

        $file = public_path('logo/EVOGROUP.png');

        if (! is_file($file)) {
            return $cache = '';
        }

        $asli = file_get_contents($file);
        $kecil = self::kecilkan($asli, 320);

        return $cache = 'data:image/png;base64,' . base64_encode($kecil ?: $asli);
    }

    /**
     * Perkecil PNG ke lebar tertentu, dengan latar transparan dipertahankan.
     *
     * Null bila GD tidak tersedia atau gambarnya gagal dibaca — pemanggil
     * memakai berkas aslinya. Logo yang berat jauh lebih baik daripada
     * dokumen tanpa logo.
     */
    private static function kecilkan(string $isi, int $lebarTarget): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $sumber = @imagecreatefromstring($isi);

            if (! $sumber) {
                return null;
            }

            $lebar = imagesx($sumber);
            $tinggi = imagesy($sumber);

            if ($lebar <= $lebarTarget) {
                imagedestroy($sumber);

                return null;
            }

            $tinggiTarget = max(1, (int) round($tinggi * ($lebarTarget / $lebar)));
            $tujuan = imagecreatetruecolor($lebarTarget, $tinggiTarget);

            // Tanpa dua baris ini latar transparan logo berubah jadi hitam
            // pekat — dan logo itu dicetak di atas kertas putih.
            imagealphablending($tujuan, false);
            imagesavealpha($tujuan, true);

            imagecopyresampled($tujuan, $sumber, 0, 0, 0, 0, $lebarTarget, $tinggiTarget, $lebar, $tinggi);

            ob_start();
            imagepng($tujuan, null, 9);
            $hasil = ob_get_clean();

            imagedestroy($sumber);
            imagedestroy($tujuan);

            return $hasil ?: null;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] logo gagal diperkecil: ' . $e->getMessage());

            return null;
        }
    }
}
