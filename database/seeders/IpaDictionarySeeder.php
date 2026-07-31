<?php

namespace Database\Seeders;

use App\Models\IpaEntry;
use Illuminate\Database\Seeder;

/**
 * Seed từ điển phiên âm để tra IPA tự động. Nguồn: từ vựng phổ thông SGK lớp 6–9 (open data).
 * Số lượng seed hiện tại ~72 từ — đủ cho tra + auto-fill khi import; có thể bổ sung sau.
 */
class IpaDictionarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->words() as [$word, $ipa, $pos]) {
            IpaEntry::updateOrCreate(['word' => strtolower($word)], ['ipa' => $ipa, 'pos' => $pos]);
        }
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function words(): array
    {
        return [
            ['apple', '/ˈæp.əl/', 'n.'], ['banana', '/bəˈnɑː.nə/', 'n.'], ['orange', '/ˈɒr.ɪndʒ/', 'n.'],
            ['water', '/ˈwɔː.tər/', 'n.'], ['school', '/skuːl/', 'n.'], ['teacher', '/ˈtiː.tʃər/', 'n.'],
            ['student', '/ˈstjuː.dənt/', 'n.'], ['book', '/bʊk/', 'n.'], ['pencil', '/ˈpen.səl/', 'n.'],
            ['friend', '/frend/', 'n.'], ['family', '/ˈfæm.əl.i/', 'n.'], ['house', '/haʊs/', 'n.'],
            ['city', '/ˈsɪt.i/', 'n.'], ['country', '/ˈkʌn.tri/', 'n.'], ['journey', '/ˈdʒɜː.ni/', 'n.'],
            ['souvenir', '/ˌsuː.vənˈɪər/', 'n.'], ['weather', '/ˈweð.ər/', 'n.'], ['holiday', '/ˈhɒl.ə.deɪ/', 'n.'],
            ['festival', '/ˈfes.tɪ.vəl/', 'n.'], ['museum', '/mjuːˈziː.əm/', 'n.'], ['hospital', '/ˈhɒs.pɪ.təl/', 'n.'],
            ['library', '/ˈlaɪ.brər.i/', 'n.'], ['garden', '/ˈɡɑː.dən/', 'n.'], ['kitchen', '/ˈkɪtʃ.ɪn/', 'n.'],
            ['bridge', '/brɪdʒ/', 'n.'], ['mountain', '/ˈmaʊn.tɪn/', 'n.'], ['river', '/ˈrɪv.ər/', 'n.'],
            ['island', '/ˈaɪ.lənd/', 'n.'], ['beach', '/biːtʃ/', 'n.'], ['forest', '/ˈfɒr.ɪst/', 'n.'],
            ['animal', '/ˈæn.ɪ.məl/', 'n.'], ['elephant', '/ˈel.ɪ.fənt/', 'n.'], ['dolphin', '/ˈdɒl.fɪn/', 'n.'],
            ['beautiful', '/ˈbjuː.tɪ.fəl/', 'adj.'], ['happy', '/ˈhæp.i/', 'adj.'], ['difficult', '/ˈdɪf.ɪ.kəlt/', 'adj.'],
            ['important', '/ɪmˈpɔː.tənt/', 'adj.'], ['delicious', '/dɪˈlɪʃ.əs/', 'adj.'], ['famous', '/ˈfeɪ.məs/', 'adj.'],
            ['expensive', '/ɪkˈspen.sɪv/', 'adj.'], ['comfortable', '/ˈkʌm.fə.tə.bəl/', 'adj.'], ['modern', '/ˈmɒd.ən/', 'adj.'],
            ['traditional', '/trəˈdɪʃ.ən.əl/', 'adj.'], ['friendly', '/ˈfrend.li/', 'adj.'], ['careful', '/ˈkeə.fəl/', 'adj.'],
            ['travel', '/ˈtræv.əl/', 'v.'], ['discover', '/dɪˈskʌv.ər/', 'v.'], ['imagine', '/ɪˈmædʒ.ɪn/', 'v.'],
            ['remember', '/rɪˈmem.bər/', 'v.'], ['celebrate', '/ˈsel.ɪ.breɪt/', 'v.'], ['practise', '/ˈpræk.tɪs/', 'v.'],
            ['develop', '/dɪˈvel.əp/', 'v.'], ['protect', '/prəˈtekt/', 'v.'], ['improve', '/ɪmˈpruːv/', 'v.'],
            ['communicate', '/kəˈmjuː.nɪ.keɪt/', 'v.'], ['describe', '/dɪˈskraɪb/', 'v.'], ['explain', '/ɪkˈspleɪn/', 'v.'],
            ['listen', '/ˈlɪs.ən/', 'v.'], ['answer', '/ˈɑːn.sər/', 'v.'], ['question', '/ˈkwes.tʃən/', 'n.'],
            ['quickly', '/ˈkwɪk.li/', 'adv.'], ['carefully', '/ˈkeə.fəl.i/', 'adv.'], ['usually', '/ˈjuː.ʒu.ə.li/', 'adv.'],
            ['together', '/təˈɡeð.ər/', 'adv.'], ['already', '/ɔːlˈred.i/', 'adv.'], ['finally', '/ˈfaɪ.nəl.i/', 'adv.'],
            ['information', '/ˌɪn.fəˈmeɪ.ʃən/', 'n.'], ['environment', '/ɪnˈvaɪ.rən.mənt/', 'n.'], ['technology', '/tekˈnɒl.ə.dʒi/', 'n.'],
            ['adventure', '/ədˈven.tʃər/', 'n.'], ['knowledge', '/ˈnɒl.ɪdʒ/', 'n.'], ['weekend', '/ˌwiːkˈend/', 'n.'],
        ];
    }
}
