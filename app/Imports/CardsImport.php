<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Đọc file import thẻ từ vựng. Header (dòng 1): term | meaning | ipa | pos | example.
 */
class CardsImport implements ToArray, WithHeadingRow
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function array(array $rows): array
    {
        return $rows;
    }
}
