<?php

namespace Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Repositories;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest;
use Tamkeen\Musaned\PROs\Offices\Office;
use Tamkeen\Musaned\GIO\Offices\GIOffice;

final class ReplacementIssueRequestsEloquentRepository implements ReplacementIssueRequestsRepository
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    private $db;

    /**
     * IssueRequestsEloquentRepository constructor.
     *
     * @param \Illuminate\Database\DatabaseManager $db
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function getByIdForUser(EndUser $user, $id)
    {
        $builder = ReplacementVisaIssueRequest::query();

        $builder->where('user_id', $user->getKey());

        try {
            return $builder->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            // TODO: create exception
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginateForUser(EndUser $user, $page = 1, $perPage = 20)
    {
        $builder = ReplacementVisaIssueRequest::query();

        $builder->getQuery()->forPage($page, $perPage);
        $builder->where('user_id', $user->getKey());

        return $builder->get();
    }

    /**
     * {@inheritdoc}
     */
    public function countForUser(EndUser $user)
    {
        $builder = ReplacementVisaIssueRequest::query();

        $builder->where('user_id', $user->getKey());

        return $builder->getQuery()->count();
    }

    public function paginateForOffice(Office $office, $page = 1, $perPage = 20)
    {
        $builder = ReplacementVisaIssueRequest::query()->with('visa');
        $builder->where('office_id', $office->getKey());
        $builder->getQuery()->forPage($page, $perPage);

        return $builder->get();
    }
    /**
     * @param \Tamkeen\Musaned\PROs\Offices\Office $office
     *
     * @return int
     */
    public function countForOffice(Office $office)
    {
        $query = ReplacementVisaIssueRequest::query();

        $query->where('office_id', $office->getKey());

        return $query->getQuery()->count();
    }
    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest $replacementVisaIssueRequest
     */
    public function persist(ReplacementVisaIssueRequest $replacementVisaIssueRequest)
    {
        $this->db->connection()->transaction(function () use ($replacementVisaIssueRequest) {
            $replacementVisaIssueRequest->save();
        });
    }
    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function countForGIOffice(GIOffice $office)
    {
        $query = ReplacementVisaIssueRequest::query();

        $query->where('gio_id', $office->getKey());

        return $query->getQuery()->count();
    }

    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function paginateForGIOffice(GIOffice $office, $page = 1, $perPage = 20)
    {
        $builder = ReplacementVisaIssueRequest::query()->with('visa');
        $builder->where('gio_id', $office->getKey());
        $builder->getQuery()->forPage($page, $perPage);

        return $builder->get();
    }
}
