<?php

namespace App\Http\Controllers\Career\Referensi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — REFERENSI PENDIDIKAN (endpoint tunggal untuk formulir).
 *
 * KENAPA ADA.
 * Field ber-`sumber_opsi` dulu menerima daftar opsinya lewat props Inertia.
 * Cara itu berhenti masuk akal begitu Master Kampus diisi impor Dapodik/PDDIKTI:
 * 328.998 baris = ±7,8 MB JSON di SETIAP pembukaan halaman lamaran, dan sebuah
 * <el-select> berisi 329 ribu opsi. Jadi opsi tidak lagi dikirim di muka —
 * formulir mencarinya ke sini sambil kandidat mengetik.
 *
 * BENTUK JAWABAN. Seragam untuk semua sumber:
 *     [{ nilai, label, ket }]
 *   `nilai` yang tersimpan di Jawaban_Json, `label` yang dibaca kandidat,
 *   `ket` keterangan kecil di sebelah kanan (kota, kelompok bidang, dsb).
 *   Khusus `kampus` ada tambahan `bendera` — kode negara ISO alfa-2 huruf
 *   kecil, atau null. Sumber lain tidak mengirimnya sama sekali.
 *
 * NILAI YANG DISIMPAN sengaja dipilih supaya syarat auto-gugur yang sudah
 * berjalan tidak perlu diubah:
 *   jenjang & jenis_institusi -> Kode  ('S1', 'Politeknik')  = persis opsi statis lama
 *   kampus & prodi            -> Nama  (yang dibaca manusia)
 *
 * RANTAI PENYARINGAN (semuanya data, bukan if di kode):
 *   jenjang -> jenis_institusi   lewat N_WEB_CAREERS_Jenis_Institusi_Jenjang
 *   jenjang -> prodi             lewat N_WEB_CAREERS_Prodi_Jenjang
 *   jenis   -> kampus            lewat Master_Kampus.Jenis_Institusi_Kode
 */
class ReferensiController extends Controller
{
    /** Jumlah baris maksimum sekali minta — pelindung, bukan paginasi. */
    private const BATAS_BAWAAN = 50;

    private const BATAS_MAKS = 100;

    /**
     * Pencarian kampus TANPA penyaring jenis institusi menyentuh 328 ribu baris.
     * Di bawah ambang ini hasilnya pasti tidak berguna, jadi tidak usah dicari.
     */
    private const MIN_CARI_KAMPUS = 2;

    public function opsi(Request $request, string $sumber)
    {
        try {
            $cari = trim((string) $request->query('cari', ''));
            $batas = min(self::BATAS_MAKS, max(1, (int) $request->query('limit', self::BATAS_BAWAAN)));
            $jenjang = trim((string) $request->query('jenjang', ''));
            $jenis = trim((string) $request->query('jenis', ''));

            switch ($sumber) {
                case 'jenjang':
                    $rows = $this->jenjang($cari, $batas);
                    break;
                case 'jenis_institusi':
                    $rows = $this->jenisInstitusi($cari, $jenjang, $batas);
                    break;
                case 'kampus':
                    $rows = $this->kampus($cari, $jenjang, $jenis, $batas);
                    break;
                case 'prodi':
                    $rows = $this->prodi($cari, $jenjang, $batas);
                    break;
                default:
                    return ResponseHelper::error('Sumber referensi tidak dikenal.', 404);
            }

            return ResponseHelper::success($rows, 'Referensi dimuat');
        } catch (\Throwable $e) {
            Log::error("Gagal memuat referensi {$sumber}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat pilihan', 500);
        }
    }

    /**
     * Jenjang pendidikan — urut dari yang TERTINGGI (S3) turun ke SD.
     *
     * `Urutan` di master menaik dari SD=10 sampai S3=120, jadi dibalik di sini.
     * Pelamar EVO Group didominasi lulusan perguruan tinggi; menaruh SD di
     * puncak daftar memaksa mayoritas menggulir melewati jenjang yang tidak
     * relevan setiap kali. Abjad tidak dipakai karena akan mengacak D1 dan S3
     * ke tempat yang tidak bermakna.
     */
    private function jenjang(string $cari, int $batas): array
    {
        $q = DB::table('N_WEB_CAREERS_Master_Jenjang')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Urutan')
            ->limit($batas);

        $this->cocokkan($q, ['Nama', 'Kode'], $cari);

        return array_map(fn ($r) => [
            'nilai' => $r->Kode,
            'label' => $r->Nama,
            'ket' => null,
        ], $this->jalankan($q));
    }

    /**
     * Jenis institusi. Bila jenjang sudah dipilih, hanya jenis yang memang
     * menyelenggarakan jenjang itu yang muncul — sumbernya tabel jembatan,
     * BUKAN kolom CSV Jenjang_Berlaku (CSV tidak bisa di-join & mudah basi).
     */
    private function jenisInstitusi(string $cari, string $jenjang, int $batas): array
    {
        // Urut abjad, bukan `Urutan` master. Setelah disaring jenjang, sisanya
        // tinggal beberapa baris yang setara — mencarinya menurut nama lebih
        // cepat daripada menghafal urutan hierarki yang tidak terlihat.
        $q = DB::table('N_WEB_CAREERS_Master_Jenis_Institusi as ji')
            ->where('ji.Flag_Aktif', 'Y')
            ->orderBy('ji.Nama')
            ->limit($batas)
            ->select('ji.Kode', 'ji.Nama', 'ji.Kategori');

        if ($jenjang !== '') {
            $q->join('N_WEB_CAREERS_Jenis_Institusi_Jenjang as jij', function ($j) use ($jenjang) {
                $j->on('jij.Kode_Jenis', '=', 'ji.Kode')->where('jij.Kode_Jenjang', '=', $jenjang);
            });
        }

        $this->cocokkan($q, ['ji.Nama', 'ji.Kode'], $cari);

        return array_map(fn ($r) => [
            'nilai' => $r->Kode,
            'label' => $r->Nama,
            'ket' => $r->Kategori === 'PT' ? 'Perguruan Tinggi' : ($r->Kategori === 'SEKOLAH' ? 'Sekolah' : null),
        ], $this->jalankan($q));
    }

    /**
     * Kampus / sekolah. Disaring jenis institusi bila ada (memakai indeks
     * IX_MK_Cascade); bila kandidat baru memilih jenjang saja, jenisnya
     * diturunkan dulu dari tabel jembatan.
     */
    private function kampus(string $cari, string $jenjang, string $jenis, int $batas): array
    {
        // Diambil dua kali lipat: baris kembar dibuang setelah query (rapikanKembar),
        // dan tanpa cadangan ini satu halaman penuh pasangan kembar akan menyusut
        // jadi setengah isi. Tetap dipagari BATAS_MAKS supaya tidak jadi celah beban.
        $q = DB::table('N_WEB_CAREERS_Master_Kampus')
            ->where('Flag_Aktif', 'Y')
            ->limit(min(self::BATAS_MAKS * 2, $batas * 2))
            ->select('Nama', 'Kode', 'Kota', 'Provinsi', 'Negara', 'Negara_Kode', 'Kepemilikan');

        if ($jenis !== '') {
            $q->where('Jenis_Institusi_Kode', $jenis);
        } elseif ($jenjang !== '') {
            $q->whereIn('Jenis_Institusi_Kode', function ($sub) use ($jenjang) {
                $sub->from('N_WEB_CAREERS_Jenis_Institusi_Jenjang')
                    ->where('Kode_Jenjang', $jenjang)
                    ->select('Kode_Jenis');
            });
        } elseif (mb_strlen($cari) < self::MIN_CARI_KAMPUS) {
            // Tanpa penyaring apa pun, "ambil 50 teratas dari 328 ribu" hanya
            // memberi daftar acak yang membingungkan. Lebih jujur kosong.
            return [];
        }

        $this->cocokkan($q, ['Nama'], $cari);
        $this->urutkanKemiripan($q, 'Nama', $cari);

        $rows = $this->rapikanKembar($this->jalankan($q), $batas);
        $this->lengkapiNegara($rows);

        return array_map(fn ($r) => [
            'nilai' => $r->Nama,
            'label' => $r->Nama,
            'ket' => $this->lokasi($r),
            'bendera' => $this->benderaKode($r->Negara_Kode),
        ], $rows);
    }

    /**
     * Isi negara yang kosong dari KEMBARAN kampus yang sama di jenis institusi lain.
     *
     * KENAPA PERLU, padahal sudah ada rapikanKembar().
     * Dua impor bertemu di tabel ini. Daftar `world` membawa negara lengkap tapi
     * seluruhnya terdaftar sebagai "Universitas" — 10.057 baris, tanpa satu pun
     * pengecualian. Daftar PDDIKTI luar negeri tidak membawa negara sama sekali
     * (3.497 baris, kolomnya cuma berbunyi "Luar Negeri") tapi tersebar ke semua
     * jenis: Institut, Sekolah Tinggi, Politeknik, Akademi.
     *
     * Akibatnya "Ashikaga Institute of Technology" tercatat dua kali: satu di
     * bawah Universitas dengan bendera Jepang, satu di bawah Institut tanpa
     * negara apa pun. Begitu kandidat memilih jenis institusi "Institut",
     * penyaring membuang kembaran yang berbendera itu sebelum rapikanKembar()
     * sempat melihatnya — sehingga SELURUH kampus luar negeri di jenis selain
     * Universitas tampil bergambar bola dunia. Bukan kerusakan tampilan;
     * pasangannya memang tidak pernah ikut terambil.
     *
     * CARA KERJA. Namanya dicari ulang di baris yang PUNYA kode negara. Ini
     * bukan menebak negara dari nama — institusinya memang sudah tercatat di
     * tabel yang sama, hanya di baris lain. Nama yang menghasilkan LEBIH DARI
     * SATU negara ("American University" ada di Amerika dan Bosnia, "City
     * University" di tiga negara) sengaja dibiarkan tanpa bendera: bendera yang
     * salah lebih menyesatkan daripada tidak ada bendera.
     *
     * BIAYANYA satu query tambahan, dan hanya bila memang ada baris tanpa
     * negara. Kampus Indonesia — mayoritas pemakaian — sudah berkode 'ID'
     * sehingga tidak pernah menyentuh jalur ini.
     */
    private function lengkapiNegara(array $rows): void
    {
        $dicari = [];
        foreach ($rows as $r) {
            if ($this->benderaKode($r->Negara_Kode) !== null) {
                continue;
            }
            // Dikunci nama ternormalkan supaya "Institute Of" dan "Institute of"
            // tidak dicari dua kali, tapi yang dikirim ke SQL tetap nama aslinya
            // (perbandingan SQL Server sendiri sudah abai huruf besar-kecil).
            $dicari[$this->kunciNama($r->Nama)] = $r->Nama;
        }

        if (! $dicari) {
            return;
        }

        $temuan = [];
        foreach (array_chunk(array_values($dicari), 200) as $bagian) {
            $baris = DB::table('N_WEB_CAREERS_Master_Kampus')
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Negara_Kode')
                ->where('Negara_Kode', '<>', '')
                ->whereIn('Nama', $bagian)
                ->distinct()
                ->get(['Nama', 'Negara', 'Negara_Kode']);

            foreach ($baris as $b) {
                $kode = $this->benderaKode($b->Negara_Kode);
                if ($kode === null) {
                    continue;
                }
                $temuan[$this->kunciNama($b->Nama)][$kode] = $b->Negara;
            }
        }

        foreach ($rows as $r) {
            if ($this->benderaKode($r->Negara_Kode) !== null) {
                continue;
            }

            $calon = $temuan[$this->kunciNama($r->Nama)] ?? null;
            if (! $calon || count($calon) !== 1) {
                continue; // tidak ketemu, atau namanya dipakai beberapa negara
            }

            $kode = array_key_first($calon);
            // Baris ini menyatakan dirinya di LUAR negeri; kembaran yang
            // menunjuk Indonesia berarti keduanya bukan institusi yang sama.
            // Percaya pada pertentangan itu sama saja mengarang.
            if ($kode === 'id' && $r->Negara === 'Luar Negeri') {
                continue;
            }

            $r->Negara_Kode = $kode;
            $r->Negara = reset($calon) ?: $r->Negara;
        }
    }

    /** Nama kampus yang disamakan bentuknya untuk dibandingkan. */
    private function kunciNama(?string $nama): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $nama)));
    }

    /**
     * Buang kampus kembar, pertahankan baris yang datanya paling lengkap.
     *
     * Impor PDDIKTI daftar luar negeri menduplikasi daftar `world`: 1.370 nama
     * tercatat dua kali, dan salinan PDDIKTI-nya tidak menyimpan negara sama
     * sekali. Akibatnya pelamar melihat "Abilene Christian University" dua kali
     * berturut-turut — satu berbendera Amerika, satu tanpa negara — lalu harus
     * menebak mana yang benar. Padahal keduanya kampus yang sama.
     *
     * Dibereskan saat DIBACA, bukan dengan menghapus baris master: data impor
     * masih dipakai modul lain, dan `Kode`-nya sudah telanjur tersimpan di
     * lamaran yang berjalan.
     *
     * Karena LIMIT bekerja di SQL sebelum penyaringan ini, pemanggil mengambil
     * lebih banyak baris lalu memotongnya di sini — kalau tidak, satu halaman
     * penuh pasangan kembar menyusut jadi setengah.
     *
     * NAMA SAJA TIDAK CUKUP jadi penanda kembar. Ada 59 nama yang dipakai
     * institusi BERBEDA di negara berbeda — "American University" ada di
     * Amerika dan Bosnia, "City University" di tiga negara, "Deakin University"
     * punya baris Australia dan Indonesia. Menyatukannya menurut nama membuang
     * 71 kampus yang sungguh-sungguh ada, dan lulusan American University
     * Washington cuma disodori bendera Bosnia. Karena itu yang disatukan adalah
     * nama + negara; baris tanpa negara baru dibuang bila kembaran BERNEGARA-nya
     * memang muncul — di situlah ia terbukti salinan, bukan kampus lain.
     */
    private function rapikanKembar(array $rows, int $batas): array
    {
        $terbaik = [];
        $bernegara = [];

        foreach ($rows as $r) {
            $nama = $this->kunciNama($r->Nama);
            $kode = $this->benderaKode($r->Negara_Kode);
            if ($kode !== null) {
                $bernegara[$nama] = true;
            }

            // Baris tanpa negara berbagi satu slot: berapa pun salinannya,
            // sebagai pilihan ia cuma satu.
            $terbaik[$nama . '|' . ($kode ?? '')] ??= $r;
        }

        $hasil = [];
        foreach ($terbaik as $r) {
            if ($this->benderaKode($r->Negara_Kode) === null && isset($bernegara[$this->kunciNama($r->Nama)])) {
                continue;
            }
            $hasil[] = $r;
        }

        return array_slice($hasil, 0, $batas);
    }

    /**
     * Kode negara ISO-3166 alfa-2 HURUF KECIL untuk ikon bendera, atau null.
     *
     * Data impor Dapodik/PDDIKTI menyisakan ribuan baris tanpa kode negara, dan
     * sebagian berisi teks yang bukan kode sama sekali. Yang tidak lolos pola
     * dikembalikan null supaya tampilan menghilangkan benderanya — jauh lebih
     * baik daripada memuat gambar yang pasti 404 di tiap baris daftar.
     */
    private function benderaKode(?string $kode): ?string
    {
        $k = strtolower(trim((string) $kode));

        return preg_match('/^[a-z]{2}$/', $k) === 1 ? $k : null;
    }

    /**
     * Program studi / jurusan — hanya daftar kurasi Indonesia (Cakupan = 'ID'):
     * SEK-* untuk SMA, SMK-* untuk SMK, ID-* untuk perguruan tinggi. Daftar CIP
     * global sengaja tidak ikut karena namanya berbahasa Inggris dan akan
     * bercampur jadi rancu bagi pelamar lokal.
     */
    private function prodi(string $cari, string $jenjang, int $batas): array
    {
        $q = DB::table('N_WEB_CAREERS_Master_Prodi as p')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Cakupan', 'ID')
            ->limit($batas)
            ->select('p.Nama', 'p.Kode', 'p.Kelompok', 'p.Gelar');

        if ($jenjang !== '') {
            $q->join('N_WEB_CAREERS_Prodi_Jenjang as pj', function ($j) use ($jenjang) {
                $j->on('pj.Kode_Prodi', '=', 'p.Kode')->where('pj.Kode_Jenjang', '=', $jenjang);
            });
        }

        $this->cocokkan($q, ['p.Nama'], $cari);
        $this->urutkanKemiripan($q, 'p.Nama', $cari);

        return array_map(fn ($r) => [
            'nilai' => $r->Nama,
            'label' => $r->Nama,
            'ket' => $r->Gelar ?: $r->Kelompok,
        ], $this->jalankan($q));
    }

    /**
     * Jalankan query dengan OPTION (RECOMPILE).
     *
     * BUKAN hiasan. Nilai pencarian di sini punya selektivitas yang berbeda
     * jauh antar permintaan — 'Universitas' menyaring 13 ribu baris, 'SMK'
     * 14 ribu, dan kata pencarian bebas bisa menyisakan satu baris atau lima
     * puluh ribu. Dengan rencana yang dipakai ulang, SQL Server menebak rata
     * dan memilih memindai IX_MK_Nama demi menghindari sort, lalu menyaring
     * 328 ribu baris satu per satu.
     *
     * Terukur di data ini: 1.100 ms tanpa RECOMPILE, ~110 ms dengan. Ongkos
     * menyusun rencana tiap kali jauh lebih murah daripada selisih itu.
     */
    private function jalankan($q): array
    {
        return DB::select($q->toSql() . ' OPTION (RECOMPILE)', $q->getBindings());
    }

    /**
     * "Kota Palembang, Sumatera Selatan" — atau nama negara bila di luar negeri.
     *
     * Sengaja TIDAK jatuh ke kolom Kepemilikan waktu lokasi kosong. Kepemilikan
     * bukan lokasi, dan pada data impor nilainya kerap salah (Universitas
     * Sriwijaya tercatat "Swasta"); menampilkannya di kolom keterangan membuat
     * kandidat mengira ia sedang membaca informasi yang sudah diverifikasi.
     */
    private function lokasi(object $r): ?string
    {
        if ($r->Negara && $r->Negara !== 'Indonesia') {
            return $r->Negara;
        }
        $teks = implode(', ', array_filter([$r->Kota, $r->Provinsi]));

        return $teks !== '' ? $teks : null;
    }

    /**
     * Cocokkan per KATA, bukan sebagai satu potongan utuh: semua kata harus
     * ada (DAN), masing-masing boleh di kolom mana pun (ATAU).
     *
     * Alasannya nama di Dapodik/PDDIKTI tidak seragam urutannya. "sriwijaya
     * universitas" dan "universitas sriwijaya" harus sama-sama ketemu, dan
     * "smk palembang negeri" harus menemukan "SMK NEGERI 01 PALEMBANG".
     */
    private function cocokkan($q, array $kolom, string $cari): void
    {
        $kata = preg_split('/\s+/', trim($cari), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($kata as $k) {
            $pola = '%' . $this->amanLike($k) . '%';
            $q->where(function ($w) use ($kolom, $pola) {
                foreach ($kolom as $kol) {
                    $w->orWhere($kol, 'like', $pola);
                }
            });
        }
    }

    /**
     * Yang berawalan kata pencarian naik ke atas, sisanya menyusul menurut abjad.
     * Tanpa ini "Teknik" bisa menampilkan "Politeknik ..." sebelum "Teknik ...".
     */
    private function urutkanKemiripan($q, string $kolom, string $cari): void
    {
        if ($cari !== '') {
            $q->orderByRaw("CASE WHEN {$kolom} LIKE ? THEN 0 ELSE 1 END", [$this->amanLike($cari) . '%']);
        }
        $q->orderBy($kolom);
    }

    /** Netralkan wildcard SQL Server supaya ketikan kandidat tidak jadi pola. */
    private function amanLike(string $s): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $s);
    }
}
