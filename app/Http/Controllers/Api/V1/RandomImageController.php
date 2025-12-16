<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RandomImageController extends Controller
{
    /**
     * 随机获取用户的一张图片（不限制存储策略）
     * 支持参数：
     * - type: 文件类型，如 png, jpg, mp4
     * - format: 返回格式 json/url/raw
     * 支持三种返回方式：
     * - 默认：返回图片URL链接
     * - ?format=json：返回图片信息JSON
     * - ?format=raw：直接返回图片内容
     */
    public function random(Request $request): Response
    {
        $format = $request->query('format', 'url');
        $type = $request->query('type');

        /** @var User $user */
        $user = Auth::user();

        $query = $user->images();

        // 如果指定了文件类型，添加筛选条件
        if ($type) {
            $query->where('extension', strtolower($type));
        }

        // 随机获取用户的一张图片
        $image = $query->inRandomOrder()->first();

        if (!$image) {
            if ($type) {
                return $this->fail("没有找到 {$type} 类型的文件");
            }
            return $this->fail('没有找到图片');
        }

        return $this->formatImageResponse($image, $format);
    }

    /**
     * 根据存储策略ID随机获取一张图片
     * 支持三种返回方式：
     * - 默认：返回图片URL链接
     * - ?format=json：返回图片信息JSON
     * - ?format=raw：直接返回图片内容
     */
    public function byStrategy(Request $request): Response
    {
        $strategyId = $request->route('strategy_id');
        $format = $request->query('format', 'url');

        // 验证存储策略是否存在
        $strategy = Strategy::find($strategyId);
        if (!$strategy) {
            return $this->fail('存储策略不存在');
        }

        /** @var User $user */
        $user = Auth::user();

        // 从该存储策略中随机获取用户的一张图片
        $image = $user->images()
            ->where('strategy_id', $strategyId)
            ->inRandomOrder()
            ->first();

        if (!$image) {
            return $this->fail('该存储策略下没有找到图片');
        }

        return $this->formatImageResponse($image, $format);
    }

    /**
     * 根据相册ID随机获取一张图片
     * 支持三种返回方式：
     * - 默认：返回图片URL链接
     * - ?format=json：返回图片信息JSON
     * - ?format=raw：直接返回图片内容
     */
    public function byAlbum(Request $request): Response
    {
        $albumId = $request->route('album_id');
        $format = $request->query('format', 'url');

        /** @var User $user */
        $user = Auth::user();

        // 验证相册是否存在且属于当前用户
        $album = $user->albums()->find($albumId);
        if (!$album) {
            return $this->fail('相册不存在或无权访问');
        }

        // 从该相册中随机获取一张图片
        $image = $user->images()
            ->where('album_id', $albumId)
            ->inRandomOrder()
            ->first();

        if (!$image) {
            return $this->fail('该相册下没有找到图片');
        }

        return $this->formatImageResponse($image, $format);
    }

    /**
     * 批量随机获取图片
     * 支持参数：
     * - count: 获取数量，默认5，最大20
     * - strategy_id: 限制存储策略
     * - album_id: 限制相册
     * - type: 限制文件类型
     */
    public function batch(Request $request): Response
    {
        $count = min((int)$request->query('count', 5), 20);
        $strategyId = $request->query('strategy_id');
        $albumId = $request->query('album_id');
        $type = $request->query('type');

        /** @var User $user */
        $user = Auth::user();

        $query = $user->images();

        // 添加筛选条件
        if ($strategyId) {
            $strategy = Strategy::find($strategyId);
            if (!$strategy) {
                return $this->fail('存储策略不存在');
            }
            $query->where('strategy_id', $strategyId);
        }

        if ($albumId) {
            $album = $user->albums()->find($albumId);
            if (!$album) {
                return $this->fail('相册不存在或无权访问');
            }
            $query->where('album_id', $albumId);
        }

        if ($type) {
            $query->where('extension', strtolower($type));
        }

        // 随机获取指定数量的图片
        $images = $query->inRandomOrder()->limit($count)->get();

        if ($images->isEmpty()) {
            return $this->fail('没有找到符合条件的图片');
        }

        // 格式化返回数据
        $images->each(function (Image $image) {
            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });

        return $this->success('获取成功', [
            'count' => $images->count(),
            'images' => $images
        ]);
    }

    /**
     * 格式化图片响应
     */
    private function formatImageResponse(Image $image, string $format): Response
    {
        switch ($format) {
            case 'url':
            case 'text':
                // 返回图片URL
                return response($image->url, 200, [
                    'Content-Type' => 'text/plain',
                ]);

            case 'raw':
                // 直接返回图片内容
                try {
                    $contents = $image->filesystem()->read($image->pathname);
                    return response($contents, 200, [
                        'Content-Type' => $image->mimetype,
                        'Content-Length' => strlen($contents),
                        'Cache-Control' => 'public, max-age=3600',
                        'Content-Disposition' => 'inline; filename="' . $image->origin_name . '"',
                    ]);
                } catch (\Exception $e) {
                    return $this->fail('读取文件失败');
                }

            default:
                // 返回JSON格式的图片信息
                $image->human_date = $image->created_at->diffForHumans();
                $image->date = $image->created_at->format('Y-m-d H:i:s');
                $image->append(['pathname', 'links'])->setVisible([
                    'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                    'width', 'height', 'links', 'human_date', 'date',
                ]);

                return $this->success('获取成功', $image);
        }
    }
}
