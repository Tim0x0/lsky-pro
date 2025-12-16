<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImagePermission;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * 图片列表接口 v2
 * 20251214 by Tim 新增：支持按存储策略、相册灵活查询图片
 */
class Images2Controller extends Controller
{
    /**
     * 获取图片列表
     *
     * 参数组合逻辑：
     * - 无参数：返回默认相册（未分类）的图片
     * - album_id=5：返回相册5的所有图片
     * - strategy_id=1：返回存储策略1的所有图片（跨相册）
     * - strategy_id=1&album_id=5：返回存储策略1且相册5的图片
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $strategyId = (int) $request->query('strategy_id');
        $albumId = (int) $request->query('album_id');
        $perPage = (int) $request->query('per_page') ?: 40;

        // 验证用户是否有权限访问该存储策略
        if ($strategyId) {
            $hasAccess = $user->group->strategies()->where('strategies.id', $strategyId)->exists();
            if (! $hasAccess) {
                return $this->fail('无权访问该存储策略');
            }
        }
        
        // 语法：->when(条件,true回调,false回调)
        $images = $user->images()
            // 按存储策略过滤
            ->when($strategyId, function (Builder $builder, $strategyId) {
                $builder->where('strategy_id', $strategyId);
            })
            // 相册过滤
            ->when($albumId, function (Builder $builder, $albumId) {
                // 有相册ID，按相册过滤
                $builder->where('album_id', $albumId);
            }, function (Builder $builder) use ($strategyId) {
                // 没有相册ID时：有存储策略则不限制相册，没有存储策略则返回默认相册（未分类）
                if (!$strategyId) {
                    $builder->whereNull('album_id');
                }
            })
            // 排序
            ->when($request->query('order') ?: 'newest', function (Builder $builder, $order) {
                switch ($order) {
                    case 'earliest':
                        $builder->orderBy('created_at');
                        break;
                    case 'utmost':
                        $builder->orderByDesc('size');
                        break;
                    case 'least':
                        $builder->orderBy('size');
                        break;
                    default:
                        $builder->latest();
                }
            })
            // 权限过滤
            ->when($request->query('permission') ?: 'all', function (Builder $builder, $permission) {
                switch ($permission) {
                    case 'public':
                        $builder->where('permission', ImagePermission::Public);
                        break;
                    case 'private':
                        $builder->where('permission', ImagePermission::Private);
                        break;
                }
            })
            // 关键词搜索
            ->when($request->query('keyword'), function (Builder $builder, $keyword) {
                $builder->where(function (Builder $query) use ($keyword) {
                    $query->where('origin_name', 'like', "%{$keyword}%")
                          ->orWhere('alias_name', 'like', "%{$keyword}%");
                });
            })
            ->paginate($perPage)
            ->withQueryString();

        // 处理返回字段，与 images 接口保持一致
        $images->getCollection()->each(function (Image $image) {
            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });

        return $this->success('success', $images);
    }
}
