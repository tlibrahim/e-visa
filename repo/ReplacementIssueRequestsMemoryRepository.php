<?php

namespace Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Repositories;

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest;
use Tamkeen\Musaned\PROs\Offices\Office;
use Tamkeen\Musaned\GIO\Offices\GIOffice;

class ReplacementIssueRequestsMemoryRepository implements ReplacementIssueRequestsRepository
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private $issueRequests;

    /**
     * @param array $replacementVisaIssueRequest
     */
    public function __construct(array $replacementVisaIssueRequest = [])
    {
        $this->replacementVisaIssueRequest = collect($replacementVisaIssueRequest)->keyBy(function (ReplacementVisaIssueRequest $replacementVisaIssueRequest) {
            return $replacementVisaIssueRequest->getKey();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getByIdForUser(EndUser $user, $id)
    {
        // TODO: Implement getByIdForUser() method.
    }

    /**
     * {@inheritdoc}
     */
    public function paginateForUser(EndUser $user, $page = 1, $perPage = 20)
    {
        // TODO: Implement paginateForUser() method.
    }

    /**
     * {@inheritdoc}
     */
    public function countForUser(EndUser $user)
    {
        // TODO: Implement countForUser() method.
    }

    public function paginateForOffice(Office $office, $page = 1, $perPage = 20)
    {
        // TODO: Implement paginateForOffice() method.
    }

    /**
     * @param \Tamkeen\Musaned\PROs\Offices\Office $office
     *
     * @return int
     */
    public function countForOffice(Office $office)
    {
        // TODO: Implement countForOffice() method.
    }

    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest $replacementVisaIssueRequest
     */
    public function persist(ReplacementVisaIssueRequest $replacementVisaIssueRequest)
    {
    }

    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function countForGIOffice(GIOffice $office)
    {
    }

    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function paginateForGIOffice(GIOffice $office, $page = 1, $perPage = 20)
    {
    }
}
