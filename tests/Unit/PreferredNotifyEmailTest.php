<?php

namespace Tests\Unit;

use App\Support\PreferredNotifyEmail;
use PHPUnit\Framework\TestCase;

class PreferredNotifyEmailTest extends TestCase
{
    public function test_both_expands_to_father_and_mother(): void
    {
        $this->assertSame(['father', 'mother'], PreferredNotifyEmail::targets('both'));
        $this->assertSame(['father', 'mother'], PreferredNotifyEmail::targets(null));
    }

    public function test_single_targets(): void
    {
        $this->assertSame(['father'], PreferredNotifyEmail::targets('father'));
        $this->assertSame(['mother'], PreferredNotifyEmail::targets('mother'));
        $this->assertSame(['athlete'], PreferredNotifyEmail::targets('athlete'));
    }

    public function test_comma_separated_targets(): void
    {
        $this->assertSame(
            ['father', 'mother', 'athlete'],
            PreferredNotifyEmail::targets('father,mother,athlete')
        );
    }
}
