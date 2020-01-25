<?php

namespace App\Console\Jobs;

use App\Http\Repositories\PinRepository;
use App\Http\Repositories\Repository;
use App\Http\Repositories\UserRepository;
use App\Models\IdolExtra;
use App\Models\Pin;
use App\Models\Tag;
use App\Services\OpenSearch\Search;
use App\Services\Qiniu\Qshell;
use App\Services\Spider\BangumiSource;
use App\Services\Spider\Query;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class GetHottestBangumi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GetHottestBangumi';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get hottest bangumi';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bangumiSource = new BangumiSource();
        $bangumiSource->loadHottestBangumi();
        $bangumiSource->retryFailedBangumiIdols();
        $bangumiSource->retryFailedIdolDetail();
        return true;
    }
}
