<?php


namespace App\Models;


use App\Http\Modules\RichContentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Relation\Traits\CanBeVoted;
use Spatie\Permission\Traits\HasRoles;

class Comment extends Model
{
    use SoftDeletes, CanBeVoted, HasRoles;

    protected $fillable = [
        'from_user_slug',
        'to_user_slug',
        'pin_slug',
        'trial_type',       // 审核结果，默认是 0，不在审核中
        'like_count',       // 喜欢和反对最后算出的值
    ];

    public function author()
    {
        return $this->belongsTo('App\User', 'from_user_slug', 'slug');
    }

    public function getter()
    {
        return $this->belongsTo('App\User', 'to_user_slug', 'slug');
    }

    public function pin()
    {
        return $this->belongsTo('App\Models\Pin', 'pin_slug', 'slug');
    }

    public function content()
    {
        return $this->morphOne('App\Models\Content', 'contentable');
    }

    public function history()
    {
        return $this->morphMany('App\Models\Content', 'contentable');
    }

    public function reports()
    {
        return $this->morphMany('App\Models\Report', 'reportable');
    }

    public static function createComment($content, $pinSlug, $targetUserSlug, $author)
    {
        $richContentService = new RichContentService();
        $risk = $richContentService->detectContentRisk($content, false);

        if ($risk['risk_score'] > 0)
        {
            return null;
        }

        $comment = self::create([
            'from_user_slug' => $author->slug,
            'to_user_slug' => $targetUserSlug,
            'pin_slug' => $pinSlug
        ]);

        $comment->content()->create([
            'text' => $richContentService->saveRichContent($content)
        ]);

        event(new \App\Events\Comment\Create($comment, $author));

        return $comment;
    }

    public function deleteComment($user)
    {
        $this->delete();

        event(new \App\Events\Comment\Delete($this, $user));
    }
}
