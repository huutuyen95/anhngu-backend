<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClassReportExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $byStudent
     */
    public function __construct(private readonly array $byStudent) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Học viên', '% Hoàn thành', 'Lượt làm', 'Bài <60%', 'Buổi đi học', 'Điểm tuần trước'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['user']['name'],
            $r['completion_pct'].'%',
            $r['attempts'],
            $r['low_score_count'],
            $r['attended'],
            $r['last_week_score'],
        ], $this->byStudent);
    }
}
