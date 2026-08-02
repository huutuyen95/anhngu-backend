<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CardsImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['term', 'meaning', 'ipa', 'pos', 'example'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['souvenir', 'quà lưu niệm', '/ˌsuː.vənˈɪər/', 'n.', 'I bought a *souvenir* for my mom.'],
            ['journey', 'chuyến đi', '', 'n.', 'It was a long *journey*.'],
        ];
    }
}
