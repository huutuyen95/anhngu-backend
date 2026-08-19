<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

/**
 * Đọc file .docx theo mẫu, tách thành cây Part → Section → Question → Option.
 * Cú pháp: "PART n: ..." · "SECTION n" · "Câu n."/"Question n." · "A." "B."… ·
 * "==DA: A" hoặc "==DA: taught/teached" · "==LG: ..." · "==fill" · "==essay" ·
 * "==LIMIT: 150" · "==PASSAGE: ... ==ENDPASSAGE". Giữ in đậm/gạch chân → <b>/<u>.
 */
class WordTestParser
{
    /**
     * @return array{parts: array<int, mixed>, questions: array<int, mixed>, summary: array<string, int>}
     */
    public function parse(string $filePath): array
    {
        $lines = $this->readLines($filePath);

        $parts = [];
        $pi = -1;
        $si = -1;
        $qi = -1;
        $qNo = 0;
        $capturingPassage = false;
        $passageBuffer = [];

        $newPart = function (string $title) use (&$parts, &$pi, &$si, &$qi) {
            $parts[] = ['title' => $title, 'sections' => []];
            $pi = count($parts) - 1;
            $si = -1;
            $qi = -1;
        };
        $newSection = function () use (&$parts, &$newPart, &$pi, &$si, &$qi) {
            if ($pi < 0) {
                $newPart('Phần 1');
            }
            $parts[$pi]['sections'][] = ['passage' => null, 'audio_url' => null, 'max_plays' => null, 'questions' => []];
            $si = count($parts[$pi]['sections']) - 1;
            $qi = -1;
        };
        $ensureSection = function () use (&$newSection, &$si) {
            if ($si < 0) {
                $newSection();
            }
        };

        foreach ($lines as $line) {
            $plain = trim($line['plain']);
            $html = trim($line['html']);
            if ($plain === '') {
                continue;
            }

            if ($capturingPassage) {
                if (preg_match('/^==ENDPASSAGE/i', $plain)) {
                    $ensureSection();
                    $parts[$pi]['sections'][$si]['passage'] = trim(implode("\n", $passageBuffer));
                    $capturingPassage = false;
                    $passageBuffer = [];
                } else {
                    $passageBuffer[] = $html;
                }

                continue;
            }

            if (preg_match('/^PART\s+\d+/iu', $plain)) {
                $newPart($plain);

                continue;
            }
            if (preg_match('/^SECTION\s+\d+/iu', $plain)) {
                $newSection();

                continue;
            }
            if (preg_match('/^==PASSAGE\s*:?\s*(.*)$/i', $plain, $m)) {
                $ensureSection();
                $capturingPassage = true;
                $passageBuffer = trim($m[1]) !== '' ? [trim($m[1])] : [];

                continue;
            }
            if (preg_match('/^(?:Câu|Question)\s+\d+/iu', $plain)) {
                $ensureSection();
                $qNo++;
                $parts[$pi]['sections'][$si]['questions'][] = [
                    'order' => count($parts[$pi]['sections'][$si]['questions']),
                    'type' => 'multiple_choice',
                    'content' => $this->stripPrefixHtml($html),
                    'explanation' => null,
                    'word_limit' => null,
                    'options' => [],
                    '_n' => $qNo,
                    '_da' => null,
                ];
                $qi = count($parts[$pi]['sections'][$si]['questions']) - 1;

                continue;
            }

            if ($qi < 0) {
                continue;
            }
            $q = &$parts[$pi]['sections'][$si]['questions'][$qi];

            if (preg_match('/^([A-H])\s*[.)]\s*(.*)$/u', $plain, $m)) {
                $q['options'][] = ['label' => strtoupper($m[1]), 'content' => trim($this->stripPrefixHtml($html)), 'is_correct' => false];
            } elseif (preg_match('/^==DA\s*:?\s*(.*)$/i', $plain, $m)) {
                $q['_da'] = trim($m[1]);
            } elseif (preg_match('/^==LG\s*:?\s*(.*)$/i', $plain, $m)) {
                $q['explanation'] = trim($m[1]);
            } elseif (preg_match('/^==fill/i', $plain)) {
                $q['type'] = 'fill_blank';
            } elseif (preg_match('/^==essay/i', $plain)) {
                $q['type'] = 'writing';
            } elseif (preg_match('/^==LIMIT\s*:?\s*(\d+)/i', $plain, $m)) {
                $q['word_limit'] = (int) $m[1];
            } else {
                $q['content'] = trim(($q['content'] ?? '')."\n".$html);
            }
            unset($q);
        }

        return $this->finalize($parts);
    }

    /**
     * Áp ==DA vào options, chuyển sang cấu trúc chuẩn + báo cáo trạng thái từng câu.
     *
     * @param  array<int, mixed>  $parts
     * @return array{parts: array<int, mixed>, questions: array<int, mixed>, summary: array<string, int>}
     */
    private function finalize(array $parts): array
    {
        $questions = [];
        $summary = ['ok' => 0, 'warn' => 0, 'error' => 0];

        foreach ($parts as &$part) {
            foreach ($part['sections'] as &$section) {
                foreach ($section['questions'] as &$q) {
                    $reasons = [];
                    $da = $q['_da'];
                    $type = $q['type'];

                    if ($type === 'fill_blank') {
                        // ==DA: taught/teached → mỗi đáp án là 1 option đúng.
                        $answers = $da !== null ? array_filter(array_map('trim', explode('/', $da))) : [];
                        $q['options'] = array_map(fn ($a) => ['label' => null, 'content' => $a, 'is_correct' => true], array_values($answers));
                        if ($answers === []) {
                            $reasons[] = 'Thiếu ==DA (đáp án đúng)';
                        }
                    } elseif ($type === 'writing') {
                        $q['options'] = [];
                    } else {
                        // multiple_choice/select: ==DA: A hoặc A/C
                        $correct = $da !== null ? array_map(fn ($s) => strtoupper(trim($s)), explode('/', $da)) : [];
                        foreach ($q['options'] as &$opt) {
                            $opt['is_correct'] = in_array($opt['label'], $correct, true);
                        }
                        unset($opt);
                        if ($da === null) {
                            $reasons[] = 'Thiếu ==DA (đáp án đúng)';
                        } elseif (! collect($q['options'])->contains('is_correct', true)) {
                            $reasons[] = 'Không khớp đáp án đúng nào';
                        }
                    }

                    if ($type !== 'writing' && blank($q['explanation'])) {
                        // Thiếu lời giải chỉ là cảnh báo (warn), không phải lỗi.
                        $status = $reasons === [] ? 'warn' : 'error';
                        if ($status === 'warn') {
                            $reasons[] = 'Thiếu ==LG (lời giải)';
                        }
                    } else {
                        $status = $reasons === [] ? 'ok' : 'error';
                    }

                    $summary[$status]++;
                    $questions[] = [
                        'n' => $q['_n'],
                        'text' => Str::limit(strip_tags($q['content'] ?? ''), 120),
                        'type' => $type,
                        'status' => $status,
                        'reasons' => $reasons,
                    ];

                    unset($q['_n'], $q['_da']);
                }
                unset($q);
            }
            unset($section);
        }
        unset($part);

        return ['parts' => $parts, 'questions' => $questions, 'summary' => $summary];
    }

    /**
     * @return array<int, array{plain: string, html: string}>
     */
    private function readLines(string $filePath): array
    {
        $phpWord = IOFactory::load($filePath);
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                if ($el instanceof TextRun) {
                    $plain = '';
                    $html = '';
                    foreach ($el->getElements() as $child) {
                        if (! method_exists($child, 'getText')) {
                            continue;
                        }
                        $text = $child->getText();
                        if (! is_string($text)) {
                            continue;
                        }
                        $plain .= $text;
                        $t = htmlspecialchars($text);
                        $font = method_exists($child, 'getFontStyle') ? $child->getFontStyle() : null;
                        if (is_object($font)) {
                            if ($font->isBold()) {
                                $t = "<b>{$t}</b>";
                            }
                            if ($font->getUnderline() && $font->getUnderline() !== 'none') {
                                $t = "<u>{$t}</u>";
                            }
                        }
                        $html .= $t;
                    }
                    $lines[] = ['plain' => $plain, 'html' => $html];
                } elseif (method_exists($el, 'getText')) {
                    $text = $el->getText();
                    $text = is_string($text) ? $text : '';
                    $lines[] = ['plain' => $text, 'html' => htmlspecialchars($text)];
                }
            }
        }

        return $lines;
    }

    /** Bỏ tiền tố "Câu n." / "A." khỏi chuỗi HTML (dựa trên phần text đầu). */
    private function stripPrefixHtml(string $html): string
    {
        return preg_replace('/^(?:<[^>]+>)*\s*(?:Câu|Question)\s+\d+\s*[.:]?\s*|^(?:<[^>]+>)*\s*[A-H]\s*[.)]\s*/u', '', $html) ?? $html;
    }
}
