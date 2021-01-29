<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\ReplacementVisa;

use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Exceptions\NoReplaceableVisaException;
use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplaceableVisa;
use Tamkeen\Musaned\tests\eVisa\TestCase;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaDummyRepository;

class ReplaceableVisaTest extends TestCase
{
    /** @test */
    public function get_replaceable_visa_for_labor()
    {
        $replaceable_visa = new ReplaceableVisa(new VisaDummyRepository());

        $this->assertEquals('1900021320', $replaceable_visa->forLaborer('1010101010', '1400-01-01', '2048860635'));
    }

    /** @test */
    public function throws_exception_when_there_is_no_replaceable_visa()
    {
        $this->expectException(NoReplaceableVisaException::class);

        (new ReplaceableVisa(new VisaDummyRepository()))
            ->forLaborer('2020202020', '1400-01-01', '2048860635');
    }
}
