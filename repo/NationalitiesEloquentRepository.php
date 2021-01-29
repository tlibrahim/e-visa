<?php

namespace Tamkeen\Musaned\eVisa\Visas\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tamkeen\Musaned\eVisa\Visas\Nationality;
use Tamkeen\Musaned\eVisa\Visas\NationalityId;
use Tamkeen\Musaned\eVisa\Visas\Occupation;

final class NationalitiesEloquentRepository implements NationalitiesRepository
{
    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return Nationality::query()->get();
    }

    /**
     * @return mixed
     */
    public function NoGioNationality()
    {
        return Nationality::query()->NoGioNationality()->get();
    }

    /**
     * @return array
     */
    public function OnlineNationality()
    {
        return Nationality::query()->onlineNationality()->get();
    }

    /**
     * {@inheritdoc}
     */
    public function forOccupation(Occupation $occupation, $type = 'ONLINE')
    {
        if ($type == 'GIO') {
            $builder = Nationality::query()->where('gio_only', 1);
        } elseif ($type == 'PRO') {
            $builder = Nationality::query()->where('pro_only', 1);
        } elseif ($type == 'DL') {
            $builder = Nationality::query()->where('demostic_labor_only', 1);
        } elseif ($type == 'VIP') {
            $builder = Nationality::query()->where('vip_only', 1);
        } else {
            $builder = Nationality::query()->where('online_only', 1);
        }

        $builder->whereHas('allowedRequests', function (Builder $query) use ($occupation) {
            $query->where('occupation_id', $occupation->getKey());
        });

        return $builder->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getById(NationalityId $id)
    {
        try {
            return Nationality::query()->findOrFail($id->toScalar());
        } catch (ModelNotFoundException $e) {
            throw new NationalityNotFoundException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByType($type = 'online')
    {
        return Nationality::query()->where($type.'_only', 1)->get();
    }

    /**
     * @param $code
     *
     * @return Builder|\Illuminate\Database\Eloquent\Model|mixed
     */
    public function getByCode($code)
    {
        try {
            return Nationality::query()->where('code', $code)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new NationalityNotFoundException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByLabel($label)
    {
        try {
            $builder = Nationality::query();
            $builder->where('label', 'like', '%'.$label.'%');

            return $builder->first();
        } catch (ModelNotFoundException $e) {
            throw new NationalityNotFoundException();
        }
    }
}
