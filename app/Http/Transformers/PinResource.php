<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers;

use App\Http\Modules\RichContentService;
use App\Http\Transformers\Bangumi\BangumiItemResource;
use App\Http\Transformers\User\UserHomeResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PinResource extends JsonResource
{
    public function toArray($request)
    {
        $richContentService = new RichContentService();

        $title = [
            'banner' => null,
            'text' => ''
        ];

        $content = $richContentService->parseRichContent($this->content->text);
        if ($content[0]['type'] === 'title')
        {
            $title = array_shift($content)['data'];
        }

        $badge = '帖子';
        if ($richContentService->getFirstType($content, 'baidu'))
        {
            $badge = '网盘';
        }
        else if ($richContentService->getFirstType($content, 'vote'))
        {
            $badge = '投票';
        }

        return [
            'slug' => $this->slug,
            'title' => $title,
            'badge' => $badge,
            'content' => $content,
            'banner' => array_map(function ($image)
            {
                return patchImage($image['url']);
            }, $richContentService->parseRichBanner($content, $title['banner'] ?? null)),
            'intro' => mb_substr($richContentService->paresPureContent($content), 0, 60, 'utf-8'),
            'bangumi' => new BangumiItemResource($this->bangumi),
            'author' => new UserHomeResource($this->author),
            'trial_type' => $this->trial_type,
            'comment_type' => $this->comment_type,
            'last_top_at' => $this->last_top_at,
            'recommended_at' => $this->recommended_at,
            'created_at' => $this->created_at,
            'last_edit_at' => $this->last_edit_at,
            'published_at' => $this->published_at,
            'deleted_at' => $this->deleted_at,
            'visit_count' => $this->visit_count,
            'comment_count' => $this->comment_count,
            'like_count' => $this->upvoters()->count(),
            'mark_count' => $this->mark_count,
            'reward_count' => $this->reward_count,
            'can_up' => $this->can_up
        ];
    }
}
