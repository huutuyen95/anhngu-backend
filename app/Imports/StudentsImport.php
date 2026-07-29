<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Đọc file import học sinh. Header chuẩn (dòng 1): name | email | phone | class | note.
 * Trả về mảng dòng đã keyed theo header để StudentService xử lý (preview/commit).
 */
class StudentsImport implements ToArray, WithHeadingRow
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
