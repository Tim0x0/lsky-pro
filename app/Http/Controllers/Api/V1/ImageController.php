<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UploadException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use App\Services\UserService;
use App\Utils;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function upload(Request $request, ImageService $service): Response
    {
        if ($request->hasHeader('Authorization')) {
            $guards = array_keys(config('auth.guards'));

            if (empty($guards)) {
                $guards = [null];
            }

            foreach ($guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    Auth::shouldUse($guard);
                    break;
                }
            }

            if (! Auth::check()) {
                throw new AuthenticationException('Authentication failed.');
            }
        }

        try {
            $image = $service->store($request);
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 上传文件时发生异常');
            if (config('app.debug')) {
                return $this->fail($e->getMessage());
            }
            return $this->fail('服务异常，请稍后再试');
        }
        return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
            'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
        ));
    }

    /**
     * 获取当前用户的图片列表
     * 注意：默认只返回未分类（album_id=NULL）的图片，不包括相册内的图片
     *
     * @param Request $request 支持的查询参数：
     *   - order: 排序方式 (newest|earliest|utmost|least)
     *   - permission: 权限过滤 (all|public|private)
     *   - keyword: 关键词搜索
     *   - album_id: 相册ID，不传则只返回未分类图片
     * @return Response
     */
    public function images(Request $request): Response
    {
        /** @var User $user */
        // 获取当前登录用户
        $user = Auth::user();

        // 查询用户的图片，filter() 是 Image 模型的 scopeFilter 方法
        // 关键：filter() 默认会添加 whereNull('album_id') 条件，只返回未分类图片
        // paginate(40) 每页40条，withQueryString() 保留URL查询参数用于分页链接
        $images = $user->images()->filter($request)->paginate(40)->withQueryString();

        // 遍历每张图片，添加额外的展示字段
        $images->getCollection()->each(function (Image $image) {
            // 人性化时间，如"3天前"
            $image->human_date = $image->created_at->diffForHumans();
            // 格式化日期时间
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            // append() 添加访问器字段（pathname、links）
            // setVisible() 限制返回的字段，隐藏敏感信息
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });

        return $this->success('success', $images);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        (new UserService())->deleteImages([$request->route('key')], $user, 'key');
        return $this->success('删除成功');
    }
}
