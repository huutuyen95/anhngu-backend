<?php

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_paginated_response_has_the_shared_data_and_meta_shape(): void
    {
        $items = collect([['id' => 11], ['id' => 12]]);
        $paginator = new LengthAwarePaginator($items, 12, 5, 2);

        $response = ApiResponse::paginated(
            JsonResource::collection($items),
            $paginator,
            ['status_counts' => ['todo' => 3]],
        );

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'data' => [['id' => 11], ['id' => 12]],
            'meta' => [
                'current_page' => 2,
                'last_page' => 3,
                'per_page' => 5,
                'from' => 6,
                'to' => 7,
                'total' => 12,
                'status_counts' => ['todo' => 3],
            ],
        ], $response->getData(true));
    }

    public function test_resource_response_keeps_the_v1_key_and_additional_data(): void
    {
        $response = ApiResponse::resource(
            new JsonResource(['id' => 7]),
            'article',
            201,
            ['warning' => null],
        );

        $this->assertSame(201, $response->status());
        $this->assertSame([
            'article' => ['id' => 7],
            'warning' => null,
        ], $response->getData(true));
    }

    public function test_message_response_can_include_mutation_metadata(): void
    {
        $response = ApiResponse::message('Đã xoá.', additional: ['deleted' => 2]);

        $this->assertSame([
            'message' => 'Đã xoá.',
            'deleted' => 2,
        ], $response->getData(true));
    }
}
