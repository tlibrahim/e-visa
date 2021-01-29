<?php

namespace Tamkeen\Musaned\eVisa\Visas\Repositories;

use Tamkeen\Musaned\eVisa\Visas\Occupation;
use Tamkeen\Musaned\eVisa\Visas\OccupationId;

class OccupationsMemoryRepository implements OccupationsRepository
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private $occupations;

    /**
     * @param array $occupations
     */
    public function __construct(array $occupations = [])
    {
        $this->occupations = collect($occupations)->keyBy(function (Occupation $occupation) {
            return $occupation->getKey();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->occupations;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(OccupationId $id)
    {
        return Occupation::make(OccupationId::make(1), 'occupation');
    }

    /**
     * @param $code
     *
     * @return Occupation
     */
    public function getByCode($code)
    {
        return Occupation::make(OccupationId::make(1), 'occupation');
    }

    /**
     * {@inheritdoc}
     */
    public function getYemeni()
    {
    }
}
