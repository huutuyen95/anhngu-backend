<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestResource;
use App\Models\Test;
use App\Models\TestSection;
use App\Services\AdminTestService;
use App\Services\WordTestParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TestImportController extends Controller
{
    public function __construct(
        private readonly WordTestParser $parser,
        private readonly AdminTestService $tests,
    ) {}

    /** Tải file .docx mẫu. */
    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        $this->writeTemplate($path);

        return response()->download($path, 'mau-de-thi.docx')->deleteFileAfterSend(true);
    }

    /** Bước phân tích — CHỈ parse, không ghi DB. */
    public function dryRun(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:docx,doc,zip'],
        ]);

        $path = $request->file('file')->getRealPath();
        $result = $this->parser->parse($path);

        return response()->json($result);
    }

    /** Ghi thật: tạo đề + toàn bộ cấu trúc đã (được sửa) gửi lên. */
    public function commit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'skill' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:test_categories,id'],
            'parts' => ['required', 'array', 'min:1'],
        ]);

        $test = $this->tests->create([
            'title' => $data['title'],
            'skill' => $data['skill'],
            'category_id' => $data['category_id'] ?? null,
        ], $request->user());

        $parts = collect($data['parts'])->values()->map(function ($part, $pi) {
            return [
                'order' => $pi,
                'title' => $part['title'] ?? 'Phần '.($pi + 1),
                'sections' => collect($part['sections'] ?? [])->values()->map(function ($section, $si) {
                    return [
                        'order' => $si,
                        'instruction' => $section['instruction'] ?? null,
                        'passage' => $section['passage'] ?? null,
                        'audio_url' => $section['audio_url'] ?? null,
                        'max_plays' => $section['max_plays'] ?? null,
                        'questions' => collect($section['questions'] ?? [])->values()->map(function ($q, $qi) {
                            return [
                                'order' => $qi,
                                'type' => $q['type'] ?? 'multiple_choice',
                                'content' => $q['content'] ?? null,
                                'explanation' => $q['explanation'] ?? null,
                                'images' => [],
                                'record_limit_seconds' => null,
                                'score' => 1,
                                'options' => collect($q['options'] ?? [])->map(fn ($o) => [
                                    'label' => $o['label'] ?? null,
                                    'content' => $o['content'] ?? '',
                                    'is_correct' => (bool) ($o['is_correct'] ?? false),
                                ])->all(),
                            ];
                        })->all(),
                    ];
                })->all(),
            ];
        })->all();

        $this->tests->saveStructure($test, ['parts' => $parts]);

        return response()->json(['test' => new TestResource($test->fresh())], 201);
    }

    /** Gắn/đổi file mp3 cho một section. */
    public function sectionAudio(Request $request, Test $test, TestSection $section): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:mp3,mpga,wav,m4a']]);

        $path = $request->file('file')->store('test-audio', 'public');
        $url = Storage::disk('public')->url($path);
        $section->update(['audio_url' => $url]);

        return response()->json(['audio_url' => $url]);
    }

    private function writeTemplate(string $path): void
    {
        $doc = new PhpWord();
        $s = $doc->addSection();
        foreach ($this->templateLines() as [$text, $bold]) {
            $run = $s->addTextRun();
            $run->addText($text, ['bold' => $bold]);
        }
        WordWriter::createWriter($doc, 'Word2007')->save($path);
    }

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    private function templateLines(): array
    {
        return [
            ['PART 1: Đọc hiểu', true],
            ['SECTION 1', true],
            ['==PASSAGE: Bài đọc mẫu', false],
            ['Lake Baikal is the deepest lake in the world.', false],
            ['==ENDPASSAGE', false],
            ['Câu 1. What is the capital of Vietnam?', false],
            ['A. Hanoi', false],
            ['B. Ho Chi Minh City', false],
            ['C. Da Nang', false],
            ['D. Hue', false],
            ['==DA: A', false],
            ['==LG: Hà Nội là thủ đô của Việt Nam.', false],
            ['Câu 2. She ___ to school yesterday.', false],
            ['==fill', false],
            ['==DA: went/walked', false],
            ['==LG: Quá khứ của "go" là "went".', false],
            ['Câu 3. Lake Baikal is in Russia.', false],
            ['A. True', false],
            ['B. False', false],
            ['C. Not Given', false],
            ['==DA: A', false],
            ['Câu 4. Write about your last holiday.', false],
            ['==essay', false],
            ['==LIMIT: 150', false],
        ];
    }
}
