<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Modules\Counter\TagPatchCounter;
use App\Http\Repositories\TagRepository;
use App\Http\Transformers\Tag\TagResource;
use App\Models\Pin;
use App\Models\Tag;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    /**
     * 根据 parent_slug 获取到 children
     * parent_slug 支持 string 和 array？
     */
    public function show(Request $request)
    {
        $slug = $request->get('slug');

        $tagRepository = new TagRepository();
        $tag = $tagRepository->item($slug);
        if (is_null($tag))
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($tag);
    }

    public function patch(Request $request)
    {
        $slug = $request->get('slug');

        $tagRepository = new TagRepository();
        $data = $tagRepository->item($slug);
        if (is_null($data))
        {
            return $this->resErrNotFound();
        }

        $tagPatchCounter = new TagPatchCounter();
        $patch = $tagPatchCounter->all($slug);
        $user = $request->user();

        if (!$user)
        {
            return $this->resOK($patch);
        }

        $tagId = slug2id($slug);
        $patch['is_marked'] = $user->hasBookmarked($tagId, Tag::class);
        $patch['is_master'] = $user->hasFavorited($tagId, Tag::class);

        return $this->resOK($patch);
    }

    /**
     * 返回热门 tag
     */
    public function hottest(Request $request)
    {
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 12;
        $tagRepository = new TagRepository();
        $hottest = $tagRepository->hottest($page, $take);

        foreach ($hottest['result'] as $i => $item)
        {
            $hottest['result'][$i]->type = 'grid';
        }

        return $this->resOK($hottest);
    }

    public function children(Request $request)
    {
        $slug = $request->get('slug');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 10;
        if (!$slug)
        {
            return $this->resErrBad();
        }

        $tagRepository = new TagRepository();

        $children = $tagRepository->children($slug, $page, $take);

        return $this->resOK($children);
    }

    public function batchPatch(Request $request)
    {
        $list = $request->get('slug') ? explode(',', $request->get('slug')) : [];
        $tagPatchCounter = new TagPatchCounter();

        $result = [];
        foreach ($list as $slug)
        {
            $result[$slug] = $tagPatchCounter->all($slug);
        }

        return $this->resOK($result);
    }

    /**
     * 获取用户的收藏版区
     */
    public function bookmarks(Request $request)
    {
        $slug = $request->get('slug');

        $tagRepository = new TagRepository();
        $result = $tagRepository->bookmarks($slug);

        if (is_null($result))
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($result);
    }

    /**
     * 创建 tag
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:1|max:32',
            'parent_slug' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $name = $request->get('name');
        $user = $request->user();
        $parentSlug = $request->get('parent_slug');
        $isNotebook = $parentSlug === config('app.tag.notebook');
        if (!$isNotebook && $user->cant('create_tag'))
        {
            return $this->resErrRole();
        }

        $parent = Tag
            ::where('slug', $parentSlug)
            ->first();

        if (is_null($parent))
        {
            return $this->resErrBad();
        }

        $wordsFilter = new WordsFilter();
        if ($wordsFilter->count($name))
        {
            return $this->resErrBad();
        }

        $tag = Tag::createTag($name, $user, $parent);

        return $this->resOK(new TagResource($tag));
    }

    /**
     * 更新 tag
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:1|max:32',
            'slug' => 'required|string',
            'avatar' => 'required|string',
            'intro' => 'required|string|max:233',
            'alias' => 'required|string|max:100',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $request->user();
        $slug = $request->get('slug');

        $tag = Tag
            ::where('slug', $slug)
            ->first();

        if (is_null($tag))
        {
            return $this->resErrNotFound();
        }

        if (!$user->favorite($tag))
        {
            return $this->resErrRole();
        }

        $name = $request->get('name');
        $intro = $request->get('intro');
        $alias = $request->get('alias');

        $wordsFilter = new WordsFilter();
        if ($wordsFilter->count($name . $intro . $alias))
        {
            return $this->resErrBad('请修改文字');
        }

        $image = $request->get('avatar');
        $imageFilter = new ImageFilter();
        $result = $imageFilter->check($image);
        if ($result['delete'] || $result['review'])
        {
            return $this->resErrBad('请更换图片');
        }

        $tag->updateTag([
            'avatar' => trimImage($image),
            'name' => $name,
            'intro' => $intro,
            'alias' => $alias
        ], $user);

        return $this->resNoContent();
    }

    /**
     * 删除 tag，子标签移到回收站
     */
    public function delete(Request $request)
    {
        $user = $request->user();
        if ($user->cant('delete_tag'))
        {
            return $this->resErrRole();
        }

        $validator = Validator::make($request->all(), [
            'slug' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $slug = $request->get('slug');

        if ($slug === config('app.tag.trash'))
        {
            return $this->resErrRole();
        }

        $tag = Tag
            ::where('slug', $slug)
            ->first();

        if (is_null($tag))
        {
            return $this->resErrNotFound();
        }

        if ($tag->parent_slug === config('app.tag.calibur'))
        {
            return $this->resNoContent();
        }

        $tag->deleteTag($user);

        return $this->resNoContent();
    }

    public function atfield(Request $request)
    {
        $slug = $request->get('slug');
        if (!$slug)
        {
            return $this->resErrBad();
        }

        $trialCount = Pin
            ::where('content_type', 2)
            ->whereHas('tags', function ($query) use ($slug)
            {
                $query->where('slug', $slug);
            })
            ->whereNull('recommended_at')
            ->count();

        $passCount = Pin
            ::where('content_type', 2)
            ->whereHas('tags', function ($query) use ($slug)
            {
                $query->where('slug', $slug);
            })
            ->whereNotNull('recommended_at')
            ->count();

        return $this->resOK([
            'trial' => $trialCount,
            'pass' => $passCount
        ]);
    }

    public function search()
    {
        $tagRepository = new TagRepository();
        $result = $tagRepository->search();

        return $this->resOK($result);
    }
}
