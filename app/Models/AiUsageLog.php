<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một lần gọi API chấm bài. Cộng dồn `cost_usd` trong tháng để đối chiếu với hạn mức cô
 * đặt ở Cài đặt — hết hạn mức thì ngừng gọi AI và báo cô, bài rơi về chấm tay như cũ.
 */
class AiUsageLog extends Model
{
    protected $fillable = [
        'test_attempt_id',
        'provider',
        'model',
        'kind',
        'input_tokens',
        'output_tokens',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
        ];
    }
}
