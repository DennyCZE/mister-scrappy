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

        $lines = [];
        $primaryHref = null;
        $simpleHrefFormat = config('scrapper.simple_href_format');

        foreach ($data as $row) {
            if (!is_array($row)) {
                $text = trim($row);
                if ($text === '') {
                    continue;
                }
                if (strpos($text, ":")) {
                    $lines[] = "**" . Str::replaceFirst(":", ":** ", $text);
                } else {
                    $lines[] = "**" . $text . "**";
                }
                continue;
            }

            $label = array_key_exists('label', $row) ? trim($row['label']) : null;
            $text = isset($row['text']) ? trim($row['text']) : '';
            $href = $row['href'] ?? null;

            if ($label !== null) {
                // New labeled shape from childLabelWrapper rule.
                if ($label !== '' && $text !== '') {
                    $lines[] = "**{$label}:** {$text}";
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
}
