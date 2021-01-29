<?php

namespace Tamkeen\Musaned\eVisa\Events;

use Illuminate\Contracts\View\Factory;
use Tamkeen\Musaned\EndUsers\Services\Notifications\SmsNotificationsService;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest;
use Tamkeen\Platform\Model\Scalar\BigString;

class SendVisaIssuingRequestInformation
{
    /**
     * @var \Illuminate\Contracts\View\Factory
     */
    private $views;

    /**
     * @var \Tamkeen\Musaned\EndUsers\Services\Notifications\SmsNotificationsService
     */
    private $smsNotificationsService;

    /**
     * @param \Illuminate\Contracts\View\Factory $views
     *
     * @var \Tamkeen\Musaned\EndUsers\Services\Notifications\SmsNotificationsService
     */
    public function __construct(Factory $views, SmsNotificationsService $smsNotificationsService)
    {
        $this->views = $views;
        $this->smsNotificationsService = $smsNotificationsService;
    }

    /**
     * @param \Tamkeen\Musaned\eVisa\Events\VisaIssuingRequested $event
     */
    public function visaIssuingRequested(VisaIssuingRequested $event)
    {
        if (app()->isLocal()) {
            return;
        }
        /** @var IssueRequest $issueRequest */
        $issueRequest = $event->getIssueRequest();
        $issueRequestId = $issueRequest->getId();
        $nationality = $issueRequest->nationality->getLabel();
        $occupation = $issueRequest->occupation->getLabel();
        $issue_place = $issueRequest->visaIssuePlace->getLabel();
        $message = BigString::make(trans('sms.visa_issue_requested', ['issueRequestId' => $issueRequestId, 'nationality' => $nationality, 'occupation' => $occupation, 'issue_place' => $issue_place]));
        $user = $issueRequest->getUser();
        // Send SMS message
        $this->smsNotificationsService->sendSms($user, $message);
    }
}
