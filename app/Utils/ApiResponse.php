<?php

namespace App\Utils;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return self::error($message, 403);
    }

    public static function serverError(string $message = 'ServerError'): JsonResponse
    {
        return self::error($message, 500);
    }

    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => [
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'path' => request()->url(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'message' => 'Success'
        ]);
    }
}
