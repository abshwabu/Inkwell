<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImageUploadService
{
    /**
     * Upload, resize to max width 1600px, re-encode to WebP, and store on public disk.
     */
    public function upload(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        $contents = file_get_contents($realPath);

        if ($contents === false) {
            throw new InvalidArgumentException('Could not read uploaded image file.');
        }

        $srcImage = @imagecreatefromstring($contents);
        if ($srcImage === false) {
            throw new InvalidArgumentException('Invalid or unsupported image format.');
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        if ($origWidth <= 0 || $origHeight <= 0) {
            imagedestroy($srcImage);
            throw new InvalidArgumentException('Invalid image dimensions.');
        }

        // Generate max-width 1600 version
        if ($origWidth > 1600) {
            $targetWidth = 1600;
            $targetHeight = (int) round(($origHeight / $origWidth) * 1600);
        } else {
            $targetWidth = $origWidth;
            $targetHeight = $origHeight;
        }

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve alpha transparency for PNG/GIF/WebP
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        imagecopyresampled(
            $targetImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $origWidth,
            $origHeight
        );

        $disk = Storage::disk('public');
        $directory = 'uploads/images';
        $disk->makeDirectory($directory);

        $filename = Str::random(40) . '.webp';
        $relativeFilePath = $directory . '/' . $filename;
        $absoluteFilePath = $disk->path($relativeFilePath);

        $saved = imagewebp($targetImage, $absoluteFilePath, 85);

        imagedestroy($srcImage);
        imagedestroy($targetImage);

        if (! $saved) {
            throw new \RuntimeException('Failed to save WebP image.');
        }

        return $disk->url($relativeFilePath);
    }
}
