<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\Http;
use function Termwind\parse;

class PageData
{
    public function fetchPageData(string $uri, array $rules) :array
    {
        $data = [];
        $page = Http::get($uri)->body();

        $page = str_replace(["\n", "\t", "\r"], '', $page);

        foreach ($rules as $key => $rule) {
            $stringPos = strpos($page, $rule['child_text']);

            $start = strrpos(substr($page, 0, $stringPos), $rule['parent'][0]);
            $end = strpos($page, $rule['parent'][1], $start);

            $element = substr($page, $start, ($end - $start) + strlen($rule['parent'][1]));

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $element);
            libxml_clear_errors();

            $finder = new DomXPath($dom);
            $nodes = $finder->query("//" . $rule['wrapper']);

            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $value = $node->nodeValue;

                if ($rule['rule']['method']($value, $rule['rule']['value'])) {
                    $data[$key][] = $this->htmlToArray($node->ownerDocument->saveXML($node), $uri, $rule['childWrapper'] ?? null);
                }
            }
        }

        return count($data) == 1 ? collect($data)->first() : $data;
    }

    private function htmlToArray(string $html, $uri = null, $wrapper = 'div') :array
    {
        try {
            $uri = parse_url($uri);
        } catch (Exception $exception) {
            $uri = null;
        }


        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html);
        libxml_clear_errors();

        $finder = new DomXPath($dom);
        $nodes = $finder->query("//" . $wrapper);

        $array = [];

        /** @var DOMElement $node */
        foreach($nodes as $node)
        {
            $nodeHtml = $dom->saveHTML($node);

            if (str_contains($nodeHtml, "href")) {
                $link = new DOMDocument();
                $link->loadHTML($nodeHtml);
                $link = $link->getElementsByTagName('a')->item(0);

                $text = $link->nodeValue;
                $href = $link->getAttribute('href');

                if (!str_contains($href, "http") && $uri && is_array($uri)) {
                    $href = $uri['scheme'] . "://" . $uri['host'] . $href;
                }

                $array[] = [
                    'text' => $text,
                    'href' => $href,
                ];
            } else {
                $array[] = trim(strip_tags($nodeHtml));
            }
        }
        return $array;
    }
}
