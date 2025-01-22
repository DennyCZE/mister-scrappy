<?php

namespace App\Console\Commands;

use App\Models\DiscordNotifier;
use App\Models\PageData;
use App\Models\PageStage;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
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

        $this->info('- Check DB for last saved state...');
        $stage = PageStage::where('uri', config('scrapper.url'))->first();
        if (!$stage) {
            $this->info('-- Page was not scrapped yet. Scrapping now.');
            $stage = $this->scrapAndSave();
            $this->info('-- Page scrapped. Page stage set.');
        }

        $this->info('- Watching page');
        while (true) {
            sleep(config('scrapper.watcher_timeout'));
            try {
                $this->scrapAndCompare($stage);
            } catch (Exception $exception) {
                $this->error(sprintf('Unexcepted error (%s) occured at %s', $exception->getMessage(), Carbon::now()->format('Y-m-d H:i:s')));
                Log::error('Watching page command exception: ' . $exception->getMessage(), $exception->getTrace());
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

    private function scrapAndCompare(PageStage $stage)
    {
        $first = collect($this->scrap())->first();

        if ($stage->first_element_data != json_encode($first)) {
            $this->warn(sprintf('!!! Elements updated at %s !!!', Carbon::now()->format('Y-m-d H:i:s')));

            $discordNotifier = new DiscordNotifier();
            $discordNotifier->notifyWebhook(
                $discordNotifier->prepareMessage($first)
            );

            $stage->update([
                'first_element_data' => json_encode($first),
                'updated_at' => now(),
            ]);
        }
    }
}
