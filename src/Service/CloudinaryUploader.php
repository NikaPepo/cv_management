<?php

declare(strict_types=1);

namespace App\Service;

use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Cloudinary;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CloudinaryUploader
{
    private readonly Cloudinary $cloudinary;

    public function __construct(
        string $cloudName,
        string $apiKey,
        string $apiSecret,
    ) {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
        ]);
    }

    /**
     * Uploads an image and returns its secure_url. Cloudinary SDK throws
     * ApiError on transport / API failures; we translate that into an HTTP
     * 502 so the frontend can surface a real error instead of a 500.
     */
    public function uploadImage(string $dataUri, string $folder, string $publicId): string
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload(
                $dataUri,
                [
                    'folder' => $folder,
                    'public_id' => $publicId,
                    'resource_type' => 'image',
                ]
            );
        } catch (ApiError $e) {
            throw new HttpException(502, 'Cloudinary upload failed: ' . $e->getMessage(), $e);
        }

        return (string) $result['secure_url'];
    }
}