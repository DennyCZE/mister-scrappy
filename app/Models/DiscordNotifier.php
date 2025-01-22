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

    public function prepareMessage(string $uri, array $data)
    {
        $message = sprintf("*Změna na sledované stránce %s *\n\n", $uri);
        foreach ($data as $row) {
            if (!is_array($row)) {
                if (strpos($row, ":")) {
                    $message .= "**" . Str::replaceFirst(":", ":** ", $row);
                } else {
                    $message .= "**" . $row . "**";
                }
            }  elseif (is_array($row) && isset($row['href'])) {
                $message .= "Odkaz: " . $row['href'];
            }

            $message .= "\n";
        }

        return $message;
    }
}
