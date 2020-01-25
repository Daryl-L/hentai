<?php

namespace App\Listeners\Pin\Update;

use App\Http\Modules\Counter\TagPatchCounter;
use App\Http\Repositories\FlowRepository;
use App\Http\Repositories\TagRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdatePinTagRelation
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
     * @param  \App\Events\Pin\Update  $event
     * @return void
     */
    public function handle(\App\Events\Pin\Update $event)
    {
        $pin = $event->pin;
        $arr = array_filter($event->tags, function ($item)
        {
            return $item;
        });

        $newTagSlugs = [];
        $tagRepository = new TagRepository();
        foreach ($arr as $slug)
        {
            $newTagSlugs = array_merge($newTagSlugs, $tagRepository->receiveTagChain($slug));
        }

        $newTagSlugs = array_unique($newTagSlugs);
        $flowRepository = new FlowRepository();
        $tagPatchCounter = new TagPatchCounter();
        $pinSlug = $pin->slug;

        if ($event->doPublish)
        {
            $attachIds = array_map(function ($slug)
            {
                return slug2id($slug);
            }, $newTagSlugs);
            $pin->tags()->attach($attachIds);

            foreach ($newTagSlugs as $tagSlug)
            {
                $flowRepository->add_pin($tagSlug, $pinSlug);
                $tagPatchCounter->add($tagSlug, 'pin_count', 1);
            }

            return;
        }

        $oldTagSlugs = $pin
            ->tags()
            ->pluck('slug')
            ->toArray();

        $attachTags = array_diff($newTagSlugs, $oldTagSlugs);
        $detachTags = array_diff($oldTagSlugs, $newTagSlugs);

        if (!empty($detachTags))
        {
            $detachIds = array_map(function ($slug)
            {
                return slug2id($slug);
            }, $detachTags);
            $pin->tags()->detach($detachIds);
        }

        if (!empty($attachTags))
        {
            $attachIds = array_map(function ($slug)
            {
                return slug2id($slug);
            }, $attachTags);
            $pin->tags()->attach($attachIds);
        }

        if (!$event->published || $pin->content_type != 1)
        {
            return;
        }

        foreach ($detachTags as $tagSlug)
        {
            $flowRepository->del_pin($tagSlug, $pinSlug);
            $tagPatchCounter->add($tagSlug, 'pin_count', -1);
        }

        foreach ($attachTags as $tagSlug)
        {
            $flowRepository->add_pin($tagSlug, $pinSlug);
            $tagPatchCounter->add($tagSlug, 'pin_count', 1);
        }
    }
}
