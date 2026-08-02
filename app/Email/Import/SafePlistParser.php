<?php

declare(strict_types=1);

namespace Katakata\Email\Import;

use DOMDocument;
use DOMElement;
use RuntimeException;

final class SafePlistParser
{
    public const MAX_BYTES = 262144;

    /** @return mixed */
    public function parse(string $contents): mixed
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('Configuration profile is empty or exceeds 256 KiB.');
        }
        if (!str_starts_with(ltrim($contents), '<')) {
            throw new RuntimeException('Signed or binary configuration profiles are not supported; export an unsigned XML plist profile.');
        }
        if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw new RuntimeException('Configuration profile contains unsupported declarations.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$loaded || $document->documentElement?->tagName !== 'plist') {
                throw new RuntimeException('Configuration profile is not a valid XML plist.');
            }
            $root = $this->firstElement($document->documentElement);
            if (!$root instanceof DOMElement) {
                throw new RuntimeException('Configuration profile has no plist payload.');
            }
            return $this->value($root);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function value(DOMElement $element): mixed
    {
        return match ($element->tagName) {
            'dict' => $this->dictionary($element),
            'array' => $this->array($element),
            'string', 'data', 'date' => $element->textContent,
            'integer' => (int) trim($element->textContent),
            'true' => true,
            'false' => false,
            default => throw new RuntimeException('Configuration profile contains an unsupported plist value.'),
        };
    }

    /** @return array<string,mixed> */
    private function dictionary(DOMElement $element): array
    {
        $children = $this->elements($element);
        $result = [];
        for ($index = 0, $count = count($children); $index < $count; $index += 2) {
            $key = $children[$index] ?? null;
            $value = $children[$index + 1] ?? null;
            if (!$key instanceof DOMElement || $key->tagName !== 'key' || !$value instanceof DOMElement) {
                throw new RuntimeException('Configuration profile contains an invalid dictionary.');
            }
            $result[$key->textContent] = $this->value($value);
        }
        return $result;
    }

    /** @return list<mixed> */
    private function array(DOMElement $element): array
    {
        return array_map(fn (DOMElement $child): mixed => $this->value($child), $this->elements($element));
    }

    /** @return list<DOMElement> */
    private function elements(DOMElement $element): array
    {
        $result = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $result[] = $child;
            }
        }
        return $result;
    }

    private function firstElement(DOMElement $element): ?DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }
        return null;
    }
}
