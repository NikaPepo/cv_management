<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Issues signed payloads the browser needs for direct uploads to Cloudinary.
 *
 * We do not stream images through Symfony — the assignment says "external
 * cloud storage by drag-n-drop", so the browser talks to Cloudinary directly
 * using a short-lived signature.
 *
 * The signature is computed manually (per Cloudinary docs at
 * https://cloudinary.com/documentation/upload_images#generating_authentication_signature)
 * so we do not depend on any internal SDK property. The parameters must be
 * sorted alphabetically before being hashed together with the API secret.
 */
final readonly class CloudinarySignatureService
{
    public function __construct(
        private string $cloudName,
        private string $apiKey,
        private string $apiSecret,
    ) {
    }

    /**
     * @return array{cloudName: string, apiKey: string, timestamp: int, signature: string, folder: string}
     */
    public function issueSignature(string $folder): array
    {
        $timestamp = time();
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        // Cloudinary requires alphabetically-sorted params before hashing.
        ksort($params);

        $signature = sha1(http_build_query($params) . $this->apiSecret);

        return [
            'cloudName' => $this->cloudName,
            'apiKey' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $folder,
        ];
    }
}