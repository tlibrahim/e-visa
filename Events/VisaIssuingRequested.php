<?php

namespace Tamkeen\Musaned\eVisa\Events;

use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest;

class VisaIssuingRequested
{
    /**
     * @var \Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest
     */
    private $issueRequest;

    /**
     * @param \Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest $issueRequest
     */
    public function __construct(IssueRequest $issueRequest)
    {
        $this->issueRequest = $issueRequest;
    }

    /**
     * @return issueRequest
     */
    public function getIssueRequest()
    {
        return $this->issueRequest;
    }
}
