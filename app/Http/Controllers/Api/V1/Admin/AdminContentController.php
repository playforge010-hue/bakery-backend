<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\WriteAdminAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminBannerRequest;
use App\Http\Requests\Api\V1\Admin\AdminCatalogActionRequest;
use App\Models\Banner;
use App\Services\Admin\AdminBannerService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/** Admin endpoints for managing homepage promotional banners. */
class AdminContentController extends Controller
{
    use ApiResponseTrait;

    /** Return current banners with customer-visible fields and image URLs. */
    public function getBannerList(AdminCatalogActionRequest $request): JsonResponse
    {
        $banners = Banner::query()
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 30), 100));

        return $this->successResponse(
            'Homepage banners loaded.',
            $banners->through(fn (Banner $banner) => [
                'id' => $banner->id,
                'title_translations' => $banner->title_translations,
                'subtitle_translations' => $banner->subtitle_translations,
                'image_url' => $banner->image_url,
                'link_type' => $banner->link_type,
                'link_value' => $banner->link_value,
                'starts_at' => $banner->starts_at?->toIso8601String(),
                'ends_at' => $banner->ends_at?->toIso8601String(),
                'is_active' => $banner->is_active,
                'sort_order' => $banner->sort_order,
            ]),
            ['current_page' => $banners->currentPage(), 'last_page' => $banners->lastPage(), 'total' => $banners->total()]
        );
    }

    /** Create a banner using its allowlisted form fields. */
    public function createBanner(AdminBannerRequest $request, AdminBannerService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveBanner($request, $service, $audit);
    }

    /** Update the text, schedule, destination or image of a banner. */
    public function updateBanner(AdminBannerRequest $request, AdminBannerService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveBanner($request, $service, $audit);
    }

    /** Enable or disable a banner without losing its configured presentation. */
    public function updateBannerStatus(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $banner = Banner::query()->findOrFail($data['id']);
        $banner->is_active = $data['is_active'];
        $banner->save();
        $audit->execute($request, 'banner.status_changed', $banner, ['is_active' => $banner->is_active]);

        return $this->successResponse('Banner status updated.', ['id' => $banner->id, 'is_active' => $banner->is_active]);
    }

    /** Delete a banner record and its stored image. */
    public function deleteBanner(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $banner = Banner::query()->findOrFail($request->validated('id'));
        $imagePath = $banner->image_path;
        $banner->delete();
        if ($imagePath) {
            Storage::disk(config('imagepath.disk'))->delete($imagePath);
        }
        $audit->execute($request, 'banner.deleted', $banner);

        return $this->successResponse('Banner deleted.', []);
    }

    /** Persist a banner and return only fields required by the admin form. */
    private function saveBanner(AdminBannerRequest $request, AdminBannerService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        $banner = $service->save($request, $request->validated());
        $audit->execute($request, 'banner.saved', $banner);

        return $this->successResponse('Banner saved.', [
            'id' => $banner->id,
            'title_translations' => $banner->title_translations,
            'image_url' => $banner->image_url,
            'link_type' => $banner->link_type,
            'link_value' => $banner->link_value,
            'is_active' => $banner->is_active,
        ]);
    }
}
