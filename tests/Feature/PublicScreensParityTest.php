<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicScreensParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_render_greek_docs(): void
    {
        $this->get('/legal')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Legal')
            ->where('doc', 'privacy')
            ->has('docs', 6)
            ->has('html'));

        $this->get('/legal/cookies')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Legal')
            ->where('doc', 'cookies'));

        $this->get('/legal/terms')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('doc', 'terms'));
    }
}
