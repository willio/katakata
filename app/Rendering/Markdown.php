<?php

declare(strict_types=1);

namespace Katakata\Rendering;

/**
 * Small, dependency-free Markdown renderer for published prose.
 *
 * Raw HTML is always escaped. The supported subset is deliberately
 * narrow: headings, paragraphs, blockquotes, ordered/unordered lists,
 * fenced code, links, emphasis, strong text, and inline code.
 */
final class Markdown
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', str_replace(["\r\n", "\r"], "\n", trim($markdown))) ?: [];
        $html = [];
        $paragraph = [];
        $list = null;
        $inCode = false;
        $code = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = function () use (&$list, &$html): void {
            if ($list !== null) {
                $html[] = '</' . $list . '>';
                $list = null;
            }
        };

        foreach ($lines as $line) {
            if (preg_match('/^\x60\x60\x60(?:[a-zA-Z0-9_-]+)?\s*$/', $line)) {
                $flushParagraph();
                $flushList();

                if ($inCode) {
                    $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                    $code = [];
                }

                $inCode = !$inCode;
                continue;
            }

            if ($inCode) {
                $code[] = $line;
                continue;
            }

            if (trim($line) === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                $flushList();
                $level = strlen($match[1]);
                $html[] = "<h{$level}>" . $this->inline($match[2]) . "</h{$level}>";
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $match)) {
                $flushParagraph();
                $flushList();
                $html[] = '<blockquote><p>' . $this->inline($match[1]) . '</p></blockquote>';
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                if ($list !== 'ul') {
                    $flushList();
                    $list = 'ul';
                    $html[] = '<ul>';
                }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                if ($list !== 'ol') {
                    $flushList();
                    $list = 'ol';
                    $html[] = '<ol>';
                }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            $flushList();
            $paragraph[] = trim($line);
        }

        if ($inCode) {
            $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
        }

        $flushParagraph();
        $flushList();

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $tokens = [];
        $text = preg_replace_callback('/\x60([^\x60]+)\x60/', static function (array $match) use (&$tokens): string {
            $key = "\x1A" . count($tokens) . "\x1A";
            $tokens[$key] = '<code>' . e($match[1]) . '</code>';

            return $key;
        }, $text) ?? $text;

        $text = e($text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)\)/', function (array $match): string {
            $href = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');

            if (!preg_match('#^(?:https?://|mailto:|/|\#)#i', $href)) {
                return $match[1];
            }

            return '<a href="' . e($href) . '">' . $match[1] . '</a>';
        }, $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;

        return strtr($text, $tokens);
    }
}
