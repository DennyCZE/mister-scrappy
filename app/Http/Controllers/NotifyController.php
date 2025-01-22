<?php

namespace App\Http\Controllers;

use App\Models\DiscordNotifier;

class NotifyController
{
    public function discordTest()
    {
        $discordNotifier = new DiscordNotifier();
        $discordNotifier->notifyWebhook('Test Webhook');
    }
}
