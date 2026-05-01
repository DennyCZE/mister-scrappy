<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordNotifier
{
    private $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook');
    }

    public function notifyWebhook($content)
    {
        $response = Http::post($this->webhookUrl, [
            'content' => $content
        ]);

        return response()->json([
            'status' => $response->status()
        ]);
    }

    public function prepareMessage(string $uri, array $data, $note = null)
    {
        $message = sprintf("*Změna na sledované stránce* %s\n\n", $uri);

        // Some pages split the time across sibling elements to animate the
        // colon separator (e.g. "27.4. 2026 15:" + "30:00"). Stitch those
        // fragments back together before rendering so the time stays on one line.
        $data = $this->mergeDanglingColonRows($data);

        $lines = [];
        $primaryHref = null;
        $simpleHrefFormat = config('scrapper.simple_href_format');

        foreach ($data as $row) {
            if (!is_array($row)) {
                $text = $this->escapeDiscordListMarkers(trim($row));
                if ($text === '') {
                    continue;
                }
                // Treat as "label: value" only when there's a real word before
                // the colon, otherwise times like "15:30" get mangled into
                // "**15:** 30".
                if (preg_match('/^[^:]*\p{L}[^:]*:/u', $text)) {
                    $lines[] = "**" . Str::replaceFirst(":", ":** ", $text);
                } else {
                    $lines[] = "**" . $text . "**";
                }
                continue;
            }

            $label = array_key_exists('label', $row) ? $this->escapeDiscordListMarkers(trim($row['label'])) : null;
            $text = isset($row['text']) ? $this->escapeDiscordListMarkers(trim($row['text'])) : '';
            $href = $row['href'] ?? null;

            if ($label !== null) {
                // New labeled shape from childLabelWrapper rule.
                if ($label !== '' && $text !== '') {
                    $lines[] = "**{$label}:** {$text}";
                } elseif ($label === '' && $text !== '') {
                    // Empty label cell (e.g. hardtask's "volno: N" register
                    // button has no field-name sibling). Render the text as
                    // a standalone bold line so the info isn't dropped.
                    if (preg_match('/^[^:]*\p{L}[^:]*:/u', $text)) {
                        $lines[] = "**" . Str::replaceFirst(":", ":** ", $text);
                    } else {
                        $lines[] = "**" . $text . "**";
                    }
                }
                if ($href) {
                    $primaryHref = $href;
                }
                continue;
            }

            // Legacy link shape: { text, href }.
            if ($simpleHrefFormat && $text !== '' && $href) {
                $lines[] = "**" . $text . "**: " . $href;
            } elseif ($href) {
                $primaryHref = $href;
            } elseif ($text !== '') {
                $lines[] = $text;
            }
        }

        if ($primaryHref) {
            $lines[] = "Odkaz: " . $primaryHref;
        }

        $message .= implode("\n", $lines);

        if ($note) {
            $message .= "\n\n*" . $note . "*";
        }

        return $message;
    }

    private function escapeDiscordListMarkers(string $text): string
    {
        // Czech dates like "4. 5. 2026" get parsed as an ordered list by
        // Discord (notably mobile), which rewrites "4." / "5." as list
        // markers and visually swallows the date. Escape the dot so the
        // parser leaves it alone while users still see "4. 5. 2026".
        return preg_replace('/(?<!\d)(\d{1,9})\./u', '$1\\.', $text);
    }

    private function mergeDanglingColonRows(array $data): array
    {
        $merged = [];
        foreach ($data as $row) {
            $lastIndex = count($merged) - 1;
            $last = $lastIndex >= 0 ? $merged[$lastIndex] : null;
            if (is_string($row) && is_string($last) && str_ends_with(rtrim($last), ':')) {
                $merged[$lastIndex] = rtrim($last) . ' ' . trim($row);
                continue;
            }
            $merged[] = $row;
        }
        return $merged;
    }
}
