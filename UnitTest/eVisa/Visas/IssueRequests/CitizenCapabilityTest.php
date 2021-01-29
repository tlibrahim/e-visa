<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\IssueRequests;

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Services\CA\CitizenCapabilityService;
use Tamkeen\Musaned\tests\eVisa\TestCase;

class CitizenCapabilityTest extends TestCase
{
    public function test_user_capability_is_true()
    {
        $endUser = factory(EndUser::class)->make(['id_number' => '1010101010']);
        $citizenRepository = app()->make(CitizenCapabilityService::class);
        $result = $citizenRepository->forUser($endUser)->isCapable(10000000);
        $this->assertEquals($result, 'yes');
    }

    public function test_user_capability_is_false()
    {
        $endUser = factory(EndUser::class)->make(['id_number' => '1020101000']);
        $citizenRepository = app()->make(CitizenCapabilityService::class);
        $result = $citizenRepository->forUser($endUser)->isCapable(10000000);
        $this->assertEquals($result, 'no');
    }
}
