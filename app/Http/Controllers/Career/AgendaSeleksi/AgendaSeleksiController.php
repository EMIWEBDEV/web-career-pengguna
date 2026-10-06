<?php

namespace App\Http\Controllers\Career\AgendaSeleksi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\JejakJadwal;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\UndanganJadwal;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — AGENDA SELEKSI: konfirmasi kehadiran dari sisi tim.
 *
 * ══ DUA TAB ══
 *
 *   Perlu Tindakan  permintaan jadwal lain (urut tenggat), belum menjawab
 *                   (daftar PENGINGAT MANUAL), menyatakan mundur, surel gagal,
 *                   jadwal yang sudah dimulai tetapi kehadirannya belum
 *                   dicatat (termasuk yang tidak menjawab — batas konfirmasi
 *                   = waktu mulai), dan jadwal DITUNDA yang penggantinya
 *                   belum terbit (urut perkiraan tanggal).
 *   Agenda          sesi per hari — siapa akan hadir, siapa belum menjawab.
 *
 * ══ TANPA JOIN KE TABEL LAMARAN YANG SIBUK ══
 *
 * Seluruh daftar dibaca dari snapshot N_WEB_CAREERS_CRM_Konfirmasi_Email (satu
 * baris per undangan: nama, email, aktivitas, jam, batas, status, hitungan
 * surel). Lingkup akun disaring lewat SUBKUERI ke master Program/Posisi yang
 * kecil; rincian lamaran hanya dicari per kunci primer untuk baris yang tampil.
 *
 * ══ HAK ══
 *
 * Halaman & daftar: agendaSeleksiPage VIEW. Rincian & aksi memakai hak
 * WORKLIST (pelamarPage VIEW/EDIT) — kandidatnya sama, tombolnya juga ada di
 * drawer worklist, dan satu tindakan tidak boleh punya dua gerbang berbeda.
 * Lingkup (kategori program + PIC loker) mengikuti worklist.
 */
class AgendaSeleksiController extends Controller
{
    private const URL = '/karir/agenda-seleksi';

    /** Lingkup & hak aksi: kandidat yang sama dengan worklist. */
    private const PAGE_WORKLIST = 'pelamarPage';

    /** Batas baris per kelompok — daftar kerja, bukan laporan. */
    private const BARIS_MAKS = 300;

    public function index()
    {
        $master = KonfirmasiJadwal::siap() ? KonfirmasiJadwal::master() : collect();

        return Inertia::render('Career/admin/agenda-seleksi/AgendaSeleksi', CareerShell::props(self::URL, 'Agenda Seleksi', [
            'siap' => KonfirmasiJadwal::siap(),
            'hak' => ['ubah' => AksesService::boleh(self::PAGE_WORKLIST, 'EDIT')],
            'master' => $master->map(fn ($m) => [
                'kode' => $m->Kode,
                'label' => $m->Label_Tim,
                'warna' => $m->Warna,
                'ikon' => $m->Ikon,
                'bukaPermintaan' => ($m->Flag_Buka_Permintaan ?? 'T') === 'Y',
                'pilihanKandidat' => ! empty($m->Label_Tombol),
            ])->values()->all(),
            'alasan' => KonfirmasiJadwal::siap() ? [
                'JADWAL_LAIN' => self::bentukAlasan('JADWAL_LAIN'),
                'MUNDUR' => self::bentukAlasan('MUNDUR'),
                'TUNDA' => self::bentukAlasan('TUNDA'),
            ] : ['JADWAL_LAIN' => [], 'MUNDUR' => [], 'TUNDA' => []],
            'aturan' => [
                'jedaKirimMenit' => (int) config('konfirmasi.jeda_kirim_menit', 10),
                'jarakSopanJam' => (int) config('konfirmasi.jarak_sopan_jam', 16),
                'pengingatMaks' => self::pengingatMaks(),
                'bagianHari' => array_values(config('konfirmasi.bagian_hari', [])),
                'kanalTim' => KonfirmasiJadwal::KANAL_TIM,
            ],
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════
    //  DAFTAR
    // ════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/karir/agenda-seleksi/perlu-tindakan */
    public function perluTindakan(Request $request)
    {
        if (! KonfirmasiJadwal::siap()) {
            return ResponseHelper::success(['siap' => false], 'Fitur konfirmasi belum aktif.');
        }

        $now = now();
        $cari = trim((string) $request->query('q', ''));
        $programId = Hashids::decode((string) $request->query('program', ''))[0] ?? null;

        $dasar = function () use ($cari, $programId) {
            $q = DB::table(KonfirmasiJadwal::T_CRM.' as c');
            self::saringLingkup($q, 'c');
            if ($programId) {
                $q->where('c.Program_Id', $programId);
            }
            if ($cari !== '') {
                $q->where(fn ($w) => $w->where('c.Nama', 'like', "%{$cari}%")
                    ->orWhere('c.Email', 'like', "%{$cari}%")
                    ->orWhere('c.Aktivitas', 'like', "%{$cari}%"));
            }

            return $q;
        };

        // 1) PERMINTAAN JADWAL LAIN — urut tenggat tim (SLA) terdekat.
        $minta = $dasar()
            ->join(KonfirmasiJadwal::T_MINTA.' as m', function ($j) {
                $j->on('m.Lamaran_Tahap_Tes_Id', '=', 'c.Lamaran_Tahap_Tes_Id')->on('m.Versi', '=', 'c.Versi');
            })
            ->where('m.Status', KonfirmasiJadwal::P_TERBUKA)
            ->orderBy('m.Sla_Batas')
            ->limit(self::BARIS_MAKS)
            ->get(['c.*', 'm.Id_Jadwal_Permintaan', 'm.Status as MintaStatus', 'm.Versi as MintaVersi', 'm.Alasan_Kode',
                'm.Catatan_Kandidat', 'm.Usulan_Json', 'm.Kanal', 'm.Diminta_At', 'm.Sla_Batas', 'm.Diproses_At',
                'm.Diproses_By', 'm.Catatan_Tim']);

        // 2) BELUM MENJAWAB, jadwalnya belum dimulai — daftar PENGINGAT MANUAL.
        //    Batas konfirmasi = waktu mulai, jadi "masih dalam batas" sama
        //    dengan "jadwal belum dimulai".
        $belum = $dasar()
            ->where('c.Selesai', 'T')
            ->where('c.Status_Konfirmasi', KonfirmasiJadwal::MENUNGGU)
            ->where('c.Batas_Konfirmasi', '>', $now)
            ->where('c.Jadwal_Mulai', '>', $now)
            ->orderBy('c.Batas_Konfirmasi')
            ->limit(self::BARIS_MAKS)
            ->get();

        // 3) MENYATAKAN MUNDUR 30 hari terakhir — disaring di bawah: yang
        //    lamarannya sudah ditutup tim tidak perlu ditindaklanjuti lagi.
        $mundur = $dasar()
            ->where('c.Status_Konfirmasi', KonfirmasiJadwal::MUNDUR)
            ->where('c.Selesai_Alasan', 'MUNDUR')
            ->where('c.Dijawab_At', '>=', $now->copy()->subDays(30))
            ->orderByDesc('c.Dijawab_At')
            ->limit(self::BARIS_MAKS)
            ->get();

        // 4) SUREL GAGAL TERKIRIM pada undangan yang masih berjalan.
        $gagal = $dasar()
            ->where('c.Selesai', 'T')
            ->where('c.Jumlah_Gagal', '>', 0)
            ->orderByDesc('c.Updated_At')
            ->limit(self::BARIS_MAKS)
            ->get();

        // 5) JADWAL SUDAH DIMULAI, kehadirannya belum dicatat (7 hari terakhir)
        //    — termasuk yang TIDAK MENJAWAB sampai jadwal dimulai.
        $hadir = $dasar()
            ->where('c.Selesai', 'T')
            ->where('c.Jadwal_Mulai', '<=', $now)
            ->where('c.Jadwal_Mulai', '>=', $now->copy()->subDays(7)->startOfDay())
            ->orderByDesc('c.Jadwal_Mulai')
            ->limit(self::BARIS_MAKS)
            ->get();

        $semuaId = collect([$minta, $belum, $mundur, $gagal, $hadir])
            ->flatten(1)->pluck('Lamaran_Tahap_Tes_Id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $info = self::infoAktivitas($semuaId);

        $mundur = $mundur->filter(fn ($c) => ($info[(int) $c->Lamaran_Tahap_Tes_Id]->StatusLamaran ?? '') === 'BERJALAN')->values();
        $nama = self::namaProgramPosisi(collect([$minta, $belum, $mundur, $gagal, $hadir])->flatten(1));

        $baris = fn (Collection $rows) => $rows->map(fn ($c) => self::bentukBaris($c, $info, $nama))->values()->all();

        // 6) DITUNDA — jadwal dikosongkan tim, penggantinya belum terbit.
        //    Tidak ada di snapshot CRM (undangannya sudah ditutup).
        $ditunda = self::daftarDitunda($cari, $programId ? (int) $programId : null);

        return ResponseHelper::success([
            'siap' => true,
            'permintaan' => $minta->map(fn ($c) => self::bentukBaris($c, $info, $nama) + [
                'permintaan' => KonfirmasiJadwal::bentukPermintaan((object) [
                    'Id_Jadwal_Permintaan' => $c->Id_Jadwal_Permintaan,
                    'Status' => $c->MintaStatus,
                    'Versi' => $c->MintaVersi,
                    'Alasan_Kode' => $c->Alasan_Kode,
                    'Catatan_Kandidat' => $c->Catatan_Kandidat,
                    'Usulan_Json' => $c->Usulan_Json,
                    'Kanal' => $c->Kanal,
                    'Diminta_At' => $c->Diminta_At,
                    'Sla_Batas' => $c->Sla_Batas,
                    'Diproses_By' => $c->Diproses_By,
                    'Diproses_At' => $c->Diproses_At,
                    'Catatan_Tim' => $c->Catatan_Tim,
                ]),
            ])->values()->all(),
            'belumJawab' => $baris($belum),
            'mundur' => $baris($mundur),
            'gagalKirim' => $baris($gagal),
            'belumHadir' => $baris($hadir),
            'ditunda' => $ditunda,
            'jam' => $now->format('Y-m-d H:i:s'),
        ], 'Perlu tindakan');
    }

    /**
     * Aktivitas yang MASIH ditunda (dalam lingkup akun). Calonnya dari baris
     * jejak TUNDA 120 hari terakhir (indeks waktu), lalu diperiksa per kunci
     * primer — tanpa memindai tabel aktivitas. Urut perkiraan jadwal
     * pengganti terdekat; yang belum punya perkiraan di belakang.
     */
    private static function daftarDitunda(string $cari, ?int $programId): array
    {
        $ids = DB::table(JejakJadwal::TABEL)
            ->where('Aksi', KonfirmasiJadwal::A_TUNDA)
            ->where('Created_At', '>=', now()->subDays(120))
            ->distinct()
            ->pluck('Lamaran_Tahap_Tes_Id')->map(fn ($v) => (int) $v)->all();
        if (! $ids) {
            return [];
        }

        $rows = collect();
        foreach (array_chunk($ids, 500) as $potong) {
            $q = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->whereIn('t.Id_Lamaran_Tahap_Tes', $potong)
                ->where('t.Konfirmasi_Status', KonfirmasiJadwal::DITUNDA)
                ->whereNull('t.Jadwal_Mulai')
                ->where('l.Status', 'BERJALAN')
                ->where('h.Status', 'BERJALAN')
                ->where(fn ($w) => $w->whereNull('t.Flag_Selesai')->orWhere('t.Flag_Selesai', '<>', 'Y'));
            if ($izin = AksesService::kategoriDiizinkan(self::PAGE_WORKLIST)) {
                $q->whereIn('p.Kategori', $izin);
            }
            $pic = AksesService::picDiizinkan(self::PAGE_WORKLIST);
            if ($pic !== null) {
                $q->whereIn('x.Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']);
            }
            if ($programId) {
                $q->where('l.Program_Id', $programId);
            }
            if ($cari !== '') {
                $q->where(fn ($w) => $w->where('u.Nama', 'like', "%{$cari}%")
                    ->orWhere('u.Email', 'like', "%{$cari}%")
                    ->orWhere('t.Label', 'like', "%{$cari}%"));
            }
            $rows = $rows->concat($q->get(['t.Id_Lamaran_Tahap_Tes', 't.Label', 'h.Label as TahapLabel', 'l.Kode as LamaranKode',
                'u.Nama', 'u.Email', 'p.Nama as ProgramNama', 'x.Posisi']));
        }

        $info = KonfirmasiJadwal::infoTundaBanyak($rows->pluck('Id_Lamaran_Tahap_Tes')->all());
        $def = KonfirmasiJadwal::master()->get(KonfirmasiJadwal::DITUNDA);

        return $rows->map(function ($r) use ($info, $def) {
            $i = $info[(int) $r->Id_Lamaran_Tahap_Tes] ?? null;

            return [
                'id' => Hashids::encode((int) $r->Id_Lamaran_Tahap_Tes),
                'nama' => $r->Nama,
                'email' => $r->Email,
                'aktivitas' => $r->Label,
                'tahap' => $r->TahapLabel,
                'kode' => $r->LamaranKode,
                'program' => $r->ProgramNama,
                'posisi' => $r->Posisi,
                'status' => KonfirmasiJadwal::DITUNDA,
                'statusLabel' => $def->Label_Tim ?? 'Ditunda',
                'warna' => $def->Warna ?? null,
                'ikon' => $def->Ikon ?? null,
                'tunda' => $i ? array_diff_key($i, array_flip(['ringkas'])) : null,
            ];
        })
            ->sortBy(fn ($x) => $x['tunda']['perkiraan'] ?? '9999-12-31')
            ->take(self::BARIS_MAKS)
            ->values()->all();
    }

    /** GET /api/v1/karir/agenda-seleksi/agenda?dari=Y-m-d&sampai=Y-m-d */
    public function agenda(Request $request)
    {
        if (! KonfirmasiJadwal::siap()) {
            return ResponseHelper::success(['siap' => false, 'hari' => []], 'Fitur konfirmasi belum aktif.');
        }

        try {
            $dari = Carbon::parse((string) $request->query('dari', now()->format('Y-m-d')))->startOfDay();
            $sampai = Carbon::parse((string) $request->query('sampai', $dari->copy()->addDays(6)->format('Y-m-d')))->endOfDay();
        } catch (\Throwable $e) {
            return ResponseHelper::error('Rentang tanggal tidak dikenali.', 422);
        }
        if ($sampai->lt($dari) || $dari->diffInDays($sampai) > 31) {
            return ResponseHelper::error('Rentang agenda paling panjang 31 hari.', 422);
        }

        $q = DB::table(KonfirmasiJadwal::T_CRM.' as c')
            ->whereBetween('c.Jadwal_Mulai', [$dari, $sampai])
            // Versi yang BERLAKU: masih berjalan, atau berakhir karena hadir /
            // mundur. Versi yang diganti, ditunda, diulang, atau tahapnya sudah
            // diputus bukan agenda siapa pun.
            ->where(fn ($w) => $w->where('c.Selesai', 'T')->orWhereIn('c.Selesai_Alasan', ['KEHADIRAN', 'MUNDUR']));
        self::saringLingkup($q, 'c');
        if ($programId = Hashids::decode((string) $request->query('program', ''))[0] ?? null) {
            $q->where('c.Program_Id', $programId);
        }

        $rows = $q->orderBy('c.Jadwal_Mulai')->orderBy('c.Nama')->limit(2000)->get();
        $info = self::infoAktivitas($rows->pluck('Lamaran_Tahap_Tes_Id')->map(fn ($v) => (int) $v)->all());
        $nama = self::namaProgramPosisi($rows);

        $hari = $rows->groupBy(fn ($c) => Carbon::parse($c->Jadwal_Mulai)->format('Y-m-d'))
            ->map(function (Collection $perHari, string $tgl) use ($info, $nama) {
                $sesi = $perHari->groupBy(fn ($c) => Carbon::parse($c->Jadwal_Mulai)->format('H:i').'|'.$c->Aktivitas)
                    ->map(function (Collection $isi, string $kunci) use ($info, $nama) {
                        [$jam, $aktivitas] = explode('|', $kunci, 2);
                        $orang = $isi->map(fn ($c) => self::bentukBaris($c, $info, $nama))->values();

                        return [
                            'jam' => str_replace(':', '.', $jam),
                            'aktivitas' => $aktivitas,
                            'jumlah' => $orang->count(),
                            'hitung' => $orang->countBy('status')->all(),
                            'orang' => $orang->all(),
                        ];
                    })->values();

                return [
                    'tanggal' => $tgl,
                    'judul' => self::hariTeks(Carbon::parse($tgl)),
                    'jumlah' => $perHari->count(),
                    'hitung' => $perHari->countBy(fn ($c) => KonfirmasiJadwal::statusEfektif($c->Status_Konfirmasi, $c->Batas_Konfirmasi))->all(),
                    'sesi' => $sesi->all(),
                ];
            })->values()->all();

        return ResponseHelper::success([
            'siap' => true,
            'dari' => $dari->format('Y-m-d'),
            'sampai' => $sampai->format('Y-m-d'),
            'hari' => $hari,
        ], 'Agenda');
    }

    /** GET /api/v1/karir/konfirmasi-jadwal/{id} — rincian + riwayat satu aktivitas. */
    public function detail(string $id)
    {
        $sub = self::sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $versi = (int) ($sub->Jadwal_Versi ?? 0);
        $crm = KonfirmasiJadwal::crm((int) $sub->Id_Lamaran_Tahap_Tes, $versi);
        $minta = KonfirmasiJadwal::permintaanTerbuka((int) $sub->Id_Lamaran_Tahap_Tes);
        $ditunda = ($sub->Konfirmasi_Status ?? null) === KonfirmasiJadwal::DITUNDA && empty($sub->Jadwal_Mulai);

        return ResponseHelper::success([
            'id' => $id,
            'kandidat' => $sub->KandidatNama,
            'email' => $sub->KandidatEmail,
            'aktivitas' => $sub->Label,
            'tahap' => $sub->TahapLabel,
            'posisi' => $sub->Posisi ?: $sub->ProgramNama,
            'waktuTeks' => $sub->Jadwal_Mulai ? UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai) : null,
            'mulai' => $sub->Jadwal_Mulai ? (string) $sub->Jadwal_Mulai : null,
            'selesai' => $sub->Jadwal_Selesai ? (string) $sub->Jadwal_Selesai : null,
            'hadir' => $sub->Jadwal_Hadir,
            'konfirmasi' => match (true) {
                $ditunda => KonfirmasiJadwal::bentukTunda($sub, KonfirmasiJadwal::infoTunda((int) $sub->Id_Lamaran_Tahap_Tes)),
                ! empty($sub->Konfirmasi_Status) && (bool) $sub->Jadwal_Mulai => KonfirmasiJadwal::bentuk($sub, $crm, $minta),
                default => null,
            },
            // KOMITMEN kandidat untuk jadwal ini: sisa jatah ubah & batasnya —
            // supaya tim tahu kapan perubahan hanya bisa lewat "Catat jawaban".
            'ubah' => ! $ditunda && $sub->Jadwal_Mulai && ! empty($sub->Konfirmasi_Status)
                ? KonfirmasiJadwal::bentukUbah(KonfirmasiJadwal::aturanUbahUntuk($sub))
                : null,
            'jatah' => [
                'maks' => (int) (KonfirmasiJadwal::tipeDari($sub)->Jadwal_Ulang_Maks ?? 2),
                'terpakai' => KonfirmasiJadwal::jumlahPermintaan((int) $sub->Id_Lamaran_Tahap_Tes),
            ],
            'terbuka' => ($sub->StatusLamaran ?? '') === 'BERJALAN' && ($sub->Flag_Selesai ?? 'T') !== 'Y' && empty($sub->Jadwal_Hadir),
            'riwayat' => KonfirmasiJadwal::riwayat((int) $sub->Id_Lamaran_Tahap_Tes),
            // Bahan formulir panel (catat jawaban, usulan) — panel worklist tidak
            // punya props halaman Agenda, jadi dikirim bersama rinciannya.
            'opsi' => [
                'pilihan' => KonfirmasiJadwal::pilihanKandidat()->map(fn ($m) => [
                    'kode' => $m->Kode,
                    'label' => $m->Label_Tim,
                    'bukaPermintaan' => ($m->Flag_Buka_Permintaan ?? 'T') === 'Y',
                    'sinyalMundur' => ($m->Flag_Sinyal_Mundur ?? 'T') === 'Y',
                ])->values()->all(),
                'alasan' => [
                    'JADWAL_LAIN' => self::bentukAlasan('JADWAL_LAIN'),
                    'MUNDUR' => self::bentukAlasan('MUNDUR'),
                    'TUNDA' => self::bentukAlasan('TUNDA'),
                ],
                'bagianHari' => array_values(config('konfirmasi.bagian_hari', [])),
                'jedaKirimMenit' => (int) config('konfirmasi.jeda_kirim_menit', 10),
                'jarakSopanJam' => (int) config('konfirmasi.jarak_sopan_jam', 16),
                // Rentang perkiraan jadwal pengganti di jendela Tunda.
                'tunda' => self::rentangTunda(),
            ],
        ], 'Rincian konfirmasi');
    }

    /**
     * GET /api/v1/karir/konfirmasi-jadwal/opsi-tunda
     *
     * Bahan jendela Tunda yang dibuka dari baris aktivitas worklist: hanya master
     * alasan + rentang tanggal. Satu kueri — rincian aktivitas lengkap (riwayat,
     * komitmen, permintaan) terlalu berat untuk sekadar mengisi pilihan alasan.
     */
    public function opsiTunda()
    {
        return ResponseHelper::success([
            'alasan' => self::bentukAlasan('TUNDA'),
            'tunda' => self::rentangTunda(),
        ], 'Opsi penundaan');
    }

    /** Rentang perkiraan jadwal pengganti yang diterima periksaTunda(). */
    private static function rentangTunda(): array
    {
        return [
            'tanggalMin' => now()->addDay()->format('Y-m-d'),
            'tanggalMaks' => now()->addDays(max(7, (int) config('konfirmasi.tunda_hari_maks', 180)))->format('Y-m-d'),
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  AKSI TIM
    // ════════════════════════════════════════════════════════════════════════

    /** POST /api/v1/karir/konfirmasi-jadwal/{id}/catat — jawaban dicatat atas nama kandidat. */
    public function catat(Request $request, string $id)
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20',
            'kanal' => ['required', 'in:'.implode(',', KonfirmasiJadwal::KANAL_TIM)],
            'catatan' => 'required|string|max:1000',
            'alasan' => 'nullable|string|max:30',
            'usulan' => 'nullable|array|max:5',
            'usulan.*.tanggal' => 'required_with:usulan|date_format:Y-m-d',
            'usulan.*.bagian' => 'required_with:usulan|string|max:10',
            'statusDilihat' => 'nullable|string|max:20',
        ]);

        $sub = self::sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();
        $hasil = KonfirmasiJadwal::jawab(
            (int) $sub->Id_Lamaran_Tahap_Tes,
            (int) ($sub->Jadwal_Versi ?? 0),
            $data['kode'],
            [
                'alasan' => $data['alasan'] ?? null,
                'catatan' => $data['catatan'],
                'usulan' => $data['usulan'] ?? [],
                'statusDilihat' => $data['statusDilihat'] ?? null,
            ],
            KonfirmasiJadwal::pelakuTim($nama, $olehId, $data['kanal']),
        );

        return self::balas($hasil);
    }

    /** POST /api/v1/karir/konfirmasi-jadwal/permintaan/{id}/setujui */
    public function setujui(Request $request, string $id)
    {
        $data = $request->validate([
            'urutan' => 'required|integer|min:0|max:10',
            'jam' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'catatan' => 'nullable|string|max:1000',
        ]);

        $p = self::permintaan($id);
        if (! $p) {
            return ResponseHelper::error('Permintaan tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();

        return self::lindungi(fn () => KonfirmasiJadwal::setujui(
            (int) $p->Id_Jadwal_Permintaan,
            (int) $data['urutan'],
            $data['jam'],
            $data['catatan'] ?? null,
            $nama,
            $olehId,
        ));
    }

    /** POST /api/v1/karir/konfirmasi-jadwal/permintaan/{id}/tawarkan */
    public function tawarkan(Request $request, string $id)
    {
        $data = $request->validate([
            'mulai' => 'required|date',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $p = self::permintaan($id);
        if (! $p) {
            return ResponseHelper::error('Permintaan tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();

        return self::lindungi(fn () => KonfirmasiJadwal::tawarkan(
            (int) $p->Id_Jadwal_Permintaan,
            (string) $data['mulai'],
            $data['catatan'] ?? null,
            $nama,
            $olehId,
        ));
    }

    /** POST /api/v1/karir/konfirmasi-jadwal/permintaan/{id}/tolak */
    public function tolak(Request $request, string $id)
    {
        $data = $request->validate([
            'catatan' => 'required|string|max:1000',
        ]);

        $p = self::permintaan($id);
        if (! $p) {
            return ResponseHelper::error('Permintaan tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();

        return self::lindungi(fn () => KonfirmasiJadwal::tolak(
            (int) $p->Id_Jadwal_Permintaan,
            $data['catatan'],
            $nama,
            $olehId,
        ));
    }

    /**
     * POST /api/v1/karir/konfirmasi-jadwal/pengingat — PENGINGAT MANUAL
     * "mohon segera konfirmasi", satu atau banyak kandidat.
     *
     * Tiap surel diantrekan di wc-konfirmasimail berselang beberapa detik;
     * hitungannya masuk snapshot CRM (pengingat + kirim manual + oleh siapa).
     */
    public function pengingat(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:'.self::pengingatMaks(),
            'ids.*' => 'required|string|max:64',
        ]);

        [$ids, $luar] = self::dalamLingkupBanyak($data['ids']);
        if (! $ids) {
            return ResponseHelper::error('Tidak ada kandidat yang bisa diingatkan.', 422);
        }

        [$nama, $olehId] = self::oleh();
        $hasil = KonfirmasiJadwal::kirimPengingat($ids, $nama, $olehId);
        $hasil['gagal'] = array_merge($hasil['gagal'], $luar);

        return ResponseHelper::success($hasil, self::kalimatBanyak($hasil, 'pengingat diantrekan'));
    }

    /**
     * POST /api/v1/karir/konfirmasi-jadwal/{id}/tunda — kosongkan jadwal,
     * kabari kandidat: alasan (master), perkiraan jadwal pengganti (boleh
     * kosong = "akan dikabarkan"), pesan.
     */
    public function tunda(Request $request, string $id)
    {
        $data = self::isianTunda($request);

        $sub = self::sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();

        return self::lindungi(fn () => KonfirmasiJadwal::tunda((int) $sub->Id_Lamaran_Tahap_Tes, $data, $nama, $olehId));
    }

    /**
     * POST /api/v1/karir/konfirmasi-jadwal/{id}/tunda/perbarui — perbarui info
     * penundaan selama jadwal pengganti belum terbit; `kabari` = kirim surel
     * kabar terbaru ke kandidat.
     */
    public function perbaruiTunda(Request $request, string $id)
    {
        $data = self::isianTunda($request);
        $kabari = $request->boolean('kabari', true);

        $sub = self::sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        [$nama, $olehId] = self::oleh();

        return self::lindungi(fn () => KonfirmasiJadwal::perbaruiTunda((int) $sub->Id_Lamaran_Tahap_Tes, $data, $kabari, $nama, $olehId));
    }

    /** Isian jendela Tunda — bentuknya saja; isinya diperiksa KonfirmasiJadwal::periksaTunda. */
    private static function isianTunda(Request $request): array
    {
        return $request->validate([
            'alasan' => 'required|string|max:30',
            'perkiraan' => 'nullable|date_format:Y-m-d',
            'pesan' => 'nullable|string|max:1000',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PEMBANTU
    // ════════════════════════════════════════════════════════════════════════

    private static function pengingatMaks(): int
    {
        return max(1, min(300, (int) config('konfirmasi.pengingat_maks', 300)));
    }

    /** [nama, id] akun yang bertindak. */
    private static function oleh(): array
    {
        return [
            (string) (session('career_auth.nama') ?: 'ADMIN'),
            session('career_auth.id') ? (int) session('career_auth.id') : null,
        ];
    }

    /** Hasil layanan → JSON. */
    private static function balas(array $hasil)
    {
        if ($hasil['ok'] ?? false) {
            return ResponseHelper::success(['status' => $hasil['status'] ?? null], $hasil['pesan']);
        }

        return response()->json([
            'success' => false,
            'status' => $hasil['kode'] ?? 422,
            'message' => $hasil['pesan'] ?? 'Gagal memproses.',
            'result' => ['status' => $hasil['status'] ?? null],
        ], $hasil['kode'] ?? 422);
    }

    /** Jalankan aksi; tabrakan dengan rekan lain dijawab 409, bukan 500. */
    private static function lindungi(\Closure $aksi)
    {
        try {
            return self::balas($aksi());
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[KONFIRMASI] aksi tim gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal memproses — coba lagi.', 500);
        }
    }

    /** Aktivitas dari hash, HANYA bila dalam lingkup akun ini. */
    private static function sasaran(string $hash): ?object
    {
        $id = Hashids::decode($hash)[0] ?? null;
        $sub = $id && KonfirmasiJadwal::siap() ? KonfirmasiJadwal::konteks((int) $id) : null;

        return $sub && self::dalamLingkup($sub) ? $sub : null;
    }

    /** Permintaan dari hash, HANYA bila aktivitasnya dalam lingkup akun ini. */
    private static function permintaan(string $hash): ?object
    {
        $id = Hashids::decode($hash)[0] ?? null;
        $p = $id ? DB::table(KonfirmasiJadwal::T_MINTA)->where('Id_Jadwal_Permintaan', $id)->first() : null;
        if (! $p) {
            return null;
        }
        $sub = KonfirmasiJadwal::konteks((int) $p->Lamaran_Tahap_Tes_Id);

        return $sub && self::dalamLingkup($sub) ? $p : null;
    }

    /** Lingkup yang sama dengan worklist: kategori program + PIC loker. */
    private static function dalamLingkup(object $sub): bool
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE_WORKLIST);
        if ($izin && ! in_array($sub->ProgramKategori, $izin, true)) {
            return false;
        }
        $pic = AksesService::picDiizinkan(self::PAGE_WORKLIST);

        return $pic === null || in_array((string) $sub->Pic_Kode_Karyawan, $pic, true);
    }

    /**
     * Saring banyak aktivitas sekaligus (satu kueri).
     *
     * @return array{0: array<int>, 1: array} [id dalam lingkup, baris "gagal" untuk sisanya]
     */
    private static function dalamLingkupBanyak(array $hashes): array
    {
        $peta = [];
        foreach ($hashes as $h) {
            if ($id = Hashids::decode((string) $h)[0] ?? null) {
                $peta[(int) $id] = (string) $h;
            }
        }
        if (! $peta) {
            return [[], []];
        }

        $q = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->whereIn('t.Id_Lamaran_Tahap_Tes', array_keys($peta));
        if ($izin = AksesService::kategoriDiizinkan(self::PAGE_WORKLIST)) {
            $q->whereIn('p.Kategori', $izin);
        }
        $pic = AksesService::picDiizinkan(self::PAGE_WORKLIST);
        if ($pic !== null) {
            $q->whereIn('x.Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']);
        }

        $boleh = $q->pluck('t.Id_Lamaran_Tahap_Tes')->map(fn ($v) => (int) $v)->all();
        $luar = array_diff(array_keys($peta), $boleh);

        return [$boleh, array_map(fn ($id) => ['nama' => '#'.$peta[$id], 'alasan' => 'Di luar lingkup akun Anda.'], array_values($luar))];
    }

    /** Lingkup akun pada kueri CRM — subkueri ke master kecil, tanpa join lamaran. */
    private static function saringLingkup($q, string $alias): void
    {
        if ($izin = AksesService::kategoriDiizinkan(self::PAGE_WORKLIST)) {
            $q->whereIn("{$alias}.Program_Id", fn ($s) => $s->select('Id_Program')->from('N_WEB_CAREERS_Program')->whereIn('Kategori', $izin));
        }
        $pic = AksesService::picDiizinkan(self::PAGE_WORKLIST);
        if ($pic !== null) {
            $q->whereIn("{$alias}.Program_Posisi_Id", fn ($s) => $s->select('Id_Program_Posisi')->from('N_WEB_CAREERS_Program_Posisi')
                ->whereIn('Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']));
        }
    }

    /**
     * Rincian yang TIDAK ada di snapshot — per kunci primer, hanya untuk baris
     * yang tampil: nama tahap, status lamaran, kehadiran, dan jadwal kini.
     *
     * @param  array<int>  $ids
     * @return array<int, object>
     */
    private static function infoAktivitas(array $ids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($ids)), 500) as $potong) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->whereIn('t.Id_Lamaran_Tahap_Tes', $potong)
                ->get(['t.Id_Lamaran_Tahap_Tes', 't.Jadwal_Hadir', 't.Jadwal_Versi', 't.Jadwal_Mode', 't.Flag_Selesai',
                    'h.Label as TahapLabel', 'l.Status as StatusLamaran', 'l.Kode as LamaranKode', 'l.Id_Lamaran'])
                ->each(function ($r) use (&$out) {
                    $out[(int) $r->Id_Lamaran_Tahap_Tes] = $r;
                });
        }

        return $out;
    }

    /** Nama program & posisi untuk baris yang tampil — dari master kecil. */
    private static function namaProgramPosisi(Collection $rows): array
    {
        $prog = $rows->pluck('Program_Id')->filter()->unique()->values()->all();
        $pos = $rows->pluck('Program_Posisi_Id')->filter()->unique()->values()->all();

        return [
            'program' => $prog ? DB::table('N_WEB_CAREERS_Program')->whereIn('Id_Program', $prog)->pluck('Nama', 'Id_Program')->all() : [],
            'posisi' => $pos ? DB::table('N_WEB_CAREERS_Program_Posisi')->whereIn('Id_Program_Posisi', $pos)->pluck('Posisi', 'Id_Program_Posisi')->all() : [],
        ];
    }

    /** Satu baris daftar dari satu baris snapshot CRM. */
    private static function bentukBaris(object $c, array $info, array $nama): array
    {
        $id = (int) $c->Lamaran_Tahap_Tes_Id;
        $i = $info[$id] ?? null;
        $status = KonfirmasiJadwal::statusEfektif($c->Status_Konfirmasi, $c->Batas_Konfirmasi);
        $def = KonfirmasiJadwal::master()->get($status);
        $jeda = max(1, (int) config('konfirmasi.jeda_kirim_menit', 10));
        $sopan = max(1, (int) config('konfirmasi.jarak_sopan_jam', 16));
        $menit = $c->Terakhir_Kirim_At ? (int) floor(Carbon::parse($c->Terakhir_Kirim_At)->diffInMinutes(now())) : null;

        return [
            'id' => Hashids::encode($id),
            'versi' => (int) $c->Versi,
            'versiBerlaku' => $i ? (int) $i->Jadwal_Versi === (int) $c->Versi : true,
            'nama' => $c->Nama,
            'email' => $c->Email,
            'aktivitas' => $c->Aktivitas,
            'tahap' => $i->TahapLabel ?? null,
            'kode' => $i->LamaranKode ?? null,
            'program' => $nama['program'][$c->Program_Id] ?? null,
            'posisi' => $c->Program_Posisi_Id ? ($nama['posisi'][$c->Program_Posisi_Id] ?? null) : null,
            'mulai' => (string) $c->Jadwal_Mulai,
            'mulaiTeks' => UndanganJadwal::waktuTeks($c->Jadwal_Mulai),
            'batas' => (string) $c->Batas_Konfirmasi,
            'batasTeks' => UndanganJadwal::teksBatas($c->Batas_Konfirmasi),
            'status' => $status,
            'statusLabel' => $def->Label_Tim ?? $status,
            'warna' => $def->Warna ?? null,
            'ikon' => $def->Ikon ?? null,
            'undanganAt' => $c->Undangan_At ? (string) $c->Undangan_At : null,
            'dijawabAt' => $c->Dijawab_At ? (string) $c->Dijawab_At : null,
            'jumlahPengingat' => (int) $c->Jumlah_Pengingat,
            'jumlahKirimManual' => (int) $c->Jumlah_Kirim_Manual,
            'jumlahGagal' => (int) $c->Jumlah_Gagal,
            'terakhirKirimAt' => $c->Terakhir_Kirim_At ? (string) $c->Terakhir_Kirim_At : null,
            'terakhirKirimJenis' => $c->Terakhir_Kirim_Jenis,
            'terakhirKirimOleh' => $c->Terakhir_Kirim_Oleh,
            'menitSejakKirim' => $menit,
            // Pengingat: hanya yang MENUNGGU, jeda keras 10 menit; di bawah 16 jam
            // layar memperingatkan (bukan menolak) — satu surel sehari itu sopan.
            'bolehIngat' => $status === KonfirmasiJadwal::MENUNGGU && ($menit === null || $menit >= $jeda),
            'tungguMenit' => $menit !== null && $menit < $jeda ? $jeda - $menit : 0,
            'baruDikirim' => $menit !== null && $menit < $sopan * 60,
            'hadir' => $i->Jadwal_Hadir ?? null,
            'selesai' => $c->Selesai === 'Y',
            'selesaiAlasan' => $c->Selesai_Alasan,
        ];
    }

    /** "Kamis, 08 Okt 2026" */
    private static function hariTeks(Carbon $t): string
    {
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$t->dayOfWeek];

        return $hari.', '.UndanganJadwal::tanggalPendek($t);
    }

    private static function bentukAlasan(string $jenis): array
    {
        return KonfirmasiJadwal::alasan($jenis)->map(fn ($a) => [
            'kode' => $a->Kode,
            'nama' => $a->Nama,
            'butuhCatatan' => ($a->Flag_Butuh_Catatan ?? 'T') === 'Y',
        ])->values()->all();
    }

    /** "3 pengingat diantrekan, 1 dilewati." */
    private static function kalimatBanyak(array $hasil, string $kata): string
    {
        $ok = count($hasil['berhasil'] ?? []);
        $no = count($hasil['gagal'] ?? []);

        return $ok.' '.$kata.($no ? ", {$no} dilewati — periksa rinciannya." : '.');
    }
}
