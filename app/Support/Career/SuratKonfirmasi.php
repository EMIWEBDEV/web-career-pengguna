<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — ISI SUREL KONFIRMASI KEHADIRAN.
 *
 * Satu baris jejak berstatus ANTRE = satu surel. Kelas ini merakit surel itu
 * SAAT DIKIRIM, dari data terkini — dan memutuskan surel itu masih pantas
 * berangkat atau sudah basi (jadwalnya sudah diganti, kandidat sudah menjawab).
 * Surel basi ditandai LEWAT, tidak pernah dikirim.
 *
 * Tiga templat EVO Mail:
 *   undangan-jadwal   pengingat konfirmasi, jadwal baru dari permintaan
 *   tanggapan-jadwal  tanda terima jawaban, permintaan ditolak, jadwal ditunda,
 *                     kabar terbaru penundaan
 *   kabar-tim         kabar segera ke PIC (akan hadir / minta jadwal lain /
 *                     mundur)
 */
final class SuratKonfirmasi
{
    /**
     * @return array{0: ?array{kepada: string, template: string, data: array}, 1: ?string} [surel, alasan dilewati]
     */
    public static function susun(object $jejak, array $data): array
    {
        $jenis = $data['surel'] ?? null;
        $tesId = (int) $jejak->Lamaran_Tahap_Tes_Id;
        $versi = $jejak->Versi !== null ? (int) $jejak->Versi : null;

        // Jadwal ditunda / info penundaan diperbarui: barisnya sudah
        // dikosongkan, surelnya dirakit dari ringkasan yang dibekukan saat
        // penundaan + info penundaan di baris jejaknya.
        if ($jenis === KonfirmasiJadwal::S_DITUNDA || $jenis === KonfirmasiJadwal::S_TUNDA_INFO) {
            return self::tunda($tesId, $data, $jenis === KonfirmasiJadwal::S_TUNDA_INFO);
        }

        $sub = KonfirmasiJadwal::konteks($tesId);
        if (! $sub) {
            return [null, 'Aktivitas tidak ditemukan.'];
        }

        if ($jenis === KonfirmasiJadwal::S_KABAR_TIM) {
            return self::kabarTim($sub, $jejak, $data);
        }

        if (($sub->StatusLamaran ?? '') !== 'BERJALAN' && ($data['jenis'] ?? null) !== KonfirmasiJadwal::MUNDUR) {
            return [null, 'Lamaran sudah tidak berjalan.'];
        }
        // Versi sudah diganti: surel untuk jadwal lama hanya membingungkan —
        // undangan versi baru sudah menyusul.
        if ($versi !== null && (int) ($sub->Jadwal_Versi ?? 0) !== $versi) {
            return [null, 'Jadwal sudah diganti versi baru.'];
        }
        if (empty($sub->KandidatEmail)) {
            return [null, 'Tanpa alamat email kandidat.'];
        }

        $crm = KonfirmasiJadwal::crm($tesId, (int) $sub->Jadwal_Versi);
        $status = KonfirmasiJadwal::statusEfektif($sub->Konfirmasi_Status, $crm->Batas_Konfirmasi ?? null);

        return match ($jenis) {
            KonfirmasiJadwal::S_PENGINGAT => $status === KonfirmasiJadwal::MENUNGGU
                ? [self::undangan($tesId, [
                    'pengingat_konfirmasi' => true,
                    'pengingat_ke' => $data['ke'] ?? null,
                    // Sisa waktu dihitung SAAT surel berangkat, bukan saat
                    // diantrekan. Kunci sendiri — `sisa_teks` sudah dipakai
                    // pengingat batas unggah MCU dengan arti berbeda.
                    'sisa_konfirmasi_teks' => ! empty($crm->Batas_Konfirmasi)
                        ? PengingatKonfirmasi::sisaTeks(now(), Carbon::parse($crm->Batas_Konfirmasi))
                        : null,
                ]), null]
                : [null, 'Kandidat sudah menjawab atau batasnya lewat.'],
            KonfirmasiJadwal::S_JADWAL_BARU => [self::undangan($tesId, [
                'jadwal_baru' => true,
                'konfirmasi_tercatat' => $status === KonfirmasiJadwal::AKAN_HADIR,
                'catatan_tim' => $data['catatanTim'] ?? null,
            ]), null],
            KonfirmasiJadwal::S_TANDA_TERIMA => [self::tanggapan($sub, $crm, $data['jenis'] ?? $status, $jejak, $data), null],
            KonfirmasiJadwal::S_DITOLAK => [self::tanggapan($sub, $crm, 'DITOLAK', $jejak, $data), null],
            default => [null, 'Jenis surel tidak dikenali.'],
        };
    }

    /**
     * Kabar penundaan ke kandidat — templat tanggapan-jadwal jenis DITUNDA:
     * alasan, perkiraan jadwal pengganti (atau "akan dikabarkan"), pesan tim.
     */
    private static function tunda(int $tesId, array $data, bool $pembaruan): array
    {
        // Jadwal pengganti sudah terbit sebelum surel ini berangkat → basi;
        // undangan jadwal penggantilah yang dibaca kandidat. Baris lama
        // (sebelum status DITUNDA ada) berstatus NULL — tetap dianggap ditunda.
        $sub = KonfirmasiJadwal::konteks($tesId);
        if ($sub && (! empty($sub->Jadwal_Mulai)
            || ! in_array($sub->Konfirmasi_Status ?? null, [KonfirmasiJadwal::DITUNDA, null], true))) {
            return [null, 'Jadwal pengganti sudah terbit.'];
        }
        if ($sub && ($sub->StatusLamaran ?? '') !== 'BERJALAN') {
            return [null, 'Lamaran sudah tidak berjalan.'];
        }

        $kepada = ($data['ringkas']['kepada'] ?? null) ?: ($sub->KandidatEmail ?? null);
        if (empty($kepada)) {
            return [null, 'Tanpa alamat email kandidat.'];
        }

        return [['kepada' => $kepada, 'template' => 'tanggapan-jadwal', 'data' => self::isiTunda($data, $sub, $pembaruan)], null];
    }

    /**
     * Isi surel penundaan dari data baris jejak TUNDA / TUNDA_INFO — murni,
     * tanpa memeriksa apakah jadwalnya masih ditunda (itu tugas tunda()).
     */
    public static function isiTunda(array $data, ?object $sub, bool $pembaruan): array
    {
        $r = $data['ringkas'] ?? [];
        $t = $data['tunda'] ?? null;
        $perkiraan = fn (?array $i) => ! empty($i['perkiraan'])
            ? 'Diperkirakan '.KonfirmasiJadwal::tanggalTeks($i['perkiraan'])
            : 'Belum ditetapkan';

        return [
            'jenis' => 'DITUNDA',
            'pembaruan' => $pembaruan,
            'nama' => $r['nama'] ?? ($sub->KandidatNama ?? 'Kandidat'),
            'aktivitas' => $r['aktivitas'] ?? ($sub->Label ?? 'Jadwal'),
            'posisi' => $r['posisi'] ?? null,
            'tahap' => $r['tahap'] ?? null,
            'kode' => $r['kode'] ?? null,
            // Jadwal LAMA yang tidak berlaku lagi (dicoret di surel).
            'waktu_teks' => $r['waktu_teks'] ?? null,
            'alasan_tim' => $t ? KonfirmasiJadwal::alasanTundaTeks($t) : ($data['alasan'] ?? null),
            'perkiraan_teks' => ! empty($t['perkiraan']) ? KonfirmasiJadwal::tanggalTeks($t['perkiraan']) : null,
            // "Lainnya" sudah diwakili pesannya sebagai alasan — jangan diulang.
            'pesan_tim' => $t && empty($t['butuhCatatan']) ? ($t['pesan'] ?? null) : null,
            'sebelum_teks' => $pembaruan && ! empty($data['sebelum']) ? $perkiraan($data['sebelum']) : null,
        ];
    }

    /** Surel berbasis templat undangan — rinciannya dari UndanganJadwal::muatan(). */
    private static function undangan(int $tesId, array $bunyi): ?array
    {
        $m = UndanganJadwal::muatan($tesId);
        if (! $m || empty($m['data']['email'])) {
            return null;
        }
        $d = $m['data'];

        return [
            'kepada' => $d['email'],
            'template' => 'undangan-jadwal',
            'data' => $d + $bunyi + [
                'waktu_teks' => ! empty($d['batas_waktu'])
                    ? ($d['rentang_teks'] ?? '—')
                    : UndanganJadwal::waktuTeks($d['mulai'] ?? null, $d['selesai'] ?? null),
                'daring' => strtoupper((string) ($d['mode'] ?? '')) === 'DARING',
            ],
        ];
    }

    /** Tanda terima jawaban / permintaan ditolak — templat tanggapan-jadwal. */
    private static function tanggapan(object $sub, ?object $crm, string $jenis, object $jejak, array $data): array
    {
        $tesId = (int) $sub->Id_Lamaran_Tahap_Tes;
        $mode = UndanganJadwal::mode($sub->Jadwal_Mode);
        $tempat = \App\Http\Controllers\Career\Lamaran\LamaranController::tempatJadwal($sub);
        $daring = strtoupper((string) $sub->Jadwal_Mode) === 'DARING';

        $isi = [
            'jenis' => $jenis,
            'nama' => $sub->KandidatNama,
            'aktivitas' => $sub->Label,
            'posisi' => $sub->Posisi ?: $sub->ProgramNama,
            'program' => $sub->ProgramNama,
            'tahap' => $sub->TahapLabel,
            'kode' => $sub->LamaranKode,
            'waktu_teks' => UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai),
            'mode_nama' => $mode->Nama ?? $sub->Jadwal_Mode,
            'daring' => $daring,
            'link' => $daring ? $sub->Jadwal_Link : null,
            'lokasi' => ! $daring ? ($tempat['nama'] ?? $sub->Jadwal_Lokasi) : null,
            'alamat' => ! $daring ? ($tempat['alamatLengkap'] ?? null) : null,
            'maps_url' => ! $daring ? ($tempat['mapsUrl'] ?? null) : null,
            'kontak' => $sub->Jadwal_Kontak ?? null,
            'konfirmasi_url' => KonfirmasiJadwal::tautan($tesId, (int) $sub->Jadwal_Versi, (string) $sub->Jadwal_Mulai),
            'batas_konfirmasi_teks' => $crm ? UndanganJadwal::teksBatas($crm->Batas_Konfirmasi) : null,
        ];

        if ($jenis === KonfirmasiJadwal::JADWAL_LAIN && ! empty($jejak->Permintaan_Id)) {
            $p = DB::table(KonfirmasiJadwal::T_MINTA)->where('Id_Jadwal_Permintaan', $jejak->Permintaan_Id)->first();
            if ($p) {
                $bp = KonfirmasiJadwal::bentukPermintaan($p);
                $isi['alasan'] = $bp['alasan'];
                $isi['catatan'] = $bp['catatan'];
                $isi['usulan'] = array_column($bp['usulan'], 'teks');
                $isi['sla_teks'] = $bp['slaTeks'];
            }
        }

        if ($jenis === KonfirmasiJadwal::MUNDUR) {
            $isi['alasan'] = $data['alasanNama'] ?? null;
        }

        // KOMITMEN — sisa jatah ubah SESUDAH jawaban ini, atau bahwa jawabannya
        // kini final (dihitung saat surel berangkat: selalu keadaan terbaru).
        if (in_array($jenis, [KonfirmasiJadwal::AKAN_HADIR, KonfirmasiJadwal::JADWAL_LAIN], true)) {
            $aturan = KonfirmasiJadwal::aturanUbahUntuk($sub);
            $isi['ubah_teks'] = KonfirmasiJadwal::teksUbah($aturan, true);
            $isi['ubah_terkunci'] = $aturan['terkunci'];
        }

        if ($jenis === 'DITOLAK') {
            $isi['catatan_tim'] = $data['catatanTim'] ?? null;
            if (! empty($data['batas'])) {
                $isi['batas_konfirmasi_teks'] = UndanganJadwal::teksBatas($data['batas']);
            }
        }

        return ['kepada' => $sub->KandidatEmail, 'template' => 'tanggapan-jadwal', 'data' => $isi];
    }

    /** Kabar segera ke PIC loker — templat kabar-tim. */
    private static function kabarTim(object $sub, object $jejak, array $data): array
    {
        $penerima = self::penerimaTim($sub);
        if (! $penerima) {
            return [null, 'PIC loker tidak punya alamat email.'];
        }

        $isi = [
            'jenis' => $data['jenis'] ?? null,
            // AKAN_HADIR sesudah membatalkan permintaan jadwal lain.
            'dicabut' => (bool) ($data['dicabut'] ?? false),
            'nama_tim' => $penerima['nama'],
            'kandidat' => $sub->KandidatNama,
            'kode' => $sub->LamaranKode,
            'posisi' => $sub->Posisi ?: $sub->ProgramNama,
            'program' => $sub->ProgramNama,
            'tahap' => $sub->TahapLabel,
            'aktivitas' => $sub->Label,
            'waktu_teks' => UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai),
            'alasan' => $data['alasanNama'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'agenda_url' => rtrim((string) config('app.url'), '/').'/karir/agenda-seleksi',
        ];

        if (! empty($jejak->Permintaan_Id)) {
            $p = DB::table(KonfirmasiJadwal::T_MINTA)->where('Id_Jadwal_Permintaan', $jejak->Permintaan_Id)->first();
            if ($p) {
                $bp = KonfirmasiJadwal::bentukPermintaan($p);
                $isi['alasan'] = $bp['alasan'];
                $isi['catatan'] = $bp['catatan'];
                $isi['usulan'] = array_column($bp['usulan'], 'teks');
                $isi['sla_teks'] = $bp['slaTeks'];
            }
        }

        return [['kepada' => $penerima['email'], 'template' => 'kabar-tim', 'data' => $isi], null];
    }

    /**
     * Siapa yang dikabari — selalu dari basis data: PIC loker (akun admin
     * ber-Kode_Karyawan sama) → orang yang menjadwalkan.
     *
     * @return array{email: string, nama: string}|null
     */
    public static function penerimaTim(object $sub): ?array
    {
        if (! empty($sub->Pic_Kode_Karyawan)) {
            $pic = DB::table('N_WEB_CAREERS_Users')
                ->where('Kode_Karyawan', $sub->Pic_Kode_Karyawan)
                ->whereIn('Role', ['ADMIN', 'SUPERADMIN'])
                ->orderBy('Id_Users')
                ->first(['Nama', 'Email']);
            if ($pic && $pic->Email) {
                return ['email' => $pic->Email, 'nama' => $pic->Nama];
            }
        }

        if (! empty($sub->Jadwal_By_Id)) {
            $u = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $sub->Jadwal_By_Id)->first(['Nama', 'Email']);
            if ($u && $u->Email) {
                return ['email' => $u->Email, 'nama' => $u->Nama];
            }
        }

        return null;
    }
}
