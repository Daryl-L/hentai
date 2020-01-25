<?php

namespace App\Listeners\Idol\BuyStock;

use App\Http\Modules\Counter\IdolPatchCounter;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateIdolPatch
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle(\App\Events\Idol\BuyStock $event)
    {
        $slug = $event->idol->slug;
        $idolPatchCounter = new IdolPatchCounter();
        if (!$event->fansData)
        {
            $idolPatchCounter->add($slug, 'fans_count', 1);
        }
        $idolPatchCounter->add($slug, 'coin_count', $event->coinAmount);
        $idolPatchCounter->add($slug, 'stock_count', $event->stockCount);
        $idolPatchCounter->add($slug, 'market_price', $event->coinAmount);
    }
}
