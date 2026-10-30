<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Tiptap\Core\Node;
use Tiptap\Editor;
use Tiptap\Extensions\Color;
use Tiptap\Extensions\StarterKit;
use Tiptap\Extensions\TextAlign;
use Tiptap\Marks\Highlight;
use Tiptap\Marks\Link;
use Tiptap\Marks\TextStyle;
use Tiptap\Marks\Underline;
use Tiptap\Nodes\CodeBlock;
use Tiptap\Nodes\Image;
use Tiptap\Nodes\Table;
use Tiptap\Nodes\TableCell;
use Tiptap\Nodes\TableHeader;
use Tiptap\Nodes\TableRow;
use Tiptap\Nodes\TaskItem;
use Tiptap\Nodes\TaskList;

class ContentSanitizerService
{
    /**
     * Tags that must be removed completely along with their inner contents.
     */
    protected array $dangerousTags = [
        'script', 'style', 'link', 'meta', 'object', 'embed', 'applet',
        'svg', 'math', 'form', 'textarea', 'select', 'button', 'noscript',
    ];

    /**
     * Allowed tags.
     */
    protected array $allowedTags = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr', 'blockquote', 'pre', 'code',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'mark',
        'sub', 'sup', 'span', 'div',
        'a', 'img', 'iframe',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'input',
    ];

    /**
     * Convert TipTap JSON to HTML using tiptap-php.
     */
    public function renderFromJSON(array $json): string
    {
        $youtubeNode = new class extends Node {
            public static $name = 'youtube';

            public function addAttributes()
            {
                return [
                    'src' => ['default' => null],
                    'width' => ['default' => '100%'],
                    'height' => ['default' => 'auto'],
                ];
            }

            public function renderHTML($node, $HTMLAttributes = [])
            {
                $src = $node->attrs->src ?? '';

                return [
                    'div',
                    ['class' => 'aspect-video w-full my-6 overflow-hidden rounded-xl'],
                    [
                        'iframe',
                        array_merge($HTMLAttributes, [
                            'src' => $src,
                            'frameborder' => '0',
                            'allowfullscreen' => 'true',
                            'class' => 'w-full h-full',
                        ]),
                    ],
                ];
            }
        };

        $editor = new Editor([
            'extensions' => [
                new StarterKit(['codeBlock' => false]),
                new CodeBlock(),
                new Table(),
                new TableRow(),
                new TableHeader(),
                new TableCell(),
                new TaskList(),
                new TaskItem(),
                new Image(),
                new Underline(),
                new Highlight(),
                new Link(),
                new TextStyle(),
                new Color(),
                new TextAlign(['types' => ['heading', 'paragraph']]),
                $youtubeNode,
            ],
        ]);

        return $editor->setContent($json)->getHTML();
    }

    /**
     * Purify HTML with strict allowlist and security rules.
     */
    public function sanitize(?string $html): string
    {
        if (empty($html) || trim($html) === '') {
            return '';
        }

        // Setup DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Wrap with proper HTML5 header to preserve UTF-8
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="sanitizer-root">'
            . $html
            . '</div></body></html>';

        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // 1. Remove dangerous tags and their content
        foreach ($this->dangerousTags as $tag) {
            $elements = $xpath->query('//' . $tag);
            foreach ($elements as $el) {
                if ($el->parentNode) {
                    $el->parentNode->removeChild($el);
                }
            }
        }

        // 2. Process all remaining nodes
        $this->cleanNodeTree($dom, $xpath);

        // 3. Serialize root contents back to HTML string
        $root = $xpath->query('//div[@id="sanitizer-root"]')->item(0);
        if (! $root) {
            return '';
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Recursively traverse and clean nodes.
     */
    protected function cleanNodeTree(DOMDocument $dom, DOMXPath $xpath): void
    {
        $appHost = parse_url(config('app.url') ?? 'http://localhost', PHP_URL_HOST);

        $nodes = $xpath->query('//*[@id="sanitizer-root"]//*');
        $elementsToProcess = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elementsToProcess[] = $node;
            }
        }

        foreach ($elementsToProcess as $node) {
            $tagName = strtolower($node->tagName);

            // Disallow tags not in whitelist
            if (! in_array($tagName, $this->allowedTags, true)) {
                $this->unwrapNode($node);
                continue;
            }

            // Clean attributes
            $this->cleanAttributes($node, $appHost);
        }
    }

    /**
     * Clean and validate element attributes.
     */
    protected function cleanAttributes(DOMElement $node, ?string $appHost): void
    {
        $tagName = strtolower($node->tagName);
        $attributesToRemove = [];

        // Check each attribute
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->name);
            $val = trim($attr->value);

            // 1. Strip all on* attributes (onload, onclick, onerror, etc.)
            if (str_starts_with($name, 'on')) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // 2. Strip javascript:, data: or vbscript: in any attribute (except data-*)
            $normalizedVal = preg_replace('/\s+/', '', strtolower($val));
            if (
                str_starts_with($normalizedVal, 'javascript:') ||
                str_starts_with($normalizedVal, 'vbscript:') ||
                (! str_starts_with($name, 'data-') && str_starts_with($normalizedVal, 'data:') && $tagName !== 'img')
            ) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // 3. Clean inline style attribute if present
            if ($name === 'style') {
                $cleanStyle = $this->sanitizeStyle($val);
                if ($cleanStyle) {
                    $node->setAttribute('style', $cleanStyle);
                } else {
                    $attributesToRemove[] = 'style';
                }
            }
        }

        foreach ($attributesToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }

        // Element-specific validations
        if ($tagName === 'a') {
            $this->sanitizeAnchor($node, $appHost);
        } elseif ($tagName === 'iframe') {
            $this->sanitizeIframe($node);
        } elseif ($tagName === 'img') {
            $this->sanitizeImage($node);
        } elseif ($tagName === 'input') {
            $this->sanitizeInput($node);
        }
    }

    /**
     * Sanitize anchor link: strip javascript:, force rel="noopener noreferrer nofollow ugc" on external links.
     */
    protected function sanitizeAnchor(DOMElement $node, ?string $appHost): void
    {
        $href = trim($node->getAttribute('href'));

        // Strip javascript/vbscript
        $cleanHref = preg_replace('/[\x00-\x1F\x7F]/', '', $href);
        if (
            preg_match('/^\s*(javascript|vbscript|data):/i', $cleanHref) ||
            empty($cleanHref)
        ) {
            $node->removeAttribute('href');
            return;
        }

        $node->setAttribute('href', $cleanHref);

        // Check if external link
        $parsedUrl = parse_url($cleanHref);
        $linkHost = $parsedUrl['host'] ?? null;

        $isExternal = false;
        if (! empty($linkHost)) {
            $isExternal = empty($appHost) || strcasecmp($linkHost, $appHost) !== 0;
        }

        if ($isExternal) {
            $node->setAttribute('rel', 'noopener noreferrer nofollow ugc');
            $node->setAttribute('target', '_blank');
        } else {
            // Internal or relative links
            $rel = $node->getAttribute('rel');
            if ($node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /**
     * Sanitize iframe: strictly allow ONLY YouTube-nocookie embeds.
     */
    protected function sanitizeIframe(DOMElement $node): void
    {
        $src = trim($node->getAttribute('src'));

        // Must match YouTube embed or watch URL
        if (preg_match('/(?:youtube(?:-nocookie)?\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $src, $matches)) {
            $videoId = $matches[1];
            // Force YouTube-nocookie embed URL
            $node->setAttribute('src', 'https://www.youtube-nocookie.com/embed/' . $videoId);
            $node->setAttribute('frameborder', '0');
            $node->setAttribute('allowfullscreen', 'true');
            $node->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        } else {
            // Remove non-YouTube iframes completely!
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Sanitize img: ensure safe src and attributes.
     */
    protected function sanitizeImage(DOMElement $node): void
    {
        $src = trim($node->getAttribute('src'));
        $cleanSrc = preg_replace('/[\x00-\x1F\x7F]/', '', $src);

        if (
            preg_match('/^\s*(javascript|vbscript):/i', $cleanSrc) ||
            empty($cleanSrc)
        ) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            return;
        }

        $node->setAttribute('src', $cleanSrc);
    }

    /**
     * Sanitize input (for task lists only: type="checkbox" disabled).
     */
    protected function sanitizeInput(DOMElement $node): void
    {
        $type = strtolower($node->getAttribute('type'));
        if ($type === 'checkbox') {
            $node->setAttribute('disabled', 'disabled');
        } else {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Sanitize CSS style strings to allow only safe layout/color rules.
     */
    protected function sanitizeStyle(string $style): ?string
    {
        // Disallow dangerous CSS functions and bindings
        if (preg_match('/(expression|behavior|url|javascript|binding|-moz-binding)/i', $style)) {
            return null;
        }

        $allowedProperties = [
            'text-align', 'color', 'background-color', 'width', 'max-width', 'height',
            'float', 'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
            'padding', 'font-weight', 'font-style', 'text-decoration',
        ];

        $declarations = explode(';', $style);
        $cleanDeclarations = [];

        foreach ($declarations as $decl) {
            $decl = trim($decl);
            if (empty($decl)) {
                continue;
            }

            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);

            if (in_array($prop, $allowedProperties, true)) {
                // Ensure value does not contain quotes or parenthesis
                if (! preg_match('/[\(\)"\']/', $val)) {
                    $cleanDeclarations[] = "{$prop}: {$val}";
                }
            }
        }

        return ! empty($cleanDeclarations) ? implode('; ', $cleanDeclarations) : null;
    }

    /**
     * Replace element with its child nodes.
     */
    protected function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        while ($node->hasChildNodes()) {
            $child = $node->firstChild;
            $node->removeChild($child);
            $parent->insertBefore($child, $node);
        }

        $parent->removeChild($node);
    }
}
