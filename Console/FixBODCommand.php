<?php

namespace Tamkeen\Musaned\eVisa\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Tamkeen\Musaned\EndUsers\Users\CheckDate;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\EndUsers\Users\Validation\EndUserValidationService;
use Tamkeen\Platform\NIC\Repositories\Citizens\CitizenDataNotFoundException;
use Tamkeen\Platform\NIC\Repositories\Citizens\CitizensRepository;
use Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException;
use Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignersRepository;

class FixBODCommand extends Command
{
    protected $signature = 'eVisa:fix-BOD {from} {to}';

    protected $description = 'Fix BOD';

    /**
     * @var \Tamkeen\Platform\NIC\Repositories\Citizens\CitizensRepository
     */
    private $citizensRepo;

    /**
     * @var \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignersRepository
     */
    private $foreignersRepo;

    /**
     * FixBODCommand constructor.
     *
     * @param \Tamkeen\Platform\NIC\Repositories\Citizens\CitizensRepository      $citizensRepo
     * @param \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignersRepository  $foreignersRepo
     * @param \Tamkeen\Musaned\EndUsers\Users\Validation\EndUserValidationService $validationService
     */
    public function __construct(
        CitizensRepository $citizensRepo,
        ForeignersRepository $foreignersRepo,
        EndUserValidationService $validationService
    ) {
        parent::__construct();

        $this->citizensRepo = $citizensRepo;
        $this->foreignersRepo = $foreignersRepo;
        $this->validationService = $validationService;
    }

    public function handle()
    {
        $periodStart = Carbon::parse($this->argument('from'));
        $periodEnd = Carbon::parse($this->argument('to'));

        /** @var EndUser[] $users */
        $users = EndUser::whereBetween('created_at', [$periodStart, $periodEnd])->get();

        $errorCount = 0;

        foreach ($users as $user) {
            try {
                if ($user->getIdNumber()->isNationalIdNumber()) {
                    $date = $this->getCitizenBirthDate($user);
                } else {
                    $date = $this->getForeignerExpiryDate($user);
                }

                if ($user->getCheckDate()->equals($date)) {
                    $this->info('Valid date found for: '.$user->getIdNumber()->toScalar());
                } else {
                    $user->check_date = $date->toString('-');
                    $user->save();
                    $this->info('Updated date for:'.$user->getIdNumber()->toScalar());
                }
            } catch (CitizenDataNotFoundException $e) {
                $this->error('Error in fetch data: '.$user->id_number);
                ++$errorCount;
            } catch (ForeignerDataNotFoundException $e) {
                $this->error('Error in fetch data: '.$user->id_number);
                ++$errorCount;
            }
        }

        if ($errorCount > 0) {
            $this->error('Number of users having a problem: '.$errorCount);
        } else {
            $this->info('No errors.');
        }
    }

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     *
     * @return CheckDate
     */
    public function getCitizenBirthDate(EndUser $user)
    {
        $citizen = $this->citizensRepo->findByNameAndId(
            $user->id_number,
            $user->getName()->getFirstName(),
            $user->getName()->getLastName()
        );

        return CheckDate::fromDelimiteredString($citizen->getBirthDate()->getHijriDate()->toString('-'));
    }

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     *
     * @return CheckDate
     */
    public function getForeignerExpiryDate(EndUser $user)
    {
        $foreigner = $this->foreignersRepo->findByNumber($user->id_number);

        $hijriExpiryDate = $foreigner->getResidency()->getExpiryDate()->getHijriDate();

        return CheckDate::fromDelimiteredString($hijriExpiryDate->toString('-'));
    }
}
