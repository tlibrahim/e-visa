<?php

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\Repositories\VisaNotFoundException;
use Tamkeen\Musaned\eVisa\Visas\Repositories\VisasEloquentRepository;
use Tamkeen\Musaned\eVisa\Visas\Visa;
use Tamkeen\Musaned\eVisa\Visas\VisaId;
use Tamkeen\Musaned\eVisa\Visas\VisaStatus;
use Tamkeen\Musaned\tests\EndUsers\TestCase;

class VisasRepositoryTest extends TestCase
{
    protected $user;

    public function setUp()
    {
        parent::setUp();
        $this->user = factory(EndUser::class)->create(['id_number' => '1010101010']);
    }

    public function test_cant_get_unissued_visas_without_flag()
    {
        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::issued()->toScalar(),
        ]);

        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $visas = $repo->paginateUserVisas($this->user, 1, 20, false);

        $this->assertEquals(1, $visas->count());
    }

    public function test_can_get_unissued_visas_with_flag()
    {
        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::issued()->toScalar(),
        ]);

        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $visas = $repo->paginateUserVisas($this->user, 1, 20, true);

        $this->assertEquals(2, $visas->count());
    }

    public function test_cant_get_unissued_visas_count_without_flag()
    {
        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::issued()->toScalar(),
        ]);

        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $count = $repo->countForUser($this->user);

        $this->assertEquals(1, $count);
    }

    public function test_can_get_unissued_visas_count_with_flag()
    {
        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::issued()->toScalar(),
        ]);

        factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $count = $repo->countForUser($this->user, true);

        $this->assertEquals(2, $count);
    }

    public function test_can_get_unissued_visas_by_id_with_flag()
    {
        $visa = factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $visaId = VisaId::make($visa->id);
        $visa = $repo->getById($visaId, $this->user, true);

        $this->assertNotNull($visa);
    }

    /**
     * @expectedException \Tamkeen\Musaned\eVisa\Visas\Repositories\VisaNotFoundException
     */
    public function test_can_not_get_unissued_visas_by_id_without_flag()
    {
        $visa = factory(Visa::class)->create([
            'user_id' => $this->user->id,
            'status'  => VisaStatus::unissued()->toScalar(),
        ]);

        $repo = new VisasEloquentRepository();

        $visaId = VisaId::make($visa->id);
        $visa = $repo->getById($visaId, $this->user);

        $this->setExpectedException(VisaNotFoundException::class);
    }
}
