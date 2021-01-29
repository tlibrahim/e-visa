<?php

namespace Tamkeen\Musaned\eVisa\Visas\Repositories;

use Tamkeen\Musaned\eVisa\Visas\OccupationId;

interface OccupationsRepository
{
    /**
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Occupation[]
     */
    public function all();

    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\OccupationId $id
     *
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Occupation
     */
    public function getById(OccupationId $id);

    /**
     * @param $code
     *
     * @return mixed
     */
    public function getByCode($code);

    /**
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Occupation
     */
    public function getYemeni();
}
