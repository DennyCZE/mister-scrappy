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

        $difference = $rememberedElements->flatten()->diff($newElements->flatten());
        foreach ($difference as $element) {
            $this->warn(sprintf('!!! Element updated at %s !!!', Carbon::now()->format('Y-m-d H:i:s')));

            $discordNotifier = new DiscordNotifier();
            $discordNotifier->notifyWebhook(
                $discordNotifier->prepareMessage(config('scrapper.url'), $element)
            );

            if ($newElements !== $rememberedElements) {
                $rememberedElements = $newElements;
            }
        }

        return $rememberedElements;
    }
}
