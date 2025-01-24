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
                $pageData = new PageData();
                dd(
                    $pageData->fetchPageData(
                        config('scrapper.url'),
                        json_decode(config('scrapper.rules'), true)
                    )
                );
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
