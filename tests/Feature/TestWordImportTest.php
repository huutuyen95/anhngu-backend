<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\User;
use App\Services\WordTestParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class TestWordImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocx(): string
    {
        $doc = new PhpWord();
        $s = $doc->addSection();
        $add = function (string $text) use ($s) {
            $r = $s->addTextRun();
            $r->addText($text);
        };

        $add('PART 1: Đọc hiểu');
        $add('SECTION 1');
        $add('==PASSAGE: Bài đọc');
        $add('Lake Baikal is deep.');
        $add('==ENDPASSAGE');

        // Câu 1 — trắc nghiệm, có chữ in đậm trong đề
        $r = $s->addTextRun();
        $r->addText('Câu 1. What is the ');
        $r->addText('capital', ['bold' => true]);
        $r->addText(' of Vietnam?');
        $add('A. Hanoi');
        $add('B. Hue');
        $add('==DA: A');
        $add('==LG: Hà Nội.');

        // Câu 2 — điền từ, nhiều đáp án
        $add('Câu 2. She ___ to school.');
        $add('==fill');
        $add('==DA: went/walked');

        // Câu 3 — select True/False/Not Given, THIẾU ==DA (error)
        $add('Câu 3. Lake Baikal is in Russia.');
        $add('A. True');
        $add('B. False');
        $add('C. Not Given');

        // Câu 4 — writing
        $add('Câu 4. Write about your holiday.');
        $add('==essay');
        $add('==LIMIT: 150');

        $path = tempnam(sys_get_temp_dir(), 'w').'.docx';
        IOFactory::createWriter($doc, 'Word2007')->save($path);

        return $path;
    }

    public function test_parser_handles_four_types_passage_and_bold(): void
    {
        $result = app(WordTestParser::class)->parse($this->makeDocx());

        // 1 part, 1 section, passage nhận đúng
        $this->assertCount(1, $result['parts']);
        $section = $result['parts'][0]['sections'][0];
        $this->assertStringContainsString('Lake Baikal is deep', $section['passage']);

        $questions = $section['questions'];
        $this->assertCount(4, $questions);

        // Câu 1: trắc nghiệm, giữ in đậm, đáp án A đúng
        $this->assertEquals('multiple_choice', $questions[0]['type']);
        $this->assertStringContainsString('<b>capital</b>', $questions[0]['content']);
        $this->assertTrue(collect($questions[0]['options'])->firstWhere('label', 'A')['is_correct']);
        $this->assertFalse(collect($questions[0]['options'])->firstWhere('label', 'B')['is_correct']);

        // Câu 2: fill_blank với 2 đáp án đúng
        $this->assertEquals('fill_blank', $questions[1]['type']);
        $this->assertCount(2, $questions[1]['options']);
        $this->assertTrue(collect($questions[1]['options'])->every(fn ($o) => $o['is_correct']));

        // Câu 3: select nhưng thiếu ==DA → error
        $this->assertEquals('multiple_choice', $questions[2]['type']);

        // Câu 4: writing
        $this->assertEquals('writing', $questions[3]['type']);

        // Summary: câu 3 là error
        $this->assertGreaterThanOrEqual(1, $result['summary']['error']);
    }

    public function test_dry_run_does_not_write_db(): void
    {
        $teacher = User::factory()->teacher()->create();
        $before = Test::count();

        $file = new \Illuminate\Http\Testing\File('de.docx', fopen($this->makeDocx(), 'r'));

        $this->actingAs($teacher)->post('/api/v1/admin/tests/import-word', ['file' => $file])
            ->assertOk()
            ->assertJsonStructure(['parts', 'questions' => [['n', 'type', 'status', 'reasons']], 'summary' => ['ok', 'warn', 'error']]);

        $this->assertEquals($before, Test::count());
    }

    public function test_commit_creates_test_with_structure(): void
    {
        $teacher = User::factory()->teacher()->create();
        $parsed = app(WordTestParser::class)->parse($this->makeDocx());

        $res = $this->actingAs($teacher)->postJson('/api/v1/admin/tests/import-word/commit', [
            'title' => 'Đề import',
            'skill' => 'reading',
            'parts' => $parsed['parts'],
        ])->assertCreated()->json('test');

        $test = Test::find($res['id']);
        $this->assertNotNull($test);
        $this->assertEquals(4, $test->questionCount());
    }

    public function test_student_cannot_import(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->post('/api/v1/admin/tests/import-word')->assertForbidden();
    }
}
