<?php

namespace Tests\Unit;

use App\Support\Career\PipelineReadModel;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PipelineReadModelTest extends TestCase
{
    private function lamaran(string $status): object
    {
        return (object) ['Status' => $status];
    }

    private function tahap(array $attrs): object
    {
        return (object) array_merge([
            'Id_Lamaran_Tahap' => 1,
            'Status' => 'BERJALAN',
            'Hold_Flag' => 'T',
            'Siap_Diputus' => 'N',
            'Flag_Tuntas' => 'T',
            'Urutan' => 1,
            'Keputusan_Mode' => 'MANUAL',
        ], $attrs);
    }

    public function test_hold_mendominasi_siap_diputus(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'Y', 'Siap_Diputus' => 'Y']);
        $tahapList = new Collection([$t]);

        $hasil = PipelineReadModel::bucket($l, $tahapList, []);

        $this->assertSame('HOLD', $hasil['bucket']);
    }

    public function test_siap_diputus_ketika_tidak_hold(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'T', 'Siap_Diputus' => 'Y']);
        $tahapList = new Collection([$t]);

        $hasil = PipelineReadModel::bucket($l, $tahapList, []);

        $this->assertSame('SIAP_DIPUTUS', $hasil['bucket']);
    }

    /**
     * Outcome TERMINAL dibaca dari flag Oleh_Kandidat di masterHasil, bukan
     * dari kode string status lamaran. Ini memastikan setiap kode hasil dapat
     * dikonfigurasi apakah itu outcome oleh kandidat atau bukan.
     */
    public function test_outcome_oleh_kandidat_dibaca_dari_flag_bukan_kode_string(): void
    {
        $l = $this->lamaran('KODE_BARU_APAPUN');
        $tahapList = new Collection([$this->tahap([])]);
        $master = ['KODE_BARU_APAPUN' => (object) ['Flag_Oleh_Kandidat' => 'Y']];

        $hasil = PipelineReadModel::bucket($l, $tahapList, [], $master);

        $this->assertSame('TERMINAL', $hasil['bucket']);
        $this->assertTrue($hasil['outcomeOlehKandidat']);
        $this->assertSame('KODE_BARU_APAPUN', $hasil['outcomeKode']);
    }

    /**
     * Aktivitas manual (Provider='INTERNAL') yang belum selesai menunjukkan
     * bahwa tim admin masih mengerjakan sesuatu (wawancara, test, evaluasi manual).
     * Status bucket TINDAKAN_ADMIN memastikan worklist dan monitoring tahu siapa
     * yang sedang bertanggung jawab.
     */
    public function test_tindakan_admin_saat_aktivitas_manual_belum_dikerjakan(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'T', 'Siap_Diputus' => 'N']);
        $tahapList = new Collection([$t]);
        $subAktif = [
            (object) ['Peran' => 'PENENTU', 'Flag_Selesai' => 'N', 'Provider' => 'INTERNAL', 'Penjadwalan_Tahap_Id' => null],
        ];

        $hasil = PipelineReadModel::bucket($l, $tahapList, $subAktif);

        $this->assertSame('TINDAKAN_ADMIN', $hasil['bucket']);
    }

    /**
     * Aktivitas online (Provider='THIRD_PARTY') yang belum terjadwal berarti
     * admin masih perlu membuat jadwal integrasi (email link, meet link, dll).
     * Status MENUNGGU_JADWAL membedakan dengan TINDAKAN_ADMIN karena yang
     * ditunggu adalah terbentuknya sesi, bukan keputusan admin atas kandidat.
     */
    public function test_menunggu_jadwal_saat_aktivitas_online_belum_terjadwal(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'T', 'Siap_Diputus' => 'N']);
        $tahapList = new Collection([$t]);
        $subAktif = [
            (object) ['Peran' => 'PENENTU', 'Flag_Selesai' => 'N', 'Provider' => 'THIRD_PARTY', 'Penjadwalan_Tahap_Id' => null],
        ];

        $hasil = PipelineReadModel::bucket($l, $tahapList, $subAktif);

        $this->assertSame('MENUNGGU_JADWAL', $hasil['bucket']);
    }

    /**
     * Aktivitas online (Provider='THIRD_PARTY') yang sudah terjadwal berarti
     * sesi sudah dibuat. Kandidat atau sistem pihak ketiga sedang menjalankan tes.
     * Status MENUNGGU_HASIL menunjukkan kami menunggu hasil integrasi balik dari
     * penyedia layanan (skor, rekomendasi, log, dll).
     */
    public function test_menunggu_hasil_saat_aktivitas_online_sudah_terjadwal(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'T', 'Siap_Diputus' => 'N']);
        $tahapList = new Collection([$t]);
        $subAktif = [
            (object) ['Peran' => 'PENENTU', 'Flag_Selesai' => 'N', 'Provider' => 'THIRD_PARTY', 'Penjadwalan_Tahap_Id' => 55],
        ];

        $hasil = PipelineReadModel::bucket($l, $tahapList, $subAktif);

        $this->assertSame('MENUNGGU_HASIL', $hasil['bucket']);
    }
}
