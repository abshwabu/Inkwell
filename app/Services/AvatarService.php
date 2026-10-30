<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarService
{
    /**
     * Resize avatar to 256x256 square crop and store on public disk.
     */
    public function resizeAndStore(UploadedFile $file, ?string $oldAvatarPath = null): string
    {
        $disk = Storage::disk('public');
        $disk->makeDirectory('avatars');

        $filename = Str::random(40) . '.webp';
        $destinationPath = $disk->path('avatars/' . $filename);

        $source = imagecreatefromstring($file->get());
        if ($source === false) {
            throw new \InvalidArgumentException('Invalid image file provided.');
        }

        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        $cropSize = min($origWidth, $origHeight);
        $cropX = (int) (($origWidth - $cropSize) / 2);
        $cropY = (int) (($origHeight - $cropSize) / 2);

        $target = imagecreatetruecolor(256, 256);

        // Retain alpha channel transparency for PNG/WEBP
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            256,
            256,
            $cropSize,
            $cropSize
        );

        imagewebp($target, $destinationPath, 85);

        imagedestroy($target);
        imagedestroy($source);

        // Delete old local avatar if exists
        if ($oldAvatarPath && str_starts_with($oldAvatarPath, '/storage/avatars/')) {
            $oldFilename = basename($oldAvatarPath);
            $disk->delete('avatars/' . $oldFilename);
        }

        return '/storage/avatars/' . $filename;
    }
}
