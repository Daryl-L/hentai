<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-09
 * Time: 21:51
 */

namespace App\Models;


use App\Http\Modules\RichContentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Relation\Traits\CanBeBookmarked;
use App\Services\Relation\Traits\CanBeFavorited;
use App\Services\Relation\Traits\CanBeVoted;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

class Pin extends Model
{
    use SoftDeletes, HasRoles, CanBeVoted, CanBeBookmarked, CanBeFavorited;

    protected $guard_name = 'api';

    protected $fillable = [
        'slug',
        'user_slug',
        'bangumi_slug',     // 番剧 slug
        'trial_type',       // 进入审核池的类型，默认 0 不在审核池，1 创建触发敏感词过滤进入审核池
        'comment_type',     // 评论权限的类型，默认 0 允许所有人评论
        'last_top_at',      // 最后置顶时间
        'last_edit_at',     // 最后编辑时间
        'published_at',     // 发布时间
        'recommended_at',   // 推荐的时间
        'visit_count',      // 访问数
        'comment_count',    // 评论数
        'like_count',       // 点赞数
        'mark_count',       // 收藏数
        'reward_count',     // 打赏数
        'can_up',           // 是否能被顶起来
    ];

    public function tags()
    {
        return $this->morphToMany('App\Models\Tag', 'taggable');
    }

    public function content()
    {
        return $this->morphOne('App\Models\Content', 'contentable');
    }

    public function history()
    {
        return $this->morphMany('App\Models\Content', 'contentable');
    }

    public function timeline()
    {
        /**
         * 0 => 创建帖子
         * 1 => 更新帖子
         * 2 => 删除帖子
         * 3 => 公开帖子
         * 4 => 被推荐
         * 5 => 移动帖子
         * 6 => 推荐
         * 7 => 取消推荐
         * 8 => 撤销删除
         * 9 => 更新文章触发一级敏感词进入审核
         * 10 =>更新文章触发二级敏感词进入审核
         * 11 =>创建文章触发一级敏感词进入审核
         * 12 =>创建文章触发二级敏感词进入审核
         */
        return $this->morphMany('App\Models\Timeline', 'timelineable');
    }

    public static function convertTimeline($event_type)
    {
        if ($event_type == 0) {
            return '创建帖子';
        } else if ($event_type == 1) {
            return '更新帖子';
        } else if ($event_type == 2) {
            return '删除帖子';
        } else if ($event_type == 3) {
            return '公开帖子';
        } else if ($event_type == 4) {
            return '被推荐';
        } else if ($event_type == 5) {
            return '修改分区';
        }
        return '未知：' . $event_type;
    }

    public function comments()
    {
        return $this->hasMany('App\Models\Comment', 'pin_slug', 'slug');
    }

    public function answers()
    {
        return $this->hasMany('App\Models\PinAnswer', 'pin_slug', 'slug');
    }

    public function reports()
    {
        return $this->morphMany('App\Models\Report', 'reportable');
    }

    public static function createPin($content, $publish, $user, $bangumi_slug)
    {
        $richContentService = new RichContentService();
        $content = $richContentService->preFormatContent($content);

        $now = Carbon::now();
        $data = [
            'user_slug' => $user->slug,
            'bangumi_slug' => $bangumi_slug,
            'last_edit_at' => $now
        ];
        if ($publish)
        {
            $data['published_at'] = $now;
        }

        $pin = self::create($data);

        $pin->update([
            'slug' => id2slug($pin->id)
        ]);

        $pin->content()->create([
            'text' => $richContentService->saveRichContent($content)
        ]);

        event(new \App\Events\Pin\Create($pin, $user, $bangumi_slug, $publish, $content));

        return $pin;
    }

    public function updatePin($content, $publish, $user, $bangumi_slug)
    {
        $richContentService = new RichContentService();
        $content = $richContentService->preFormatContent($content);
        if ($this->published_at)
        {
            // 已发布的文章
            // 不能编辑投票
            $newVote = $richContentService->getFirstType($content, 'vote');
            if ($newVote)
            {
                $lastContent = $this
                    ->content()
                    ->pluck('text')
                    ->first();
                $oldVote = $richContentService->getFirstType($lastContent, 'vote');

                if ($oldVote)
                {
                    foreach ($content as $i => $row)
                    {
                        if ($row['type'] === 'vote')
                        {
                            $content[$i] = [
                                'type' => 'vote',
                                'data' => $oldVote
                            ];
                        }
                    }
                }
            }
        }

        $now = Carbon::now();
        $data = [
            'last_edit_at' => $now,
            'bangumi_slug' => $bangumi_slug
        ];
        $doPublish = !$this->published_at && $publish;
        if ($doPublish)
        {
            $data['published_at'] = $now;
        }
        $oldBangumiSlug = $this->bangumi_slug;

        $this->update($data);

        $this->content()->delete();
        $this->content()->create([
            'text' => $richContentService->saveRichContent($content)
        ]);

        event(new \App\Events\Pin\Update($this, $user, $doPublish, $oldBangumiSlug, $bangumi_slug, $content));

        return true;
    }

    public function deletePin($user)
    {
        $this->delete();

        event(new \App\Events\Pin\Delete($this, $user));
    }

    public function recoverPin($user)
    {
        $this->restore();
        $this->update([
            'deleted_at' => null,
            'trial_type' => 0
        ]);

        event(new \App\Events\Pin\Recover($this, $user));
    }
}
