<?php

namespace Tests\Unit;

use App\Support\PersonNameSplitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PersonNameSplitterTest extends TestCase
{
    #[Test]
    public function split_uses_surname_first_catalog_order(): void
    {
        $this->assertSame(
            ['last_name' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'first_name' => 'ΓΙΩΡΓΟΣ'],
            PersonNameSplitter::split('ΠΑΠΑΔΟΠΟΥΛΟΣ ΓΙΩΡΓΟΣ'),
        );
    }

    #[Test]
    public function split_given_name_first_puts_surname_last(): void
    {
        $this->assertSame(
            ['last_name' => 'ΘΕΟΦΑΝΟΠΟΥΛΟΥ', 'first_name' => 'ΕΥΔΟΞΙΑ'],
            PersonNameSplitter::splitGivenNameFirst('ΕΥΔΟΞΙΑ ΘΕΟΦΑΝΟΠΟΥΛΟΥ'),
        );
    }
}
