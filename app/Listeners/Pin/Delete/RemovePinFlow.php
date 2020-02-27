<?php

namespace App\Listeners\Pin\Delete;

use App\Http\Repositories\BangumiRepository;
use App\Http\Repositories\UserRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemovePinFlow
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

    /**
     * Handle the event.
     *
     * @param  \App\Events\Pin\Create  $event
     * @return void
     */
    public function handle(\App\Events\Pin\Delete $event)
    {
        if (!$event->published)
        {
            return;
        }

        $bangumiRepository = new BangumiRepository();
        $bangumiRepository->del_pin($event->pin->bangumi_slug, $event->pin->slug);

        $userRepository = new UserRepository();
        $userRepository->toggle_pin($event->pin->user_slug, $event->pin->slug, true);
    }
}
