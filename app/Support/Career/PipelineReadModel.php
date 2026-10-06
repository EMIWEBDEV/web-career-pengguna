<?php

namespace App\Support\Career;

use Illuminate\Support\Collection;

/**
 * WEB CAREER — READ MODEL BUCKET OPERASIONAL PIPELINE (read-only).
 *
 * Satu sumber kebenaran "kandidat ini sedang menunggu siapa/apa", dipakai
 * Worklist (lewat PipelineProgress yang sudah ada), Monitoring Rekrutmen, dan
 * Dashboard Admin. Prioritas bucket TIDAK simetris — HOLD mendominasi seluruh
 * status lain karena selama ditahan, tak ada keputusan yang boleh diambil
 * (lihat LamaranService::evaluasiTahap).
 */
class PipelineReadModel
{
    /**
     * @param  object  $lamaran  baris N_WEB_CAREERS_Lamaran (butuh ->Status)
     * @param  Collection  $tahapList  seluruh Lamaran_Tahap milik lamaran ini
     * @param  iterable  $subAktif  Lamaran_Tahap_Tes milik tahap aktif
     * @param  array  $masterHasil  [Kode => object] Master_Hasil_Keputusan, dari LamaranService::masterHasilKeputusan()
     */
    public static function bucket(object $lamaran, Collection $tahapList, iterable $subAktif = [], array $masterHasil = []): array
    {
        $tAktif = PipelineProgress::tahapAktif($lamaran, $tahapList);

        // ── OUTCOME TERMINAL ────────────────────────────────────────────────
        // Lamaran sudah tidak BERJALAN dan tidak sedang pasca-penerimaan.
        if (! in_array($lamaran->Status, ['BERJALAN', 'LULUS'], true)) {
            $def = $masterHasil[$lamaran->Status] ?? null;

            return [
                'bucket' => 'TERMINAL',
                'outcomeOlehKandidat' => ($def->Flag_Oleh_Kandidat ?? 'T') === 'Y',
                'outcomeKode' => $lamaran->Status,
            ];
        }

        // ── HOLD mendominasi seluruh status lain ─────────────────────────────
        if ($tAktif && ($tAktif->Hold_Flag ?? 'T') === 'Y') {
            return ['bucket' => 'HOLD', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        // ── PASCAPENERIMAAN: LULUS tapi masih ada tahap BERJALAN ────────────
        if ($lamaran->Status === 'LULUS' && $tAktif) {
            return ['bucket' => 'PASCAPENERIMAAN', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        if ($lamaran->Status === 'LULUS') {
            return ['bucket' => 'TERMINAL', 'outcomeOlehKandidat' => false, 'outcomeKode' => 'LULUS'];
        }

        // ── SIAP_DIPUTUS: mesin sudah selesai, admin tinggal memutus ────────
        if ($tAktif && ($tAktif->Siap_Diputus ?? 'N') === 'Y') {
            return ['bucket' => 'SIAP_DIPUTUS', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        // ── AKTIVITAS: siapa yang ditunggu ──────────────────────────────────
        //
        // ══ KENAPA BUKAN "YANG ONLINE MENANG" ═════════════════════════════
        //
        // Dulu baris ini berbunyi `$belumSelesai->first(Provider === 'THIRD_PARTY')`,
        // dan begitu ada satu aktivitas online yang belum selesai, hasilnya
        // selalu MENUNGGU_JADWAL / MENUNGGU_HASIL. Cabang TINDAKAN_ADMIN
        // praktis tak pernah tercapai pada tahap campuran.
        //
        // Akibatnya nyata: tahap "FGD + Psikotes + Wawancara" yang FGD-nya sudah
        // berlangsung dan tinggal dicatat kehadiran serta hasilnya TIDAK PERNAH
        // muncul sebagai giliran admin — cukup karena psikotesnya belum
        // dijadwalkan. Pekerjaan yang sudah menumpuk tidak terlihat di layar
        // mana pun, dan tak ada galat yang menandainya.
        //
        // Sekarang seluruh aktivitas dinilai, lalu bucket-nya dipilih dengan
        // aturan YANG PALING MENUNTUT TINDAKAN MENANG — urutan yang sama dengan
        // yang dipakai portal kandidat (`keadaanTahap` di LamaranDetail.vue),
        // supaya apa yang dibaca admin dan apa yang dibaca kandidat tidak
        // pernah bercerita berbeda tentang tahap yang sama.
        //
        //   1. TINDAKAN_ADMIN  aktivitas manual menunggu dicatat tim;
        //   2. MENUNGGU_JADWAL aktivitas online belum punya sesi — yang ditunggu
        //                      sesinya terbentuk, bukan keputusan atas kandidat,
        //                      jadi sengaja dibedakan dari TINDAKAN_ADMIN;
        //   3. MENUNGGU_HASIL  aktivitas online sudah terjadwal, hasil menyusul.
        //
        // ══ PERAN TIDAK IKUT MENYARING ══
        //
        // Dulu di sini ada `Peran === 'PENENTU'`. Peran menjawab pertanyaan
        // LAIN: apakah aktivitas ini menentukan lulus/gagalnya tahap. Ia tidak
        // menjawab apakah ada yang harus dikerjakan — psikotes INFORMATIF tetap
        // harus dijadwalkan, dikerjakan, dan dicatat.
        //
        // Penyaring itu juga berselisih dengan mesin keputusannya sendiri:
        // evaluasiTahap() menahan tahap lewat `Wajib`, bukan `Peran`, sehingga
        // aktivitas INFORMATIF ber-Wajib='Y' benar-benar menahan tahap tapi
        // tidak pernah terhitung menunggu siapa pun. Tahap semacam itu tampil
        // BERPROSES — "tidak ada apa-apa" — padahal kandidatnya sedang membaca
        // "menunggu dijadwalkan" di portal.
        $belumSelesai = collect($subAktif)->filter(
            fn ($s) => ($s->Flag_Selesai ?? 'N') !== 'Y'
        )->values();

        if ($belumSelesai->isEmpty()) {
            return ['bucket' => 'BERPROSES', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        // ── TAHAP BERURUTAN: HANYA YANG TERDEPAN YANG BERARTI ────────────────
        //
        // Pada tahap yang aktivitasnya dikunci berurutan, wawancara di posisi
        // ketiga belum boleh dikerjakan siapa pun selama FGD belum selesai.
        // Menghitungnya sebagai "menunggu tindakan admin" mengirim tim
        // mengerjakan sesuatu yang gerbangnya masih tertutup.
        //
        // Penghalangnya dihitung dari SELURUH aktivitas — termasuk yang
        // disembunyikan dari kandidat seperti background check — karena yang
        // menahan giliran adalah kenyataan alurnya, bukan apa yang terlihat.
        //
        // Urutan sumbernya sudah menaik (Monitoring & worklist sama-sama
        // `orderBy('Urutan')`), tapi diurutkan lagi di sini supaya kebenarannya
        // tidak bergantung pada kueri pemanggil.
        if (self::urutanMengunci($tAktif->Urutan_Aktivitas ?? null)) {
            $terdepan = collect($subAktif)
                ->sortBy('Urutan')
                ->first(fn ($s) => ($s->Flag_Selesai ?? 'N') !== 'Y');

            // Yang terdepan ITULAH yang sedang ditunggu — apa pun perannya, dan
            // termasuk bila ia aktivitas yang disembunyikan dari kandidat.
            // Selama ia belum selesai, tak ada yang boleh menyalipnya.
            $belumSelesai = $terdepan ? collect([$terdepan]) : collect();

            if ($belumSelesai->isEmpty()) {
                return ['bucket' => 'BERPROSES', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
            }
        }

        $daring = fn ($s) => ($s->Provider ?? '') === 'THIRD_PARTY';

        if ($belumSelesai->contains(fn ($s) => ! $daring($s))) {
            return ['bucket' => 'TINDAKAN_ADMIN', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        if ($belumSelesai->contains(fn ($s) => $daring($s) && ! self::sesiSiap($s))) {
            return ['bucket' => 'MENUNGGU_JADWAL', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        return ['bucket' => 'MENUNGGU_HASIL', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
    }

    /**
     * Sesi ujian aktivitas ini SUNGGUH sudah jadi — bukan sekadar tertaut.
     *
     * `Penjadwalan_Tahap_Id` berarti "aktivitas ini sudah diikutkan ke sebuah
     * sesi", bukan "sesinya siap dipakai". Di antara keduanya ada jeda nyata:
     * penerbitan token ke HCLearn berjalan di antrean dan bisa gagal. Selama
     * jeda itu tautannya sudah ada tapi kandidat belum punya apa pun untuk
     * dibuka — portalnya jujur berbunyi "menunggu dijadwalkan", sementara papan
     * admin dulu berbunyi "menunggu hasil", seolah kandidat sedang mengerjakan
     * ujian yang tokennya bahkan belum terbit.
     *
     * `Token_Terbit` disediakan MetrikRekrutmen::aktivitasDenganToken(). Bila
     * pemanggil tidak membawanya, keputusannya turun ke ukuran lama — pemanggil
     * lama tetap berperilaku persis seperti sebelumnya, bukan tiba-tiba salah.
     */
    private static function sesiSiap(object $s): bool
    {
        if (property_exists($s, 'Token_Terbit')) {
            return $s->Token_Terbit === 'Y';
        }

        return (bool) ($s->Penjadwalan_Tahap_Id ?? null);
    }

    /**
     * Mode urutan aktivitas ini MENGUNCI giliran?
     *
     * Dibaca dari `Master_Mode_Urutan.Flag_Berurutan`, bukan dari membandingkan
     * Kode dengan 'BERURUTAN'. Bedanya baru terasa saat mode ketiga ditambahkan
     * lewat master: dengan perbandingan Kode, mode baru itu diam-diam
     * berperilaku seperti PARALEL di setiap tempat yang lupa diubah.
     *
     * Di-cache per proses — dipanggil sekali per baris papan.
     */
    private static ?Collection $modeUrutan = null;

    public static function urutanMengunci(?string $kode): bool
    {
        self::$modeUrutan ??= \Illuminate\Support\Facades\DB::table('N_WEB_CAREERS_Master_Mode_Urutan')
            ->get()->keyBy('Kode');

        return (self::$modeUrutan->get((string) $kode)->Flag_Berurutan ?? 'T') === 'Y';
    }
}
