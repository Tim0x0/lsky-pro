# Lsky Pro 修改日志 (Tim Fork)

> 本文档记录 Tim 对 Lsky Pro 的所有自定义修改

---

## 目录

- [2025-12-14 前端优化 & API文档更新](#2025-12-14-前端优化--api文档更新)
- [2025-12-14 图片列表接口升级](#2025-12-14-图片列表接口升级)
- [2025-12-13 上传API支持指定相册 & 格式排除修复](#2025-12-13-上传api支持指定相册--格式排除修复)
- [2025-08-01 随机图片API & 视频格式支持](#2025-08-01-随机图片api--视频格式支持)

---

## 2025-12-14 前端优化 & API文档更新

### 一、相册列表显示 ID

#### 修改文件
- `resources/views/user/images.blade.php`

#### 功能说明
在相册列表中显示相册 ID，格式为 `[ID] 相册名称`，方便用户识别和使用 API。

#### 修改位置
1. **显示模板** - 第 127 行：添加 ID 显示
2. **编辑逻辑** - 第 378 行：从 `data-json` 获取数据，避免 HTML 干扰
3. **更新逻辑** - 第 440-447 行：编辑成功后保留 ID 显示格式，同步更新 `data-json`

#### 显示效果
```
[1] 我的相册          5
[2] 风景照片          12
[3] 头像收藏          3
```

### 二、API 文档页面更新

#### 修改文件
- `resources/views/common/api.blade.php`

#### 更新内容

| 接口 | 更新内容 |
|------|----------|
| 上传图片 `POST /upload` | 新增 `album_id` 参数 |
| 图片列表 `GET /images` | 新增 `strategy_id`、`per_page` 参数 |
| 随机图片 | 新增整个章节（4个接口） |

#### 新增随机图片接口文档
- `GET /random` - 随机获取图片
- `GET /strategies/:strategy_id/random` - 按存储策略随机获取
- `GET /albums/:album_id/random` - 按相册随机获取
- `GET /random/batch` - 批量随机获取

#### NEW 标签
为所有新增字段和接口添加绿色 NEW 标签，便于用户识别：
```html
<span class="ml-1 px-1.5 py-0.5 text-xs bg-green-100 text-green-600 rounded">NEW</span>
```

---

## 2025-12-14 图片列表接口升级

### 概述

升级图片列表接口，支持按存储策略、相册灵活查询图片。

### 变更文件

| 文件 | 操作 |
|------|------|
| `app/Http/Controllers/Api/V1/Images2Controller.php` | 新建 |
| `app/Http/Controllers/Api/V1/StrategyImageController.php` | 删除 |
| `app/Http/Controllers/Api/V1/ImageController.php` | 添加注释 |
| `routes/api.php` | 修改路由，使用新控制器 |

### 接口信息

| 项目 | 值 |
|------|------|
| 路由 | `GET /api/v1/images` |
| 控制器 | `Images2Controller@index` |
| 认证 | 需要登录（auth:sanctum） |

### 查询参数

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `strategy_id` | int | 否 | - | 存储策略ID |
| `album_id` | int | 否 | - | 相册ID |
| `order` | string | 否 | `newest` | newest/earliest/utmost/least |
| `permission` | string | 否 | `all` | all/public/private |
| `keyword` | string | 否 | - | 按 origin_name 或 alias_name 搜索 |
| `per_page` | int | 否 | `40` | 每页数量 |

### 参数组合逻辑

| 参数组合 | 返回结果 |
|---------|---------|
| 无参数 | 默认相册（未分类）的图片 |
| `album_id=5` | 相册5的所有图片 |
| `strategy_id=1` | 存储策略1的所有图片（跨相册） |
| `strategy_id=1&album_id=5` | 存储策略1 且 相册5 的图片 |

### 请求示例

```bash
# 获取默认相册（未分类）图片
GET /api/v1/images

# 获取相册5的图片
GET /api/v1/images?album_id=5

# 获取存储策略1的所有图片（跨相册）
GET /api/v1/images?strategy_id=1

# 获取存储策略1下相册5的图片
GET /api/v1/images?strategy_id=1&album_id=5

# 分页 + 排序 + 筛选
GET /api/v1/images?strategy_id=1&order=earliest&permission=public&per_page=20&page=2
```

### 向后兼容

| 对比项 | 原接口 | 新接口 |
|--------|--------|--------|
| 无参数 | 返回未分类图片 | 返回未分类图片 ✅ |
| `album_id` | 支持 | 支持 ✅ |
| `order/permission/keyword` | 支持 | 支持 ✅ |
| 返回字段 | 一致 | 一致 ✅ |
| `strategy_id` | ❌ 不支持 | ✅ 新增 |
| `per_page` | ❌ 固定40 | ✅ 可配置 |

---

## 2025-12-13 上传API支持指定相册 & 格式排除修复

### 一、上传 API 支持指定相册

#### 修改文件
- `app/Services/ImageService.php`（第 139-149 行）

#### API 使用方式

**请求示例：**
```
POST /api/v1/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: (图片文件)
album_id: 1        # 可选，指定相册ID
strategy_id: 1     # 可选，指定存储策略ID
```

#### 参数说明

| 参数 | 行为 |
|------|------|
| 不传 `album_id` | 使用用户默认相册设置 |
| `album_id=1` | 保存到指定相册（需属于当前用户） |
| `album_id=` (空值) | 不保存到任何相册（覆盖默认设置） |

#### 验证规则
- 只有登录用户可以指定相册
- 相册必须属于当前用户，否则返回错误："指定的相册不存在或不属于您"

### 二、图片处理格式排除修复

新增排除格式：`psd`, `tif`, `bmp`, `ico`

#### 格式排除汇总表

| 处理环节 | 排除格式 |
|----------|----------|
| 图片处理（压缩/水印） | ico, gif, svg, psd, tif, bmp, mp4, mov, avi, mkv, webm |
| 图片检测（审核） | psd, ico, tif, bmp, svg, mp4, mov, avi, mkv, webm |
| 缩略图生成 | svg, psd, tif, bmp, ico, mp4, mov, avi, mkv, webm |

### 三、问题记录：本地存储策略符号链接

#### 问题描述
上传成功后访问图片 URL 返回 404。

#### 解决方案
在后台重新编辑并保存本地策略配置，触发 `Strategy::booted` 中的自动创建符号链接逻辑。

---

## 2025-08-01 随机图片API & 视频格式支持

### 一、随机图片 API

#### 新增文件
- `app/Http/Controllers/Api/V1/RandomImageController.php`

#### 修改文件
- `routes/api.php`（添加随机图片相关路由）

#### API 路由
```
GET /api/v1/random                           - 随机获取一张图片
GET /api/v1/strategies/{strategy_id}/random  - 按存储策略随机获取
GET /api/v1/albums/{album_id}/random         - 按相册随机获取
GET /api/v1/random/batch                     - 批量随机获取（最多20张）
```

#### API 参数
| 参数 | 说明 |
|------|------|
| `format` | 返回格式：`url`(默认) / `json` / `raw` / `text` |
| `type` | 文件类型筛选（jpg, png, mp4 等） |
| `count` | 批量数量（最大 20，仅 batch 接口） |
| `strategy_id` | 存储策略 ID（仅 batch 接口） |
| `album_id` | 相册 ID（仅 batch 接口） |

### 二、视频格式上传支持

新增支持的视频格式：`mp4`, `mov`, `avi`, `mkv`, `webm`

#### 修改文件清单

| 序号 | 文件路径 | 修改内容 |
|------|----------|----------|
| 1 | `config/convention.php` | 添加视频格式到允许上传列表 |
| 2 | `app/Http/Requests/Admin/GroupRequest.php` | 添加视频格式到验证规则 |
| 3 | `resources/views/components/upload.blade.php` | 视频格式排除预览 |
| 4 | `app/Http/Controllers/Controller.php` | 水印处理跳过视频、视频直接输出 |
| 5 | `app/Services/ImageService.php` | 图片处理/审核/缩略图跳过视频 |
| 6 | `app/Models/Image.php` | 视频缩略图路径返回空 |

#### 视频格式处理逻辑

| 处理环节 | 是否跳过 | 说明 |
|----------|----------|------|
| 文件上传 | ❌ 不跳过 | 允许上传 |
| 图片质量/格式转换 | ✅ 跳过 | 不处理视频文件 |
| 水印处理 | ✅ 跳过 | 不添加水印 |
| 图片审核 | ✅ 跳过 | 不进行内容审核 |
| 缩略图生成 | ✅ 跳过 | 不生成缩略图 |
| 文件输出 | ✅ 直接输出 | 不经过 InterventionImage |
| 预览功能 | ✅ 跳过 | 前端排除预览 |

---

## 所有修改文件汇总

| 文件路径 | 修改日期 | 说明 |
|----------|----------|------|
| `resources/views/user/images.blade.php` | 2025-12-14 | 相册列表显示 ID |
| `resources/views/common/api.blade.php` | 2025-12-14 | API 文档更新，新增 NEW 标签 |
| `app/Http/Controllers/Api/V1/RandomImageController.php` | 2025-08-01 | 新增：随机图片控制器 |
| `app/Http/Controllers/Api/V1/Images2Controller.php` | 2025-12-14 | 新增：升级版图片列表控制器 |
| `routes/api.php` | 多次修改 | API 路由配置 |
| `config/convention.php` | 2025-08-01 | 默认配置 |
| `app/Http/Requests/Admin/GroupRequest.php` | 2025-08-01 | 表单验证 |
| `resources/views/components/upload.blade.php` | 2025-08-01 | 上传组件 |
| `app/Http/Controllers/Controller.php` | 2025-08-01 | 主控制器 |
| `app/Services/ImageService.php` | 多次修改 | 图片服务 |
| `app/Models/Image.php` | 2025-08-01 | 图片模型 |

---

## 注释规范

所有修改统一使用日期标记风格：
```php
// 20250801 by Tim 功能说明
// 20251213 by Tim 功能说明
// 20251214 by Tim 功能说明
```
