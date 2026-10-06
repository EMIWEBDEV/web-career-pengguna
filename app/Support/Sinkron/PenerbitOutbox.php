<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PENERBIT OUTBOX — memindahkan baris MENUNGGU ke Pub/Sub, urut per akun.
 *
 * Dua jalur, kode yang sama:
 *   - job TerbitkanOutbox (queue wcp-terbit) sesudah transaksi aksi kandidat
 *     commit — cepat;
 *   - penyapu tiap menit (Cloud Scheduler → POST /api/tugas/terbit-outbox) —
 *     pasti: apa pun yang terlewat jalur cepat ditangkap di sini.
 *
 * URUTAN. Baris satu kunci urut (akun) diterbitkan berurutan Id, dalam satu
 * permintaan publish berkunci urut sama. Baris yang gagal menahan baris
 * sesudahnya dari akun yang sama — peristiwa akun itu tidak boleh menyalip.
 *
 * GANDA. Dua penerbit pada kunci yang sama saling menunggu (UPDLOCK); yang
 * belakangan menemukan barisnya sudah TERBIT. Kalaupun satu pesan terbit dua
 * kali (penerbit mati sesudah publish, sebelum menandai), Event_Id-nya sama
 * dan kotak masuk zona dalam menjawab DUPLIKAT.
 *
 * Koneksi `penerbit` (peran wc_publik_penerbit): baca Outbox + ubah kolom
 * status terbit — tidak menyentuh tabel bisnis apa pun.
 */
final class PenerbitOutbox
{
    private const T = Outbox::TABEL;

    /** Paling banyak baris satu kunci dalam satu permintaan publish. */
    private const PER_KUNCI = 50;

    public function __construct(private PubSubKlien $pubsub) {}

    /** @return array{terbit: int, gagal: int, kunci: int} */
    public function terbitkanKunci(string $kunciUrut): array
    {
        return $this->satuKunci($kunciUrut) + ['kunci' => 1];
    }

    /**
     * Penyapu: setiap kunci yang punya baris MENUNGGU jatuh tempo, mulai dari
     * yang tertua.
     *
     * @return array{terbit: int, gagal: int, kunci: int}
     */
    public function sapu(?int $batas = null): array
    {
        $batas ??= (int) config('sinkron.penerbit.batas_sapu', 200);

        $kunci = DB::connection('penerbit')->table(self::T)
            ->where('Status', 'MENUNGGU')
            ->where(fn ($q) => $q->whereNull('Coba_Lagi_At')->orWhere('Coba_Lagi_At', '<=', $this->kini()))
            ->groupBy('Kunci_Urut')
            ->orderByRaw('MIN([Id_Outbox])')
            ->limit($batas)
            ->pluck('Kunci_Urut');

        $hasil = ['terbit' => 0, 'gagal' => 0, 'kunci' => 0];
        foreach ($kunci as $k) {
            $r = $this->satuKunci((string) $k);
            $hasil['terbit'] += $r['terbit'];
            $hasil['gagal'] += $r['gagal'];
            $hasil['kunci']++;
            if ($hasil['terbit'] >= $batas) {
                break;
            }
        }

        return $hasil;
    }

    /** @return array{terbit: int, gagal: int} */
    private function satuKunci(string $kunciUrut): array
    {
        $db = DB::connection('penerbit');

        return $db->transaction(function () use ($db, $kunciUrut) {
            // rowlock+updlock TANPA holdlock: baris baru akun ini (kandidat
            // sedang beraksi) tidak ikut tertahan menunggu publish selesai.
            $baris = $db->table(self::T)
                ->lock('with(rowlock,updlock)')
                ->where('Kunci_Urut', $kunciUrut)
                ->where('Status', 'MENUNGGU')
                ->orderBy('Id_Outbox')
                ->limit(self::PER_KUNCI)
                ->get(['Id_Outbox', 'Event_Id', 'Idempotency_Key', 'Jenis', 'Versi_Skema', 'Kunci_Urut', 'Muatan',
                    'Percobaan_Terbit', 'Coba_Lagi_At', 'Created_At']);

            if ($baris->isEmpty()) {
                return ['terbit' => 0, 'gagal' => 0];
            }

            // Baris terdepan masih menunggu jedanya → seluruh akun ini menunggu.
            $depan = $baris->first();
            if ($depan->Coba_Lagi_At && Carbon::parse($depan->Coba_Lagi_At, 'UTC')->gt($this->kini())) {
                return ['terbit' => 0, 'gagal' => 0];
            }

            try {
                $ids = $this->pubsub->terbitkan($baris->map(fn ($b) => $this->pesan($b))->all());
            } catch (\Throwable $e) {
                $ke = (int) $depan->Percobaan_Terbit + 1;
                $jeda = min(
                    (int) config('sinkron.penerbit.jeda_maks_detik', 1800),
                    (int) config('sinkron.penerbit.jeda_awal_detik', 30) * (2 ** min(10, $ke - 1)),
                );
                $db->table(self::T)->where('Id_Outbox', $depan->Id_Outbox)->update([
                    'Percobaan_Terbit' => $ke,
                    'Coba_Lagi_At' => $this->kini()->addSeconds($jeda),
                    'Galat_Terakhir' => Str::limit($e->getMessage(), 390, ''),
                ]);
                Log::warning("[OUTBOX] terbit gagal ({$kunciUrut}, percobaan {$ke}, coba lagi {$jeda} dtk): ".$e->getMessage());

                return ['terbit' => 0, 'gagal' => 1];
            }

            foreach ($baris->values() as $i => $b) {
                $db->table(self::T)
                    ->where('Id_Outbox', $b->Id_Outbox)
                    ->where('Status', 'MENUNGGU')
                    ->update([
                        'Status' => 'TERBIT',
                        'Terbit_At' => $this->kini(),
                        'Pubsub_Message_Id' => Str::limit((string) $ids[$i], 60, ''),
                        'Galat_Terakhir' => null,
                    ]);
            }

            return ['terbit' => $baris->count(), 'gagal' => 0];
        });
    }

    /**
     * Satu pesan Pub/Sub. Event_Id ikut di atribut (penyaring worker) DAN di
     * badan pesan (yang disimpan kotak masuk); kunci urut = akun.
     */
    private function pesan(object $b): array
    {
        $eventId = strtolower((string) $b->Event_Id);
        $amplop = [
            'event_id' => $eventId,
            'jenis' => (string) $b->Jenis,
            'versi_skema' => (int) $b->Versi_Skema,
            'idempotency_key' => (string) $b->Idempotency_Key,
            'kunci_urut' => (string) $b->Kunci_Urut,
            'dibuat_at' => (string) $b->Created_At,
            'muatan' => json_decode((string) $b->Muatan, true),
        ];

        return [
            'data' => base64_encode(json_encode($amplop, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'attributes' => [
                'event_id' => $eventId,
                'jenis' => (string) $b->Jenis,
                'versi_skema' => (string) $b->Versi_Skema,
            ],
            'orderingKey' => (string) $b->Kunci_Urut,
        ];
    }

    /** Waktu UTC — kolom waktu Outbox diisi SYSUTCDATETIME(). */
    private function kini(): Carbon
    {
        return Carbon::now('UTC');
    }
}
