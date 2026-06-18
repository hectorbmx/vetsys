<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class RichTextSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'blockquote', 'a', 'span',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="report-content">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('report-content');
        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $class = $node->getAttribute('class');
            $href = $node->getAttribute('href');

            while ($node->attributes->length > 0) {
                $node->removeAttributeNode($node->attributes->item(0));
            }

            $classes = array_filter(preg_split('/\s+/', $class) ?: [], fn ($value) => preg_match('/^ql-(align-(center|right|justify)|indent-[1-8])$/', $value)
            );
            if ($classes) {
                $node->setAttribute('class', implode(' ', $classes));
            }

            if ($tag === 'a' && $this->isSafeLink($href)) {
                $node->setAttribute('href', $href);
            }
        }
    }

    private function isSafeLink(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
