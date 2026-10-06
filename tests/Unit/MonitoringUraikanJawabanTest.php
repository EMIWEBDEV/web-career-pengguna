<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Monitoring\MonitoringController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * BAGIAN BERULANG DI DETAIL TAHAP MONITORING.
 *
 * Formulir mengirim bagian berulang (riwayat kerja, organisasi, sertifikasi,
 * kenalan) sebagai LIST berisi objek. array_is_list() bernilai TRUE untuk
 * bentuk itu, sehingga uraikanJawaban() dulu meneruskannya ke implode() —
 * yang melempar "Array to string conversion".
 *
 * Akibatnya bukan sekadar satu baris isian yang kosong: seluruh endpoint
 * tahapPelamar() tertangkap catch(\Throwable) dan membalas
 * HTTP 500 "Gagal memuat detail tahap", sehingga admin tidak bisa membuka
 * detail tahap MANA PUN pada pelamar yang formulirnya memakai bagian
 * berulang. Bentuk data di sini disalin apa adanya dari lamaran sungguhan.
 */
class MonitoringUraikanJawabanTest extends TestCase
{
    /** @return array<int, array{label: string, nilai: mixed}> */
    private function uraikan(array $jawaban): array
    {
        $m = new ReflectionMethod(MonitoringController::class, 'uraikanJawaban');
        $m->setAccessible(true);

        return $m->invoke(null, json_encode($jawaban, JSON_UNESCAPED_UNICODE));
    }

    public function test_bagian_berulang_tidak_menggagalkan_detail_tahap(): void
    {
        $hasil = $this->uraikan([
            'kenalan_evo_list' => [
                ['kenalan_nama' => 'FRANS', 'kenalan_hubungan' => 'TEMAN KULIAH'],
                ['kenalan_nama' => 'BUDI', 'kenalan_hubungan' => 'REKAN KERJA'],
            ],
        ]);

        $this->assertSame([
            ['label' => 'Kenalan Evo List #1', 'nilai' => 'Kenalan Nama: FRANS, Kenalan Hubungan: TEMAN KULIAH'],
            ['label' => 'Kenalan Evo List #2', 'nilai' => 'Kenalan Nama: BUDI, Kenalan Hubungan: REKAN KERJA'],
        ], $hasil);
    }

    /**
     * Baris berulang yang ditambahkan lalu dibiarkan kosong tetap dilaporkan
     * sebagai barisnya sendiri — "pelamar membuka satu baris dan tidak
     * mengisinya" adalah informasi, bukan alasan menyembunyikan datanya.
     */
    public function test_baris_berulang_yang_kosong_tetap_tampil_tanpa_nilai(): void
    {
        $hasil = $this->uraikan([
            'riwayat_kerja_magang' => [
                [
                    'kerja_perusahaan' => null,
                    'kerja_jabatan' => null,
                    'kerja_periode' => null,
                    'kerja_uraian' => null,
                ],
            ],
        ]);

        $this->assertSame(
            [['label' => 'Riwayat Kerja Magang #1', 'nilai' => null]],
            $hasil,
        );
    }

    /** Daftar biasa (pilihan ganda) tetap digabung koma seperti sebelumnya. */
    public function test_daftar_skalar_tetap_digabung_dengan_koma(): void
    {
        $hasil = $this->uraikan(['keahlian' => ['PHP', 'Vue', 'SQL']]);

        $this->assertSame(
            [['label' => 'Keahlian', 'nilai' => 'PHP, Vue, SQL']],
            $hasil,
        );
    }

    /** Objek tunggal (bukan list) tetap disajikan sebagai JSON seperti sebelumnya. */
    public function test_objek_tunggal_tetap_json(): void
    {
        $hasil = $this->uraikan(['alamat' => ['kota' => 'Bandung', 'pos' => '40123']]);

        $this->assertSame(
            [['label' => 'Alamat', 'nilai' => '{"kota":"Bandung","pos":"40123"}']],
            $hasil,
        );
    }

    /** Boolean dan string kosong tetap diperlakukan seperti sebelumnya. */
    public function test_boolean_dan_kosong_tidak_berubah(): void
    {
        $hasil = $this->uraikan(['bersedia' => true, 'menolak' => false, 'catatan' => '']);

        $this->assertSame([
            ['label' => 'Bersedia', 'nilai' => 'Ya'],
            ['label' => 'Menolak', 'nilai' => 'Tidak'],
            ['label' => 'Catatan', 'nilai' => null],
        ], $hasil);
    }
}
