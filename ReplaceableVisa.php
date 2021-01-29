<?php

namespace Tamkeen\Musaned\eVisa\Visas\ReplacementVisa;

use Carbon\Carbon;
use Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Exceptions\NoReplaceableVisaException;
use Tamkeen\Platform\Model\Common\HijriDate;
use Tamkeen\Platform\Model\NIC\IdNumber;
use Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException;
use Tamkeen\Platform\NIC\Exceptions\NicException;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;

class ReplaceableVisa
{
    /**
     * @var \Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository
     */
    private $visa_repository;

    /**
     * ReplaceableVisa constructor.
     *
     * @param \Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository $visa_repository
     */
    public function __construct(VisaRepository $visa_repository)
    {
        $this->visa_repository = $visa_repository;
    }

    /**
     * @param $id_number
     * @param $check_date
     * @param $labor_id
     *
     * @return string
     *
     * @throws \Tamkeen\Musaned\eVisa\Visas\ReplacementVisa\Exceptions\NoReplaceableVisaException
     */
    public function forLaborer($id_number, $check_date, $labor_id)
    {
        $worker = collect($this->visa_repository->getLaborInfo(
            IdNumber::fromString($id_number),
            HijriDate::resolveString($check_date)
        ))->where('Id', $labor_id)->first();

        $visas = collect($this->visa_repository->getAllVisas(
            IdNumber::fromString($id_number),
            HijriDate::resolveString($check_date)
        ));

        $line = Carbon::now()." -1- $id_number, $check_date, $labor_id -- ".json_encode($worker).' -- '.$visas->toJson()."\n";

        $visas = $visas->where('Nationality', trim($worker['Nationality']))
            ->where('Occupation', trim($worker['Occupation']['Name']));

        file_put_contents(storage_path('logs/alt-visa'), $line, FILE_APPEND);

        $bulkError = collect([]);
        $visas = $visas->reject(function ($visa) use ($check_date, $id_number, $labor_id, $bulkError) {
            try {
                $this->visa_repository->canAskForCompensation(
                    IdNumber::fromString($id_number),
                    IdNumber::fromString($labor_id),
                    trim($visa['VisaNo']),
                    IdNumber::fromString('1101891883')
                );
            } catch (NicException $e) {
                $bulkError->push('#'.$visa['VisaNo'].' : '.$e->getMessage());

                return true;
            } catch (FailedToProcessNICException $e) {
                $bulkError->push('#'.$visa['VisaNo'].' : '.$e->getMessage());

                return true;
            }

            return false;
        });

        $line = Carbon::now()." -2- $id_number, $check_date, $labor_id -- ".$visas->toJson()."\n";
        file_put_contents(storage_path('logs/alt-visa'), $line, FILE_APPEND);

        if ($visas->count() == 0) {
            throw new NoReplaceableVisaException('<br />'.$bulkError->unique()->implode('<br />'), 0);
        }

        return trim(data_get($visas->first(), 'VisaNo'));
    }
}
