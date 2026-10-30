<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class TableOfContentsService
{
    /**
     * Extract headings and inject IDs into headings in HTML for scroll-spy and anchor navigation.
     *
     * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>}
     */
    public function extractAndInject(string $html): array
    {
        if (empty(trim($html))) {
            return ['html' => '', 'toc' => []];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="toc-root">'
            . $html
            . '</div></body></html>';

        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $headings = [];
        $slugCounts = [];

        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $node) {
            $text = trim($node->textContent);
            if (empty($text)) {
                continue;
            }

            $level = (int) substr($node->nodeName, 1);
            $baseSlug = Str::slug($text) ?: 'heading';

            $count = $slugCounts[$baseSlug] ?? 0;
            $slugCounts[$baseSlug] = $count + 1;
            $id = $count > 0 ? "{$baseSlug}-{$count}" : $baseSlug;

            $node->setAttribute('id', $id);

            $headings[] = [
                'id' => $id,
                'text' => $text,
                'level' => $level,
            ];
        }

        $root = $xpath->query('//div[@id="toc-root"]')->item(0);
        $outputHtml = '';
        if ($root) {
            foreach ($root->childNodes as $child) {
                $outputHtml .= $dom->saveHTML($child);
            }
        }

        return [
            'html' => trim($outputHtml),
            'toc' => $headings,
        ];
    }
}
