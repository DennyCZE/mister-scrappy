<?php

namespace App\Console\Commands;

use App\Models\DiscordNotifier;
use App\Models\PageData;
use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WatchPage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:watch-page';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will scrap page and watch for changes in scrapped elements with configure timeout';

    private $discordNotifier;

    private array $downPages = [];

    public function __construct()
    {
        parent::__construct();
        $this->discordNotifier = new DiscordNotifier();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pages = config('scrapper.pages');

        if (empty($pages)) {
            $this->error('No pages configured. Set SCRAPPER_URL_1 / SCRAPPER_RULES_1 in .env');
            return 1;
        }

        $this->info(sprintf('Starting watch for %d page(s)', count($pages)));
        foreach ($pages as $idx => $page) {
            $this->info(sprintf('  [%d] %s', $idx + 1, $page['url']));
        }

        $this->info('- Testing discord webhook...');
        $this->discordNotifier->notifyWebhook('*Scrapper watcher started*');

        $this->info('- Initial scrape...');
        $remembered = [];
        foreach ($pages as $idx => $page) {
            $remembered[$idx] = collect($this->scrap($page));
        }

        $i = 1;
        $this->info('- Watching pages');
        while (true) {
            sleep(config('scrapper.watcher_timeout'));

            foreach ($pages as $idx => $page) {
                try {
                    $remembered[$idx] = $this->scrapAndCompare($idx, $page, $remembered[$idx]);
                    Log::debug(sprintf('ScrapAndCompare for %s run at %s', $page['url'], Carbon::now()->format('Y-m-d H:i:s')));
                } catch (Exception $exception) {
                    $this->error(sprintf('Unexcepted error (%s) occured at %s for %s', $exception->getMessage(), Carbon::now()->format('Y-m-d H:i:s'), $page['url']));
                    Log::error('Watching page command exception: ' . $exception->getMessage());
                    Log::error($exception);

                    try {
                        $this->discordNotifier->notifyWebhook(
                            sprintf("## Warning \n**Unexcepted error on %s:** *%s*", $page['url'], $exception->getMessage())
                        );
                    } catch (Error|Exception $e) {}
                }
            }

            if ($i % config('scrapper.watcher_alive_message_period') === 0) {
                $this->info('- Still watching pages');
                $this->discordNotifier->notifyWebhook('*Scrapper watcher is still alive*');
                $i = 0;
            }

            $i++;
        }
    }

    private function scrap(array $page)
    {
        $pageData = new PageData();
        return $pageData->fetchPageData(
            $page['url'],
            json_decode($page['rules'], true) ?? [],
        );
    }

    private function scrapAndCompare(int $idx, array $page, Collection $rememberedElements)
    {
        $newElements = collect($this->scrap($page));

        if ($newElements->isEmpty()) {
            $this->warn(sprintf('!!! No elements found for %s at %s !!!', $page['url'], Carbon::now()->format('Y-m-d H:i:s')));

            if (empty($this->downPages[$idx])) {
                $this->downPages[$idx] = true;
                $this->discordNotifier->notifyWebhook(sprintf('*No elements scrapped for %s. Page might be down!*', $page['url']));
            }

            return $rememberedElements;
        }

        if (!empty($this->downPages[$idx])) {
            unset($this->downPages[$idx]);
            $this->discordNotifier->notifyWebhook(sprintf('*Page %s is back up.*', $page['url']));
        }

        $rememberedFingerprints = $rememberedElements->map(function ($element) {
            return md5(json_encode($element));
        });
        $newFingerprints = $newElements->map(function ($element) {
            return md5(json_encode($element));
        });

        $differences = [
            'missing' => array_values(array_flip($rememberedFingerprints->diff($newFingerprints)->toArray())),
            'new' => array_values(array_flip($newFingerprints->diff($rememberedFingerprints)->toArray())),
        ];

        $notifyElements = [];
        foreach ($differences['missing'] as $key) {
            if (in_array($key, $differences['new'])) {
                $notifyElements[] = [
                    'type' => 'updated',
                    'element' => $newElements->get($key),
                    'orig_element' => $rememberedElements->get($key),
                ];
            } else {
                $notifyElements[] = [
                    'type' => 'removed',
                    'element' => $rememberedElements->get($key),
                ];
            }
        }
        foreach ($differences['new'] as $key) {
            if (!in_array($key, $differences['missing'])) {
                $notifyElements[] = [
                    'type' => 'added',
                    'element' => $newElements->get($key),
                ];
            }
        }

        foreach ($notifyElements as $element) {
            $this->warn(sprintf('!!! Element updated on %s at %s !!!', $page['url'], Carbon::now()->format('Y-m-d H:i:s')));

            $this->discordNotifier->notifyChange(
                $page['url'],
                $element['element'],
                $element['type'],
                $element['orig_element'] ?? null,
                $page['timezone'] ?? null,
                $page['thumbnail'] ?? null,
            );
        }

        if ($newElements !== $rememberedElements || count($notifyElements) > 0) {
            $rememberedElements = $newElements;
        }

        return $rememberedElements;
    }
}
