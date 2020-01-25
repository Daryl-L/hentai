<?php

namespace App\Events\Tag;

use App\Models\Tag;
use App\User;
use Illuminate\Queue\SerializesModels;

class Delete
{
    use SerializesModels;

    public $tag;
    public $user;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Tag $tag, User $user)
    {
        $this->tag = $tag;
        $this->user = $user;
    }
}
