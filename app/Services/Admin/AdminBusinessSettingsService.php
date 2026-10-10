<?php

namespace App\Services\Admin;

use App\Models\BusinessSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Persist bakery settings and configured logo/cover uploads. */
class AdminBusinessSettingsService
{
    /** Save allowed fields and clean newly uploaded files when persistence fails. */
    public function updateSettings(array $data): BusinessSetting
    {
        $uploadedPaths = [];
        try {
            foreach (['logo' => 'logo_path', 'cover_image' => 'cover_image_path'] as $field => $column) {
                $file = $data[$field] ?? null;
                unset($data[$field]);
                if ($file instanceof UploadedFile) {
                    $directory = $field === 'logo' ? 'business.logo' : 'business.cover';
                    $path = $file->store(config('imagepath.'.$directory), config('imagepath.disk'));
                    if (! $path) {
                        throw new RuntimeException('The business image could not be stored.');
                    }
                    $uploadedPaths[] = $path;
                    $data[$column] = $path;
                }
            }

            return DB::transaction(function () use ($data): BusinessSetting {
                $record = BusinessSetting::query()->orderBy('id')->lockForUpdate()->first() ?? new BusinessSetting();
                $record->fill($data)->save();

                return $record;
            });
        } catch (Throwable $exception) {
            Storage::disk(config('imagepath.disk'))->delete($uploadedPaths);
            throw $exception;
        }
    }
}
