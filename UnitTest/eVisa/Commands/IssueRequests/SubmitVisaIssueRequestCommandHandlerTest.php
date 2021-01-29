<?php

namespace Tamkeen\Musaned\tests\eVisa\Commands\IssueRequests;

use Carbon\Carbon;
use Doctrine\DBAL\Query\QueryException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tamkeen\Musaned\BackOffice\Visas\Blacklist\BlacklistMemoryRepository;
use Tamkeen\Musaned\EndUsers\Billing\Bill;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\EndUsers\Users\Repositories\EndUsersMemoryRepository;
use Tamkeen\Musaned\EndUsers\Users\Uploads\EndUserUploadId;
use Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\SubmitVisaIssueRequestCommand;
use Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\SubmitVisaIssueRequestCommandHandler;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\AttachedDocument;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\DocumentType;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\FinancialCapabilityProof;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\FinancialCapabilityProofType;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IsDisable;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueCheckerService;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequestStatus;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsMemoryRepository;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsRepository;
use Tamkeen\Musaned\eVisa\Visas\NationalityChecksMemoryService;
use Tamkeen\Musaned\eVisa\Visas\NationalityId;
use Tamkeen\Musaned\eVisa\Visas\Occupation;
use Tamkeen\Musaned\eVisa\Visas\OccupationId;
use Tamkeen\Musaned\eVisa\Visas\Repositories\NationalitiesMemoryRepository;
use Tamkeen\Musaned\eVisa\Visas\Repositories\OccupationsMemoryRepository;
use Tamkeen\Musaned\eVisa\Visas\Repositories\VisaIssuePlacesMemoryRepository;
use Tamkeen\Musaned\eVisa\Visas\VisaIssuePlaceId;
use Tamkeen\Musaned\eVisa\Visas\VisaReplacementRequest;
use Tamkeen\Musaned\eVisa\Visas\VisaReplacementRequestStatues;
use Tamkeen\Musaned\Takamol\Setting;
use Tamkeen\Musaned\tests\eVisa\TestCase;
use Tamkeen\Platform\Model\Common\HijriDate;
use Tamkeen\Platform\Model\NIC\IdNumber;
use Tamkeen\Platform\Model\Scalar\Integer;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaDummyRepository;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaSoapRepository;
use Tamkeen\Platform\Security\Services\Auditing\Auditor;

class SubmitVisaIssueRequestCommandHandlerTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private $auditor;

    private $userHolder;

    private $allowedOccupationMapping = [];

    private $allowedVisaIssuePlaceMapping = [];

    private $issueRequests;

    private $endUser;

    private $_disabilityUser;

    private $info;

    public function setUp()
    {
        parent::setUp();

        $this->auditor = $this->prophesize(Auditor::class);
        $this->instance(Auditor::class, $this->auditor->reveal());

        $this->allowedOccupationMapping = [
            1 => [1 => true],
        ];

        $this->allowedVisaIssuePlaceMapping = [
            1 => [1 => true],
        ];

        $this->issueRequests = [];

        $this->endUser = factory(EndUser::class)->create(['id_number' => 1010101010, 'check_date' => '1400-01-01', 'is_sponsor' => true]);
        $this->_disabilityUser = factory(EndUser::class)->create(['id_number' => '1020101000', 'mobile_active' => true, 'is_sponsor' => true]);

        $client =
            $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case5/saudi_man_married_has_childs.xml')));

        $this->info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($this->endUser->getIdNumber()->toScalar()),
            HijriDate::resolveString($this->endUser->getCheckDate()->toString('-'))
        );
    }

    /**
     * @test
     */
    public function it_returns_the_issueRequest_for_regular_visas_on_success_when_billing_is_off()
    {
        $command = $this->createCommand(
            $this->endUser,
            1,
            1,
            1,
            [
                AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
            ],
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false
        );

        $issueRequest = $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);

        $this->assertEquals(1, $issueRequest->occupation_id);
        $this->assertEquals(1, $issueRequest->nationality_id);
        $this->assertEquals(1, $issueRequest->visa_issue_place_id);
        $this->assertEquals(0, $issueRequest->is_disable);
        $this->assertEquals(IssueRequestStatus::PENDING, $issueRequest->status);
        $this->assertEquals(null, $issueRequest->visa_replacement_id);
    }

    /**
     * @test
     */
    public function it_returns_issueRequest_for_regular_visas_no_financial_proof_when_capable_when_billing_is_off()
    {
        $command = $this->createCommand(
            $this->endUser,
            1,
            1,
            1,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false
        );

        $issueRequest = $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);

        $this->assertEquals(1, $issueRequest->occupation_id);
        $this->assertEquals(1, $issueRequest->nationality_id);
        $this->assertEquals(1, $issueRequest->visa_issue_place_id);
        $this->assertEquals(0, $issueRequest->is_disable);
        $this->assertEquals(IssueRequestStatus::PENDING, $issueRequest->status);
        $this->assertEquals(null, $issueRequest->visa_replacement_id);
    }

    /**
     * @test
     */
    public function it_returns_the_issueRequest_for_disability_visas_on_success_when_billing_is_off()
    {
        Setting::where('key', 'is_nic_automation_disabled')->update(['value' => 1]);
        Cache::flush();

        $command = $this->createCommand(
            $this->_disabilityUser,
            1,
            1,
            1,
            [
                AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
            ],
            null,
            null,
            null,
            null,
            null,
            isDisable::make(1),
            null,
            null,
            false
        );

        $issueRequest = $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);

        $this->assertEquals(1, $issueRequest->occupation_id);
        $this->assertEquals(1, $issueRequest->nationality_id);
        $this->assertEquals(1, $issueRequest->visa_issue_place_id);
        $this->assertEquals(1, $issueRequest->is_disable);
        $this->assertEquals(IssueRequestStatus::PENDING, $issueRequest->status);
        $this->assertEquals(null, $issueRequest->visa_replacement_id);
    }

    /**
     * @test
     */
    public function it_returns_the_issueRequest_for_alt_visas_on_success_when_billing_is_off()
    {
        $replacementRequest = VisaReplacementRequest::open(
            Integer::make(1900021320),
            IdNumber::make(2048860635),
            VisaReplacementRequestStatues::getPending()
        );

        $command = $this->createCommand(
            $this->endUser,
            1,
            1,
            1,
            [
                AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
            ],
            $replacementRequest,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false
        );

        $issueRequest = $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);

        $this->assertEquals(1, $issueRequest->occupation_id);
        $this->assertEquals(1, $issueRequest->nationality_id);
        $this->assertEquals(1, $issueRequest->visa_issue_place_id);
        $this->assertEquals(0, $issueRequest->is_disable);
        $this->assertEquals(IssueRequestStatus::PENDING, $issueRequest->status);
        $this->assertNotNull($issueRequest->visa_replacement_id);
        $this->assertNotNull($replacementRequest->id);
        $this->assertEquals($replacementRequest->id, $issueRequest->visa_replacement_id);
        $this->assertEquals(2048860635, $issueRequest->visaReplacementRequest->alien_id);
        $this->assertEquals(1900021320, $issueRequest->visaReplacementRequest->old_visa_followup);
        $this->assertEquals(
            VisaReplacementRequestStatues::getPending(),
            $issueRequest->visaReplacementRequest->alt_request_status
        );
    }

    /**
     * @test
     */
    public function it_returns_the_issueRequest_for_alt_visas_on_success_when_the_EndUser_have_a_balance()
    {
        $bill = factory(Bill::class)->create([
            'user_id' => $this->endUser->id,
            'amount'  => 157.5,
            'status'  => Bill::STATUS_PAID,
        ]);
        $replacementRequest = VisaReplacementRequest::open(
            Integer::make(1900021320),
            IdNumber::make(2048860635),
            VisaReplacementRequestStatues::getPending()
        );

        $command = $this->createCommand(
            $this->endUser,
            1,
            1,
            1,
            [
                AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
            ],
            $replacementRequest,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            true
        );

        $issueRequest = $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);

        $this->assertEquals(1, $issueRequest->occupation_id);
        $this->assertEquals(1, $issueRequest->nationality_id);
        $this->assertEquals(1, $issueRequest->visa_issue_place_id);
        $this->assertEquals(0, $issueRequest->is_disable);
        $this->assertEquals(IssueRequestStatus::APPROVED, $issueRequest->status);

        $this->assertNotNull($issueRequest->visa_replacement_id);
        $this->assertNotNull($replacementRequest->id);
        $this->assertEquals($replacementRequest->id, $issueRequest->visa_replacement_id);
        $this->assertEquals(2048860635, $issueRequest->visaReplacementRequest->alien_id);
        $this->assertEquals(1900021320, $issueRequest->visaReplacementRequest->old_visa_followup);
        $this->assertEquals(
            VisaReplacementRequestStatues::getIssued(),
            $issueRequest->visaReplacementRequest->alt_request_status
        );

        $this->assertEquals($bill->id, $issueRequest->bill->id);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Visas\UnallowedOccupationForNationality
     */
    public function it_throws_an_exception_when_a_not_allowed_nationality_is_passed()
    {
        $this->allowedOccupationMapping = [
            1 => [2 => true],
        ];

        $command = $this->createCommand($this->endUser, 1, 1, 1, []);

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))
            ->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\MissingFinancialCapabilityProofDocument
     */
    public function it_throws_an_exception_when_a_no_financial_capability_document_is_available()
    {
        $user = EndUser::where(['id_number' => 1020101000])->first();

        $command = $this->createCommand($user, 1, 1, 1, []);

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\UserAlreadyHasWaitingPaymentIssueRequest
     */
    public function it_throws_an_exception_when_the_user_has_a_waiting_payment_request()
    {
        $this->issueRequests = [
            IssueRequest::open(
                $this->endUser,
                OccupationId::make(1),
                NationalityId::make(1),
                VisaIssuePlaceId::make(1),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                IssueRequestStatus::waitingPayment()
            ),
        ];

        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\UserAlreadyHasPendingIssueRequest
     */
    public function it_throws_an_exception_when_the_user_has_a_pending_request()
    {
        $issueRequests = IssueRequest::open(
            $this->endUser,
            OccupationId::make(1),
            NationalityId::make(1),
            VisaIssuePlaceId::make(1)
        );
        $issueRequests->status = IssueRequestStatus::pending()->toScalar();
        $issueRequests->updated_at = Carbon::now();
        $this->issueRequests = [
            $issueRequests,
        ];
        $this->endUser->issue_requests = $this->issueRequests;
        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     * @expectedException \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\UserIsBlacklisted
     */
    public function it_throws_an_exception_when_a_blacklisted_user_submits()
    {
        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $this->createHandler(
            [
                'personal_info' => $this->info,
                'visas'         => [],
                'labors'        => [],
            ],
            [$this->endUser->id_number],
            new IssueRequestsMemoryRepository($this->issueRequests)
        )->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\UserBelowAllowedAge
     */
    public function it_throws_an_exception_when_the_user_man_single_and_below_24()
    {
        $this->issueRequests = [];

        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $client =
            $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/saudi_man_single_and_below24.xml')));

        $this->info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($this->endUser->getIdNumber()->toScalar()),
            HijriDate::resolveString($this->endUser->getCheckDate()->toString('-'))
        );

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\UserBelowAllowedAge
     */
    public function it_throws_an_exception_when_the_user_women_single_and_below24()
    {
        $this->issueRequests = [];

        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $client =
            $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/saudi_women_single_and_below24.xml')));

        $this->info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($this->endUser->getIdNumber()->toScalar()),
            HijriDate::resolveString($this->endUser->getCheckDate()->toString('-'))
        );

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     *
     * @expectedException \Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\UserBelowAllowedAge
     */
    public function it_throws_an_exception_when_the_user_foreign_man_married_and_has_not_childs()
    {
        $command = $this->createCommand($this->endUser, 1, 1, 1, [
            AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
        ]);

        $client =
            $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/saudi_women_single_and_below24.xml')));

        $this->info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($this->endUser->getIdNumber()->toScalar()),
            HijriDate::resolveString($this->endUser->getCheckDate()->toString('-'))
        );

        $this->createHandler([
            'personal_info' => $this->info,
            'visas'         => [
                [
                    'BorderNo'      => '3345817898',
                    'Nationality'   => 'الفلبين',
                    'Occupation'    => 'عاملة منزلية',
                    'Sex'           => 'Female',
                    'VisaIssueDate' => '2005-12-12T00:00:00',
                    'VisaNo'        => '1100278465',
                    'VisaStatus'    => 'اصدرت',
                ],
            ],
            'labors'        => [],
        ], [], new IssueRequestsMemoryRepository($this->issueRequests))->handle($command);
    }

    /**
     * @test
     * @expectedException \Doctrine\DBAL\Query\QueryException
     */
    public function it_rejects_the_alt_visas_issueRequest_on_database_exception()
    {
        $replacementRequest = VisaReplacementRequest::open(
            Integer::make(1900021320),
            IdNumber::make(2048860635),
            VisaReplacementRequestStatues::getPending()
        );

        $command = $this->createCommand(
            $this->endUser,
            1,
            1,
            1,
            [
                AttachedDocument::make(EndUserUploadId::make(1), DocumentType::financialCapabilityProof()),
            ],
            $replacementRequest,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false
        );

        $issueRequestRepo = Mockery::mock(IssueRequestsMemoryRepository::class);
        $issueRequestRepo->shouldReceive('hasWaitingPaymentRequest')->andReturn(false);
        $issueRequestRepo->shouldReceive('hasPendingRequest')->andReturn(false);
        $issueRequestRepo->shouldReceive('persist')->andThrow(new QueryException());

        try {
            $this->createHandler([
                'personal_info' => $this->info,
                'visas'         => [],
                'labors'        => [],
            ], [], $issueRequestRepo)->handle($command);
        } catch (\Exception $e) {
            $replacementRequest = VisaReplacementRequest::findOrFail($replacementRequest->id);

            $this->assertEquals(
                VisaReplacementRequestStatues::getRejected(),
                $replacementRequest->alt_request_status
            );

            $this->assertEquals(
                IssueRequestStatus::REJECTED,
                $replacementRequest->issueRequest->status
            );
            throw $e;
        }
    }

    /**
     * @param null  $nic_data
     * @param array $blacklisted
     *
     * @return \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\SubmitVisaIssueRequestCommandHandler
     */
    public function createHandler($nic_data = null, $blacklisted = [], IssueRequestsRepository $issueRequestsRepository)
    {
        $visaDummyRepository = new VisaDummyRepository($nic_data ? $nic_data : ['personal_info' => $this->info]);

        if ($nic_data) {
            $this->instance(VisaRepository::class, $visaDummyRepository);
        }

        return new SubmitVisaIssueRequestCommandHandler(
            new OccupationsMemoryRepository([
                Occupation::make(OccupationId::make(1), 'occupation'),
            ]),
            new NationalitiesMemoryRepository(),
            new VisaIssuePlacesMemoryRepository(),
            $issueRequestsRepository,
            new NationalityChecksMemoryService($this->allowedOccupationMapping, $this->allowedVisaIssuePlaceMapping),
            new BlacklistMemoryRepository($blacklisted),
            $this->auditor->reveal(),
            new IssueCheckerService(
                $visaDummyRepository,
                new IssueRequestsMemoryRepository($this->issueRequests)
            ),
            new EndUsersMemoryRepository()
        );
    }

    /**
     * @param $occupationId
     * @param $nationalityId
     * @param $visaIssuePlaceId
     * @param $documents
     * @param null $replacementRequestId
     * @param null $hasFinancialCapacity
     * @param null $employmentStatus
     * @param null $office
     * @param null $gioffice
     * @param null $isDisable
     * @param null $request_agent_src
     * @param null $issueRequestStatus
     * @param bool $enable_billing
     * @param bool $gio_user
     *
     * @return \Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\SubmitVisaIssueRequestCommand
     */
    protected function createCommand(
        EndUser $user,
        $occupationId,
        $nationalityId,
        $visaIssuePlaceId,
        $documents,
        $visaReplacementRequest = null,
        $hasFinancialCapacity = null,
        $employmentStatus = null,
        $office = null,
        $gioffice = null,
        $isDisable = null,
        $request_agent_src = null,
        $issueRequestStatus = null,
        $enable_billing = true,
        $gio_user = false
    ) {
        return new SubmitVisaIssueRequestCommand(
            $user,
            OccupationId::make($occupationId),
            NationalityId::make($nationalityId),
            VisaIssuePlaceId::make($visaIssuePlaceId),
            FinancialCapabilityProof::make(FinancialCapabilityProofType::salary(), 10000),
            new Collection($documents),
            $visaReplacementRequest,
            $hasFinancialCapacity,
            $employmentStatus,
            $office,
            $gioffice,
            $isDisable,
            $request_agent_src,
            $issueRequestStatus,
            $enable_billing,
            $gio_user
        );
    }

    /**
     * @param      $response_body
     * @param bool $exception
     *
     * @return \Prophecy\Prophecy\ObjectProphecy
     */
    protected function getClient($response_body, $exception = false)
    {
        $message = $this->prophesize(StreamInterface::class);
        $message->getContents()->willReturn($response_body);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->willReturn($message->reveal());

        $client = $this->prophesize(ClientInterface::class);

        if ($exception) {
            $client->request('POST', Argument::type('string'), Argument::type('array'))
                ->willThrow(new RequestException(
                    Argument::type('string'),
                    new Request('POST', 'asd'),
                    new Response(500, [], $response_body)
                ));
        } else {
            $client->request('POST', Argument::type('string'), Argument::type('array'))
                ->willReturn($response->reveal());
        }

        return $client;
    }
}
