<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    /**
     * Return a paginated API Resource collection using the shared data/meta contract.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(
        AnonymousResourceCollection $resources,
        LengthAwarePaginator $paginator,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'data' => $resources,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                ...$meta,
            ],
        ]);
    }

    /**
     * Return a non-paginated API Resource collection.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function collection(AnonymousResourceCollection $resources, array $meta = []): JsonResponse
    {
        $payload = ['data' => $resources];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    /**
     * Keep existing v1 entity keys while ensuring the entity is serialized by JsonResource.
     * New endpoints should use the default `data` key.
     *
     * @param  array<string, mixed>  $additional
     */
    public static function resource(
        JsonResource $resource,
        string $key = 'data',
        int $status = 200,
        array $additional = [],
    ): JsonResponse {
        return response()->json([$key => $resource, ...$additional], $status);
    }

    /**
     * Return a mutation result that does not represent an Eloquent resource.
     *
     * @param  array<string, mixed>  $additional
     */
    public static function message(string $message, int $status = 200, array $additional = []): JsonResponse
    {
        return response()->json(['message' => $message, ...$additional], $status);
    }
}
