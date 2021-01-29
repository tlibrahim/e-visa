<?php

namespace Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Repositories;

use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\PROs\Offices\Office;
use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest;
use Tamkeen\Musaned\GIO\Offices\GIOffice;

interface ReplacementIssueRequestsRepository
{
    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     * @param int                                     $id
     *
     * @return \Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest
     */
    public function getByIdForUser(EndUser $user, $id);

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     * @param int                                     $page
     * @param int                                     $perPage
     *
     * @return \Illuminate\Support\Collection|\Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest
     */
    public function paginateForUser(EndUser $user, $page = 1, $perPage = 20);

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     *
     * @return int
     */
    public function countForUser(EndUser $user);
    /**
     * @param \Tamkeen\Musaned\PROs\Offices\Office $office
     *
     * @return int
     */
    public function countForOffice(Office $office);
    /**
     * @param \Tamkeen\Musaned\PROs\Offices\Office $office
     *
     * @return int
     */
    public function paginateForOffice(Office $office, $page = 1, $perPage = 20);
    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\ReplacementVisaIssueRequest $replacementVisaIssueRequest
     */
    public function persist(ReplacementVisaIssueRequest $replacementVisaIssueRequest);
    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function countForGIOffice(GIOffice $office);
    /**
     * @param \Tamkeen\Musaned\GIO\Offices\GIOffice $office
     *
     * @return int
     */
    public function paginateForGIOffice(GIOffice $office, $page = 1, $perPage = 20);
}
