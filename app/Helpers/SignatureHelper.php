<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SignatureHelper
{
    private static function buildUrl($endpoint, $params = [])
    {
        $domain = rtrim(config('services.frans.hcis_api.domain'), '/');
        // Pastikan tidak ada double slash
        $endpoint = ltrim($endpoint, '/');
        $url = $domain . '/' . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    private static function sign($url, $method, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $publicKey = config('services.frans.hcis_api.public');
        $secretKey = config('services.frans.hcis_api.secret');

        $timestamp = date('Y-m-d H:i:s');
        $nonce = Str::random(32);

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY) ?? '';

        $bodyJson = empty($body) ? '' : json_encode($body);
        $bodyHash = hash('sha256', $bodyJson);

        $payloadParts = [strtoupper($method), $path, $query, $timestamp, $nonce, $bodyHash, $publicKey];

        $payload = implode("\n", $payloadParts);
        $signature = hash_hmac('sha512', $payload, $secretKey);

        $headers = [
            'X-HC-Public' => $publicKey,
            'X-HC-Timestamp' => $timestamp,
            'X-HC-Nonce' => $nonce,
            'X-HC-Body-Hash' => $bodyHash,
            'X-HC-Signature' => $signature,
        ];

        if ($appId) {
            $headers['X-App-ID'] = $appId;
        }

        if ($kodeKaryawan) {
            $headers['X-Kode-Karyawan'] = $kodeKaryawan;
        }

        return [
            'headers' => $headers,
            'body' => $bodyJson,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET LIST TANPA PARAMETER
    |--------------------------------------------------------------------------
    */
    // Tambahkan parameter $endpoint di awal
    public static function get($endpoint, $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);
        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | GET LIST + SEARCH
    |--------------------------------------------------------------------------
    */
    public static function getWithParams($endpoint, $params = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint, $params);
        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | GET DETAIL
    |--------------------------------------------------------------------------
    */
    public static function getDetail($endpoint, $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);
        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public static function store($endpoint, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);
        return self::sign($url, 'POST', $body, $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE (POST)
    |--------------------------------------------------------------------------
    */
    public static function update($endpoint, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);
        return self::sign($url, 'POST', $body, $appId, $kodeKaryawan);
    }
}
