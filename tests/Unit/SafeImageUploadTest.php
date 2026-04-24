<?php

namespace Tests\Unit;

use App\Support\SafeImageUpload;
use PHPUnit\Framework\TestCase;

/**
 * Locks the image-type → extension mapping used by both
 * WorkOrderPhotoController (H-6) and CheckinService (H-6b).
 * If someone later widens or narrows the allowlist, this test must
 * be updated in the same change.
 */
class SafeImageUploadTest extends TestCase
{
    public function test_it_returns_jpg_for_jpeg_image_type(): void
    {
        $this->assertSame('jpg', SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, 2 => IMAGETYPE_JPEG,
        ]));
    }

    public function test_it_returns_png_for_png_image_type(): void
    {
        $this->assertSame('png', SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, 2 => IMAGETYPE_PNG,
        ]));
    }

    public function test_it_returns_webp_for_webp_image_type(): void
    {
        $this->assertSame('webp', SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, 2 => IMAGETYPE_WEBP,
        ]));
    }

    /**
     * Anything outside the allowlist returns null — callers must treat
     * this as a validation failure, not silently store the file with a
     * default extension.
     */
    public function test_it_returns_null_for_gif(): void
    {
        $this->assertNull(SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, 2 => IMAGETYPE_GIF,
        ]));
    }

    public function test_it_returns_null_for_bmp(): void
    {
        $this->assertNull(SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, 2 => IMAGETYPE_BMP,
        ]));
    }

    public function test_it_returns_null_when_getimagesize_failed(): void
    {
        // getimagesize() returns false when the file is not a real image.
        // The helper accepts that sentinel and normalises to null.
        $this->assertNull(SafeImageUpload::extensionFor(false));
    }

    public function test_it_returns_null_for_an_array_without_imagetype_index(): void
    {
        $this->assertNull(SafeImageUpload::extensionFor([
            0 => 100, 1 => 100, // no index 2
        ]));
    }
}
