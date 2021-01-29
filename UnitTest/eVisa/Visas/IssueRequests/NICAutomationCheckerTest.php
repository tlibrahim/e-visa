<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\IssueRequests;

use Mockery;
use stdClass;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\NICAutomationChecker;
use Tamkeen\Musaned\eVisa\Visas\Occupation;
use Tamkeen\Musaned\eVisa\Visas\Repositories\NicAutomationRepository;
use Tamkeen\Musaned\tests\eVisa\TestCase;

class NICAutomationCheckerTest extends TestCase
{
    private $nicAutomationRules;
    private $occupations;

    private $maleLabor;
    private $maleVisa;
    private $femaleLbaor;
    private $femaleVisa;

    private $maleWorkerVisa;

    public function setUp()
    {
        parent::setUp();
        $this->nicAutomationRules = new stdClass();
        $this->nicAutomationRules->value = [
            'occupations' => [
                'all' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            ],
            'financial_capacity' => [
                'salary'       => [5000, 8000],
                'bank_balance' => [35000, 80000],
            ],
            'extra_files'       => 'EMPLOYER_OR_HAS_COMPANY',
            'max_allowed_visas' => 5,
        ];
        $this->occupations = $this->loadOccupations();
        $this->maleVisa = $this->constructNicObject('سائق خاص', 4, 'Male');
        $this->maleWorkerVisa = $this->constructNicObject('عامل منزلي', 1, 'Male');
        $this->femaleLbaor = $this->constructNicLaborObject('مربية', 6132075, 'داخل المملكة', 'انثى');
    }

    private function constructNicObject($name = 'عامل منزلي', $id = null, $sex = 'Male')
    {
        return [
            'VisaStatus'    => 'اصدرت',
            'Occupation'    => $name,
            'VisaIssueDate' => '2019-10-28 19:18:44',
            'Sex'           => $sex,
            'occupation_id' => $id,
        ];
    }

    private function constructNicLaborObject($name, $code, $travelStatus = 'داخل المملكة', $sex = 'أنثى')
    {
        return [
            'Status'       => 'صالح',
            'TravelStatus' => $travelStatus,
            'Occupation'   => [
                'Name' => $name,
                'Code' => $code,
            ],
            'Sex' => [
                'Name' => $sex,
            ],
        ];
    }

    private function loadOccupations()
    {
        // Get the occupations from the config file
        $occupations = collect(config('occupations'));
        $databaseOccupations = Occupation::whereIn('id', $this->nicAutomationRules->value['occupations']['all'])->get();

        //Append the dabase index in all occupations
        $occupations->transform(function ($occupation) use ($databaseOccupations) {
            $databaseOccupation = $databaseOccupations->where('label', $occupation['name'])->first();

            if ( ! $databaseOccupation) {
                return $occupation;
            }

            $occupation['database_index'] = $databaseOccupation['id'];

            return $occupation;
        });

        // Return only the occupations available in the database
        $availableOccupations = $occupations->filter(function ($occupation) {
            return $occupation['database_index'] !== null;
        });

        return $availableOccupations;
    }

    public function testSaudiWomanDivorcedWidowedHasNotChildsAllowedWithMalesIfHasNoMale()
    {
        $this->instance(NicAutomationRepository::class, Mockery::mock(NicAutomationRepository::class, function ($mock) {
            $mock->shouldReceive('find')->once()->andReturn($this->nicAutomationRules);
        }));

        $endUser = factory(EndUser::class)->create(['id_number' => '1010101010']);
        $checker = new NICAutomationChecker('SaudiWomanDivorcedWidowedHasNotChilds', collect(), collect(), $endUser);

        $result = $checker->getOccupations();

        $this->occupations->each(function ($value) use ($result) {
            $this->assertContains($value['database_index'], $result);
        });
    }

    public function testSaudiWomanDivorcedWidowedHasNotChildsNotAllowedMoreThanOneMale()
    {
        $this->instance(NicAutomationRepository::class, Mockery::mock(NicAutomationRepository::class, function ($mock) {
            $mock->shouldReceive('find')->once()->andReturn($this->nicAutomationRules);
        }));
        $endUser = factory(EndUser::class)->create(['id_number' => '1010101010']);

        $currentVisa = collect(
            [
                $this->maleWorkerVisa,
            ]
        );

        $checker = new NICAutomationChecker('SaudiWomanDivorcedWidowedHasNotChilds', $currentVisa, collect(), $endUser);

        $result = $checker->getOccupations();
        $expected = $this->occupations->filter(function ($occupation) {
            return $occupation['gender'] != 'male';
        });

        $unexpected = $this->occupations->filter(function ($occupation) {
            return $occupation['gender'] != 'female';
        });

        $expected->each(function ($value) use ($result) {
            $this->assertContains($value['database_index'], $result);
        });

        $unexpected->each(function ($value) use ($result) {
            $this->assertNotContains($value['database_index'], $result);
        });
    }

    public function testSaudiManDivorcedWidowedHasChildsAllowedWithOneMaleWorker()
    {
        $this->instance(NicAutomationRepository::class, Mockery::mock(NicAutomationRepository::class, function ($mock) {
            $mock->shouldReceive('find')->once()->andReturn($this->nicAutomationRules);
        }));

        $currentVisa = collect(
            [
                $this->maleWorkerVisa,
            ]
        );

        $endUser = factory(EndUser::class)->create(['id_number' => '1010101010']);
        $checker = new NICAutomationChecker('SaudiManDivorcedWidowedHasChilds', $currentVisa, collect(), $endUser);

        $result = $checker->getOccupations();

        $this->occupations->each(function ($value) use ($result) {
            if ($value['name'] === 'عامل منزلي') {
                $this->assertNotContains($value['database_index'], $result);
            } else {
                $this->assertContains($value['database_index'], $result);
            }
        });
    }
}
