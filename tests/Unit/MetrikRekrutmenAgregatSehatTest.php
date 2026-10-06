<?php

namespace Tests\Unit;

use App\Support\Career\MetrikRekrutmen;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * agregatSehat() memakai DATEDIFF(day,...)/GETDATE() (sintaks SQL Server) di
 * dalam SATU selectRaw() yang sama dengan kolom macet/siapTua/siap/nungguTes
 * yang dikecualikan HOLD. Menjalankannya sungguhan (->get()) di atas SQLite
 * gagal total ("no such column: day") sebelum satu baris pun terbaca, karena
 * PDO benar-benar dieksekusi.
 *
 * DB::connection()->pretend() menghindari itu TANPA reimplementasi method:
 * Connection::select() mengecek pretending() dan langsung `return []` SEBELUM
 * memanggil getPdoForSelect()->prepare($query) (lihat
 * vendor/laravel/framework/src/Illuminate/Database/Connection.php:412-431).
 * pretend() mengembalikan query log yang SQL-nya sudah disubstitusi bindingnya
 * (logQuery(), baris ~854-867) — jadi kita bisa memeriksa STRING SQL yang
 * benar-benar dihasilkan agregatSehat() tanpa PDO prepare/execute pernah
 * dipanggil. Tidak perlu tabel sungguhan sama sekali karena query tidak
 * pernah benar-benar dijalankan.
 */
class MetrikRekrutmenAgregatSehatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Supaya reconnectIfMissingConnection() tidak mencoba menyambung ke
        // sqlsrv produksi saat pretend() dijalankan.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    public function test_predikat_exclusion_hold_null_safe_dan_hanya_di_empat_kolom(): void
    {
        $queries = DB::connection()->pretend(function () {
            MetrikRekrutmen::agregatSehat([100]);
        });

        $this->assertCount(1, $queries, 'agregatSehat() harus menghasilkan tepat satu query select.');

        $sql = $queries[0]['query'];
        $predikat = "COALESCE(lt.Hold_Flag, 'T') <> 'Y'";

        // Predikat NULL-safe (bukan `lt.Hold_Flag <> 'Y'` telanjang — NULL <> 'Y'
        // adalah NULL di SQL, bukan TRUE, sehingga baris Hold_Flag NULL diam-diam
        // ikut terkecualikan padahal seharusnya hanya Hold_Flag='Y' yang dikecualikan).
        $this->assertSame(
            4,
            substr_count($sql, $predikat),
            'Predikat exclusion HOLD NULL-safe harus muncul persis 4x (macet, siapTua, siap, nungguTes).'
        );

        // Potong SQL per kolom berdasarkan alias yang berurutan, supaya predikat
        // bisa dibuktikan hadir di MASING-MASING empat kolom itu, dan TIDAK
        // menyusup ke kolom `aktif` (yang harus tetap menghitung semua baris,
        // termasuk yang HOLD).
        $posAktif = strpos($sql, 'as aktif,');
        $posMacet = strpos($sql, 'as macet,');
        $posSiapTua = strpos($sql, 'as siapTua,');
        $posSiap = strpos($sql, 'as siap,');
        $posNunggu = strpos($sql, 'as nungguTes,');
        $posMaxAging = strpos($sql, 'as maxAging');

        $this->assertNotFalse($posAktif);
        $this->assertNotFalse($posMacet);
        $this->assertNotFalse($posSiapTua);
        $this->assertNotFalse($posSiap);
        $this->assertNotFalse($posNunggu);
        $this->assertNotFalse($posMaxAging);

        $fragmenAktif = substr($sql, 0, $posAktif + strlen('as aktif,'));
        $fragmenMacet = substr($sql, $posAktif + strlen('as aktif,'), $posMacet + strlen('as macet,') - ($posAktif + strlen('as aktif,')));
        $fragmenSiapTua = substr($sql, $posMacet + strlen('as macet,'), $posSiapTua + strlen('as siapTua,') - ($posMacet + strlen('as macet,')));
        $fragmenSiap = substr($sql, $posSiapTua + strlen('as siapTua,'), $posSiap + strlen('as siap,') - ($posSiapTua + strlen('as siapTua,')));
        $fragmenNunggu = substr($sql, $posSiap + strlen('as siap,'), $posNunggu + strlen('as nungguTes,') - ($posSiap + strlen('as siap,')));

        $this->assertStringNotContainsString('Hold_Flag', $fragmenAktif, 'Kolom aktif (COUNT(*)) tidak boleh ikut mengecualikan HOLD — HOLD tetap masuk total aktif.');
        $this->assertStringContainsString($predikat, $fragmenMacet, 'Kolom macet harus mengecualikan HOLD (NULL-safe).');
        $this->assertStringContainsString($predikat, $fragmenSiapTua, 'Kolom siapTua harus mengecualikan HOLD (NULL-safe).');
        $this->assertStringContainsString($predikat, $fragmenSiap, 'Kolom siap harus mengecualikan HOLD (NULL-safe).');
        $this->assertStringContainsString($predikat, $fragmenNunggu, 'Kolom nungguTes harus mengecualikan HOLD (NULL-safe).');
    }
}
