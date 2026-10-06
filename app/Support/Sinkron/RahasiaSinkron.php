<?php

namespace App\Support\Sinkron;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Pembungkus bagian RAHASIA muatan Outbox — token verifikasi surel dan kode
 * reset sandi.
 *
 * Surelnya dikirim dari zona dalam, jadi kodenya harus ikut menyeberang. Yang
 * dicegah: siapa pun yang sekadar BISA MEMBACA database publik (mis. lewat
 * celah injeksi) memanen kode reset yang masih hidup dari tabel Outbox. Kunci
 * pembukanya (SINKRON_KUNCI_RAHASIA) dipegang aplikasi ini dan zona dalam —
 * bukan APP_KEY, dan tidak tersimpan di database.
 */
final class RahasiaSinkron
{
    public static function bungkus(array $isi): string
    {
        return self::enkriptor()->encryptString(json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function enkriptor(): Encrypter
    {
        $kunci = (string) config('sinkron.kunci_rahasia');
        if (str_starts_with($kunci, 'base64:')) {
            $kunci = (string) base64_decode(substr($kunci, 7), true);
        }
        if (strlen($kunci) !== 32) {
            throw new RuntimeException('SINKRON_KUNCI_RAHASIA belum diatur (base64:<32 byte>).');
        }

        return new Encrypter($kunci, 'AES-256-CBC');
    }
}
