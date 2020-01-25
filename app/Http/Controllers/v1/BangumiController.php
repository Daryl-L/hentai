<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Modules\Counter\BangumiPatchCounter;
use App\Http\Repositories\BangumiRepository;
use App\Http\Repositories\IdolRepository;
use App\Http\Repositories\UserRepository;
use App\Models\Bangumi;
use App\Models\BangumiQuestion;
use App\Models\Search;
use App\Services\Spider\BangumiSource;
use App\Services\Spider\Query;
use Illuminate\Http\Request;

class BangumiController extends Controller
{
    public function show(Request $request)
    {
        $slug = $request->get('slug');
        if (!$slug)
        {
            return $this->resErrBad();
        }

        $bangumiRepository = new BangumiRepository();

        $bangumi = $bangumiRepository->item($slug);
        if (!$bangumi)
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($bangumi);
    }

    public function patch(Request $request)
    {
        $slug = $request->get('slug');

        $bangumiRepository = new BangumiRepository();
        $data = $bangumiRepository->item($slug);
        if (is_null($data))
        {
            return $this->resErrNotFound();
        }

        $bangumiPatchCounter = new BangumiPatchCounter();
        $patch = $bangumiPatchCounter->all($slug);
        $user = $request->user();

        if (!$user)
        {
            return $this->resOK($patch);
        }

        $bangumiId = slug2id($slug);
        $patch['is_liked'] = $user->hasLiked($bangumiId, Bangumi::class);

        return $this->resOK($patch);
    }

    public function atfield(Request $request)
    {
        $slug = $request->get('slug');

        $trialCount = BangumiQuestion
            ::where('status', 0)
            ->when($slug, function ($query) use ($slug)
            {
                return $query->where('bangumi_slug', $slug);
            })
            ->count();

        $passCount = BangumiQuestion
            ::where('status', 1)
            ->when($slug, function ($query) use ($slug)
            {
                return $query->where('bangumi_slug', $slug);
            })
            ->count();

        return $this->resOK([
            'trial' => $trialCount,
            'pass' => $passCount
        ]);
    }

    public function rank250(Request $request)
    {
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 20;

        $bangumiRepository = new BangumiRepository();
        $idsObj = $bangumiRepository->rank($page, $take);

        if (empty($idsObj['result']))
        {
            return $this->resOK($idsObj);
        }

        $idsObj['result'] = $bangumiRepository->list($idsObj['result']);

        return $this->resOK($idsObj);
    }

    public function score(Request $request)
    {

    }

    public function liker(Request $request)
    {
        $slug = $request->get('slug');
        $page = $request->get('page') ?: 1;
        $take = $request->get('take') ?: 10;

        $bangumiRepository = new BangumiRepository();
        $idsObj = $bangumiRepository->likeUsers($slug, $page - 1, $take);
        if (empty($idsObj['result']))
        {
            return $this->resOK($idsObj);
        }

        $userRepository = new UserRepository();
        $idsObj['result'] = $userRepository->list($idsObj['result']);

        return $this->resOK($idsObj);
    }

    public function relation(Request $request)
    {
        $slug = $request->get('slug');

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($slug);
        if (!$bangumi)
        {
            return $this->resErrNotFound();
        }

        $result = [
            'parent' => null,
            'children' => []
        ];

        if ($bangumi->is_parent)
        {
            $childrenSlug = Bangumi
                ::where('parent_slug', $bangumi->slug)
                ->pluck('slug')
                ->toArray();

            $result['children'] = $bangumiRepository->list($childrenSlug);
        }

        if ($bangumi->parent_slug)
        {
            $result['parent'] = $bangumiRepository->item($bangumi->parent_slug);
        }

        return $this->resOK($result);
    }

    public function idols(Request $request)
    {
        $slug = $request->get('slug');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 20;

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($slug);
        if (!$bangumi)
        {
            return $this->resErrNotFound();
        }

        $idsObj = $bangumiRepository->idol_slugs($slug, $page, $take);
        if (empty($idsObj['result']))
        {
            return $this->resOK($idsObj);
        }

        $idolRepository = new IdolRepository();

        $idsObj['result'] = $idolRepository->list($idsObj['result']);

        return $this->resOK($idsObj);
    }

    public function fetch(Request $request)
    {
        $sourceId = $request->get('source_id');
        $hasBangumi = Bangumi
            ::where('source_id', $sourceId)
            ->first();

        if ($hasBangumi)
        {
            return $this->resErrBad($hasBangumi->slug);
        }

        $query = new Query();
        $info = $query->getBangumiDetail($sourceId);

        return $this->resOK($info);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if (!$user->is_admin)
        {
            return $this->resErrRole();
        }

        $bangumiSource = new BangumiSource();
        $bangumi = $bangumiSource->importBangumi([
            'id' => $request->get('id'),
            'name' => $request->get('name'),
            'alias' => $request->get('alias'),
            'intro' => $request->get('intro'),
            'avatar' => $request->get('avatar'),
            'type' => $request->get('type') ?: 0
        ]);

        if (is_null($bangumi))
        {
            return $this->resErrServiceUnavailable();
        }

        return $this->resOK($bangumi->slug);
    }

    public function fetchIdols(Request $request)
    {
        $slug = $request->get('slug');
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($slug);

        if (!$bangumi)
        {
            return $this->resErrNotFound();
        }

        $bangumiSource = new BangumiSource();
        $bangumiSource->moveBangumiIdol($bangumi->slug, $bangumi->source_id);
        $bangumiRepository->idol_slugs($slug, 0, 0, true);

        return $this->resNoContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if ($user->cant('update_bangumi'))
        {
            return $this->resErrRole();
        }

        $avatar = $request->get('avatar');
        $title = $request->get('name');
        $alias = $request->get('alias');
        $intro = $request->get('intro');
        $slug = $request->get('slug');

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($slug);
        if (!$bangumi)
        {
            return $this->resErrNotFound();
        }

        array_push($alias, $title);
        $alias = implode('|', array_unique($alias));

        Bangumi
            ::where('slug', $slug)
            ->update([
                'avatar' => $avatar,
                'title' => $title,
                'intro' => $intro,
                'alias' => $alias
            ]);

        Search
            ::where('slug', $slug)
            ->where('type', 4)
            ->update([
                'text' => str_replace('|', ',', $alias)
            ]);

        $bangumiRepository->item($slug, true);

        return $this->resNoContent();
    }

    public function updateAsParent(Request $request)
    {
        $user = $request->user();
        if ($user->cant('update_bangumi'))
        {
            return $this->resErrRole();
        }
        $bangumiSlug = $request->get('bangumi_slug');
        $bangumi = Bangumi
            ::where('slug', $bangumiSlug)
            ->first();

        $bangumi->update([
            'is_parent' => $request->get('result') ?: true
        ]);

        $bangumiRepository = new BangumiRepository();
        $bangumiRepository->item($bangumiSlug, true);

        return $this->resNoContent();
    }

    public function updateAsChild(Request $request)
    {
        $user = $request->user();
        if ($user->cant('update_bangumi'))
        {
            return $this->resErrRole();
        }
        $parentSlug = $request->get('parent_slug');
        $childSlug = $request->get('child_slug');

        $parent = Bangumi
            ::where('slug', $parentSlug)
            ->first();

        if (!$parent)
        {
            return $this->resErrNotFound();
        }

        $child = Bangumi
            ::where('slug', $childSlug)
            ->first();

        if (!$child)
        {
            return $this->resErrBad();
        }

        $bangumiRepository = new BangumiRepository();

        $child->update([
            'parent_slug' => $parent->slug
        ]);

        $bangumiRepository->item($childSlug, true);

        if (!$parent->is_parent)
        {
            Bangumi
                ::where('slug', $parentSlug)
                ->update([
                    'is_parent' => true
                ]);

            $bangumiRepository->item($parentSlug, true);
        }

        return $this->resNoContent();
    }
}
