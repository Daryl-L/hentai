<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class BangumiQuestionRule extends Model
{
    protected $table = 'bangumi_rules';

    protected $fillable = [
        'bangumi_slug',
        'question_count',   // 答题的个数
        'right_rate',       // 正确率，0 ~ 100
        'qa_minutes',       // 答题的时长（分钟）
        'rule_type',        // 门槛类型：0：直接加入，0 需要答题或邀请，1 只能答题，2 只能邀请
        'result_type',      // 算分方式：0 答完之后出结果，1 每答一道告知结果
        'is_open',          // 是否开放加入入口
    ];
}
