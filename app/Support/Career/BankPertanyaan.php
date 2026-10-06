<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — BANK PERTANYAAN: pustaka pertanyaan lintas template.
 *
 * ── SATU HAL YANG HARUS JELAS DULUAN ────────────────────────────────────────
 *
 * Bank adalah SUMBER PENULISAN, bukan tautan hidup.
 *
 * Menarik pertanyaan dari bank ke sebuah template MENYALIN isinya. Sejak saat
 * itu templatelah yang memilikinya; menyunting bank besok tidak menyentuh
 * template mana pun, apalagi sesi kandidat yang sudah berjalan.
 *
 * Kalau bank dirujuk hidup-hidup, satu suntingan di sini akan mengubah arti
 * seluruh sesi yang pernah memakainya di seluruh program — persis hal yang
 * seluruh disiplin snapshot di modul ini ada untuk cegah.
 *
 * Yang didapat dari bank karena itu adalah kemudahan menulis dan KODE YANG
 * SERAGAM: "gaji_harapan" di template HR Umum dan di template MT adalah kode
 * yang sama, sehingga hasilnya bisa diagregasi lintas program. Tanpa bank,
 * ketiganya jadi tiga pertanyaan berbeda yang kebetulan mirip.
 *
 * ── SEGARKAN, DAN APA YANG TIDAK IKUT ───────────────────────────────────────
 *
 * Draf boleh ditarik ulang dari bank kapan saja. Salinan yang sudah dirombak
 * di template ditandai `Flag_Ubahan = 'Y'` dan SENGAJA dilewati — ambang
 * knockout gaji memang harus berbeda antara loker staf dan loker manajer,
 * dan menyegarkannya balik ke bawaan bank akan menghapus pekerjaan orang
 * tanpa memberitahunya.
 */
class BankPertanyaan
{
    public const TABEL = 'N_WEB_CAREERS_Master_Pertanyaan';

    public const T_TAG = 'N_WEB_CAREERS_Master_Tag';

    public const T_IKAT_TAG = 'N_WEB_CAREERS_Master_Pertanyaan_Tag';

    /**
     * Dimensi tag, berikut CARA MENYARINGNYA.
     *
     * Di DALAM satu dimensi penyaringan bersifat ATAU (IT atau Finance);
     * ANTAR dimensi bersifat DAN (IT dan Manager). Itu satu-satunya penafsiran
     * yang masuk akal bagi orang yang menyusun template — dan ia mustahil
     * dinyatakan kalau tag cuma daftar kata tanpa dimensi.
     */
    public const DIMENSI = ['JOB_FAMILY', 'LEVEL', 'CANDIDATE_TYPE', 'KOMPETENSI', 'FASE'];

    /**
     * Jenis pertanyaan — kosakata Talent Acquisition.
     *
     * BUKAN kolom `Tipe`. `Tipe` menjawab "bentuk isiannya apa" (RADIO,
     * RATING, TEXTAREA); `Jenis` menjawab "pertanyaan ini untuk apa"
     * (BEHAVIORAL, TECHNICAL, RED_FLAG). Menyatukan keduanya memaksa salah
     * satunya hilang.
     */
    public const JENIS = [
        'GENERAL', 'ELIGIBILITY', 'MOTIVATION', 'BEHAVIORAL', 'COMPETENCY',
        'TECHNICAL', 'SITUATIONAL', 'EXPERIENCE', 'AVAILABILITY', 'COMPENSATION',
        'CULTURE_FIT', 'CAREER_ASPIRATION', 'RED_FLAG', 'CLOSING', 'VERIFICATION',
    ];

    public const PRIORITAS = ['MANDATORY', 'RECOMMENDED', 'OPTIONAL', 'CONDITIONAL'];

    public const STATUS_TINJAU = ['DRAFT', 'REVIEW', 'APPROVED', 'ARCHIVED'];

    /** Konteks pemakaian. Hari ini cuma satu; kolomnya ada supaya yang kedua
     *  tidak menuntut tabel baru. */
    public const KONTEKS = ['SKRINING'];

    private static ?bool $siap = null;

    private static ?bool $siapTag = null;

    /** Skema bank sudah dijalankan? */
    public static function siap(): bool
    {
        return self::$siap ??= Skema::adaTabel(self::TABEL)
            && Skema::adaKolom('N_WEB_CAREERS_Master_Skrining_Pertanyaan', 'Bank_Kode');
    }

    /** Skema TAG sudah dijalankan? Terpisah: ia datang di skrip berikutnya. */
    public static function siapTag(): bool
    {
        return self::$siapTag ??= Skema::adaTabel(self::T_TAG)
            && Skema::adaTabel(self::T_IKAT_TAG)
            && Skema::adaKolom(self::TABEL, 'Jenis');
    }

    /** Seluruh tag, dikelompokkan per dimensi — mengisi kotak penyaring di layar. */
    public static function tag(bool $hanyaAktif = true): array
    {
        if (! self::siapTag()) {
            return [];
        }

        $out = [];
        foreach (self::DIMENSI as $d) {
            $out[$d] = [];
        }

        $rows = DB::table(self::T_TAG)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', 'Y'))
            ->orderBy('Dimensi')->orderBy('Urutan')->orderBy('Nama')
            ->get();

        // Jumlah pemakaian ikut dikirim: tag yang belum dipakai satu pertanyaan
        // pun tetap ditawarkan, tapi orang berhak tahu itu sebelum menyaring
        // dengannya dan mendapat daftar kosong.
        $pakai = DB::table(self::T_IKAT_TAG)
            ->select('Dimensi', 'Tag_Kode', DB::raw('COUNT(*) as n'))
            ->groupBy('Dimensi', 'Tag_Kode')
            ->get()
            ->keyBy(fn ($r) => $r->Dimensi.'|'.$r->Tag_Kode);

        foreach ($rows as $r) {
            $out[$r->Dimensi][] = [
                'id' => (int) $r->Id_Master_Tag,
                'kode' => $r->Kode,
                'nama' => $r->Nama,
                'deskripsi' => $r->Deskripsi,
                'warna' => $r->Warna,
                'ikon' => $r->Ikon,
                'sistem' => ($r->Flag_Sistem ?? 'T') === 'Y',
                'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
                'jml' => (int) ($pakai[$r->Dimensi.'|'.$r->Kode]->n ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Cari pertanyaan di bank — bertag, bertingkat, berhalaman.
     *
     * ── KENAPA BERHALAMAN, PADAHAL DULU SELURUHNYA DIKIRIM SEKALIGUS ────────
     *
     * Karena banknya sekarang berisi seribu lebih pertanyaan, masing-masing
     * membawa rubrik penilaian, penanda risiko, dan pertanyaan lanjutan.
     * Mengirim semuanya setiap kali halaman template dibuka berarti memuat
     * beberapa megabita untuk daftar yang akan disaring jadi belasan baris.
     *
     * @param  array  $saring  ['jf'=>[], 'level'=>[], 'ct'=>[], 'komp'=>[],
     *                          'fase'=>[], 'jenis'=>[], 'prioritas'=>[],
     *                          'q'=>string, 'kecuali'=>[kode]]
     * @return array{rows: array, total: int}
     */
    public static function cari(array $saring, int $halaman = 1, int $per = 40): array
    {
        if (! self::siap()) {
            return ['rows' => [], 'total' => 0];
        }

        $q = DB::table(self::TABEL.' as p')
            ->where('p.Konteks', $saring['konteks'] ?? 'SKRINING')
            ->where('p.Flag_Aktif', 'Y');

        if (self::siapTag()) {
            // Satu EXISTS per dimensi. Itulah yang mewujudkan aturan
            // "ATAU di dalam dimensi, DAN antar dimensi" — dan alasan kenapa
            // tag harus berdimensi sejak awal.
            foreach ([
                'jf' => 'JOB_FAMILY', 'level' => 'LEVEL', 'ct' => 'CANDIDATE_TYPE',
                'komp' => 'KOMPETENSI', 'fase' => 'FASE',
            ] as $kunci => $dimensi) {
                $nilai = array_filter((array) ($saring[$kunci] ?? []));
                if (! $nilai) {
                    continue;
                }

                $q->whereExists(fn ($e) => $e
                    ->from(self::T_IKAT_TAG.' as t'.$kunci)
                    ->whereColumn('t'.$kunci.'.Master_Pertanyaan_Id', 'p.Id_Master_Pertanyaan')
                    ->where('t'.$kunci.'.Dimensi', $dimensi)
                    ->whereIn('t'.$kunci.'.Tag_Kode', $nilai));
            }

            foreach (['jenis' => 'Jenis', 'prioritas' => 'Prioritas'] as $kunci => $kolom) {
                $nilai = array_filter((array) ($saring[$kunci] ?? []));
                if ($nilai) {
                    $q->whereIn('p.'.$kolom, $nilai);
                }
            }
        }

        if (! empty($saring['q'])) {
            $kata = '%'.$saring['q'].'%';
            $q->where(fn ($w) => $w
                ->where('p.Label', 'like', $kata)
                ->orWhere('p.Kode', 'like', $kata)
                ->orWhere('p.Kelompok', 'like', $kata)
                ->orWhere('p.Sub_Kelompok', 'like', $kata));
        }

        // Kode yang SUDAH ada di draf disingkirkan dari hasil, bukan sekadar
        // ditandai: menawarkan sesuatu yang tidak bisa dipilih hanya memenuhi
        // daftar dengan baris mati.
        $kecuali = array_filter((array) ($saring['kecuali'] ?? []));
        if ($kecuali) {
            $q->whereNotIn('p.Kode', $kecuali);
        }

        $total = (clone $q)->count();

        if (($saring['urut'] ?? '') === 'baru') {
            // Id menang atas Created_At sebagai pemecah seri: dua pertanyaan
            // yang tersimpan pada detik yang sama punya waktu yang sama persis,
            // dan urutan tanpa pemecah seri berubah-ubah tiap kali dimuat —
            // baris yang sama bisa muncul di dua halaman sekaligus.
            $q->orderByDesc('p.Created_At')->orderByDesc('p.Id_Master_Pertanyaan');
        } else {
            $q->orderByRaw("CASE p.Prioritas WHEN 'MANDATORY' THEN 1 WHEN 'RECOMMENDED' THEN 2 WHEN 'CONDITIONAL' THEN 3 ELSE 4 END")
                ->orderBy('p.Kelompok')
                ->orderBy('p.Urutan');
        }

        $rows = $q->forPage(max(1, $halaman), $per)
            ->get('p.*')
            ->map(fn ($r) => self::bentuk($r))
            ->values()
            ->all();

        $rows = self::lengkapiTag($rows);

        return ['rows' => $rows, 'total' => $total];
    }

    /** Tempelkan daftar tag ke sekumpulan pertanyaan — satu kueri, bukan N. */
    public static function lengkapiTag(array $rows): array
    {
        if (! $rows || ! self::siapTag()) {
            return $rows;
        }

        $ids = array_column($rows, 'id');
        $peta = [];

        foreach (
            DB::table(self::T_IKAT_TAG.' as pt')
                ->join(self::T_TAG.' as g', 'g.Id_Master_Tag', '=', 'pt.Master_Tag_Id')
                ->whereIn('pt.Master_Pertanyaan_Id', $ids)
                ->orderBy('g.Dimensi')->orderBy('g.Urutan')
                ->get(['pt.Master_Pertanyaan_Id as pid', 'g.Dimensi', 'g.Kode', 'g.Nama', 'g.Warna', 'g.Ikon']) as $t
        ) {
            $peta[$t->pid][] = [
                'dimensi' => $t->Dimensi, 'kode' => $t->Kode,
                'nama' => $t->Nama, 'warna' => $t->Warna, 'ikon' => $t->Ikon,
            ];
        }

        foreach ($rows as &$r) {
            $r['tag'] = $peta[$r['id']] ?? [];
        }

        return $rows;
    }

    /**
     * Isi bank untuk sebuah konteks.
     *
     * `$hanyaAktif` dipakai layar PEMILIH (menawarkan yang nonaktif hanya
     * membuat orang memilih pertanyaan yang sudah sengaja dipensiunkan),
     * sedangkan halaman CRUD-nya membaca semuanya.
     */
    public static function daftar(string $konteks = 'SKRINING', bool $hanyaAktif = false): array
    {
        if (! self::siap()) {
            return [];
        }

        return DB::table(self::TABEL)
            ->where('Konteks', $konteks)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', 'Y'))
            ->orderBy('Kelompok')
            ->orderBy('Urutan')
            ->orderBy('Label')
            ->get()
            ->map(fn ($r) => self::bentuk($r))
            ->values()
            ->all();
    }

    /** Satu baris bank → bentuk yang dibaca layar. */
    public static function bentuk(object $r): array
    {
        return [
            'id' => (int) $r->Id_Master_Pertanyaan,
            'kode' => $r->Kode,
            'konteks' => $r->Konteks,
            'kelompok' => $r->Kelompok,
            'tipe' => $r->Tipe,
            'label' => $r->Label,
            'bantuan' => $r->Bantuan,
            'opsi' => Skrining::opsiArray($r->Opsi ?? null),
            'skalaMin' => $r->Skala_Min !== null ? (int) $r->Skala_Min : null,
            'skalaMax' => $r->Skala_Max !== null ? (int) $r->Skala_Max : null,
            'labelMin' => $r->Label_Min,
            'labelMax' => $r->Label_Max,
            'bobot' => $r->Bobot_Bawaan !== null ? (float) $r->Bobot_Bawaan : null,
            'knockoutOperator' => $r->Knockout_Operator,
            'knockoutNilai' => $r->Knockout_Nilai,
            'knockoutPesan' => $r->Knockout_Pesan,
            'wajib' => ($r->Flag_Wajib_Bawaan ?? 'Y') === 'Y',
            'catatan' => ($r->Flag_Catatan_Bawaan ?? 'T') === 'Y',
            'sistem' => ($r->Flag_Sistem ?? 'T') === 'Y',
            'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
            'urutan' => (int) $r->Urutan,
            // ── METADATA REKRUTMEN ─────────────────────────────────────────
            // Dibaca dengan `??` karena kolomnya datang di skrip berikutnya:
            // lingkungan yang belum menjalankannya tetap membaca bank seperti
            // biasa, hanya tanpa metadata.
            'subKelompok' => $r->Sub_Kelompok ?? null,
            'jenis' => $r->Jenis ?? null,
            'prioritas' => $r->Prioritas ?? null,
            'durasiDetik' => isset($r->Durasi_Detik) && $r->Durasi_Detik !== null ? (int) $r->Durasi_Detik : null,
            'jawabanDiharapkan' => $r->Jawaban_Diharapkan ?? null,
            'redFlag' => $r->Red_Flag ?? null,
            'pertanyaanLanjutan' => $r->Pertanyaan_Lanjutan ?? null,
            'kriteriaNilai' => Skrining::jsonArray($r->Kriteria_Nilai ?? null),
            'statusTinjau' => $r->Status_Tinjau ?? null,
            'pemilik' => $r->Pemilik ?? null,
            'tag' => [],
            'diperbaruiOleh' => $r->Updated_By ?: $r->Created_By,
            'diperbaruiPada' => (string) ($r->Updated_At ?: $r->Created_At),
        ];
    }

    /**
     * Baris bank → kolom Master_Skrining_Pertanyaan.
     *
     * Inilah PENYALINAN itu. Bawaan bank (bobot, knockout, wajib) ikut terbawa
     * sebagai nilai awal; sesudah ini templatelah yang memilikinya, dan
     * merombaknya tidak menyentuh bank.
     *
     * `Flag_Ubahan` mulai dari 'T': belum ada yang dirombak, jadi baris ini
     * masih boleh ikut disegarkan bila banknya diperbaiki.
     */
    public static function keSalinan(object $bank, int $versiId, int $urutan): array
    {
        $beropsi = in_array($bank->Tipe, Skrining::TIPE_BEROPSI, true);
        $berskala = in_array($bank->Tipe, Skrining::TIPE_BERSKALA, true);
        $berskor = in_array($bank->Tipe, Skrining::TIPE_BERSKOR, true);
        $adaKnockout = $bank->Knockout_Operator && $bank->Knockout_Nilai !== null;

        return [
            'Master_Skrining_Versi_Id' => $versiId,
            'Bank_Id' => (int) $bank->Id_Master_Pertanyaan,
            'Bank_Kode' => $bank->Kode,
            'Flag_Ubahan' => 'T',
            'Seksi' => $bank->Kelompok,
            'Urutan' => $urutan,
            'Kode' => $bank->Kode,
            'Tipe' => $bank->Tipe,
            'Label' => $bank->Label,
            'Opsi' => $beropsi ? $bank->Opsi : null,
            'Skala_Min' => $berskala ? ($bank->Skala_Min ?? 1) : null,
            'Skala_Max' => $berskala ? ($bank->Skala_Max ?? 5) : null,
            'Label_Min' => $berskala ? $bank->Label_Min : null,
            'Label_Max' => $berskala ? $bank->Label_Max : null,
            'Flag_Wajib' => $bank->Flag_Wajib_Bawaan ?? 'Y',
            // Bobot hanya ikut untuk tipe yang memang bisa dinilai. Bank boleh
            // menyimpan bobot pada tipe apa pun tanpa merusak apa-apa; yang
            // tidak boleh adalah bobot itu sampai ke template pada pertanyaan
            // esai, karena di sanalah ia ditolak validasi dengan pesan yang
            // terasa datang entah dari mana.
            'Bobot' => $berskor ? $bank->Bobot_Bawaan : null,
            'Flag_Knockout' => $adaKnockout ? 'Y' : 'T',
            'Knockout_Operator' => $adaKnockout ? $bank->Knockout_Operator : null,
            'Knockout_Nilai' => $adaKnockout ? $bank->Knockout_Nilai : null,
            'Knockout_Pesan' => $adaKnockout ? $bank->Knockout_Pesan : null,
            'Flag_Catatan' => $bank->Flag_Catatan_Bawaan ?? 'T',
            'Tampil_Jika' => null,
            // Petunjuk penilaian ikut tersalin ke template: rekruter yang
            // mengisi kuesioner di worklist membaca bantuan dari SANA, bukan
            // dari bank. Tanpa ini, seluruh panduan yang disusun di sini
            // berhenti di halaman master dan tidak pernah sampai ke orang
            // yang benar-benar memakainya.
            'Bantuan' => trim(implode(' ', array_filter([
                $bank->Bantuan ?? null,
                ($bank->Jawaban_Diharapkan ?? null) ? 'Diharapkan: '.$bank->Jawaban_Diharapkan : null,
                ($bank->Red_Flag ?? null) ? 'Perhatikan: '.$bank->Red_Flag : null,
                ($bank->Pertanyaan_Lanjutan ?? null) ? 'Lanjutan: '.$bank->Pertanyaan_Lanjutan : null,
            ]))) ?: null,
        ];
    }

    /**
     * Salinan ini masih sama dengan banknya?
     *
     * Dipakai menandai `Flag_Ubahan` saat draf disimpan. Yang dibandingkan
     * hanya hal yang BERASAL dari bank — urutan dan seksi sengaja tidak ikut,
     * karena memindahkan pertanyaan ke kelompok lain di dalam satu template
     * bukan "merombak isinya", dan menandainya sebagai ubahan akan membuatnya
     * berhenti ikut disegarkan tanpa alasan yang masuk akal bagi penyuntingnya.
     */
    public static function samaDenganBank(array $salinan, object $bank): bool
    {
        $normalOpsi = fn ($x) => json_encode(Skrining::opsiArray($x ?: null));

        return trim((string) $salinan['Label']) === trim((string) $bank->Label)
            && (string) $salinan['Tipe'] === (string) $bank->Tipe
            && (string) ($salinan['Bantuan'] ?? '') === (string) ($bank->Bantuan ?? '')
            && $normalOpsi($salinan['Opsi'] ?? null) === $normalOpsi($bank->Opsi ?? null)
            && (string) ($salinan['Skala_Min'] ?? '') === (string) ($bank->Skala_Min ?? '')
            && (string) ($salinan['Skala_Max'] ?? '') === (string) ($bank->Skala_Max ?? '')
            && (float) ($salinan['Bobot'] ?? 0) === (float) ($bank->Bobot_Bawaan ?? 0)
            && (string) ($salinan['Knockout_Operator'] ?? '') === (string) ($bank->Knockout_Operator ?? '')
            && (string) ($salinan['Knockout_Nilai'] ?? '') === (string) ($bank->Knockout_Nilai ?? '');
    }

    /**
     * Template mana saja yang memakai pertanyaan bank ini.
     *
     * Dikirim bersama daftar bank karena inilah pertanyaan yang muncul tepat
     * sebelum seseorang menyunting atau menghapus sebuah pertanyaan: "kalau
     * saya ubah ini, apa yang ikut berubah?" — jawabannya nol, karena template
     * memegang salinannya sendiri, tapi orang tetap berhak tahu angkanya
     * sebelum menekan tombol.
     *
     * @return array<string,array{template: int, terbit: int}>
     */
    public static function pemakaian(): array
    {
        if (! self::siap()) {
            return [];
        }

        $rows = DB::table('N_WEB_CAREERS_Master_Skrining_Pertanyaan as p')
            ->join('N_WEB_CAREERS_Master_Skrining_Versi as v', 'v.Id_Master_Skrining_Versi', '=', 'p.Master_Skrining_Versi_Id')
            ->whereNotNull('p.Bank_Kode')
            ->groupBy('p.Bank_Kode')
            ->get([
                'p.Bank_Kode as kode',
                DB::raw('COUNT(DISTINCT v.Master_Skrining_Id) as template'),
                DB::raw("SUM(CASE WHEN v.Status = 'PUBLISHED' THEN 1 ELSE 0 END) as terbit"),
                DB::raw("SUM(CASE WHEN p.Flag_Ubahan = 'Y' THEN 1 ELSE 0 END) as ubahan"),
            ]);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->kode] = [
                'template' => (int) $r->template,
                'terbit' => (int) $r->terbit,
                'ubahan' => (int) $r->ubahan,
            ];
        }

        return $out;
    }
}
