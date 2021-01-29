<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\IssueRequests;

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueChecker;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueCheckerService;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsMemoryRepository;
use Tamkeen\Musaned\tests\eVisa\TestCase;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaDummyRepository;

class IssueCheckerServiceTest extends TestCase
{
    /**
     * @var VisaDummyRepository
     */
    private $_visaDummyRepository;

    public function setUp()
    {
        parent::setUp();
        $this->_visaDummyRepository = new VisaDummyRepository();
    }

    public function testSaudiManDivorcedWidowedHasChildsIsAllowedToRequestVisa()
    {
        $user = factory(EndUser::class)->create(['id_number' => 1010101010]);
        $this->assertEquals('SaudiManDivorcedWidowedHasChilds', $this->getNICAutomationClassification($user));
        $checker = new IssueCheckerService($this->_visaDummyRepository, new IssueRequestsMemoryRepository());
        $this->assertTrue($checker->isAllowedToRequestVisa($user));
    }

    public function testDomesticLaborersAreIdentifiedCorrectly()
    {
        $domesticLaborers = collect([
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'عامل منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'سفرجي منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'سائق خاص']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'ممرض منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مباشر منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مباشرة منزلية']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'اخصائي علاج طبيعي خاص']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'اخصائي نطق و سمع خاص']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'حارس أمن خاص']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'حارس منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'حارس عمارة']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'خياط منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'طباخ منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مدير منزل']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مزارع منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'قهوجي منزلي']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مربية']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'ممرضة منزلية']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'عاملة منزلية']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'طباخة منزلية']],
            ['Status'       => 'صالح',
             'TravelStatus' => 'داخل المملكة',
             'Occupation'   => ['Name' => 'اخصائية علاج طبيعي خاصة'],
            ],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'خياطة منزلية']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مديرة منزل']],
            ['Status'       => 'صالح',
             'TravelStatus' => 'داخل المملكة',
             'Occupation'   => ['Name' => 'اخصائية نطق و سمع خاصة'],
            ],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مدرس خاص']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'مربية أطفال']],
            ['Status' => 'صالح', 'TravelStatus' => 'داخل المملكة', 'Occupation' => ['Name' => 'عامل نظافة']],
        ]);
        $this->assertCount(26, IssueCheckerService::DLBALabors($domesticLaborers));
    }

    private function getNICAutomationClassification(EndUser $user)
    {
        $nicInfo = $this->_visaDummyRepository->getPersonalInfo($user->getIdNumber(), $user->getCheckDate());

        return (new IssueChecker($nicInfo, []))->getKey();
    }
}
