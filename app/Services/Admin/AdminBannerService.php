<?php

namespace App\Services\Admin;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Save banner fields and their image path using the configured media disk. */
class AdminBannerService
{
    /** Persist banner fields and optional media under the configured image path. */
    public function save(Request $request, array $data): Banner
    {
        $id = $data['id'] ?? null;
        unset($data['id']);

        return DB::transaction(function () use ($request, $data, $id): Banner {
            $banner = $id ? Banner::query()->findOrFail($id) : new Banner;
            $oldImage = $banner->image_path;
            if ($data['remove_image'] ?? false) {
                $data['image_path'] = null;
            }
            unset($data['remove_image'], $data['image']);
            if ($data['link_type'] === 'none') {
                $data['link_value'] = null;
            }

            if ($request->hasFile('image')) {
                $data['image_path'] = $request->file('image')->store(
                    config('imagepath.directories.banners'),
                    config('imagepath.disk')
                );
            }

            $banner->fill($data)->save();

            if (($request->hasFile('image') || $banner->image_path === null) && $oldImage) {
                Storage::disk(config('imagepath.disk'))->delete($oldImage);
            }

            return $banner->refresh();
        });
    }
}
