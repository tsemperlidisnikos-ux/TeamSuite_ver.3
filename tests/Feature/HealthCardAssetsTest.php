<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCardAssetsTest extends TestCase
{
    public function test_builtin_health_card_templates_exist_on_disk(): void
    {
        $this->assertFileExists(public_path('health-card/health-card-template.pdf'));
        $this->assertFileExists(public_path('health-card/health-card-volleyball-template.pdf'));
        $this->assertFileExists(public_path('health-card/Arial-Bold.ttf'));

        $pdf = file_get_contents(public_path('health-card/health-card-template.pdf'), false, null, 0, 5);
        $this->assertSame('%PDF-', $pdf);
    }
}
