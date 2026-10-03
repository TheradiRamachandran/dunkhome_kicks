<?php
declare(strict_types=1);

function storeCategoryImageUpload(?array $file): array
{
    if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if (
        (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || (int) ($file['size'] ?? 0) < 1
        || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024
        || $temporaryPath === ''
        || @getimagesize($temporaryPath) === false
    ) {
        return ['path' => null, 'error' => 'Choose a JPG, PNG or WEBP image no larger than 5 MB.'];
    }

    $allowedImageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    if (!is_string($mimeType) || !isset($allowedImageTypes[$mimeType])) {
        return ['path' => null, 'error' => 'Choose a JPG, PNG or WEBP image no larger than 5 MB.'];
    }

    $uploadDirectory = __DIR__ . '/../uploads/categories';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
        return ['path' => null, 'error' => 'Unable to save the category image. Please try again.'];
    }

    $relativePath = 'uploads/categories/' . bin2hex(random_bytes(12)) . '.' . $allowedImageTypes[$mimeType];
    if (!move_uploaded_file($temporaryPath, __DIR__ . '/../' . $relativePath)) {
        return ['path' => null, 'error' => 'Unable to save the category image. Please try again.'];
    }

    return ['path' => $relativePath, 'error' => null];
}

function removeManagedCategoryImage(string $relativePath): void
{
    $uploadDirectory = realpath(__DIR__ . '/../uploads/categories');
    $imagePath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));

    if (
        $uploadDirectory !== false
        && $imagePath !== false
        && strpos($imagePath, $uploadDirectory . DIRECTORY_SEPARATOR) === 0
        && is_file($imagePath)
    ) {
        @unlink($imagePath);
    }
}