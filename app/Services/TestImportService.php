<?php

namespace App\Services;

use App\Models\Test;
use App\Models\TestSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;

class TestImportService
{
    public function __construct(private readonly WordTestParser $parser, private readonly AdminTestService $tests) {}

    public function templatePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        $doc = new PhpWord;
        $section = $doc->addSection();
        foreach ($this->templateLines() as [$text,$bold]) {
            $run = $section->addTextRun();
            $run->addText($text, ['bold' => $bold]);
        }WordWriter::createWriter($doc, 'Word2007')->save($path);

        return $path;
    }

    public function parse(UploadedFile $file): array
    {
        return $this->parser->parse($file->getRealPath());
    }

    public function commit(array $data, User $user): Test
    {
        $test = $this->tests->create(['title' => $data['title'], 'skill' => $data['skill'], 'category_id' => $data['category_id'] ?? null], $user);
        $parts = collect($data['parts'])->values()->map(fn ($part, $pi) => ['order' => $pi, 'title' => $part['title'] ?? 'Phần '.($pi + 1), 'sections' => collect($part['sections'] ?? [])->values()->map(fn ($s, $si) => ['order' => $si, 'instruction' => $s['instruction'] ?? null, 'passage' => $s['passage'] ?? null, 'audio_url' => $s['audio_url'] ?? null, 'max_plays' => $s['max_plays'] ?? null, 'questions' => collect($s['questions'] ?? [])->values()->map(fn ($q, $qi) => ['order' => $qi, 'type' => $q['type'] ?? 'multiple_choice', 'content' => $q['content'] ?? null, 'explanation' => $q['explanation'] ?? null, 'images' => [], 'record_limit_seconds' => null, 'score' => 1, 'options' => collect($q['options'] ?? [])->map(fn ($o) => ['label' => $o['label'] ?? null, 'content' => $o['content'] ?? '', 'is_correct' => (bool) ($o['is_correct'] ?? false)])->all()])->all()])->all()])->all();
        $this->tests->saveStructure($test, ['parts' => $parts]);

        return $this->tests->refresh($test);
    }

    public function uploadSectionAudio(TestSection $section, UploadedFile $file): string
    {
        $path = $file->store('test-audio', 'public');
        $url = Storage::disk('public')->url($path);
        $this->tests->updateSectionAudio($section, $url);

        return $url;
    }

    private function templateLines(): array
    {
        return [['PART 1: Đọc hiểu', true], ['SECTION 1', true], ['==PASSAGE: Bài đọc mẫu', false], ['Lake Baikal is the deepest lake in the world.', false], ['==ENDPASSAGE', false], ['Câu 1. What is the capital of Vietnam?', false], ['A. Hanoi', false], ['B. Ho Chi Minh City', false], ['C. Da Nang', false], ['D. Hue', false], ['==DA: A', false], ['==LG: Hà Nội là thủ đô của Việt Nam.', false], ['Câu 2. She ___ to school yesterday.', false], ['==fill', false], ['==DA: went/walked', false], ['==LG: Quá khứ của "go" là "went".', false], ['Câu 3. Lake Baikal is in Russia.', false], ['A. True', false], ['B. False', false], ['C. Not Given', false], ['==DA: A', false], ['Câu 4. Write about your last holiday.', false], ['==essay', false], ['==LIMIT: 150', false]];
    }
}
