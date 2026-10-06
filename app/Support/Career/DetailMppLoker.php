<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * ISI MPP SEBUAH LOKER — dibaca dari halaman Pembukaan Program & Program Kegiatan.
 *
 * ══ KENAPA BUKAN MEMANGGIL ENDPOINT MASTER MPP SAJA ═════════════════════════
 *
 * Endpoint /api/v1/master-mpp/{no} sudah mengembalikan persis data ini, tapi ia
 * dijaga izin `masterMppPage,VIEW`. Rekruter yang memegang Pembukaan Program
 * belum tentu berhak membuka Master MPP — dan memang tidak perlu: yang ia
 * butuhkan bukan wewenang mengubah MPP, melainkan MEMBACA isi loker yang sedang
 * ia terbitkan. Memaksanya lewat izin Master MPP berarti salah satu dari dua
 * hal buruk: rekruter diberi akses ke seluruh modul MPP hanya agar satu panel
 * bisa terisi, atau panelnya diam-diam kosong tanpa penjelasan.
 *
 * Jadi pembacaannya dipisah ke sini, lalu dipanggil dua controller dengan izin
 * MEREKA SENDIRI (pembukaanPage / programPage). Hanya-baca, tanpa satu pun
 * jalan untuk menulis.
 *
 * Kolom yang diambil sengaja SAMA dengan yang ditampilkan halaman Master MPP,
 * supaya panel detail di dua halaman ini benar-benar "seperti tampilan di MPP"
 * dan bukan ringkasan yang berbeda isinya.
 */
class DetailMppLoker
{
    private const KODE_PERUSAHAAN = '001';

    private const TABEL_G = 'HRIS_Transaksi_GForm';

    private const TABEL_D = 'N_WEB_CAREERS_Detail_MPP';

    /**
     * Isi satu MPP berdasarkan nomor transaksinya.
     *
     * @return array<string, mixed>|null  null bila nomornya tidak dikenal
     */
    public static function untuk(?string $noTransaksi): ?array
    {
        $no = trim((string) $noTransaksi);

        // Nomor MPP dipakai langsung di WHERE; bentuknya dibatasi lebih dulu.
        if ($no === '' || ! preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
            return null;
        }

        $head = DB::table(self::TABEL_G . ' as g')
            ->join(self::TABEL_D . ' as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->leftJoin('HRIS_Divisi as dv', fn ($j) => $j
                ->on('dv.ID_Divisi', '=', 'g.Id_Divisi')
                ->where('dv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Sub_Divisi as sd', fn ($j) => $j
                ->on('sd.ID_Sub_Divisi', '=', 'g.Id_Sub_Divisi')
                ->where('sd.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Level as lv', fn ($j) => $j
                ->on('lv.ID_Level', '=', 'g.Id_Level')
                ->where('lv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Jabatan as jb', fn ($j) => $j
                ->on('jb.ID_Jabatan', '=', 'g.Id_Jabatan')
                ->where('jb.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('N_HRIS_Master_Lokasi as lok', 'lok.Kode_Lokasi', '=', 'g.Kode_Lokasi')
            ->leftJoin('Karyawan as k', fn ($j) => $j
                ->on('k.Kode_Karyawan', '=', 'g.User_Penganggung_Jawab')
                ->where('k.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Experience_Level as mx', 'mx.Id_Experience_Level', '=', 'd.Experience_Level')
            ->where('g.No_Transaksi', $no)
            ->select([
                'g.No_Transaksi as no_transaksi',
                'g.Status as status_raw',
                'g.Flag_Selesai as flag_selesai_raw',
                'g.Flag_MT as flag_mt_raw',
                DB::raw('CONVERT(varchar(10), g.Tanggal_Periode, 23) as tanggal_periode'),
                DB::raw('CONVERT(varchar(10), g.Tanggal, 23) as tanggal_dibuat'),
                'dv.Keterangan as divisi',
                'sd.Keterangan as sub_divisi',
                'lv.Keterangan as level',
                'jb.Keterangan as jabatan',
                'g.Jumlah_Rekruitmen as jumlah_rekruitmen',
                'lok.Nama_Lokasi as lokasi',
                'g.User_Penganggung_Jawab as kode_karyawan',
                'k.Nama as penanggung_jawab',
                'd.Id_Detail_MPP as id_detail_mpp',
                'd.Deskripsi as deskripsi',
                'me.Nama_Employment as employment_type',
                'mw.Nama_Workplace as workplace_type',
                'mx.Nama_Experience_Level as experience_level',
            ])
            ->first();

        if (! $head) {
            return null;
        }

        $id = $head->id_detail_mpp;

        $points = DB::table('N_WEB_CAREERS_Points_MPP')
            ->where('Id_Detail_MPP', $id)
            ->orderBy('Urutan')->orderBy('Id_Points_MPP')
            ->get(['Section', 'Content']);

        $skill = DB::table('N_WEB_CAREERS_Detail_Skill_MPP as sk')
            ->join('N_WEB_CAREERS_Master_Skill as ms', 'ms.Id_Skill', '=', 'sk.Id_Skill')
            ->leftJoin('N_WEB_CAREERS_Master_Skill_Kategori as msk', 'msk.Id_Master_Skill_Kategori', '=', 'ms.Id_Master_Skill_Kategori')
            ->where('sk.Id_Detail_MPP', $id)
            ->orderByRaw('ISNULL(msk.Urutan, 999999)')->orderBy('ms.Nama_Skill')
            ->get(['ms.Nama_Skill as nama', 'msk.Nama as kat_nama', 'msk.Warna as kat_warna', 'msk.Ikon as kat_ikon']);

        $benefit = DB::table('N_WEB_CAREERS_Detail_Benefit_MPP as bn')
            ->join('N_WEB_CAREERS_Master_Benefit as mb', 'mb.Id_Benefit', '=', 'bn.Id_Benefit')
            ->where('bn.Id_Detail_MPP', $id)
            ->orderBy('mb.Nama_Benefit')
            ->get(['mb.Nama_Benefit as nama']);

        return [
            'noTransaksi' => $head->no_transaksi,
            // Status MPP: 'Y' pada kolom Status berarti DIBATALKAN — bacaannya
            // dibalik di sini sekali, bukan di tiap layar yang memakainya.
            'status' => ($head->status_raw === 'Y') ? 'BATAL' : (($head->flag_selesai_raw === 'Y') ? 'SELESAI' : 'AKTIF'),
            'jenisProgram' => $head->flag_mt_raw === 'Y' ? 'MT' : 'REGULER',
            'tanggalPeriode' => $head->tanggal_periode,
            'tanggalDibuat' => $head->tanggal_dibuat,
            'divisi' => $head->divisi,
            'subDivisi' => $head->sub_divisi,
            'level' => $head->level,
            'jabatan' => $head->jabatan,
            'jumlahRekrutmen' => (int) ($head->jumlah_rekruitmen ?? 0),
            'lokasi' => $head->lokasi,
            'penanggungJawab' => $head->penanggung_jawab ?: $head->kode_karyawan,
            'deskripsi' => $head->deskripsi,
            'employmentType' => $head->employment_type,
            'workplaceType' => $head->workplace_type,
            'experienceLevel' => $head->experience_level,
            'tanggungJawab' => $points->where('Section', 'responsibility')->pluck('Content')->values()->all(),
            'persyaratan' => $points->where('Section', 'requirement')->pluck('Content')->values()->all(),
            'skill' => $skill->map(fn ($r) => [
                'nama' => $r->nama,
                'kategori' => $r->kat_nama,
                'warna' => $r->kat_warna,
                'ikon' => $r->kat_ikon,
            ])->values()->all(),
            'benefit' => $benefit->pluck('nama')->values()->all(),
        ];
    }
}
