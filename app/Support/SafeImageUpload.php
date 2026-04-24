<?php

namespace App\Support;

/**
 * Helpers for safely deriving on-disk filenames for uploaded images.
 *
 * Security review H-6 / H-6b: `$file->getClientOriginalExtension()` is
 * attacker-controlled. Storing a file as `<uuid>.<client-extension>`
 * is fine for honest clients but lets a polyglot upload land as
 * `<uuid>.php.jpg` or `<uuid>.phtml`. Chained with any misconfigured
 * Apache PHP handler over the public storage tree, that becomes RCE.
 *
 * Instead, derive the stored extension from the sniffed image type
 * returned by `getimagesize()`. `IMAGETYPE_*` constants are computed
 * from the file's magic bytes and cannot be forged without a valid
 * image header.
 *
 * Callers should invoke `getimagesize()` themselves (it's a cheap I/O
 * they usually already perform) and then feed the result here. Returns
 * null when the image type isn't something we're willing to accept —
 * caller must treat null as a validation failure.
 */
final class SafeImageUpload
{
    /**
     * @param  array<int|string,mixed>|false  $imageInfo  the return value of getimagesize()
     */
    public static function extensionFor(array|false $imageInfo): ?string
    {
        if (! is_array($imageInfo)) {
            return null;
        }

        return match ($imageInfo[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
    }
}
