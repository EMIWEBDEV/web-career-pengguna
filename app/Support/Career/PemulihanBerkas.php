<?php

namespace App\Support\Career;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PEMULIHAN BERKAS: menemukan berkas kandidat yang hilang, lalu
 * memulihkannya lewat tangan admin.
 *
 * ── APA YANG DIANGGAP "HILANG" ─────────────────────────────────────────────
 *
 * Persis yang oleh worklist dinyatakan "Belum diunggah": isian berkas yang
 * TERLIHAT di formulir kandidat, wajib atau sudah disebut namanya di jawaban,
 * tetapi tidak satu pun berkas terpasang padanya. Aturan tampil & wajibnya milik
 * BerkasFormulir (cermin aturan.js di browser); cara memasangkan berkas ke
 * isiannya meniru LamaranController::worklistBerkas(). Kalau dua hal itu
 * dihitung ulang dengan cara sendiri, panel ini dan worklist akan bertengkar
 * tentang kandidat yang sama.
 *
 * Hanya formulir yang sudah TERKIRIM yang dipindai. Formulir yang belum pernah
 * dikirim tidak punya pengisian untuk ditempeli berkas — keputusan user
 * (28 Sep 2026): pemulihan hanya untuk kiriman yang sudah ada.
 *
 * ── JANGKAUAN ──────────────────────────────────────────────────────────────
 *
 * Kategori program & lingkup PIC lowongan disaring dengan penyaring yang SAMA
 * dengan worklist (AksesService, halaman ini sendiri). Setiap endpoint — daftar,
 * isian, unggah, pratinjau — lewat kueriLamaran(), jadi admin berlingkup MT
 * tidak bisa memulihkan berkas kandidat Rekrutmen hanya dengan menebak id.
 *
 * ── HAK ────────────────────────────────────────────────────────────────────
 *
 *   VIEW           melihat daftar rekomendasi
 *   CREATE         mengunggah berkas untuk isian yang DIREKOMENDASIKAN sistem
 *   TAMBAH_MANUAL  mengunggah untuk isian yang tidak ditangkap sistem
 *   TIMPA          menambahkan berkas walau isiannya SUDAH berberkas
 *
 * Dua yang terakhir aksi khusus di master N_WEB_CAREERS_Aksi — diberikan lewat
 * Manajemen Hak Akses, bawaannya ke akun SUPERADMIN (skrip di docs/28-09-2026).
 */
final class PemulihanBerkas
{
    public const PAGE = 'pemulihanBerkasPage';

    public const AKSI_MANUAL = 'TAMBAH_MANUAL';

    public const AKSI_TIMPA = 'TIMPA';

    /** Batas ukuran (MB) bila isiannya tidak ditemukan di skema. */
    public const MAKS_MB_BAWAAN = 5;

    /**
     * Status lamaran yang dipindai secara bawaan: yang masih berproses DAN yang
     * sudah diterima — berkas kandidat yang diterima justru paling dibutuhkan
     * lengkap (kontrak, onboarding).
     */
    public const STATUS_AKTIF = ['BERJALAN', 'LULUS'];

    /**
     * Hak pengguna saat ini di halaman ini. Dikirim ke layar untuk menampilkan
     * tombolnya; server tetap memeriksa ulang di setiap permintaan.
     */
    public static function hak(): array
    {
        return [
            'unggah' => AksesService::boleh(self::PAGE, 'CREATE'),
            'manual' => AksesService::boleh(self::PAGE, self::AKSI_MANUAL),
            'timpa' => AksesService::boleh(self::PAGE, self::AKSI_TIMPA),
        ];
    }

    /** Lamaran dalam jangkauan akun ini — kategori & lingkup PIC seperti worklist. */
    public static function kueriLamaran()
    {
        $q = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users');

        AksesService::saringKategori($q, self::PAGE, 'p.Kategori');

        // NULL = lingkup SEMUA. Larik KOSONG = dibatasi, dan tak ada yang cocok.
        $pic = AksesService::picDiizinkan(self::PAGE);
        if ($pic !== null) {
            $q->whereIn('x.Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']);
        }

        return $q;
    }

    public static function terjangkau(int $lamaranId): bool
    {
        return self::kueriLamaran()->where('l.Id_Lamaran', $lamaranId)->exists();
    }

    /**
     * Seluruh lamaran dalam jangkauan (status disaring) + ringkasannya.
     *
     * Dibaca sekali lalu dikelompokkan di PHP — hitungan per program, per
     * lowongan, dan daftar kandidat semuanya berasal dari pindaian yang SAMA,
     * jadi angka di pemilih tidak pernah berbeda dengan isi daftarnya.
     */
    public static function lamaran(string $status): Collection
    {
        $versi = VersiAlur::siap();

        return self::kueriLamaran()
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', function ($j) {
                $j->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')->on('t.Urutan', '=', 'l.Urutan_Tahap');
            })
            ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 'l.Master_Alur_Id')
            ->when($status !== 'SEMUA', fn ($w) => $w->whereIn('l.Status', self::STATUS_AKTIF))
            ->orderByDesc('l.Id_Lamaran')
            ->select(array_merge([
                'l.Id_Lamaran', 'l.Kode', 'l.Status', 'l.Program_Id', 'l.Program_Posisi_Id', 'l.Master_Alur_Id',
                'l.Urutan_Tahap', 'l.Total_Tahap',
                'p.Nama as ProgramNama', 'p.Kategori', 'p.Warna', 'p.Status as ProgramStatus',
                'x.Posisi', 'x.Departemen', 'x.Lokasi', 'x.Mpp_Ref',
                'u.Nama as KandidatNama', 'u.Email',
                't.Label as TahapLabel',
                'a.Nama as AlurNama',
            ], $versi ? ['a.Versi as AlurVersi'] : []))
            ->get()
            ->unique('Id_Lamaran')
            ->values();
    }

    /**
     * Berkas HILANG per lamaran, dari seluruh pengisian yang terkirim.
     *
     * @return array<int, list<array>> kunci = Id_Lamaran
     */
    public static function temukanHilang(array $lamaranIds): array
    {
        $out = [];
        foreach (self::pengisian($lamaranIds) as $ctx) {
            foreach ($ctx['slot'] as $s) {
                if (! BerkasFormulir::diharapkan($s) || self::berkasSlot($ctx['berkas'], $s)) {
                    continue;
                }
                $out[$ctx['lamaranId']][] = self::butir($ctx, $s);
            }
        }

        return $out;
    }

    /**
     * Seluruh formulir TERKIRIM milik satu lamaran, lengkap dengan SEMUA slot
     * berkasnya dan berkas yang sudah terpasang — bahan "tambah manual".
     */
    public static function isianLamaran(int $lamaranId): array
    {
        $out = [];
        foreach (self::pengisian([$lamaranId]) as $ctx) {
            $isian = [];
            foreach ($ctx['slot'] as $s) {
                $ada = self::berkasSlot($ctx['berkas'], $s);
                $isian[] = self::butir($ctx, $s) + [
                    'terlihat' => $s['terlihat'],
                    'direkomendasikan' => BerkasFormulir::diharapkan($s) && ! $ada,
                    'berkas' => array_map([self::class, 'berkasPublik'], $ada),
                ];
            }

            $out[] = [
                'pengisianId' => $ctx['hash'],
                'formulir' => $ctx['formulir'],
                'sumber' => $ctx['sumber'],
                'tahap' => $ctx['tahap'],
                'waktuKirim' => $ctx['waktuKirim'],
                'isian' => $isian,
            ];
        }

        return $out;
    }

    /** Satu pengisian dalam jangkauan akun ini (null bila tidak terjangkau / belum terkirim). */
    public static function konteksPengisian(int $pengisianId): ?array
    {
        $lamaranId = (int) DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Id_Formulir_Pengisian', $pengisianId)
            ->value('Lamaran_Id');

        if (! $lamaranId || ! self::terjangkau($lamaranId)) {
            return null;
        }

        foreach (self::pengisian([$lamaranId]) as $ctx) {
            if ($ctx['id'] === $pengisianId) {
                return $ctx;
            }
        }

        return null;
    }

    /** Slot sebuah triplet di satu pengisian, atau null bila isiannya tidak ada di skemanya. */
    public static function cariSlot(array $ctx, ?string $bagian, ?int $baris, string $field): ?array
    {
        foreach ($ctx['slot'] as $s) {
            if ($s['bagian'] === $bagian && $s['baris'] === $baris && $s['field'] === $field) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Berkas yang oleh worklist dipasangkan ke satu slot.
     *
     * Aturannya meniru worklistBerkas()/berkasSeBaris(): isian biasa mendapat
     * SEMUA berkas ber-Field_Key sama; baris bagian berulang mendapat berkas
     * ber-triplet sama, dan bila tak ada, berkas lama yang NAMA ASLI-nya sama
     * dengan nilai selnya (baris lama tersimpan tanpa posisi baris).
     */
    public static function berkasSlot(array $berkas, array $s): array
    {
        $sama = array_values(array_filter($berkas, fn ($b) => $b->Field_Key === $s['field']));
        if ($s['bagian'] === null) {
            return $sama;
        }

        $tepat = array_values(array_filter($sama, fn ($b) => $b->Bagian_Key === $s['bagian']
            && $b->Baris_Index !== null && (int) $b->Baris_Index === $s['baris']));
        if ($tepat || $s['nama'] === '') {
            return $tepat;
        }

        $utuh = array_filter($sama, fn ($b) => $b->Nama_Asli === $s['nama']);
        $nama = $utuh ? [$s['nama']] : array_filter(array_map('trim', explode(',', $s['nama'])), fn ($n) => $n !== '');

        return array_values(array_filter($sama, fn ($b) => in_array($b->Nama_Asli, $nama, true)));
    }

    /**
     * Berkas ini diunggah ADMIN atas nama kandidat? Pemilik (Id_Users) selalu
     * kandidatnya; pengunggahnya tercatat di Created_By_Id.
     */
    public static function olehAdmin(object|array $b): bool
    {
        $b = (object) $b;
        $oleh = $b->Created_By_Id ?? null;

        return $oleh !== null && (int) $oleh !== (int) ($b->Id_Users ?? 0);
    }

    /** Berkas yang sudah ada, dalam bentuk yang aman dikirim ke layar. */
    public static function berkasPublik(object $b): array
    {
        return [
            'id' => Hashids::encode($b->Id_Formulir_Berkas),
            'nama' => $b->Nama_Asli,
            'ukuran' => (int) $b->Ukuran_Byte,
            'waktu' => (string) ($b->Waktu_Unggah ?: $b->Created_At ?: ''),
            'olehAdmin' => self::olehAdmin($b),
            'oleh' => $b->Created_By,
            'url' => route('career.api.pemulihan-berkas.berkas', ['id' => Hashids::encode($b->Id_Formulir_Berkas)]),
        ];
    }

    /** Berkas milik satu pengisian (dibaca ulang, mis. di bawah kunci). */
    public static function berkasPengisian(int $pengisianId): array
    {
        return self::bacaBerkas([$pengisianId])[$pengisianId] ?? [];
    }

    // ══ DALAMAN ═════════════════════════════════════════════════════════════

    /** Butir yang dikirim ke layar untuk satu slot. */
    private static function butir(array $ctx, array $s): array
    {
        $aturan = BerkasFormulir::aturanUnggah($ctx['skema'], $s['bagian'], $s['field'], self::MAKS_MB_BAWAAN);

        return [
            'kunci' => implode('|', [$ctx['hash'], $s['bagian'] ?? '', $s['baris'] ?? '', $s['field']]),
            'pengisianId' => $ctx['hash'],
            'formulir' => $ctx['formulir'],
            'sumber' => $ctx['sumber'],
            'tahap' => $ctx['tahap'],
            'bagian' => $s['bagian'],
            'baris' => $s['baris'],
            'field' => $s['field'],
            'label' => $s['label'],
            'judulBagian' => $s['judulBagian'],
            'labelBaris' => $s['labelBaris'],
            'namaTercatat' => $s['nama'],
            'wajib' => $s['wajib'],
            'jenis' => $s['nama'] !== '' ? 'NAMA_TANPA_BERKAS' : 'WAJIB_KOSONG',
            'aturan' => [
                'format' => array_map('strtoupper', $aturan['ekstensi']),
                'accept' => BerkasFormulir::acceptDari($aturan['ekstensi']),
                'maksMb' => $aturan['maksMb'],
            ],
        ];
    }

    /**
     * Pengisian TERKIRIM milik lamaran-lamaran ini, masing-masing dengan skema
     * bekunya, jawaban, berkas, dan slot berkasnya.
     *
     * @return list<array{id:int, hash:string, lamaranId:int, idUsers:int, kandidat:?string, sumber:?string, formulir:string, tahap:?string, waktuKirim:string, skema:?array, jawaban:array, berkas:array, slot:array}>
     */
    private static function pengisian(array $lamaranIds): array
    {
        $lamaranIds = array_values(array_unique(array_map('intval', $lamaranIds)));
        if (! $lamaranIds) {
            return [];
        }

        $rows = collect();
        // SQL Server menolak lebih dari 2.100 parameter dalam satu kueri.
        foreach (array_chunk($lamaranIds, 1000) as $potong) {
            $rows = $rows->concat(DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
                ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Id_Master_Formulir', '=', 'fp.Master_Formulir_Id')
                ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'fp.Id_Users')
                ->whereIn('fp.Lamaran_Id', $potong)
                ->where('fp.Status', 'TERKIRIM')
                ->orderBy('fp.Id_Formulir_Pengisian')
                ->get([
                    'fp.Id_Formulir_Pengisian', 'fp.Lamaran_Id', 'fp.Id_Users', 'fp.Sumber',
                    'fp.Master_Formulir_Versi_Id', 'fp.Formulir_Versi', 'fp.Jawaban_Json', 'fp.Waktu_Kirim',
                    'f.Kode as FormulirKode', 'f.Nama as FormulirNama',
                    't.Label as TahapLabel', 'u.Nama as KandidatNama',
                ]));
        }

        $berkas = self::bacaBerkas($rows->pluck('Id_Formulir_Pengisian')->all());
        $cache = [];
        $out = [];

        foreach ($rows as $fp) {
            $skema = self::skema($fp, $cache);
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true);
            $jawaban = is_array($jawaban) ? $jawaban : [];
            $id = (int) $fp->Id_Formulir_Pengisian;

            $out[] = [
                'id' => $id,
                'hash' => Hashids::encode($id),
                'lamaranId' => (int) $fp->Lamaran_Id,
                'idUsers' => (int) $fp->Id_Users,
                'kandidat' => $fp->KandidatNama,
                'sumber' => $fp->Sumber,
                'formulir' => $fp->FormulirNama ?: ($fp->FormulirKode ?: 'Formulir'),
                'tahap' => $fp->TahapLabel,
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?? ''),
                'skema' => $skema,
                'jawaban' => $jawaban,
                'berkas' => $berkas[$id] ?? [],
                'slot' => BerkasFormulir::slot($skema, $jawaban),
            ];
        }

        return $out;
    }

    /** @return array<int, list<object>> berkas per Formulir_Pengisian_Id */
    private static function bacaBerkas(array $pengisianIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($pengisianIds)), 1000) as $potong) {
            $baris = DB::table('N_WEB_CAREERS_Formulir_Berkas')
                ->whereIn('Formulir_Pengisian_Id', $potong)
                ->orderBy('Urutan')
                ->orderBy('Id_Formulir_Berkas')
                ->get([
                    'Id_Formulir_Berkas', 'Formulir_Pengisian_Id', 'Id_Users', 'Field_Key', 'Bagian_Key', 'Baris_Index',
                    'Nama_Asli', 'Ukuran_Byte', 'Mime', 'Ekstensi', 'Waktu_Unggah', 'Created_At', 'Created_By', 'Created_By_Id',
                ]);
            foreach ($baris as $b) {
                $out[(int) $b->Formulir_Pengisian_Id][] = $b;
            }
        }

        return $out;
    }

    /**
     * Skema yang dipakai kandidat SAAT MENGIRIM: versi yang dibekukan di
     * pengisiannya. Snapshot hanya dibaca bila versinya tak tercatat — kolomnya
     * besar, dan membacanya untuk ratusan pengisian sekaligus tidak perlu.
     */
    private static function skema(object $fp, array &$cache): ?array
    {
        $vid = (int) ($fp->Master_Formulir_Versi_Id ?? 0);
        if ($vid) {
            if (! array_key_exists("v{$vid}", $cache)) {
                $json = DB::table('N_WEB_CAREERS_Master_Formulir_Versi')->where('Id_Master_Formulir_Versi', $vid)->value('Schema_Json');
                $cache["v{$vid}"] = $json ? (json_decode($json, true) ?: null) : null;
            }
            if ($cache["v{$vid}"]) {
                return $cache["v{$vid}"];
            }
        }

        $snap = FormulirSchema::punyaKolomPengisianSnapshot()
            ? DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Id_Formulir_Pengisian', $fp->Id_Formulir_Pengisian)->value('Schema_Snapshot_Json')
            : null;
        if ($snap && is_array($s = json_decode($snap, true))) {
            return $s;
        }

        return $fp->FormulirKode
            ? (FormulirSchema::byKodeDanVersi($fp->FormulirKode, $fp->Formulir_Versi !== null ? (int) $fp->Formulir_Versi : null)['schema'] ?? null)
            : null;
    }

    /** Kunci lowongan (MPP) — definisi yang SAMA dengan papan job vacancy worklist. */
    public static function kunciLoker(object $l): string
    {
        return LamaranController::kunciLoker((object) [
            'Mpp_Ref' => $l->Mpp_Ref ?? null,
            'Id_Program_Posisi' => $l->Program_Posisi_Id ?? ($l->Id_Program_Posisi ?? 0),
        ]);
    }
}
