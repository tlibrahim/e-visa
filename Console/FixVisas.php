<?php

namespace Tamkeen\Musaned\eVisa\Console;

use Illuminate\Console\Command;
use Tamkeen\Musaned\Common\Notifications\Email\Message\EmailMessageFactory;
use Tamkeen\Musaned\Common\Notifications\Email\SendEmailMessageCommand;
use Tamkeen\Musaned\EndUsers\Users\Exceptions\NoEmailException;
use Tamkeen\Musaned\eVisa\Visas\Visa;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\View\Factory;

class FixVisas extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'eVisa:fix-visas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Visas!.';

    public function handle(VisaRepository $visaRepository, Dispatcher $dispatcher, Factory $views)
    {
        $visas = Visa::with(['owner', 'nationality', 'occupation'])->whereIn('id', [83592, 768925, 768925, 768925, 768925, 772441, 784073, 794095, 795229, 795229, 795229, 811893, 816285, 830797, 836342, 878380, 878380, 879489, 880230, 880230, 880230, 880252, 881496, 881608, 881754, 890102, 890865, 890982, 894112, 905999, 906784, 911637, 919436, 919439, 922618, 922621, 937376, 941952, 941952, 942399, 942486, 942522, 943932, 944955, 948059, 948226, 948226, 951773, 951773, 953470, 953524, 953528, 954016, 955510, 955599, 955773, 956592, 956592, 956768, 957565, 958725, 960330, 960724, 960743, 960743, 960781, 960799, 961896, 963620, 963693, 966352, 966665, 966670, 967914, 968857, 969638, 969699, 969721, 969785, 970930, 971070, 971074, 971079, 972475, 972592, 974221, 974276, 974279, 974910, 975433, 976762, 976774, 977785, 980825, 980825, 980935, 980941, 989506, 989542, 989548, 989548, 989548, 989884, 989914, 991198, 991199, 991206, 991206, 991208, 991224, 991230, 991243, 991258, 991293, 991298, 991300, 991339, 991339, 991342, 991373, 991397, 991539, 991550, 991574, 991574, 991579, 991622, 991636, 991653, 991658, 991659, 991671, 991672, 991688, 991753, 992211, 1000433, 1000487, 1000860, 1001599, 1003828, 1004497, 1005571, 1005817, 1006691, 1006724, 1009207, 1009210])->get();
        foreach ($visas as $visa) {
            $nic_visas = collect($visaRepository->getAllVisas($visa->owner->getIdNumber(), $visa->owner->getCheckDate()));
            foreach ($nic_visas as $nic_visa) {
                if (('اصدرت' == $nic_visa['VisaStatus'])
                    && ($visa->nationality->label == $nic_visa['Nationality'])
                    && ($visa->occupation->label == $nic_visa['Occupation'])
                ) {
                    $visa->followup_date = date('Y-d-m', strtotime($nic_visa['VisaIssueDate']));
                    $visa->followup_number = $nic_visa['VisaNo'];
                    $visa->border_number = $nic_visa['BorderNo'];
                    if ($visa->save()) {
                        $this->info('Visa Id# '.$visa->id.' is updated.');
                    } else {
                        $this->error('Visa Id# '.$visa->id.' can not updated.');
                    }
                }
            }

            $issueRequest = $visa->issueRequest;
            $htmlContent = $views->make('back_office.emails.issue_requests.approved', [
                'request' => $issueRequest,
            ])->render();

            try {
                $message = EmailMessageFactory::create()
                    ->addReceiver($visa->owner->getName(), $visa->owner->getEmail())
                    ->payload(trans('emails.request_was_approved'), $htmlContent, '')
                    ->build();

                $dispatcher->dispatch(new SendEmailMessageCommand($message, [
                    'category' => ['end_user.visa_issue_request.acceptance_notification'],
                ]));
                $this->info('Email sent.');
            } catch (NoEmailException $e) {
                $this->error('Can not send email.');
            }
        }
    }
}
