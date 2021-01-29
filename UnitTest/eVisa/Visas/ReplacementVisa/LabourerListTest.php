<?php

namespace Tamkeen\Musaned\tests\eVisa\Visas\ReplacementVisa;

use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\LabourerList;
use Tamkeen\Musaned\tests\eVisa\TestCase;
use Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignersDummyRepository;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaDummyRepository;

class LabourerListTest extends TestCase
{
    /** @test */
    public function get_all_laborers_visas_for_individual()
    {
        $visa = new VisaDummyRepository([
            'personal_info' => [],
            'visas'         => [],
            'labors'        => [],
        ]);

        $foreigner = new ForeignersDummyRepository($this->dummy_foreigner);

        $list = new LabourerList($visa, $foreigner);

        $foreigners = $list->forIdNumber('1010101010', '1400-01-01');

        $this->assertCount(1,  $foreigners);
    }

    /** @test */
    public function get_all_laborers_visas_for_individual_discharged_on_90th_day()
    {
        $visa = new VisaDummyRepository([
            'personal_info' => [],
            'visas'         => [],
            'labors'        => [],
        ]);

        $invalid_laborer = $this->dummy_foreigner[0]; // 91 days
        $invalid_laborer['travel']['entry_date']['g'] = '2000-01-01';
        $invalid_laborer['travel']['last_exit_date']['g'] = '2000-04-01';

        $valid_laborer = $this->dummy_foreigner[1]; // 90 days
        $valid_laborer['travel']['entry_date']['g'] = '2000-01-01';
        $valid_laborer['travel']['last_exit_date']['g'] = '2000-03-31';

        $foreigner = new ForeignersDummyRepository([$invalid_laborer, $valid_laborer]);

        $list = new LabourerList($visa, $foreigner);

        $foreigners = $list->forIdNumber('1010101010', '1400-01-01');

        $this->assertCount(1,  $foreigners);
    }

    public $dummy_foreigner = [
        [
            'name'           => [
                'first'  => 'فلان',
                'second' => '',
                'third'  => '',
                'last'   => 'العلان',
            ],
            'residency'      => [
                'number'      => 2048860635,
                'id_type'     => 'رب أسرة',
                'issue_place' => 'الرياض',
                'issue_date'  => [
                    'h' => '14360101',
                    'g' => '2015-01-01',
                ],
                'expiry_date' => [
                    'h' => '14370101',
                    'g' => '2016-01-01',
                ],
            ],
            'status'         => [
                'person'      => [
                    'code' => 1,
                    'name' => '',
                ],
                'fingerprint' => [
                    'code' => 1,
                    'name' => '',
                ],
                'prisoner'    => [
                    'code' => 1,
                    'name' => '',
                ],
            ],
            'birth_date'     => [
                'h' => '14000101',
                'g' => '1990-01-01',
            ],
            'occupation'     => [
                'code' => '14000101',
                'name' => 'محاسب',
            ],
            'travel'         => [
                'travel_status'        => '',
                'entry_date'           => [
                    'h' => '14000101',
                    'g' => '1990-01-01',
                ],
                'last_entry_date'      => [
                    'h' => '14000101',
                    'g' => '1990-01-01',
                ],
                'last_exit_date'       => [
                    'h' => '14000101',
                    'g' => '1991-06-01',
                ],
                'passport_expiry_date' => [
                    'h' => '14000101',
                    'g' => '1990-01-01',
                ],
            ],
            'sponsor'        => [
                'name'       => 'first second third last',
                'id_number'  => '2020202020',
                'occupation' => 'test',
                'status'     => 1,
                'type'       => [
                    'code' => 1,
                    'name' => 'type',
                ],
            ],
            'visa'           => [
                'type'              => '',
                'final_exit_issued' => false,
                'expiry_date'       => [
                    'h' => '14360101',
                    'g' => '2015-01-01',
                ],
            ],
            'gender'         => [
                'code' => 1,
            ],
            'marital_status' => [
                'code' => 1,
                'name' => '',
            ],
            'nationality'    => [
                'code' => 1,
                'name' => '',
            ],
            'relationship'   => [
                'code' => 1,
                'name' => '',
            ],
            'religion'   => [
                'code' => 1,
                'name' => '',
            ],
        ],
        [
            'name'           => [
                'first'  => 'فلان',
                'second' => '',
                'third'  => '',
                'last'   => 'العلان',
            ],
            'residency'      => [
                'number'      => 2160992315,
                'id_type'     => 'رب أسرة',
                'issue_place' => 'الرياض',
                'issue_date'  => [
                    'h' => '14360101',
                    'g' => '2015-01-01',
                ],
                'expiry_date' => [
                    'h' => '14370101',
                    'g' => '2016-01-01',
                ],
            ],
            'status'         => [
                'person'      => [
                    'code' => 1,
                    'name' => '',
                ],
                'fingerprint' => [
                    'code' => 1,
                    'name' => '',
                ],
                'prisoner'    => [
                    'code' => 1,
                    'name' => '',
                ],
            ],
            'birth_date'     => [
                'h' => '14000101',
                'g' => '1990-01-01',
            ],
            'occupation'     => [
                'code' => '14000101',
                'name' => 'محاسب',
            ],
            'travel'         => [
                'travel_status'        => '',
                'entry_date'           => [
                    'h' => '14000101',
                    'g' => '2000-01-01',
                ],
                'last_entry_date'      => [
                    'h' => '14000101',
                    'g' => '1990-01-01',
                ],
                'last_exit_date'       => [
                    'h' => '14000101',
                    'g' => '2000-02-01',
                ],
                'passport_expiry_date' => [
                    'h' => '14000101',
                    'g' => '1990-01-01',
                ],
            ],
            'sponsor'        => [
                'name'       => 'first second third last',
                'id_number'  => '2020202020',
                'occupation' => 'test',
                'status'     => 1,
                'type'       => [
                    'code' => 1,
                    'name' => 'type',
                ],
            ],
            'visa'           => [
                'type'              => '',
                'final_exit_issued' => false,
                'expiry_date'       => [
                    'h' => '14360101',
                    'g' => '2015-01-01',
                ],
            ],
            'gender'         => [
                'code' => 1,
            ],
            'marital_status' => [
                'code' => 1,
                'name' => '',
            ],
            'nationality'    => [
                'code' => 1,
                'name' => '',
            ],
            'relationship'   => [
                'code' => 1,
                'name' => '',
            ],
            'religion'   => [
                'code' => 1,
                'name' => '',
            ],
        ],
    ];
}
