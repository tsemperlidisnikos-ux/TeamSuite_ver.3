<?php

namespace Tests\Feature\Auth;

use App\Models\PlatformWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->has('sports')
            ->has('levels'));
    }

    public function test_academy_can_join_waitlist(): void
    {
        $response = $this->post('/register', [
            'club_name' => 'Α.Ο. Νίκη',
            'contact_name' => 'Γιάννης Παπαδόπουλος',
            'email' => 'academy@example.com',
            'phone' => '6900000000',
            'sport' => 'Ποδόσφαιρο',
            'levels' => ['academies'],
            'accept_terms' => '1',
        ]);

        $response->assertRedirect(route('register', absolute: false));
        $this->assertGuest();
        $this->assertDatabaseHas('platform_waitlist', [
            'email' => 'academy@example.com',
            'club_name' => 'Α.Ο. Νίκη',
            'status' => 'pending',
        ]);
        $this->assertSame(1, PlatformWaitlist::query()->count());
    }

    public function test_waitlist_requires_terms(): void
    {
        $response = $this->from('/register')->post('/register', [
            'club_name' => 'Α.Ο. Νίκη',
            'contact_name' => 'Γιάννης Παπαδόπουλος',
            'email' => 'academy@example.com',
            'phone' => '6900000000',
            'sport' => 'Ποδόσφαιρο',
            'levels' => ['academies'],
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('accept_terms');
        $this->assertSame(0, PlatformWaitlist::query()->count());
    }
}
