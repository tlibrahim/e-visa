<?php

namespace Tamkeen\Musaned\eVisa\Visas\Repositories;

use Tamkeen\Musaned\eVisa\Visas\NationalityId;
use Tamkeen\Musaned\eVisa\Visas\Occupation;

interface NationalitiesRepository
{
    /**
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Nationality[]
     */
    public function all();

    /**
     * @return mixed
     */
    public function NoGioNationality();

    /**
     * @return array
     */
    public function OnlineNationality();

    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\Occupation $occupation
     * @param string                                  $type
     *
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Nationality[]
     */
    public function forOccupation(Occupation $occupation, $type);

    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\NationalityId $id
     *
     * @throws \Tamkeen\Musaned\eVisa\Visas\Repositories\NationalityNotFoundException
     *
     * @return \Tamkeen\Musaned\eVisa\Visas\Nationality
     */
    public function getById(NationalityId $id);

    /**
     * @param string $type
     *
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\Nationality[]
     */
    public function getByType($type);

    /**
     * @param $code
     *
     * @return mixed
     */
    public function getByCode($code);

    /**
     * @param str $label
     *
     * @return \Tamkeen\Musaned\eVisa\Visas\Nationality
     */
    public function getByLabel($label);
}
