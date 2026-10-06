<?php

namespace App\Helpers;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

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

    public static function successWithPagination($data, int $page, int $limit, int $total, string $message = "Berhasil", int $status = 200)
    {
        return response()->json([
            'success'     => true,
            'status'      => $status,
            'message'     => $message,
            'result'      => $data,
            'page'        => $page,
            'total_page'  => ceil($total / $limit),
            'total_data'  => $total,
        ], $status);
    }

    public static function successWithPaginationV2($data, int $page, int $limit, int $total, string $message = "Berhasil", int $status = 200)
    {
        $paginator = new LengthAwarePaginator(
            $data,
            $total,
            $limit,
            $page,
            [
                'path' => Request::url(),
                'query' => Request::query(),
            ]
        );

        $paginatorArray = $paginator->toArray();

        return response()->json([
            'success'    => true,
            'status'     => $status,
            'message'    => $message,
            'result'     => $paginatorArray['data'],
            'pagination' => [
                'total'          => $paginatorArray['total'],
                'per_page'       => $paginatorArray['per_page'],
                'current_page'   => $paginatorArray['current_page'],
                'total_pages'    => $paginatorArray['last_page'],
                'first_page_url' => $paginatorArray['first_page_url'],
                'last_page_url'  => $paginatorArray['last_page_url'],
                'next_page_url'  => $paginatorArray['next_page_url'],
                'prev_page_url'  => $paginatorArray['prev_page_url'],
                'from'           => $paginatorArray['from'],
                'to'             => $paginatorArray['to'],
                'links'          => $paginatorArray['links'],
            ],
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
