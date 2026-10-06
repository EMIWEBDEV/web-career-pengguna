<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Memastikan pembukaan, program, dan posisi adalah satu target lamaran valid. */
class LamaranTargetValidator
{
    /**
     * @return array{ok:bool,pesan:string,pembukaan?:object,program?:object,posisi?:object}
     */
    public function validasi(int $pembukaanId, int $posisiId): array
    {
        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $pembukaanId)->first();
        if (! $pembukaan) {
            return ['ok' => false, 'pesan' => 'Pembukaan tidak ditemukan.'];
        }
        if ($pembukaan->Status_Publish !== 'TERBIT') {
            return ['ok' => false, 'pesan' => 'Pembukaan ini belum terbit.'];
        }

        if ($pembukaan->Masa_Berlaku === 'BERBATAS') {
            $kini = now();
            if ($pembukaan->Tanggal_Buka && $kini->lt(Carbon::parse($pembukaan->Tanggal_Buka))) {
                return ['ok' => false, 'pesan' => 'Pendaftaran belum dibuka.'];
            }
            if ($pembukaan->Tanggal_Tutup && $kini->gt(Carbon::parse($pembukaan->Tanggal_Tutup))) {
                return ['ok' => false, 'pesan' => 'Pendaftaran sudah ditutup.'];
            }
        }

        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $pembukaan->Program_Id)->first();
        if (! $program || $program->Status !== 'BERJALAN') {
            return ['ok' => false, 'pesan' => 'Program ini sedang tidak menerima lamaran.'];
        }

        // Posisi wajib merupakan anak program milik pembukaan. Request buatan
        // tidak dapat mencampur posisi rekrutmen, magang, dan MT.
        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Id_Program_Posisi', $posisiId)
            ->where('Program_Id', $program->Id_Program)
            ->first();
        if (! $posisi) {
            return ['ok' => false, 'pesan' => 'Posisi tidak ditemukan pada program ini.'];
        }
        if (($posisi->Status ?? 'BUKA') !== 'BUKA') {
            return ['ok' => false, 'pesan' => 'Posisi ini sudah tidak menerima pelamar.'];
        }

        // ── DIMATIKAN ADMIN ─────────────────────────────────────────────────
        //
        // Menyaringnya di landing saja tidak cukup. Tautan lowongan beredar di
        // grup pesan dan tersimpan sebagai penanda; loker yang sudah dimatikan
        // tetap bisa dibuka langsung lewat tautan lamanya, dan tanpa gerbang di
        // sini lamarannya tetap masuk — ke posisi yang tim rekrutmen sudah
        // putuskan untuk tidak diisi.
        if (($posisi->Flag_Aktif ?? 'Y') === 'N') {
            return ['ok' => false, 'pesan' => 'Lowongan ini sedang tidak dibuka.'];
        }

        // ── RENCANA MPP SUDAH TERPENUHI ─────────────────────────────────────
        //
        // Status loker (BUKA/PENUH) hanya tahu jatah PROGRAM INI. Satu MPP yang
        // dibuka di dua program punya dua baris loker yang tidak saling kenal:
        // yang satu bisa masih 'BUKA' padahal seluruh kursi yang disetujui sudah
        // terisi lewat program sebelah.
        //
        // Menahan di sini, bukan cuma di gerbang keputusan, karena membiarkan
        // orang menyelesaikan seluruh proses seleksi untuk kursi yang sudah
        // tidak ada adalah hal yang paling mahal yang bisa dilakukan sistem ini
        // kepada seorang pelamar.
        $mpp = KursiMpp::keadaan($posisi->Mpp_Ref ?? null);
        if ($mpp && $mpp['penuh']) {
            return ['ok' => false, 'pesan' => 'Lowongan ini sudah terpenuhi — seluruh kursinya telah terisi.'];
        }

        return [
            'ok' => true,
            'pesan' => 'Target lamaran valid.',
            'pembukaan' => $pembukaan,
            'program' => $program,
            'posisi' => $posisi,
        ];
    }
}
