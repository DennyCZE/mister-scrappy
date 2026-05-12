<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DiscordNotifier
{
    private const COLOR_ADDED = 0x2ECC71;
    private const COLOR_UPDATED = 0xF1C40F;
    private const COLOR_REMOVED = 0xE74C3C;

    private const INLINE_LENGTH_THRESHOLD = 16;

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

    public function notifyChange(
        string $uri,
        array $data,
        string $type,
        ?array $origElement = null,
        ?string $timezone = null,
        ?string $thumbnail = null,
    ) {
        $embed = $this->buildEmbed($uri, $data, $type, $origElement, $timezone, $thumbnail);

        $response = Http::post($this->webhookUrl, [
            'embeds' => [$embed],
        ]);

        return response()->json([
            'status' => $response->status()
        ]);
    }

    private function buildEmbed(
        string $uri,
        array $data,
        string $type,
        ?array $origElement,
        ?string $timezone,
        ?string $thumbnail,
    ): array {
        $rawData = $data;
        $data = $this->mergeDanglingColonRows($data);
        if ($timezone) {
            $data = $this->shiftTimes($data, $timezone);
        }

        $titleInfo = $this->selectTitle($data);
        $titleHref = $titleInfo['href'];
        $skipIndex = $titleInfo['index'];

        $rowsForFields = $data;
        if ($skipIndex !== null) {
            unset($rowsForFields[$skipIndex]);
            $rowsForFields = array_values($rowsForFields);
        }

        $fields = $this->rowsToFields($rowsForFields, $titleHref);
        $fields = $this->applyInlineRule($fields);

        $description = sprintf('Změna na sledované [stránce](%s).', $uri);
        $description .= "\n" . $this->statusLine($type, $origElement, $rawData);

        $embed = [
            'description' => $description,
            'color' => $this->colorFor($type),
            'fields' => array_map(function ($f) {
                $out = ['name' => $f['name'], 'value' => $f['value']];
                if (!empty($f['inline'])) {
                    $out['inline'] = true;
                }
                return $out;
            }, $fields),
        ];

        if ($titleInfo['title'] !== null && $titleInfo['title'] !== '') {
            $embed['title'] = $titleInfo['title'];
            if ($titleHref) {
                $embed['url'] = $titleHref;
            }
        }

        if ($thumbnail) {
            $embed['thumbnail'] = ['url' => $thumbnail];
        }

        return $embed;
    }

    private function selectTitle(array $data): array
    {
        foreach ($data as $i => $row) {
            if (is_string($row)) {
                $text = trim($row);
                if ($text === '' || $this->looksLikeTime($text)) {
                    continue;
                }
                if (preg_match('/^([^:]*\p{L}[^:]*):\s*(.+)$/u', $text, $m)) {
                    if (str_starts_with(trim($m[1]), 'Kurz')) {
                        return ['title' => trim($m[2]), 'href' => null, 'index' => $i];
                    }
                    continue;
                }
                return ['title' => $text, 'href' => null, 'index' => $i];
            }

            if (is_array($row)) {
                $label = isset($row['label']) ? trim($row['label']) : null;
                $textVal = isset($row['text']) ? trim($row['text']) : '';
                $href = $row['href'] ?? null;
                if ($label !== null && $label !== '' && str_starts_with($label, 'Kurz') && $textVal !== '') {
                    return ['title' => $textVal, 'href' => $href, 'index' => $i];
                }
            }
        }

        return ['title' => null, 'href' => null, 'index' => null];
    }

    private function looksLikeTime(string $text): bool
    {
        return (bool) preg_match('/^[\d\s\.\:\/\-]+$/u', $text);
    }

    private function rowsToFields(array $data, ?string $titleHref): array
    {
        $fields = [];
        $primaryHref = null;
        $simpleHrefFormat = config('scrapper.simple_href_format');

        foreach ($data as $row) {
            if (!is_array($row)) {
                $text = trim($row);
                if ($text === '') {
                    continue;
                }
                $fields[] = $this->fieldFromColonString($text);
                continue;
            }

            $label = array_key_exists('label', $row) ? trim($row['label']) : null;
            $textVal = isset($row['text']) ? trim($row['text']) : '';
            $href = $row['href'] ?? null;

            if ($label !== null) {
                if ($label !== '' && $textVal !== '') {
                    $fields[] = $this->makeField($label, $textVal);
                } elseif ($label === '' && $textVal !== '') {
                    $fields[] = $this->fieldFromColonString($textVal);
                }
                if ($href) {
                    $primaryHref = $href;
                }
                continue;
            }

            if ($simpleHrefFormat && $textVal !== '' && $href) {
                $fields[] = [
                    'name' => $this->escapeDiscordListMarkers($textVal),
                    'value' => $href,
                    'length' => mb_strlen($href),
                ];
            } elseif ($href) {
                $primaryHref = $href;
            } elseif ($textVal !== '') {
                $fields[] = [
                    'name' => $this->escapeDiscordListMarkers($textVal),
                    'value' => "\u{200B}",
                    'length' => mb_strlen($textVal),
                ];
            }
        }

        if ($primaryHref && $primaryHref !== $titleHref) {
            $fields[] = [
                'name' => 'Odkaz',
                'value' => $primaryHref,
                'length' => mb_strlen($primaryHref),
            ];
        }

        return $fields;
    }

    private function fieldFromColonString(string $text): array
    {
        if (preg_match('/^([^:]*\p{L}[^:]*):\s*(.*)$/u', $text, $m)) {
            $value = trim($m[2]);
            return $this->makeField($m[1], $value);
        }
        return [
            'name' => $this->escapeDiscordListMarkers($text),
            'value' => "\u{200B}",
            'length' => mb_strlen($text),
        ];
    }

    private function makeField(string $label, string $text): array
    {
        $cleanLabel = $this->escapeDiscordListMarkers(trim($label));
        $value = $this->escapeDiscordListMarkers($text);
        return [
            'name' => $cleanLabel === '' ? "\u{200B}" : $cleanLabel,
            'value' => $value === '' ? "\u{200B}" : $value,
            'length' => mb_strlen($text),
        ];
    }

    private function applyInlineRule(array $fields): array
    {
        $count = count($fields);
        for ($i = 0; $i < $count - 1; $i++) {
            if ($fields[$i]['length'] < self::INLINE_LENGTH_THRESHOLD
                && $fields[$i + 1]['length'] < self::INLINE_LENGTH_THRESHOLD) {
                $fields[$i]['inline'] = true;
            }
        }
        return $fields;
    }

    private function colorFor(string $type): int
    {
        return match ($type) {
            'added' => self::COLOR_ADDED,
            'updated' => self::COLOR_UPDATED,
            'removed' => self::COLOR_REMOVED,
            default => 0x95A5A6,
        };
    }

    private function statusLine(string $type, ?array $origElement, array $newElement): string
    {
        if ($type === 'added') {
            return 'Element vytvořen';
        }
        if ($type === 'removed') {
            return 'Element odstraněn';
        }
        if ($type === 'updated') {
            $changes = $origElement ? $this->describeChanges($origElement, $newElement) : null;
            return $changes ? sprintf('Element aktualizován %s', $changes) : 'Element aktualizován';
        }
        return '';
    }

    private function describeChanges(array $origElement, array $newElement): ?string
    {
        try {
            $diff = collect($origElement)->flatten()
                ->diffAssoc(collect($newElement)->flatten())
                ->map(fn($value, $key) => sprintf('`line %d: %s`', $key, $value))
                ->toArray();
        } catch (\Throwable) {
            return null;
        }
        return empty($diff) ? null : implode(', ', $diff);
    }

    private function escapeDiscordListMarkers(string $text): string
    {
        // Czech dates like "4. 5. 2026" get parsed as an ordered list by
        // Discord (notably mobile), which rewrites "4." / "5." as list
        // markers and visually swallows the date. Escape the dot so the
        // parser leaves it alone while users still see "4. 5. 2026".
        return preg_replace('/(?<!\d)(\d{1,9})\./u', '$1\\.', $text);
    }

    private function shiftTimes(array $data, string $timezone): array
    {
        $shift = function (string $text) use ($timezone): string {
            return preg_replace_callback(
                '/(?<!\d)(\d{1,2}):(\d{2})(?::(\d{2}))?(?!\d)/',
                function ($m) use ($timezone) {
                    try {
                        $time = Carbon::createFromTimeString(
                            sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)),
                            'UTC'
                        )->setTimezone($timezone);
                    } catch (\Throwable) {
                        return $m[0];
                    }
                    return isset($m[3]) ? $time->format('H:i:s') : $time->format('H:i');
                },
                $text
            );
        };

        foreach ($data as $i => $row) {
            if (is_string($row)) {
                $data[$i] = $shift($row);
            } elseif (is_array($row) && isset($row['text']) && is_string($row['text'])) {
                $data[$i]['text'] = $shift($row['text']);
            }
        }
        return $data;
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
