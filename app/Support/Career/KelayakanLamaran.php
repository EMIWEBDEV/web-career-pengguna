<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — MESIN KELAYAKAN LAMARAN (per AKUN).
 *
 * Menentukan apakah seorang kandidat boleh melamar sebuah program, berdasarkan
 * RIWAYAT lamarannya + master `N_WEB_CAREERS_Aturan_Lamaran` (konfigurable).
 *
 * Aturan bawaan (bisa diubah lewat master):
 *  - Ada lamaran AKTIF (BERJALAN)     → kunci semua sampai selesai.
 *  - LOLOS di kategori Kunci_Jika_Lolos='Y' (MT) → kunci semua jalur.
 *  - MT gugur  → boleh REKRUTMEN (maks Lintas_Maks=1); re-apply MT setelah Cooldown.
 *  - REKRUTMEN → tidak bisa lintas ke MT (Lintas_Ke NULL); re-apply setelah Cooldown.
 */
class KelayakanLamaran
{
    /** Program kategori → bucket aturan. Non-MT (REKRUTMEN/INTERNSHIP) = REKRUTMEN. */
    public static function bucket(?string $kategori): string
    {
        return strtoupper((string) $kategori) === 'MT' ? 'MT' : 'REKRUTMEN';
    }

    /**
     * @return array{boleh:bool, alasan:?string, kode:?string}
     */
    public function cek(int $userId, ?string $kategoriProgram): array
    {
        $target = self::bucket($kategoriProgram);

        $aturan = DB::table('N_WEB_CAREERS_Aturan_Lamaran')->where('Flag_Aktif', 'Y')->get()->keyBy('Kategori');
        // Master kosong → jangan menghalangi (fail-open agar config hilang tak memblokir semua).
        if ($aturan->isEmpty()) {
            return $this->boleh();
        }

        $riwayat = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->select('Kategori', 'Status', 'Waktu_Lamar', 'Waktu_Selesai')
            ->get()
            ->map(function ($r) {
                $r->bucket = self::bucket($r->Kategori);

                return $r;
            });

        if ($riwayat->isEmpty()) {
            return $this->boleh(); // belum pernah melamar → bebas
        }

        // 1) Ada lamaran AKTIF (BERJALAN) → kunci semua.
        $aktif = $riwayat->firstWhere('Status', 'BERJALAN');
        if ($aktif) {
            $lbl = $aktif->bucket === 'MT' ? 'program Management Trainee' : 'proses rekrutmen';

            return $this->tolak('ADA_AKTIF', "Kamu sedang mengikuti {$lbl} yang masih berjalan. Selesaikan dulu sebelum melamar lowongan lain.");
        }

        // 2) LOLOS di kategori ber-Kunci_Jika_Lolos='Y' → kunci semua jalur.
        foreach (['MT', 'REKRUTMEN'] as $k) {
            $at = $aturan->get($k);
            if ($at && $at->Kunci_Jika_Lolos === 'Y' && $riwayat->where('bucket', $k)->firstWhere('Status', 'LULUS')) {
                return $this->tolak('SUDAH_LOLOS', 'Kamu sudah dinyatakan diterima di program ' . ($at->Nama ?: $k) . ' — tidak dapat melamar lowongan lain.');
            }
        }

        $atTarget = $aturan->get($target);

        // 3) LINTAS jalur: punya riwayat di kategori LAIN → boleh menyeberang ke target?
        $sumberLain = $target === 'MT' ? 'REKRUTMEN' : 'MT';
        if ($riwayat->where('bucket', $sumberLain)->isNotEmpty()) {
            $atSumber = $aturan->get($sumberLain);
            if (! $atSumber || $atSumber->Lintas_Ke !== $target) {
                return $this->tolak('LINTAS_DILARANG', 'Peserta jalur ' . ($atSumber->Nama ?? $sumberLain) . ' tidak dapat mengikuti ' . ($atTarget->Nama ?? $target) . '.');
            }
            $jumlahTarget = $riwayat->where('bucket', $target)->count();
            if ($jumlahTarget >= (int) $atSumber->Lintas_Maks) {
                return $this->tolak('LINTAS_HABIS', 'Kesempatan melamar ' . ($atTarget->Nama ?? $target) . ' setelah jalur ' . ($atSumber->Nama ?? $sumberLain) . " hanya {$atSumber->Lintas_Maks} kali dan sudah kamu gunakan.");
            }
        }

        // 4) COOLDOWN re-apply kategori SAMA (dari terminal terakhir).
        $riwTarget = $riwayat->where('bucket', $target)->whereIn('Status', ['GUGUR', 'LULUS']);
        if ($riwTarget->isNotEmpty() && $atTarget) {
            $terakhir = $riwTarget->sortByDesc(fn ($r) => $r->Waktu_Selesai ?: $r->Waktu_Lamar)->first();
            $selesai = $terakhir->Waktu_Selesai ?: $terakhir->Waktu_Lamar;
            if ($selesai) {
                $bolehLagi = Carbon::parse($selesai)->addDays((int) $atTarget->Cooldown_Hari);
                if (now()->lt($bolehLagi)) {
                    return $this->tolak('COOLDOWN', 'Kamu dapat melamar ' . ($atTarget->Nama ?: $target) . ' lagi mulai ' . $bolehLagi->translatedFormat('d M Y') . '.');
                }
            }
        }

        return $this->boleh();
    }

    private function boleh(): array
    {
        return ['boleh' => true, 'alasan' => null, 'kode' => null];
    }

    private function tolak(string $kode, string $alasan): array
    {
        return ['boleh' => false, 'kode' => $kode, 'alasan' => $alasan];
    }
}
