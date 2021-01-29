<?php

namespace Tamkeen\Musaned\eVisa\Events;

use Illuminate\Contracts\View\Factory;
use Tamkeen\Musaned\ContractIndonesia\ContractIndonesia\ContractIndonesia;
use Tamkeen\Musaned\EndUsers\Services\Notifications\SmsNotificationsService;
use Tamkeen\Platform\Model\Scalar\BigString;

class SendContractIndonesiaRequestInformation
{
    /**
     * @var \Illuminate\Contracts\Bus\Dispatcher
     */
    private $dispatcher;

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
     * @param \Tamkeen\Musaned\EndUsers\Services\Notifications\SmsNotificationsService
     */
    public function __construct(Factory $views, SmsNotificationsService $smsNotificationsService)
    {
        $this->views = $views;
        $this->smsNotificationsService = $smsNotificationsService;
    }

    /**
     * @param \Tamkeen\Musaned\eVisa\Events\VisaIssuingRequested $event
     */
    public function contractIndonesiaRequested(contractIndonesiaRequested $event)
    {
        /** @var contractIndonesia $contractIndonesia */
        $contractIndonesia = $event->getIssueRequest();
        $contractIndonesiaId = $contractIndonesia->getId();
        $message = BigString::make(trans('sms.contract_Indonesia_issue_requested', ['issueRequestId' => $contractIndonesiaId]));
        $user = $contractIndonesia->getUser();
        // Send SMS message
        $this->smsNotificationsService->sendSms($user, $message);
    }
}
