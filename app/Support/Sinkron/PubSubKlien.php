<?php

namespace App\Support\Sinkron;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penerbit Pub/Sub lewat REST (topik wc-masuk).
 *
 * Aplikasi ini hanya MENERBITKAN — tidak berlangganan apa pun, jadi tidak
 * pernah menerima perintah dari luar lewat jalur ini. Akun layanannya cukup
 * roles/pubsub.publisher pada satu topik itu.
 *
 * Kredensial: di Cloud Run memakai akun layanan bawaan (metadata server);
 * di lokal memakai GOOGLE_CLOUD_KEY_FILE_PATH bila diisi.
 */
final class PubSubKlien
{
    private const LINGKUP = ['https://www.googleapis.com/auth/pubsub'];

    /**
     * @param  list<array{data: string, attributes: array<string, string>, orderingKey: string}>  $pesan
     * @return list<string> messageId, urutannya sama dengan $pesan
     */
    public function terbitkan(array $pesan): array
    {
        $cfg = config('sinkron.pubsub');
        if (empty($cfg['project']) || empty($cfg['topik'])) {
            throw new RuntimeException('Pub/Sub belum diatur (PUBSUB_PROJECT / PUBSUB_TOPIK).');
        }

        $url = rtrim((string) $cfg['endpoint'], '/')."/v1/projects/{$cfg['project']}/topics/{$cfg['topik']}:publish";
        $res = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(max(3, (int) $cfg['timeout']))
            ->post($url, ['messages' => $pesan]);

        if (! $res->successful()) {
            throw new RuntimeException('Pub/Sub menjawab HTTP '.$res->status().': '.Str::limit((string) $res->body(), 200));
        }

        $ids = (array) ($res->json('messageIds') ?? []);
        if (count($ids) !== count($pesan)) {
            throw new RuntimeException('Pub/Sub tidak mengembalikan messageId untuk setiap pesan.');
        }

        return array_values(array_map('strval', $ids));
    }

    /** Token akses, disimpan sampai lima menit sebelum kedaluwarsa. */
    private function token(): string
    {
        $simpan = Cache::get('sinkron:pubsub:token');
        if (is_string($simpan) && $simpan !== '') {
            return $simpan;
        }

        $berkas = config('filesystems.disks.karantina.key_file_path');
        $kred = $berkas && is_file($berkas)
            ? new ServiceAccountCredentials(self::LINGKUP, $berkas)
            : ApplicationDefaultCredentials::getCredentials(self::LINGKUP);

        $t = $kred->fetchAuthToken();
        $akses = (string) ($t['access_token'] ?? '');
        if ($akses === '') {
            throw new RuntimeException('Token Google untuk Pub/Sub tidak didapat.');
        }

        Cache::put('sinkron:pubsub:token', $akses, max(60, (int) ($t['expires_in'] ?? 3600) - 300));

        return $akses;
    }
}
