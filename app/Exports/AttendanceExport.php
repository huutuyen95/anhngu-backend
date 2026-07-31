<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Họ tên', 'Email', 'Điểm danh', 'Nhận xét'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $label = ['on_time' => 'Đúng giờ', 'late' => 'Muộn', 'absent' => 'Nghỉ'];

        return array_map(fn ($r) => [
            $r['name'],
            $r['email'],
            $label[$r['status']] ?? '—',
            $r['comment'] ?? '',
        ], $this->rows);
    }
}
