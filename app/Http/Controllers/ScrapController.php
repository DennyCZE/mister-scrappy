<?php

namespace App\Http\Controllers;

use App\Models\PageData;

class ScrapController
{
    public function test()
    {
        $pageData = new PageData();

        dd(
            $pageData->fetchPageData(
                config('scrapper.url'),
                json_decode(config('scrapper.rules'), true)
            )
        );
    }
}
