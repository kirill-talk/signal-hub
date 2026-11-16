<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /**
     * Elements that must be completely removed along with their contents.
     *
     * @var list<string>
     */
    private const DROP_ELEMENTS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'svg',
        'canvas',
        'noscript',
        'meta',
        'link',
        'img',
    ];

    /**
     * Safe structural and formatting tags preserved in entry summaries.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'a',
        'ul',
        'ol',
        'li',
        'strong',
        'b',
        'em',
        'i',
        'code',
        'pre',
        'blockquote',
        'h1',
        'h2',
        'h3',
        'h4',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="__sh_root__">'
                .$html
                .'</div></body></html>';

            $dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $dom->getElementById('__sh_root__');

        if (! $root instanceof DOMElement) {
            return null;
        }

        $this->sanitizeNodeChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim((string) preg_replace("/\r\n|\r/", "\n", $output));
        $output = trim((string) preg_replace("/\n{3,}/", "\n\n", $output));

        if ($output === '' || trim(html_entity_decode(strip_tags($output), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
            return null;
        }

        return $output;
    }

    public function plainText(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $decoded = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = (string) preg_replace('/\s+/u', ' ', $decoded);

        return trim($collapsed);
    }

    private function sanitizeNodeChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_ELEMENTS, true)) {
                $parent->removeChild($child);
                continue;
            }

            $this->sanitizeNodeChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrapElement($parent, $child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);
        }
    }

    private function unwrapElement(DOMNode $parent, DOMElement $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $safeHref = null;

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = trim($element->getAttribute('href'));

            if (preg_match('#^https?://#i', $href) === 1) {
                $safeHref = $href;
            }
        }

        while ($element->attributes->length > 0) {
            $attribute = $element->attributes->item(0);
            if ($attribute === null) {
                break;
            }
            $element->removeAttributeNode($attribute);
        }

        if ($safeHref !== null) {
            $element->setAttribute('href', $safeHref);
            $element->setAttribute('rel', 'noopener noreferrer');
            $element->setAttribute('target', '_blank');
        }
    }
}
