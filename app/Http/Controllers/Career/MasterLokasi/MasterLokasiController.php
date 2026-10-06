<?php

namespace App\Http\Controllers\Career\MasterLokasi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER LOKASI (kantor sendiri & vendor).
 *
 * Dipakai penjadwalan tatap muka: wawancara, tes offline, dan MCU. Rekruter
 * MEMILIH lokasi, bukan mengetiknya — sebelumnya satu tempat yang sama ditulis
 * berbeda-beda ("Kantor Pusat", "HO Palembang", "Kantor EVO Lt.3"), kandidat
 * tidak punya peta, dan tidak ada cara menghitung berapa MCU yang dikirim ke
 * klinik tertentu.
 *
 * KOORDINAT, BUKAN POTONGAN <iframe>
 * Yang disimpan Lintang & Bujur. URL embed Google Maps membawa parameter
 * zoom/ukuran/bahasa yang menyatu dengan satu tampilan, mudah kedaluwarsa, dan
 * menyisipkan HTML mentah dari database ke halaman membuka pintu XSS. Dari
 * koordinat, sistem menyusun sendiri URL peta MAUPUN tautan "buka di aplikasi
 * peta" untuk ponsel — lihat bentukLokasi().
 */
class MasterLokasiController extends Controller
{
    private const JENIS = ['KANTOR', 'VENDOR'];

    /**
     * Nilai penanda "tempat ini belum terdaftar di master".
     *
     * Bukan hashid, dan mustahil bertabrakan dengan salah satunya: alfabet
     * hashid tidak memuat garis bawah. Dipakai layar sebagai nilai pilihan
     * "Lainnya" dan diperiksa server dengan perbandingan yang sama persis.
     */
    public const LAINNYA = '__LAINNYA__';

    /** Master peruntukan lokasi (KANTOR / MEDIS / …), by Kode. */
    public static function masterPeruntukan(): \Illuminate\Support\Collection
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    /** Peruntukan tiap lokasi: [Id_Master_Lokasi => ['KANTOR', …]]. */
    public static function petaPeruntukan(array $ids = []): \Illuminate\Support\Collection
    {
        $q = DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map');
        if ($ids) {
            $q->whereIn('Id_Master_Lokasi', $ids);
        }

        return $q->get()
            ->groupBy('Id_Master_Lokasi')
            ->map(fn ($g) => $g->pluck('Peruntukan_Kode')->values()->all());
    }

    /**
     * URL peta dari sebuah kueri bebas.
     *
     * Dipakai baris master MAUPUN tempat "Lainnya" yang diketik saat menjadwal.
     * Satu tempat, supaya keduanya mustahil menghasilkan peta yang berbeda.
     */
    private static function petaDari(?string $kueri): array
    {
        $kueri = trim((string) $kueri);

        return [
            'mapsEmbed' => $kueri ? 'https://www.google.com/maps?q=' . urlencode($kueri) . '&output=embed' : null,
            'mapsUrl' => $kueri ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($kueri) : null,
        ];
    }

    /**
     * Tempat yang DIKETIK saat menjadwal ("Lainnya"), dibentuk menyerupai baris
     * master supaya layar tak perlu tahu bedanya.
     *
     * TANPA PETA — sengaja. Tempat ini tidak punya koordinat yang pernah
     * diverifikasi siapa pun; menyusun peta dari hasil pencarian nama berarti
     * menampilkan pin yang BELUM TENTU benar dengan tampilan yang sama persis
     * seperti pin yang sudah dipastikan. Kandidat tidak punya cara membedakan
     * keduanya, dan yang salah mengirim orang ke gedung yang keliru di hari-H.
     *
     * Yang ditampilkan: nama dan alamatnya sebagai teks. Bila tempat itu memang
     * sering dipakai, daftarkan di Master Lokasi berikut titiknya — di sana
     * petanya muncul karena ada yang bertanggung jawab atas titik itu.
     */
    public static function lokasiLepas(?string $nama, ?string $alamat): ?array
    {
        $nama = trim((string) $nama);
        if ($nama === '') {
            return null;
        }

        $alamat = trim((string) $alamat) ?: null;

        return [
            'id' => self::LAINNYA,
            'kode' => null,
            'nama' => $nama,
            'jenis' => null,
            'kategori' => null,
            'alamat' => $alamat,
            'alamatLengkap' => $alamat,
            'kota' => null,
            'provinsi' => null,
            'kodePos' => null,
            'lintang' => null,
            'bujur' => null,
            'kontakNama' => null,
            'kontakTelp' => null,
            'catatan' => null,
            'utama' => false,
            'aktif' => true,
            'peruntukan' => [],
            // Penanda: tempat ini tidak ada di master. Layar memakainya untuk
            // menyebutkan itu apa adanya, alih-alih menampilkannya seolah
            // tempat terdaftar yang datanya kurang lengkap.
            'lepas' => true,
            // Kosong, dan HARUS tetap kosong. Layar yang menemukan keduanya null
            // menampilkan alamatnya sebagai teks — lihat JadwalKartu.
            'mapsEmbed' => null,
            'mapsUrl' => null,
        ];
    }

    public function index()
    {
        return Inertia::render('Career/admin/master-lokasi/masterLokasi', CareerShell::props(
            '/master-lokasi',
            'Master Lokasi',
        ));
    }

    /**
     * Alamat satu baris: alamat + kota/provinsi yang BELUM tertulis di dalamnya.
     *
     * Kolom Alamat umumnya sudah memuat kota dan provinsinya. Menyambung
     * ketiganya apa adanya menghasilkan "Banyuasin, Sumatera Selatan,
     * Banyuasin, Sumatera Selatan" — pada undangan resmi itu terbaca seperti
     * data rusak, dan kandidat jadi ragu alamatnya benar atau tidak.
     */
    public static function alamatLengkap(?object $r): ?string
    {
        if (! $r) {
            return null;
        }

        $alamat = trim((string) ($r->Alamat ?? ''));
        $bawah = mb_strtolower($alamat);

        $tambahan = array_filter(
            [$r->Kota ?? null, $r->Provinsi ?? null, $r->Kode_Pos ?? null],
            fn ($v) => $v && ! str_contains($bawah, mb_strtolower((string) $v)),
        );

        return implode(', ', array_filter(array_merge([$alamat], $tambahan))) ?: null;
    }

    /**
     * Bentuk satu baris untuk dikirim ke layar.
     *
     * URL peta disusun DI SINI, bukan di Vue: aturannya sama untuk admin dan
     * portal kandidat, dan menaruhnya di satu tempat mencegah keduanya lambat
     * laun berbeda.
     */
    public static function bentukLokasi(?object $r, array $peruntukan = []): ?array
    {
        if (! $r) {
            return null;
        }

        $titik = ($r->Lintang !== null && $r->Bujur !== null)
            ? $r->Lintang . ',' . $r->Bujur
            : null;

        // Kueri peta: nama tempat lebih akurat daripada koordinat untuk gedung
        // yang sudah terdaftar di Google; koordinat jadi cadangannya.
        $kueri = $r->Maps_Query ?: ($titik ?: trim(($r->Nama ?? '') . ' ' . ($r->Alamat ?? '')));

        return [
            'id' => Hashids::encode($r->Id_Master_Lokasi),
            'kode' => $r->Kode,
            'nama' => $r->Nama,
            'jenis' => $r->Jenis,
            'kategori' => $r->Kategori,
            'alamat' => $r->Alamat,
            // Alamat SATU BARIS siap tulis (surel undangan, kartu lokasi):
            // kota/provinsi hanya ditambahkan bila belum tertulis di alamatnya.
            'alamatLengkap' => self::alamatLengkap($r),
            'kota' => $r->Kota,
            'provinsi' => $r->Provinsi,
            'kodePos' => $r->Kode_Pos,
            'lintang' => $r->Lintang !== null ? (float) $r->Lintang : null,
            'bujur' => $r->Bujur !== null ? (float) $r->Bujur : null,
            'kontakNama' => $r->Kontak_Nama,
            'kontakTelp' => $r->Kontak_Telp,
            'catatan' => $r->Catatan,
            'utama' => ($r->Flag_Default ?? 'T') === 'Y',
            'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
            // UNTUK APA tempat ini boleh dipakai — kantor, medis, atau keduanya.
            // Jendela jadwal menyaring dengan ini, bukan dengan menebak dari
            // `jenis`: sebuah rumah sakit yang juga menyediakan ruang wawancara
            // tetap satu baris, satu titik peta, satu riwayat pemakaian.
            'peruntukan' => array_values($peruntukan),
            'lepas' => false,
            // Peta sematan (iframe) & tautan buka di aplikasi peta.
        ] + self::petaDari($kueri);
    }

    /** GET /api/v1/master-lokasi — daftar + pencarian + saringan jenis. */
    public function list(Request $request)
    {
        $q = DB::table('N_WEB_CAREERS_Master_Lokasi');

        if ($cari = trim((string) $request->query('cari', ''))) {
            $q->where(function ($w) use ($cari) {
                $w->where('Nama', 'like', "%{$cari}%")
                    ->orWhere('Kode', 'like', "%{$cari}%")
                    ->orWhere('Alamat', 'like', "%{$cari}%")
                    ->orWhere('Kota', 'like', "%{$cari}%");
            });
        }

        if ($jenis = $request->query('jenis')) {
            $q->where('Jenis', strtoupper($jenis));
        }

        // SARINGAN PERUNTUKAN — dipakai jendela jadwal: MCU hanya boleh melihat
        // rumah sakit.
        //
        // Jendela jadwal memuat daftarnya SEKALI lalu menyaring di layar (satu
        // tahap bisa memuat wawancara dan MCU sekaligus, dan menembak ulang per
        // aktivitas membuat dropdown-nya berkedip). Saringan di sini tetap
        // disediakan untuk pemakaian lain — dan yang menjaga kebenarannya bukan
        // keduanya, melainkan periksaPeruntukanLokasi() di LamaranController:
        // di sanalah lokasi yang tidak cocok ditolak, apa pun yang dikirim layar.
        if ($peruntukan = strtoupper(trim((string) $request->query('peruntukan', '')))) {
            $q->whereExists(fn ($w) => $w->select(DB::raw(1))
                ->from('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map as pm')
                ->whereColumn('pm.Id_Master_Lokasi', 'N_WEB_CAREERS_Master_Lokasi.Id_Master_Lokasi')
                ->where('pm.Peruntukan_Kode', $peruntukan));
        }

        // Hanya yang aktif — dipakai dropdown penjadwalan.
        if ($request->boolean('aktif')) {
            $q->where('Flag_Aktif', 'Y');
        }

        $rows = $q->orderByDesc('Flag_Default')->orderBy('Urutan')->orderBy('Nama')->get();
        $peta = self::petaPeruntukan($rows->pluck('Id_Master_Lokasi')->all());

        return ResponseHelper::success(
            $rows->map(fn ($r) => self::bentukLokasi($r, $peta->get($r->Id_Master_Lokasi, [])))->all(),
            'Daftar lokasi',
        );
    }

    /**
     * GET /api/v1/master-lokasi/peruntukan — master peruntukan berikut labelnya.
     *
     * Layar mengambil label, teks kosong, dan boleh-tidaknya "Lainnya" dari
     * sini. Tanpa itu, jendela jadwal harus tahu sendiri bahwa MEDIS berarti
     * rumah sakit — dan peruntukan baru menuntut layarnya ikut disunting.
     */
    public function peruntukan()
    {
        return ResponseHelper::success(
            self::masterPeruntukan()->values()->map(fn ($p) => [
                'kode' => $p->Kode,
                'nama' => $p->Nama,
                'deskripsi' => $p->Deskripsi,
                'labelPilih' => $p->Label_Pilih,
                'labelKosong' => $p->Label_Kosong,
                'izinkanLainnya' => ($p->Flag_Izinkan_Lainnya ?? 'T') === 'Y',
                'labelLainnya' => $p->Label_Lainnya,
                'labelNamaLainnya' => $p->Label_Nama_Lainnya,
                'labelAlamatLainnya' => $p->Label_Alamat_Lainnya,
                'wajibAlamatLainnya' => ($p->Flag_Wajib_Alamat_Lainnya ?? 'Y') === 'Y',
            ])->all(),
            'Peruntukan lokasi',
        );
    }

    private function aturan(?int $id = null): array
    {
        return [
            'nama' => 'required|string|max:200',
            'jenis' => 'required|in:' . implode(',', self::JENIS),
            'kategori' => 'nullable|string|max:60',
            'alamat' => 'nullable|string|max:500',
            'kota' => 'nullable|string|max:120',
            'provinsi' => 'nullable|string|max:120',
            'kodePos' => 'nullable|string|max:15',
            // Rentang dibatasi agar salah ketik (mis. tertukar lintang-bujur)
            // tertangkap di sini, bukan berupa pin yang jatuh di tengah laut.
            'lintang' => 'nullable|numeric|between:-90,90',
            'bujur' => 'nullable|numeric|between:-180,180',
            'kontakNama' => 'nullable|string|max:150',
            'kontakTelp' => 'nullable|string|max:40',
            'catatan' => 'nullable|string',
            'utama' => 'nullable|boolean',
            // Daftarnya DARI MASTER — peruntukan baru langsung bisa dipilih.
            'peruntukan' => 'nullable|array',
            'peruntukan.*' => ['string', Rule::in(self::masterPeruntukan()->keys()->all())],
        ];
    }

    /**
     * Tulis ulang peruntukan sebuah lokasi.
     *
     * Hapus-lalu-isi, bukan tambal: peruntukan yang DICABUT harus benar-benar
     * hilang. Kalau hanya yang baru yang ditambahkan, rumah sakit yang dulu
     * salah dipetakan sebagai KANTOR akan terus muncul di dropdown wawancara
     * meski admin sudah membetulkannya di layar.
     */
    private function simpanPeruntukan(int $id, array $kode): void
    {
        $kode = array_values(array_unique(array_filter($kode)));

        DB::transaction(function () use ($id, $kode) {
            DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map')
                ->where('Id_Master_Lokasi', $id)->delete();

            if (! $kode) {
                return;
            }

            DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map')->insert(
                array_map(fn ($k) => [
                    'Id_Master_Lokasi' => $id,
                    'Peruntukan_Kode' => $k,
                    'Created_At' => now(),
                    'Created_By' => session('career_auth.nama'),
                ], $kode),
            );
        });
    }

    /** Susun payload DB dari input yang sudah tervalidasi. */
    private function isian(array $d): array
    {
        return [
            'Nama' => $d['nama'],
            'Jenis' => strtoupper($d['jenis']),
            'Kategori' => $d['kategori'] ?? null,
            'Alamat' => $d['alamat'] ?? null,
            'Kota' => $d['kota'] ?? null,
            'Provinsi' => $d['provinsi'] ?? null,
            'Kode_Pos' => $d['kodePos'] ?? null,
            'Lintang' => $d['lintang'] ?? null,
            'Bujur' => $d['bujur'] ?? null,
            'Kontak_Nama' => $d['kontakNama'] ?? null,
            'Kontak_Telp' => $d['kontakTelp'] ?? null,
            'Catatan' => $d['catatan'] ?? null,
        ];
    }

    /**
     * Hanya SATU lokasi utama.
     *
     * Dua lokasi bertanda utama membuat bawaan dropdown tidak bisa ditebak —
     * yang terpilih tergantung urutan baris, dan itu berubah sendiri seiring
     * data bertambah.
     */
    private function jadikanUtamaTunggal(int $kecuali): void
    {
        DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->where('Id_Master_Lokasi', '!=', $kecuali)
            ->where('Flag_Default', 'Y')
            ->update(['Flag_Default' => 'T', 'Updated_At' => now()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->aturan());
        $nama = session('career_auth.nama');

        $kode = Str::upper(Str::slug($d['nama'], '-'));
        $kode = Str::limit($kode, 34, '');
        if (DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Kode', $kode)->exists()) {
            $kode .= '-' . Str::upper(Str::random(4));
        }

        $id = DB::table('N_WEB_CAREERS_Master_Lokasi')->insertGetId(
            $this->isian($d) + [
                'Kode' => $kode,
                'Flag_Default' => ! empty($d['utama']) ? 'Y' : 'T',
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $nama,
                'Created_By_Id' => session('career_auth.id'),
            ],
            'Id_Master_Lokasi',
        );

        if (! empty($d['utama'])) {
            $this->jadikanUtamaTunggal((int) $id);
        }

        $this->simpanPeruntukan((int) $id, $d['peruntukan'] ?? []);

        return ResponseHelper::success(['id' => Hashids::encode($id)], 'Lokasi ditambahkan.');
    }

    public function update(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lokasi tidak valid.', 422);
        }

        $d = $request->validate($this->aturan((int) $realId));

        DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->where('Id_Master_Lokasi', $realId)
            ->update($this->isian($d) + [
                'Flag_Default' => ! empty($d['utama']) ? 'Y' : 'T',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

        if (! empty($d['utama'])) {
            $this->jadikanUtamaTunggal((int) $realId);
        }

        // Field-nya TIDAK dikirim → peruntukan lama dibiarkan. Dikirim kosong →
        // memang dikosongkan. Menyamakan keduanya berarti setiap penyuntingan
        // dari layar lama diam-diam mencabut seluruh pemetaannya.
        if ($request->has('peruntukan')) {
            $this->simpanPeruntukan((int) $realId, $d['peruntukan'] ?? []);
        }

        return ResponseHelper::success(null, 'Lokasi diperbarui.');
    }

    /** PATCH toggle aktif/nonaktif. */
    public function toggle(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->first() : null;

        if (! $row) {
            return ResponseHelper::error('Lokasi tidak ditemukan.', 404);
        }

        $baru = $row->Flag_Aktif === 'Y' ? 'T' : 'Y';

        DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->update([
            'Flag_Aktif' => $baru,
            // Lokasi nonaktif tidak boleh tetap jadi bawaan dropdown.
            'Flag_Default' => $baru === 'T' ? 'T' : $row->Flag_Default,
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama'),
        ]);

        return ResponseHelper::success(null, $baru === 'Y' ? 'Lokasi diaktifkan.' : 'Lokasi dinonaktifkan.');
    }

    /**
     * DELETE — hanya bila belum pernah dipakai menjadwalkan.
     *
     * Menghapus lokasi yang sudah terpakai akan membuat jadwal lama kehilangan
     * tempatnya; kandidat yang membuka riwayat melihat undangan tanpa lokasi.
     * Yang terpakai cukup dinonaktifkan — ia hilang dari dropdown tapi jadwal
     * lama tetap utuh.
     */
    public function destroy(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lokasi tidak valid.', 422);
        }

        $terpakai = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Jadwal_Lokasi_Id', $realId)
            ->count();

        if ($terpakai > 0) {
            return ResponseHelper::error(
                "Lokasi ini sudah dipakai pada {$terpakai} jadwal. Nonaktifkan saja agar jadwal lama tetap utuh.",
                409,
            );
        }

        DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map')->where('Id_Master_Lokasi', $realId)->delete();
        DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->delete();

        return ResponseHelper::success(null, 'Lokasi dihapus.');
    }
}
