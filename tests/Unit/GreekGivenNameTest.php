<?php

namespace Tests\Unit;

use App\Support\GreekGivenName;
use PHPUnit\Framework\TestCase;

class GreekGivenNameTest extends TestCase
{
    public function test_guesses_father_from_male_given_name(): void
    {
        $this->assertSame('father', GreekGivenName::guessParentRole('ΘΕΟΦΑΝΗΣ ΑΓΓΕΛΟΠΟΥΛΟΣ'));
        $this->assertSame('ΘΕΟΦΑΝΗΣ', GreekGivenName::extractGivenName('ΘΕΟΦΑΝΗΣ ΑΓΓΕΛΟΠΟΥΛΟΣ'));
    }

    public function test_guesses_mother_from_female_given_name(): void
    {
        $this->assertSame('mother', GreekGivenName::guessParentRole('ΜΑΡΙΑ ΠΑΠΑΔΟΠΟΥΛΟΥ'));
        $this->assertSame('ΜΑΡΙΑ', GreekGivenName::extractGivenName('ΜΑΡΙΑ ΠΑΠΑΔΟΠΟΥΛΟΥ'));
    }

    public function test_migrates_guardian_name_into_empty_father_field(): void
    {
        $data = GreekGivenName::migrateGuardianNameIntoParentFields([
            'guardian_name' => 'ΘΕΟΦΑΝΗΣ ΑΓΓΕΛΟΠΟΥΛΟΣ',
            'father_first_name' => '',
            'mother_first_name' => '',
        ]);

        $this->assertSame('ΘΕΟΦΑΝΗΣ', $data['father_first_name']);
        $this->assertNull($data['guardian_name']);
        $this->assertSame('', $data['mother_first_name']);
    }

    public function test_does_not_overwrite_existing_parent_name(): void
    {
        $data = GreekGivenName::migrateGuardianNameIntoParentFields([
            'guardian_name' => 'ΘΕΟΦΑΝΗΣ ΑΓΓΕΛΟΠΟΥΛΟΣ',
            'father_first_name' => 'ΝΙΚΟΣ',
            'mother_first_name' => '',
        ]);

        $this->assertSame('ΝΙΚΟΣ', $data['father_first_name']);
        $this->assertSame('ΘΕΟΦΑΝΗΣ ΑΓΓΕΛΟΠΟΥΛΟΣ', $data['guardian_name']);
    }
}
