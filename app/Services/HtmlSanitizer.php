<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * Làm sạch HTML body của tài liệu/bài giảng: chỉ giữ thẻ an toàn + iframe YouTube.
 * Loại bỏ <script>, thuộc tính on*, href/src javascript:, iframe lạ.
 */
class HtmlSanitizer
{
    private const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'span', 'div', 'iframe', 'hr', 'code', 'pre',
    ];

    /** @var array<string, array<int, string>> */
    private const ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public function clean(?string $html): string
    {
        if (! $html || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__root');
        if ($root) {
            $this->walk($root);
        }

        $out = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private function walk(DOMElement $node): void
    {
        // Duyệt ngược để xoá node an toàn khi lặp.
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if ($tag === 'iframe' && ! $this->isYoutube($child->getAttribute('src'))) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
            $this->walk($child);
        }
    }

    private function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ATTRS[$tag] ?? [];
        $toRemove = [];
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            if (! in_array($name, $allowed, true)) {
                $toRemove[] = $attr->name;

                continue;
            }
            if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*(javascript|data):/i', $value)) {
                $toRemove[] = $attr->name;
            }
        }
        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isYoutube(string $src): bool
    {
        return (bool) preg_match('#^https?://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/)#i', $src);
    }
}
