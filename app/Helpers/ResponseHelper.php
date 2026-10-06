<?php

namespace App\Helpers;

class ResponseHelper
{
    /**
     * Response sukses
     */
    public static function success($data = null, string $message = "Berhasil", int $status = 200)
    {
        return response()->json([
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'result'    => $data
        ], $status);
    }

    /**
     * Response error
     */
    public static function error(string $message = "Terjadi kesalahan", int $status = 500)
    {
        return response()->json([
            'success' => false,
            'status'  => $status,
            'message' => $message,
        ], $status);
    }
}
