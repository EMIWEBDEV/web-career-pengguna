<?php

namespace App\Support\Career;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — ISI UNDANGAN JADWAL & INSTRUKSINYA.
 *
 * Satu tempat yang menyusun apa yang dibaca kandidat tentang jadwal sebuah
 * aktivitas: undangan saat dijadwalkan, dan pengingat menjelang batas waktu.
 * Keduanya dirakit di sini supaya mustahil berbeda — pengingat yang menyebut
 * tempat atau batas lain dari undangannya lebih buruk daripada tanpa pengingat.
 *
 * ══ MODE BERRENTANG TANGGAL (MCU VENDOR / MANDIRI) ══
 *
 * Master_Mode_Jadwal.Flag_Batas_Waktu = 'Y' mengubah arti waktunya: bukan jam
 * janji temu, melainkan RENTANG TANGGAL ("30 September – 07 Oktober 2026").
 * Jadwal_Mulai = awal hari pertama, Jadwal_Selesai = pukul 23.59 hari
 * terakhir. Rentang itu INFORMASI kapan memeriksakan diri; batas unggah hasil
 * (MANDIRI) awalnya sama dengan ujungnya, tetapi "Perpanjang" hanya memundurkan
 * batas unggah (Jadwal_Batas_Unggah) — lihat batasUnggah(). Flag lain pada mode
 * yang sama menentukan sisanya:
 *
 *   Flag_Butuh_Tempat     tempat DIKETIK tim (nama vendor + catatan cabang,
 *                         alamat & tautan Google Maps opsional) — VENDOR
 *   Kalimat_Undangan      "tempat" bila tidak diketik: klinik/RS pilihan
 *                         kandidat — MANDIRI
 *   Flag_Butuh_Surat      surat pengantar wajib dilampirkan (SuratJadwal)
 *   Flag_Unggah_Kandidat  KANDIDAT mengunggah hasilnya sendiri; kotak unggahnya
 *                         tertutup begitu batasnya lewat — aturanUnggah()
 *
 * ══ INSTRUKSI ══
 *
 * Ditulis SEKALI per alur (Master_Alur_Tahap_Tes.Instruksi_Html) lalu disalin
 * ke baris kandidat saat dijadwalkan (Jadwal_Catatan_Html). Salinan itu yang
 * dikirim — mengubah alur besok tidak mengubah apa yang sudah diterima orang.
 * Data per kandidat (tempat, waktu, biaya) TIDAK PERNAH ditulis di dalam teks
 * instruksi: semuanya dirakit sistem di posisi tetap, sehingga menggeser tanggal
 * tidak meninggalkan kalimat lama yang masih menyebut tanggal lama.
 */
final class UndanganJadwal
{
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'];

    private const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /**
     * Aturan unggah bagi aktivitas yang unggahannya lahir dari MODE jadwal
     * (MANDIRI), bukan dari setelan Master Alur. Hasil MCU berupa PDF dari
     * klinik atau foto kwitansi dari ponsel — keduanya jarang melebihi ini.
     */
    public const UNGGAH_FORMAT = ['pdf', 'jpg', 'jpeg', 'png'];

    public const UNGGAH_MAKS_MB = 5;

    /** Kolom salinan instruksi sudah ada? (docs/30-09-2026/01) */
    public static function siapInstruksi(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Catatan_Html');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Kolom instruksi per alur sudah ada? */
    public static function siapInstruksiAlur(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Master_Alur_Tahap_Tes', 'Instruksi_Html');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Kolom tempat vendor & surat pengantar sudah ada? (docs/30-09-2026/01) */
    public static function siapVendor(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Surat_Json');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Tempatnya DIKETIK tim (nama vendor + catatan cabang), bukan Master Lokasi? */
    public static function butuhTempat(?object $mode): bool
    {
        return ($mode->Flag_Butuh_Tempat ?? 'T') === 'Y';
    }

    /** Wajib melampirkan surat pengantar? Tanpa kolomnya, tak ada yang bisa dituntut. */
    public static function butuhSurat(?object $mode): bool
    {
        return ($mode->Flag_Butuh_Surat ?? 'T') === 'Y' && self::siapVendor();
    }

    /** Nama surat pengantar mode ini ("Surat pengantar MCU"). */
    public static function labelSurat(?object $mode): string
    {
        return trim((string) ($mode->Label_Surat ?? '')) ?: 'Surat pengantar';
    }

    /** Kandidat sendiri yang mengunggah hasilnya (MANDIRI)? */
    public static function unggahKandidat(?object $mode): bool
    {
        return ($mode->Flag_Unggah_Kandidat ?? 'T') === 'Y';
    }

    /**
     * Definisi satu mode jadwal — TERMASUK yang sudah dinonaktifkan.
     *
     * Jadwal lama tetap harus terbaca benar walau modenya kemudian dimatikan:
     * batas waktu yang tiba-tiba terbaca sebagai jam janji temu adalah undangan
     * yang salah bunyi.
     */
    public static function mode(?string $kode): ?object
    {
        static $semua = null;
        $semua ??= DB::table('N_WEB_CAREERS_Master_Mode_Jadwal')->get()->keyBy('Kode');

        return $kode ? $semua->get(strtoupper($kode)) : null;
    }

    /** Waktu jadwal ini BATAS AKHIR, bukan janji temu? */
    public static function berbatasWaktu(?object $mode): bool
    {
        return ($mode->Flag_Batas_Waktu ?? 'T') === 'Y';
    }

    /**
     * Batas dari masukan layar. Tanggal saja → pukul 23.59 hari itu: "paling
     * lambat Jumat" berarti sepanjang hari Jumat, bukan tengah malam Kamis.
     */
    public static function akhirHari(string $nilai): Carbon
    {
        $nilai = trim($nilai);
        $c = Carbon::parse($nilai);

        return strlen($nilai) <= 10 ? $c->setTime(23, 59, 0) : $c->setSecond(0);
    }

    /** Awal rentang dari masukan layar: tanggal saja → pukul 00.00 hari itu. */
    public static function awalHari(string $nilai): Carbon
    {
        $nilai = trim($nilai);
        $c = Carbon::parse($nilai);

        return strlen($nilai) <= 10 ? $c->startOfDay() : $c->setSecond(0);
    }

    /** Batas bawaan sebuah mode: hari ini + N hari, pukul 23.59. Null bila tak diatur. */
    public static function batasBawaan(?object $mode, ?Carbon $sekarang = null): ?Carbon
    {
        $hari = (int) ($mode->Batas_Hari_Bawaan ?? 0);
        if ($hari < 1) {
            return null;
        }

        return ($sekarang ?? now())->copy()->addDays($hari)->setTime(23, 59, 0);
    }

    /** "Rabu, 07 Oktober 2026" — jamnya ikut hanya bila bukan akhir hari. */
    public static function teksBatas(Carbon|string|null $batas): string
    {
        if (! $batas) {
            return '—';
        }
        $c = $batas instanceof Carbon ? $batas : Carbon::parse($batas);
        $teks = self::HARI[$c->dayOfWeek].', '.$c->format('d').' '.self::BULAN[$c->month - 1].' '.$c->format('Y');

        return $c->format('H:i') === '23:59' ? $teks : $teks.' pukul '.$c->format('H.i').' WIB';
    }

    /**
     * "30 September – 07 Oktober 2026" — rentang pemeriksaan untuk surel dan
     * portal. Tanpa nama hari: dua nama hari berkoma di satu baris terbaca
     * seperti daftar, bukan rentang. Bulan/tahun yang sama tidak diulang
     * ("11 – 14 September 2026"); tanggal selalu dua angka.
     */
    public static function teksRentang(Carbon|string|null $mulai, Carbon|string|null $selesai): string
    {
        if (! $selesai) {
            return '—';
        }
        $b = $selesai instanceof Carbon ? $selesai : Carbon::parse($selesai);
        $a = $mulai ? ($mulai instanceof Carbon ? $mulai : Carbon::parse($mulai)) : null;
        $bulan = fn (Carbon $c) => self::BULAN[$c->month - 1];

        return match (true) {
            ! $a || $a->isSameDay($b) || $a->gt($b) => $b->format('d').' '.$bulan($b).' '.$b->format('Y'),
            $a->format('Y-m') === $b->format('Y-m') => $a->format('d').' – '.$b->format('d').' '.$bulan($b).' '.$b->format('Y'),
            $a->year === $b->year => $a->format('d').' '.$bulan($a).' – '.$b->format('d').' '.$bulan($b).' '.$b->format('Y'),
            default => $a->format('d').' '.$bulan($a).' '.$a->format('Y').' – '.$b->format('d').' '.$bulan($b).' '.$b->format('Y'),
        };
    }

    /** "11–14 Sep 2026" / "30 Sep – 07 Okt 2026" — untuk baris ringkas di worklist. */
    public static function rentangPendek(Carbon|string|null $mulai, Carbon|string|null $selesai): string
    {
        if (! $selesai) {
            return '—';
        }
        $b = $selesai instanceof Carbon ? $selesai : Carbon::parse($selesai);
        $a = $mulai ? ($mulai instanceof Carbon ? $mulai : Carbon::parse($mulai)) : null;

        return match (true) {
            ! $a || $a->isSameDay($b) || $a->gt($b) => self::tanggalPendek($b),
            $a->format('Y-m') === $b->format('Y-m') => $a->format('d').'–'.self::tanggalPendek($b),
            $a->year === $b->year => $a->format('d').' '.self::BULAN_PENDEK[$a->month - 1].' – '.self::tanggalPendek($b),
            default => self::tanggalPendek($a).' – '.self::tanggalPendek($b),
        };
    }

    /** Kolom perpanjangan batas unggah sudah ada? (docs/30-09-2026/01) */
    public static function siapBatasUnggah(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Batas_Unggah');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Batas akhir yang BERLAKU pada jadwal berrentang: akhir rentang
     * pemeriksaan, atau batas unggah yang diperpanjang tim bila lebih lambat.
     *
     * Rentang pemeriksaan dan batas unggah hasil adalah dua hal. Rentang =
     * kapan kandidat memeriksakan diri (informasi); batas unggah = kapan kotak
     * unggahnya ditutup. Awalnya sama. "Perpanjang" hanya memundurkan batas
     * unggah (Jadwal_Batas_Unggah): hasil lab yang terlambat keluar tidak
     * mengubah kapan kandidat seharusnya diperiksa. Perpanjangan tidak pernah
     * memendekkan — rentang baru yang lebih lambat menggantikannya sendiri.
     *
     * @param  object  $s  baris Lamaran_Tahap_Tes
     */
    public static function batasUnggah(object $s): ?Carbon
    {
        if (empty($s->Jadwal_Selesai)) {
            return null;
        }

        return Carbon::parse(self::batasDiperpanjang($s) ? $s->Jadwal_Batas_Unggah : $s->Jadwal_Selesai);
    }

    /** Batas unggahnya sudah diperpanjang melewati akhir rentang pemeriksaan? */
    public static function batasDiperpanjang(object $s): bool
    {
        return ! empty($s->Jadwal_Batas_Unggah) && ! empty($s->Jadwal_Selesai)
            && Carbon::parse($s->Jadwal_Batas_Unggah)->gt(Carbon::parse($s->Jadwal_Selesai));
    }

    /**
     * Tautan Google Maps yang DITEMPEL tim → bentuk baku; null bila kosong,
     * false bila bukan tautan Google Maps.
     *
     * Hanya Google Maps, dan hanya yang ditempel orang. Sistem tidak pernah
     * mengarang peta dari nama tempat: vendor berjaringan punya puluhan cabang,
     * dan pin hasil pencarian nama tampil sama meyakinkannya dengan pin yang
     * benar — kandidat tak punya cara membedakannya. Domainnya dibatasi karena
     * tautan ini dikirim apa adanya lewat surel resmi.
     */
    public static function normalkanMaps(?string $url): string|false|null
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('/\s/', $url)) {
            return false;
        }
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }
        $url = (string) preg_replace('~^http://~i', 'https://', $url);

        $p = parse_url($url);
        $host = strtolower((string) ($p['host'] ?? ''));
        $path = (string) ($p['path'] ?? '');
        $tld = '(com|[a-z]{2}|co\.[a-z]{2}|com\.[a-z]{2})';

        $sah = $host === 'maps.app.goo.gl'
            || ($host === 'goo.gl' && str_starts_with($path, '/maps'))
            || ($host === 'g.co' && str_starts_with($path, '/kgs/'))
            || (preg_match('/^(www\.)?google\.'.$tld.'$/', $host) && str_starts_with($path, '/maps'))
            || preg_match('/^maps\.google\.'.$tld.'$/', $host);

        return $sah ? $url : false;
    }

    /** "2 hari lagi" / "sekitar 5 jam lagi" / "kurang dari 1 jam lagi". */
    public static function sisaTeks(Carbon $dari, Carbon $ke): string
    {
        $jam = (int) floor(max(0, $dari->diffInMinutes($ke)) / 60);

        return match (true) {
            $jam < 1 => 'kurang dari 1 jam lagi',
            $jam < 24 => "sekitar {$jam} jam lagi",
            default => ((int) floor($jam / 24)).' hari lagi',
        };
    }

    /**
     * "Kamis, 08 Oktober 2026 · 10.00 – 10.45 WIB" — bunyi waktu janji temu di
     * surel. Sama persis dengan yang disusun WcJadwalEmailJob untuk undangan,
     * supaya pengingat dan tanda terima menyebut jam yang sama bunyinya.
     */
    public static function waktuTeks(Carbon|string|null $mulai, Carbon|string|null $selesai = null): string
    {
        if (! $mulai) {
            return '—';
        }

        try {
            $m = Carbon::parse($mulai)->locale('id');
            $teks = $m->translatedFormat('l, d F Y').' · '.$m->format('H.i');
            if ($selesai) {
                $teks .= ' – '.Carbon::parse($selesai)->format('H.i');
            }

            return $teks.' WIB';
        } catch (\Throwable $e) {
            return (string) $mulai;
        }
    }

    /** "07 Okt 2026" — untuk baris ringkas di worklist. */
    public static function tanggalPendek(Carbon|string|null $at): string
    {
        if (! $at) {
            return '—';
        }
        $c = $at instanceof Carbon ? $at : Carbon::parse($at);

        return $c->format('d').' '.self::BULAN_PENDEK[$c->month - 1].' '.$c->format('Y');
    }

    /**
     * Instruksi dari editor → [HTML tersaring, teks polos].
     *
     * Gambar dibuang (CatatanEksternal::saring): gambar catatan dilayani rute
     * admin yang tak bisa dibuka kandidat, dan surel tidak memuatnya — ia hanya
     * akan jadi kotak rusak di kedua tempat. Teks polosnya disusun dengan
     * struktur yang dipertahankan (paragraf, butir "• ", tautan beserta
     * alamatnya): itulah yang dikirim ke surel dan dipakai layar lama.
     *
     * Tanpa HTML → teks polos apa adanya (klien lama / pemanggil lain).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function saringInstruksi(?string $html, ?string $polos = null): array
    {
        $bersih = CatatanEksternal::saring($html);
        if ($bersih === null) {
            $polos = trim((string) $polos);

            return [null, $polos !== '' ? $polos : null];
        }

        return [$bersih, CatatanEksternal::keTeksSurat($bersih)];
    }

    /**
     * Instruksi bawaan untuk satu aktivitas kandidat — dari Master Alur.
     *
     * Dicari di alur yang SEKARANG dipasang di programnya (versi terbaru yang
     * admin lihat), lewat kode tahap + label aktivitas; bila tidak ketemu, jatuh
     * ke baris master yang dibekukan pada lamaran itu. Labelnya selalu dicocokkan:
     * baris master dipakai ulang per urutan saat alur disunting, jadi Id saja
     * bisa menunjuk aktivitas yang sudah lain sama sekali.
     */
    public static function instruksiAlur(int $subTesId): ?string
    {
        return self::setelanAlur($subTesId)['instruksi'];
    }

    /**
     * Setelan untuk KANDIDAT milik satu aktivitas di Master Alur: instruksinya,
     * apakah informasi biaya ditampilkan (Tampil_Biaya; kosong = tampil), dan
     * kalimat biaya alur itu (Kalimat_Biaya; kosong = kalimat tipe).
     *
     * Cara mencarinya sama dengan instruksiAlur(): alur yang SEKARANG dipasang
     * di programnya (kode tahap + label), jatuh ke baris master yang dibekukan
     * pada lamaran itu. Keduanya dibaca sekaligus — jendela Atur Jadwal
     * membutuhkan keduanya sebagai isian awal.
     *
     * @return array{instruksi: ?string, tampilBiaya: bool, kalimatBiaya: ?string}
     */
    public static function setelanAlur(int $subTesId): array
    {
        $kosong = ['instruksi' => null, 'tampilBiaya' => true, 'kalimatBiaya' => null];
        if (! self::siapInstruksiAlur()) {
            return $kosong;
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as st')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as lt', 'lt.Id_Lamaran_Tahap', '=', 'st.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('st.Id_Lamaran_Tahap_Tes', $subTesId)
            ->first(['st.Label', 'st.Master_Alur_Tahap_Tes_Id', 'lt.Kode as KodeTahap', 'l.Program_Id']);

        if (! $sub) {
            return $kosong;
        }

        $label = trim((string) $sub->Label);
        $kolom = array_merge(
            ['mt.Instruksi_Html'],
            self::siapBiayaAlur() ? ['mt.Tampil_Biaya'] : [],
            self::siapKalimatBiayaAlur() ? ['mt.Kalimat_Biaya'] : [],
        );

        $kini = $sub->KodeTahap && $sub->Program_Id
            ? DB::table('N_WEB_CAREERS_Program as p')
                ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->join('N_WEB_CAREERS_Master_Alur_Tahap as m', 'm.Master_Alur_Id', '=', 'a.Id_Master_Alur')
                ->join('N_WEB_CAREERS_Master_Alur_Tahap_Tes as mt', 'mt.Master_Alur_Tahap_Id', '=', 'm.Id_Master_Alur_Tahap')
                ->where('p.Id_Program', $sub->Program_Id)
                ->where('m.Kode', $sub->KodeTahap)
                ->whereRaw('LTRIM(RTRIM(mt.Label)) = ?', [$label])
                ->first($kolom)
            : null;

        $beku = $sub->Master_Alur_Tahap_Tes_Id && (! $kini || ! $kini->Instruksi_Html)
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes as mt')
                ->where('mt.Id_Master_Alur_Tahap_Tes', $sub->Master_Alur_Tahap_Tes_Id)
                ->whereRaw('LTRIM(RTRIM(mt.Label)) = ?', [$label])
                ->first($kolom)
            : null;

        $baris = $kini ?? $beku;

        return [
            'instruksi' => CatatanEksternal::saring(($kini->Instruksi_Html ?? null) ?: ($beku->Instruksi_Html ?? null)),
            'tampilBiaya' => (($baris->Tampil_Biaya ?? null) ?: 'Y') !== 'T',
            'kalimatBiaya' => trim((string) ($baris->Kalimat_Biaya ?? '')) ?: null,
        ];
    }

    /** Kolom kalimat biaya per aktivitas alur sudah ada? */
    public static function siapKalimatBiayaAlur(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Master_Alur_Tahap_Tes', 'Kalimat_Biaya');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Kalimat biaya yang BERLAKU pada satu jadwal — null bila disembunyikan
     * admin atau tipenya memang tanpa ketentuan biaya.
     *
     * Urutannya: kalimat yang dibekukan pada jadwal itu (bisa disunting per
     * jadwal) → kalimat tipe. Jadwal lama tanpa salinan tetap membaca kalimat
     * tipenya, seperti sebelum penyuntingan ada.
     */
    public static function kalimatBiaya(object $s, ?object $tipe): ?string
    {
        $tipeKalimat = trim((string) ($tipe->Kalimat_Biaya ?? ''));
        if ($tipeKalimat === '' || ! self::tampilBiaya($s)) {
            return null;
        }

        return trim((string) ($s->Jadwal_Kalimat_Biaya ?? '')) ?: $tipeKalimat;
    }

    /** Kolom bawaan tampil-biaya per aktivitas alur sudah ada? */
    public static function siapBiayaAlur(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Master_Alur_Tahap_Tes', 'Tampil_Biaya');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Kolom tampil-biaya per jadwal sudah ada? */
    public static function siapTampilBiaya(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Tampil_Biaya');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Informasi biaya ikut ke kandidat pada jadwal ini? Kosong (jadwal lama,
     * atau kolomnya belum ada) = tampil — perilaku sebelum pengaturan ini ada.
     */
    public static function tampilBiaya(object $s): bool
    {
        return ($s->Jadwal_Tampil_Biaya ?? 'Y') !== 'T';
    }

    /**
     * Aturan unggah KANDIDAT yang BERLAKU pada satu aktivitas — atau null bila
     * aktivitas ini tidak meminta apa pun darinya.
     *
     * DUA SUMBER, satu bentuk:
     *   ALUR  setelan unggah yang dibekukan dari Master Alur (tes offline dsb.)
     *   MODE  mode jadwalnya ber-Flag_Unggah_Kandidat (MCU MANDIRI): kandidat
     *         yang memegang hasilnya, jadi ia yang mengunggah — pada VENDOR
     *         hasilnya datang ke tim. Aturannya lahir dari jadwal, bukan dari
     *         alur, sehingga memindahkan MCU dari mandiri ke vendor ikut
     *         mencabut permintaan unggahnya tanpa menyentuh apa pun.
     *
     * TERTUTUP: pada mode berrentang, batas unggahnya = akhir rentang, atau
     * tanggal perpanjangan dari tim (batasUnggah). Sesudah lewat, kotak unggah
     * ditutup (server menolak unggah, hapus, dan kirim) — admin memperpanjang
     * bila memang perlu. Yang sudah dikirim tidak pernah dianggap tertutup:
     * tidak ada yang tersisa untuk ditutup.
     *
     * @param  object  $s  baris Lamaran_Tahap_Tes
     * @param  object|null  $mode  definisi mode jadwalnya — kosong = dibaca dari master
     */
    public static function aturanUnggah(object $s, ?object $mode = null, ?Carbon $sekarang = null): ?array
    {
        // Tanpa jadwal, mode apa pun tidak berlaku — unggahan MANDIRI baru
        // lahir saat jadwalnya terbit.
        $mode = ! empty($s->Jadwal_Mulai) ? ($mode ?? self::mode($s->Jadwal_Mode ?? null)) : null;
        $dariAlur = ($s->Unggah_Kandidat ?? 'T') === 'Y';
        $dariMode = $mode && self::unggahKandidat($mode);

        if (! $dariAlur && ! $dariMode) {
            return null;
        }

        $batas = self::berbatasWaktu($mode) ? self::batasUnggah($s) : null;
        $terkirim = ! empty($s->Unggah_Kirim_At) ? (string) $s->Unggah_Kirim_At : null;
        $petunjuk = $dariAlur ? trim((string) ($s->Unggah_Petunjuk ?? '')) : '';
        if ($petunjuk === '' && $dariMode) {
            $petunjuk = trim((string) ($mode->Petunjuk_Unggah ?? ''));
        }

        return [
            'wajib' => $dariAlur ? ($s->Unggah_Wajib ?? 'T') === 'Y' : true,
            'format' => $dariAlur
                ? array_values(array_filter(array_map('trim', explode(',', (string) ($s->Unggah_Format ?: 'pdf')))))
                : self::UNGGAH_FORMAT,
            'maksMb' => $dariAlur ? (int) ($s->Unggah_Maks_Mb ?: 5) : self::UNGGAH_MAKS_MB,
            'petunjuk' => $petunjuk !== '' ? $petunjuk : null,
            'terkirim' => $terkirim,
            'batas' => $batas?->format('Y-m-d H:i:s'),
            'batasTeks' => $batas ? self::teksBatas($batas) : null,
            // Batasnya sudah dimundurkan tim melewati akhir rentang pemeriksaan.
            'diperpanjang' => $batas !== null && self::batasDiperpanjang($s),
            'tertutup' => ! $terkirim && $batas !== null && ($sekarang ?? Carbon::now())->gt($batas),
            'sumber' => $dariAlur ? 'ALUR' : 'MODE',
        ];
    }

    /**
     * Muatan surel undangan/pengingat untuk satu aktivitas.
     *
     * Mengembalikan baris aktivitasnya juga — pemanggil yang memutuskan boleh
     * tidaknya dikirim (jadwal privat, aktivitas internal); penyusun isi tidak
     * ikut memutuskan kebijakan.
     *
     * @return array{userId:int, sub:object, tipe:?object, data:array}|null
     */
    public static function muatan(int $subTesId): ?array
    {
        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
            ->select('t.*', 'h.Label as TahapLabel', 'h.Tipe_Tahap_Kode as TahapTipe', 'l.Kode', 'u.Id_Users', 'u.Nama', 'u.Email',
                'p.Nama as ProgramNama', 'x.Posisi')
            ->first();

        if (! $sub) {
            return null;
        }

        $tipe = AlurKolom::masterTipe()->get($sub->Tipe_Tahap_Kode ?: $sub->TahapTipe);
        $mode = self::mode($sub->Jadwal_Mode);
        $berbatas = self::berbatasWaktu($mode);
        // VENDOR: tempatnya diketik tim (nama vendor, catatan cabang, alamat &
        // peta opsional). MANDIRI: tidak ada tempat dari tim — kalimat modenya
        // yang menjadi "tempat". Selebihnya: tempat dari Master Lokasi.
        $vendor = self::butuhTempat($mode);
        $pakaiTempat = ! $berbatas || $vendor;

        // TEMPATNYA ikut, bukan hanya patokan yang diketik rekruter — nama
        // resmi, alamat, dan petanya (lihat LamaranController::tempatJadwal).
        $tempat = LamaranController::tempatJadwal($sub);

        $instruksi = CatatanEksternal::keTeksSurat($sub->Jadwal_Catatan_Html ?? null) ?: $sub->Jadwal_Catatan;

        $unggah = self::aturanUnggah($sub);

        // KONFIRMASI KEHADIRAN — tombol utama surel + batasnya. Hanya untuk
        // aktivitas yang memang meminta jawaban (lihat KonfirmasiJadwal).
        $konfirmasi = [];
        if (! empty($sub->Konfirmasi_Status) && KonfirmasiJadwal::siap()) {
            $versi = (int) ($sub->Jadwal_Versi ?? 0);
            $crm = KonfirmasiJadwal::crm((int) $sub->Id_Lamaran_Tahap_Tes, $versi);
            $status = KonfirmasiJadwal::statusEfektif($sub->Konfirmasi_Status, $crm->Batas_Konfirmasi ?? null);
            $konfirmasi = [
                'konfirmasi_status' => $status,
                'konfirmasi_url' => KonfirmasiJadwal::tautan((int) $sub->Id_Lamaran_Tahap_Tes, $versi, (string) $sub->Jadwal_Mulai),
                'batas_konfirmasi_teks' => $crm ? self::teksBatas($crm->Batas_Konfirmasi) : null,
                // KOMITMEN: berapa kali & sampai kapan jawabannya bisa diubah —
                // ajakan berkomitmen bila belum menjawab, sisa jatah bila sudah.
                'ubah_teks' => in_array($status, [KonfirmasiJadwal::MENUNGGU, KonfirmasiJadwal::AKAN_HADIR, KonfirmasiJadwal::JADWAL_LAIN], true)
                    ? KonfirmasiJadwal::teksUbah(KonfirmasiJadwal::aturanUbahUntuk($sub), $status !== KonfirmasiJadwal::MENUNGGU)
                    : null,
            ];
        }

        // SURAT PENGANTAR — satu tautan bertanda tangan per surat (tanpa
        // login), sebab surelnya dibuka di aplikasi surat, bukan di sesi portal.
        $surat = [];
        foreach (SuratJadwal::daftar($sub) as $urutan => $i) {
            $surat[] = [
                'nama' => $i['nama'],
                'url' => SuratJadwal::tautanEmail((int) $sub->Id_Lamaran_Tahap_Tes, $urutan, self::batasUnggah($sub)),
            ];
        }

        return [
            'userId' => (int) $sub->Id_Users,
            'sub' => $sub,
            'tipe' => $tipe,
            'data' => [
                'nama' => $sub->Nama,
                'email' => $sub->Email,
                'kode' => $sub->Kode,
                'posisi' => $sub->Posisi ?: $sub->ProgramNama,
                'program' => $sub->ProgramNama,
                'tahap' => $sub->TahapLabel,
                'aktivitas' => $sub->Label,
                'mode' => $sub->Jadwal_Mode,
                // Nama bentuknya dari master — surel tidak perlu tahu kode apa
                // berarti apa, dan bentuk baru langsung terbaca benar.
                'mode_nama' => $mode->Nama ?? null,
                'mulai' => (string) $sub->Jadwal_Mulai,
                'selesai' => (string) ($sub->Jadwal_Selesai ?: ''),
                'batas_waktu' => $berbatas,
                // Rentang pemeriksaan ("30 September – 07 Oktober 2026") — TETAP
                // walau batas unggahnya diperpanjang — dan BATAS yang berlaku
                // (unggah, pengingat, surel perpanjangan): akhir rentang, atau
                // tanggal perpanjangan dari tim.
                'rentang_teks' => $berbatas ? self::teksRentang($sub->Jadwal_Mulai, $sub->Jadwal_Selesai) : null,
                'batas_teks' => $berbatas ? self::teksBatas(self::batasUnggah($sub)) : null,
                'batas_diperpanjang' => $berbatas && self::batasDiperpanjang($sub),
                'link' => $sub->Jadwal_Link,
                'vendor' => $vendor,
                'lokasi' => $pakaiTempat ? ($tempat['nama'] ?? $sub->Jadwal_Lokasi) : null,
                'alamat' => $pakaiTempat ? ($tempat['alamatLengkap'] ?? null) : null,
                'patokan' => $pakaiTempat && ! $vendor && $tempat ? $sub->Jadwal_Lokasi : null,
                'kontak' => $pakaiTempat && ! $vendor ? ($tempat['kontakTelp'] ?? null) : null,
                // Peta vendor HANYA bila tim menempelkan tautannya — tidak pernah
                // dikarang dari nama vendor (lihat normalkanMaps).
                'mapsUrl' => $pakaiTempat ? ($tempat['mapsUrl'] ?? null) : null,
                // Cabang / lokasi vendor yang melayani — teks dari editor tim.
                'lokasi_catatan' => $vendor ? (CatatanEksternal::keTeksSurat($sub->Jadwal_Lokasi_Html ?? null) ?: null) : null,
                // "Tempat" MANDIRI: klinik/RS pilihan kandidat, dari master mode.
                'tempat_kalimat' => $berbatas && ! $vendor ? ($mode->Kalimat_Undangan ?? null) : null,
                // SURAT PENGANTAR — tautan bertanda tangan (tanpa login), sebab
                // surelnya dibuka di aplikasi surat, bukan di sesi portal.
                'surat' => $surat ?: null,
                'surat_url' => $surat[0]['url'] ?? null,
                'surat_label' => $surat ? self::labelSurat($mode) : null,
                'surat_nama' => $surat[0]['nama'] ?? null,
                'surat_jumlah' => count($surat),
                // Aturan biaya tipe ini — ikut kecuali admin mematikannya untuk
                // jadwal ini (Jadwal_Tampil_Biaya, bawaan dari Master Alur):
                // mis. biayanya sudah dijelaskan di instruksi, atau ditanggung
                // langsung perusahaan.
                'biaya' => self::kalimatBiaya($sub, $tipe),
                // Instruksi dalam bentuk TEKS: server surat hanya menerima data.
                'catatan' => $instruksi ?: null,
                // Kandidat diminta mengunggah sesuatu sesudahnya (hasil, kwitansi)
                // — dan belum melakukannya. Aturannya sama dengan portal.
                'unggah' => $unggah !== null && ! $unggah['terkirim'],
                'unggah_petunjuk' => $unggah['petunjuk'] ?? null,
                'unggah_batas_teks' => $unggah['batasTeks'] ?? null,
                // Jadwal ini PENGGANTI dari jadwal yang ditunda — surel
                // berbunyi "Jadwal pengganti", bukan undangan baru biasa.
                'pengganti' => KonfirmasiJadwal::penggantiTunda($sub),
            ] + $konfirmasi,
        ];
    }
}
