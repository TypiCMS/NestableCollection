<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use TypiCMS\NestableTrait;

class Item extends Model
{
    use NestableTrait;

    protected $guarded = [];
}
