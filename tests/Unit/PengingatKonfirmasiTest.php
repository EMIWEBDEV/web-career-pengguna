<?php

namespace Tests\Unit;

use App\Support\Career\PengingatKonfirmasi;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * PENGINGAT OTOMATIS KONFIRMASI KEHADIRAN — aturan waktunya (keputusan user
 * 3 Okt 2026): jam 05.00, 08.00, 12.00, 16.00 WIB, hari-H ikut sampai jam
 * mulai, paling banyak empat per hari.
 *
 * Tanpa basis data dan tanpa aplikasi Laravel: semua nilai config dioper
 * eksplisit. Pemilihan calon & klaim slot diuji terhadap staging (lihat
 * docs/03-10-2026/00-PANDUAN-pengingat-konfirmasi.md).
 */
class PengingatKonfirmasiTest extends TestCase
{
    public function test_jam_slot_dari_env_bawaan(): void
    {
        $this->assertSame([5, 8, 12, 16], PengingatKonfirmasi::jamSlot('05,08,12,16'));
    }

    public function test_jam_slot_toleran_tulisan_dan_membuang_yang_rusak(): void
    {
        // Spasi, urutan acak, duplikat, "05:00", jam di luar 0–23, dan teks
        // rusak — yang tersisa hanya jam yang sah, terurut, tanpa kembar.
        $this->assertSame([5, 8, 12, 16], PengingatKonfirmasi::jamSlot(' 16, 5,08:00 ; 12,99,abc,05 '));
        $this->assertSame([0, 23], PengingatKonfirmasi::jamSlot('23,0'));
        $this->assertSame([], PengingatKonfirmasi::jamSlot(''));
        $this->assertSame([7], PengingatKonfirmasi::jamSlot(['07', 7, '7:00']));
    }

    public function test_slot_memuat_seluruh_jamnya(): void
    {
        $jam = [5, 8, 12, 16];

        $this->assertSame(5, PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 05:00:00'), $jam));
        // Cloud Scheduler yang telat beberapa menit tetap masuk slotnya.
        $this->assertSame(5, PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 05:59:59'), $jam));
        $this->assertSame(16, PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 16:30:00'), $jam));
    }

    public function test_di_luar_jam_pengingat_tidak_ada_slot(): void
    {
        $jam = [5, 8, 12, 16];

        // Job Cloud Scheduler yang salah setel (mis. tiap jam) tidak bisa
        // menambah jumlah email: jam lain diabaikan.
        $this->assertNull(PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 06:00:00'), $jam));
        $this->assertNull(PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 04:59:59'), $jam));
        $this->assertNull(PengingatKonfirmasi::slotUntuk(Carbon::parse('2026-10-03 23:00:00'), $jam));
    }

    public function test_slot_kedaluwarsa_bila_tertahan_lama_di_antrean(): void
    {
        $tanggal = '2026-10-03';

        $this->assertFalse(PengingatKonfirmasi::slotKedaluwarsa($tanggal, 5, Carbon::parse('2026-10-03 05:03:00'), 120));
        $this->assertFalse(PengingatKonfirmasi::slotKedaluwarsa($tanggal, 5, Carbon::parse('2026-10-03 07:00:00'), 120));
        $this->assertTrue(PengingatKonfirmasi::slotKedaluwarsa($tanggal, 5, Carbon::parse('2026-10-03 07:00:01'), 120));
        // Batas bawah 15 menit — angka nol di .env tidak mematikan semua slot.
        $this->assertFalse(PengingatKonfirmasi::slotKedaluwarsa($tanggal, 8, Carbon::parse('2026-10-03 08:10:00'), 0));
    }

    public function test_sisa_waktu_rinci(): void
    {
        $dari = Carbon::parse('2026-10-03 12:00:00');

        // Contoh yang disepakati: undangan untuk 6 Okt 09.00.
        $this->assertSame('2 hari 21 jam lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-06 09:00:00')));
        $this->assertSame('2 hari lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-05 12:00:00')));
        $this->assertSame('5 jam lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-03 17:20:00')));
        $this->assertSame('40 menit lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-03 12:40:00')));
        $this->assertSame('kurang dari 1 menit lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-03 12:00:30')));
        // Batas lewat tidak menghasilkan angka negatif.
        $this->assertSame('kurang dari 1 menit lagi', PengingatKonfirmasi::sisaTeks($dari, Carbon::parse('2026-10-03 11:00:00')));
    }

    public function test_hari_h_masih_diingatkan_sebelum_jam_mulai(): void
    {
        // Jadwal 6 Okt 09.00 → slot 05.00 dan 08.00 hari itu masih sebelum
        // batas (= jam mulai); slot 12.00 sudah lewat.
        $batas = Carbon::parse('2026-10-06 09:00:00');

        $this->assertTrue(Carbon::parse('2026-10-06 05:00:00')->lt($batas));
        $this->assertTrue(Carbon::parse('2026-10-06 08:00:00')->lt($batas));
        $this->assertFalse(Carbon::parse('2026-10-06 12:00:00')->lt($batas));
        $this->assertSame('1 jam lagi', PengingatKonfirmasi::sisaTeks(Carbon::parse('2026-10-06 08:00:00'), $batas));
    }

    public function test_label_slot(): void
    {
        $this->assertSame('2026-10-03 05:00', PengingatKonfirmasi::labelSlot('2026-10-03', 5));
        $this->assertSame('2026-10-03 16:00', PengingatKonfirmasi::labelSlot('2026-10-03', 16));
    }
}
