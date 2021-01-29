<?php

namespace Tamkeen\Musaned\eVisa\Visas\Repositories;

use Tamkeen\Musaned\eVisa\Visas\Occupation;
use Tamkeen\Musaned\eVisa\Visas\OccupationId;

final class OccupationsEloquentRepository implements OccupationsRepository
{
    /**
     * {@inheritdoc}
     */
    public function all($ids = null, $type = 'online_only')
    {
        if (request()->has('type') && request('type') == 'DL_TRANSFER') {
            $type = 'demostic_labor_only';
        }

        if ($ids) {
            $ids = is_array($ids) ? $ids : explode(',', $ids);

            return Occupation::whereIn('id', $ids)->where($type, 1)->get();
        }

        return Occupation::where($type, 1)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getById(OccupationId $id)
    {
        if (request()->has('gio')) {
            return Occupation::query()->where('gio_only', 1)->findOrFail($id->toScalar());
        }

        return Occupation::findOrFail($id->toScalar());
    }

    /**
     * @param $code
     *
     * @return mixed
     */
    public function getByCode($code)
    {
        return Occupation::where('code', $code)->firstOrFail();
    }

    /**
     * {@inheritdoc}
     */
    public function getYemeni()
    {
        return Occupation::yemeni()->get();
    }
}
