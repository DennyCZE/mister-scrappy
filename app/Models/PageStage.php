<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageStage extends Model
{
    protected $fillable = [
        'uri',
        'first_element_data'
    ];
}
