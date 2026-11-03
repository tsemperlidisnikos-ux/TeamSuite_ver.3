<?php

namespace Tests\Unit;

use App\Support\ClubPublicMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClubPublicMediaTest extends TestCase
{
    public function test_legacy_club_media_paths_resolve_to_null(): void
    {
        $this->assertNull(ClubPublicMedia::publicUrl(
            '/api/club-media?p=ss360-media%2Fclub_1%2Fphoto.jpg&v='
        ));
        $this->assertTrue(ClubPublicMedia::isLegacyClubMediaPath(
            '/api/club-media?p=ss360-media/club_1/photo.jpg'
        ));
    }

    public function test_data_url_is_stored_on_public_disk(): void
    {
        Storage::fake('public');

        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
        $dataUrl = 'data:image/png;base64,'.$png;

        $path = ClubPublicMedia::normalizeForStorage($dataUrl, 'club-test', 'club-media/facilities');

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringContainsString('/storage/', (string) ClubPublicMedia::publicUrl($path));
    }
}
