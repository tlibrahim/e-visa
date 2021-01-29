<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\IssueRequests;

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Services\GOSI\GOSICapabilityService;
use Tamkeen\Musaned\tests\eVisa\TestCase;

class GOSITest extends TestCase
{
    public function test_user_capability_is_true()
    {
        $endUser = factory(EndUser::class)->make(['id_number' => '2044316194']);
        $citizenRepository = app()->make(GOSICapabilityService::class);
        $result = $citizenRepository->forUser($endUser)->isCapable(10000000);
        $this->assertEquals($result, false);
    }

    public function test_user_capability_is_false()
    {
        $endUser = factory(EndUser::class)->make(['id_number' => '1020101000']);
        $citizenRepository = app()->make(GOSICapabilityService::class);
        $result = $citizenRepository->forUser($endUser)->isCapable(10000000);
        $this->assertEquals($result, true);
    }
}
