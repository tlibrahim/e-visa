<?php

namespace Tamkeen\Musaned\eVisa\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tamkeen\Musaned\Common\Notifications\Email\Message\EmailMessageFactory;
use Tamkeen\Musaned\Common\Notifications\Email\SendEmailMessageCommand;
use Tamkeen\Musaned\EndUsers\Billing\Bill;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\Bus\Dispatcher;
use Tamkeen\Platform\Model\Common\Email;
use Tamkeen\Musaned\Common\Model\Name;

class BillsReport extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'eVisa:bills-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bills Report.';

    public function handle(Factory $views, Dispatcher $dispatcher)
    {
        if ( ! app()->environment('production')) {
            return;
        }

        $seven_days = $this->getLastNDays(7);
        $header = $seven_days;
        array_unshift($header, 'الحالة');
        array_push($header, 'منذ البداية');
        $statuses = $this->getStatuses();

        $bills = Bill::select(DB::raw('DATE(updated_at) AS date, status, COUNT(*) AS c'))
            ->whereRaw('DATE(updated_at) > DATE(NOW()) - INTERVAL 7 DAY')
            ->groupBy(DB::raw('DATE(updated_at), status'))
            ->orderBy(DB::raw('status, DATE(updated_at)'))
            ->get();

        $all_bills = Bill::select(DB::raw('status, COUNT(*) AS c'))
            ->groupBy(DB::raw('status'))->get();
        $all = [];

        $data = [];
        foreach ($statuses as $key => $status) {
            foreach ($bills as $bill) {
                if ($key != $bill->status) {
                    continue;
                }
                $data[$status][$bill->date] = [$bill->c];
            }

            foreach ($all_bills as $all_bill) {
                if ($key != $all_bill->status) {
                    continue;
                }
                $all[$status] = $all_bill->c;
            }
        }

        if ($diff3 = array_diff($statuses, array_keys($all))) {
            foreach ($diff3 as $v4) {
                $all[$v4] = 0;
            }
        }

        if ($diff1 = array_diff($statuses, array_keys($data))) {
            foreach ($diff1 as $v1) {
                $data[$v1][] = [0];
            }
        }

        foreach ($data as $k1 => $v2) {
            if ($diff2 = array_diff($seven_days, array_keys($v2))) {
                foreach ($diff2 as $v3) {
                    $data[$k1][$v3] = [0];
                }
            }
            if (array_diff(array_keys($v2), $seven_days)) {
                unset($data[$k1][0]);
            }

            $data[$k1][] = [$all[$k1]];
        }
        ksort($data);
        $htmlContent = $views->make('emails.evisa.bills.report', compact('header', 'data'))->render();

        $emails = [
            'Musaned_stats@tamkeentech.sa',
            'MA.AlMoghamis@tamkeentech.sa',
        ];
        $subject = 'فواتير مساند - '.Carbon::now()->format('Y-m-d');

        $messageFactory = EmailMessageFactory::create()->payload($subject, $htmlContent, '');
        foreach ($emails as $email) {
            $messageFactory->addReceiver(Name::make($email), Email::make($email));
        }
        $email = $messageFactory->build();

        $dispatcher->dispatchNow(new SendEmailMessageCommand($email, [
            'category' => ['daily_report'],
        ]));

        $this->info('Report sent.');
    }

    private function getLastNDays($days, $format = 'Y-m-d')
    {
        $m = date('m');
        $de = date('d');
        $y = date('Y');
        $dateArray = [];
        for ($i = 0; $i <= $days - 1; ++$i) {
            $dateArray[] = date($format, mktime(0, 0, 0, $m, ($de - $i), $y));
        }

        return array_reverse($dateArray);
    }

    private function getStatuses()
    {
        return [
            1 => 'PENDING',
            2 => 'PAID',
            3 => 'EXPIRED',
            4 => 'REFUNDED',
        ];
    }
}
