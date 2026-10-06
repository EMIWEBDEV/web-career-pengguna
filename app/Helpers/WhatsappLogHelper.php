<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\Whatsapp;

class WhatsappLogHelper
{
    public static function normalizeNumber($hp)
    {
        $hp = trim($hp);
        if (preg_match('/^0\d+$/', $hp)) $hp = '62' . substr($hp, 1);
        if (preg_match('/^\+62\d+$/', $hp)) $hp = substr($hp, 1);
        return $hp;
    }

    /**
     * Kirim pesan WhatsApp dan log hasilnya
     * @param callable|null $onSuccess  Callback jika sukses
     * @param callable|null $onFail     Callback jika gagal
     */
    public static function sendAndLog($kodeKaryawan, $nama, $hp, $noTransaksi, array $pesan, callable $onSuccess = null, callable $onFail = null)
    {
        // dd($kodeKaryawan, $nama, $hp, $noTransaksi, $pesan, $onSuccess, $onFail);
        $hp = self::normalizeNumber($hp);
        $isValidNumber = preg_match('/^62[1-9][0-9]{7,13}$/', $hp);

        if (!$hp || !$isValidNumber) {
            $msg = 'Nomor HP tidak valid! Format harus 628XXXXXXXXXX';
            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, 'FAILED', $msg);
            Log::warning("Nomor WA tidak valid: {$hp}", compact('nama', 'kodeKaryawan'));
            if ($onFail) $onFail('invalid_number', null);
            return ['status' => 'failed', 'reason' => $msg];
        }

        $pesan['to'] = $hp;

        try {
            $response = Whatsapp::send_message($pesan, [
                'kode_karyawan' => $kodeKaryawan,
                'nama' => $nama,
                'hp' => $hp,
                'no_transaksi' => $noTransaksi,
                'module' => 'HCIS',
                'source_context' => 'WHATSAPP_LOG_HELPER',
            ]);

            // $status = (
            //     isset($response['messages'][0]['message_status']) &&
            //     in_array($response['messages'][0]['message_status'], ['accepted', 'sent'])
            // ) ? 'SUCCESS' : 'FAILED';
            $status = (
                isset($response['messages'][0]['id']) ||
                in_array($response['messages'][0]['message_status'] ?? null, ['accepted', 'sent'])
            ) ? 'SUCCESS' : 'FAILED';

            $messageId = $response['messages'][0]['id'] ?? null;
            $errorMessage = $status === 'SUCCESS' ? null : json_encode($response, JSON_UNESCAPED_UNICODE);

            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, $status, $errorMessage, $messageId, $pesan);

            if ($status === 'SUCCESS') {
                Log::channel('WhatsappHelperLog')->info("WA sukses dikirim ke {$hp}", ['response' => $response]);
                if ($onSuccess) $onSuccess($response);
            } else {
                Log::channel('WhatsappHelperLog')->warning("Gagal kirim WA ke {$hp}", ['response' => $response]);
                if ($onFail) $onFail('api_failed', $response);
            }

            return ['status' => strtolower($status), 'response' => $response];
        } catch (\Throwable $e) {
            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, 'FAILED', $e->getMessage(), null, $pesan);
            Log::channel('WhatsappHelperLog')->error("Gagal kirim WA (exception): " . $e->getMessage());
            if ($onFail) $onFail('exception', $e);
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    public static function insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, $status, $errorMessage = null, $responseMessage = null, $pesan = null)
    {
        DB::table('Whatsapp_Log')->insert([
            'Kode_Karyawan_Destination'    => $kodeKaryawan,
            'Nama'             => $nama,
            'HP'               => $hp,
            'No_Transaksi'     => $noTransaksi,
            'Status'           => $status,
            'Error_Message'    => $errorMessage,
            'Response_Message' => $responseMessage,
            'RawMessage'       => $pesan ? print_r($pesan, true) : null,
            'RawMessageJson'   => $pesan ? json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
            'Is_Retried'       => 0,
            'Created_At'       => now(),
        ]);
    }
}
