<?php

namespace App\Console\Commands;

use App\Models\DiscordNotifier;
use App\Models\PageData;
use App\Models\PageStage;
use Carbon\Carbon;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting watch page ' . config('scrapper.url'));

        $this->info('- Scrapping page data...');
        $elements = collect($this->scrap());

        $this->info('- Watching page');
        while (true) {
            sleep(config('scrapper.watcher_timeout'));
            try {
                $elements = $this->scrapAndCompare($elements);
            } catch (Exception $exception) {
                $this->error(sprintf('Unexcepted error (%s) occured at %s', $exception->getMessage(), Carbon::now()->format('Y-m-d H:i:s')));
                Log::error('Watching page command exception: ' . $exception->getMessage());
                Log::error($exception);
            }
        }
    }

    private function scrap()
    {
        $pageData = new PageData();
        return $pageData->fetchPageData(
            config('scrapper.url'),
            json_decode(config('scrapper.rules'), true) ?? [],
        );
    }

    private function scrapAndSave()
    {
        $elements = $this->scrap();

        $stage = new PageStage();
        $stage->uri = config('scrapper.url');
        $stage->first_element_data = json_encode(collect($elements)->first());
        $stage->save();

        return $stage;
    }

    private function scrapAndCompare(Collection $rememberedElements)
    {
        $newElements = collect($this->scrap());

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
            $this->warn(sprintf('!!! Element updated at %s !!!', Carbon::now()->format('Y-m-d H:i:s')));

            $note = "Element was {$element['type']}";
            try {
                if (isset($element['orig_element'])) {
                   $note .= sprintf(
                       " (JSON with changes %s)",
                       json_encode(collect($element['orig_element'])->flatten()->diffAssoc(collect($element['element'])->flatten())->toArray())
                   );
                }
            } catch (Exception $exception) {
                $this->error(sprintf('Unexcepted error (%s) occured at %s', $exception->getMessage(), Carbon::now()->format('Y-m-d H:i:s')));
                Log::error('Watching page parsing changes exception: ' . $exception->getMessage());
                Log::error($exception);
            }

            $discordNotifier = new DiscordNotifier();
            $discordNotifier->notifyWebhook(
                $discordNotifier->prepareMessage(
                    config('scrapper.url'),
                    $element['element'],
                    $note,
                )
            );
        }

        if ($newElements !== $rememberedElements || count($notifyElements) > 0) {
            $rememberedElements = $newElements;
        }

        return $rememberedElements;
    }
}
