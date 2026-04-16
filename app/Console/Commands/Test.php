<?php

namespace App\Console\Commands;

use App\Models\DiscordNotifier;
use App\Models\PageData;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test app functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Test app functionality');
        $this->info('- Scrapper [s]');
        $this->info('- Discord [d]');
        $this->info('- Discord Error [e]');
        $functionality = $this->ask('Choose functionality for this test (enter character in [] from above):');

        switch ($functionality) {
            case 's':
                $this->info('-- Testing scrapper');
                $pages = config('scrapper.pages');
                if (empty($pages)) {
                    $this->error('No pages configured. Set SCRAPPER_URL_1 / SCRAPPER_RULES_1 in .env');
                    break;
                }

                $this->info('Configured pages:');
                foreach ($pages as $idx => $page) {
                    $this->info(sprintf('  [%d] %s', $idx + 1, $page['url']));
                }

                $selection = $this->ask('Enter page number (or "all"):', '1');
                $pageData = new PageData();

                if ($selection === 'all') {
                    foreach ($pages as $idx => $page) {
                        $this->info(sprintf('-- Page [%d] %s', $idx + 1, $page['url']));
                        dump($pageData->fetchPageData(
                            $page['url'],
                            json_decode($page['rules'], true) ?? []
                        ));
                    }
                    break;
                }

                $idx = ((int) $selection) - 1;
                if (!isset($pages[$idx])) {
                    $this->error('Invalid selection');
                    break;
                }
                dd($pageData->fetchPageData(
                    $pages[$idx]['url'],
                    json_decode($pages[$idx]['rules'], true) ?? []
                ));
                break;
            case 'd':
                $this->info('-- Testing discord');
                (new DiscordNotifier())->notifyWebhook('Test Webhook');
                break;
            case 'e':
                $this->info('-- Testing discord error message');
                (new DiscordNotifier())->notifyWebhook(
                    sprintf("## Warning \n**Unexcepted error:** *%s*", "Testing discord error message!")
                );
                break;
            default:
                $this->error('-- Unknown functionality');
                break;
        }
        $this->info('Test finished');

        return;
    }
}
