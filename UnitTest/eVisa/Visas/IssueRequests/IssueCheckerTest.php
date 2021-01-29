<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\IssueRequests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueChecker;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\NICAutomationChecker;
use Tamkeen\Musaned\tests\eVisa\TestCase;
use Tamkeen\Platform\Model\Common\HijriDate;
use Tamkeen\Platform\Model\NIC\IdNumber;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaSoapRepository;

class IssueCheckerTest extends TestCase
{
    use DatabaseTransactions;

    public function tearDown()
    {
        parent::tearDown();
        gc_collect_cycles();
    }

    // case 0
    public function test_saudi_man_single_and_below24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/saudi_man_single_and_below24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 0
    public function test_saudi_women_single_and_below24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/saudi_women_single_and_below24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 0
    public function test_foreign_man_single_and_below24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/foreign_man_single_and_below24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 0
    public function test_foreign_women_single_and_below24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/foreign_women_single_and_below24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 0
    public function test_foreign_women_divorced_widowed_or_her_husband_is_absent_and_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/foreign_women_divorced_widowed_or_her_husband_is_absent_and_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_man_single()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/foreign_man_single.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(1, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_woman_single_above24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case0/foreign_woman_single_above24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 1
    public function test_foreign_man_married_and_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case1/foreign_man_married_and_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(1, $checker->getMaximumVisasAllowedNumber());
    }

    // case 1
    public function test_foreign_women_married_her_husband_is_foreign()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case1/foreign_women_married_her_husband_is_foreign.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(1, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_man_divorced_widowed_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_man_divorced_widowed_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_man_single_above24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_man_single_and_above24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_woman_single_above24()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_woman_single_and_above24.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_woman_married_has_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_woman_married_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_woman_married_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_woman_married_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_saudi_woman_divorced_widowed_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/saudi_woman_divorced_widowed_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_man_married_has_childs()
    {
        // $this->markTestIncomplete();
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/foreign_man_married_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(2, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_woman_married_her_husband_saudi_has_childs()
    {
        //$this->markTestIncomplete();
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/foreign_woman_married_her_husband_saudi_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(1, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_woman_married_her_husband_saudi_has_not_childs()
    {
        //$this->markTestIncomplete();
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/foreign_woman_married_her_husband_saudi_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(1, $checker->getMaximumVisasAllowedNumber());
    }

    // case 2
    public function test_foreign_woman_divorced_widowed_husband_absent_has_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case2/foreign_woman_divorced_widowed_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(0, $checker->getMaximumVisasAllowedNumber());
    }

    // case 3
    public function test_saudi_woman_divorced_widowed_has_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case3/saudi_woman_divorced_widowed_husband_absent_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(3, $checker->getMaximumVisasAllowedNumber());
    }

    public function test_saudi_man_divorced_widowed_has_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case3/saudi_man_divorced_widowed_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(3, $checker->getMaximumVisasAllowedNumber());
    }

    public function test_saudi_man_married_has_not_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case3/saudi_man_married_has_not_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(3, $checker->getMaximumVisasAllowedNumber());
    }

    // case 5
    public function test_saudi_man_married_has_childs()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case5/saudi_man_married_has_childs.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);

        $this->assertEquals(5, $checker->getMaximumVisasAllowedNumber());
    }

    public function test_isTreatedAsSaudi()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case6/saudi_undefined.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);
        $this->assertEquals('UndefinedSaudi', $checker->getKey());

        $NICAutomationChecker = new NICAutomationChecker($checker->getKey(), collect(), collect(), $user);
        $this->assertEquals(2, $NICAutomationChecker->getMaxAllowedVisas());
    }

    public function test_isTreatedAsForeign()
    {
        $client = $this->getClient(file_get_contents(base_path('tests/eVisa/Visas/IssueRequests/xmls/case6/foreign_undefined.xml')));

        $user = factory(EndUser::class)->create([
            'id'        => 1,
            'id_number' => '1010101010',
        ]);

        $info = (new VisaSoapRepository($client->reveal(), '', ''))->getPersonalInfo(
            IdNumber::fromString($user->getIdNumber()->toScalar()),
            HijriDate::resolveString($user->getCheckDate()->toString('-'))
        );

        $checker = new IssueChecker($info);
        $this->assertEquals('UndefinedForeign', $checker->getKey());

        $NICAutomationChecker = new NICAutomationChecker($checker->getKey(), collect(), collect(), $user);
        $this->assertEquals(2, $NICAutomationChecker->getMaxAllowedVisas());
    }

    /**
     * @param $response_body
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
                ->willThrow(new RequestException(Argument::type('string'), new Request('POST', 'asd'),
                    new Response(500, [], $response_body)));
        } else {
            $client->request('POST', Argument::type('string'), Argument::type('array'))
                ->willReturn($response->reveal());
        }

        $response_body = null;

        return $client;
    }
}
