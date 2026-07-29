<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * File Excel mẫu để cô tải về rồi điền danh sách học sinh.
 * Cột: name (họ tên) · email · phone (SĐT) · class (tên lớp) · note (ghi chú).
 */
class StudentsTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, array<string, string>>
     */
    public function headings(): array
    {
        return ['name', 'email', 'phone', 'class', 'note'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['Nguyễn Văn A', 'vana@example.com', '0900000000', 'Lớp 12F', 'Ghi chú (không bắt buộc)'],
        ];
    }
}
