<?php

namespace Tamkeen\Musaned\eVisa\Visas\IssueRequests;

use ErrorException;
use Illuminate\Support\Collection;
use Tamkeen\Musaned\Common\Laravel\ExceptionHandler;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\StoppedByGovernmentException;
use Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\TimeoutException;
use Tamkeen\Musaned\eVisa\Commands\Visas\IssueRequests\UserHasTrafficViolationException;
use Tamkeen\Musaned\eVisa\Services\CA\CitizenCapabilityService;
use Tamkeen\Musaned\eVisa\Services\GOSI\GOSICapabilityService;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\EmptyVisaException;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\MOSAConnectException;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\NoOccupationExemptionException;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\OrderSupportException;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\PersonNotFoundException;
use Tamkeen\Musaned\eVisa\Services\MOSA\Exceptions\SystemException;
use Tamkeen\Musaned\eVisa\Commands\Visas\DomesticLaborException;
use Tamkeen\Musaned\eVisa\Services\MOSA\MOSAService;
use Tamkeen\Musaned\eVisa\Services\NIC\OpenSponsorFile;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\CannotOpenSponserFile;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\UserBelowAllowedAge;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Exceptions\UserIqamaExpired;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsRepository;
use Tamkeen\Musaned\NicLog;
use Tamkeen\Musaned\Takamol\Setting;
use Tamkeen\Platform\Model\NIC\IdNumber;
use Tamkeen\Platform\NIC\Exceptions\DiedException;
use Tamkeen\Platform\NIC\Exceptions\NicException;
use Tamkeen\Platform\NIC\Model\ReferenceNumber;
use Tamkeen\Platform\NIC\Repositories\Visas\Model\ArrivalLocation;
use Tamkeen\Platform\NIC\Repositories\Visas\Model\RequestIstikdam;
use Tamkeen\Platform\NIC\Repositories\Visas\Model\RequestReason;
use Tamkeen\Platform\NIC\Repositories\Visas\Model\VisaNationality;
use Tamkeen\Platform\NIC\Repositories\Visas\Model\VisaOccupation;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;
use Tamkeen\Platform\NIC\Utils\Parser;
use Illuminate\Support\Facades\DB;
use Tamkeen\Musaned\DL\Services\DLTransferRequestToLaborerBinding;

class IssueCheckerService
{
    const ERROR__MAX_FILES_EXCEEDED = 101;
    const ERROR__MISSING_FILES = 102;
    const ERROR__PENDING_REQUEST = 103;
    const ERROR__USER_NOT_ALLOWED = 104;
    const ERROR__NOT_FOUND = 105;
    const ERROR__DIED = 106;
    const ERROR__CANNOT_OPEN_SPONSER_FILE = 107;
    const ERROR__VISA_NOT_FOUND = 108;
    const ERROR__DLBA_VISA_ISSUE = 108;
    const ERROR__VISA_ALREADY_ISSUED = 109;
    const ERROR__INVALID_BOD = 109;
    const ERROR__ORDER_SUPPORT = 110;
    const ERROR__SYSTEM = 111;
    const ERROR__HAS_TRAFFIC_VIOLATION = 111;
    const ERROR__BELOW_ALLOWED_AGE = 112;
    const ERROR__MAX_VISAS_EXCEEDED = 120;
    const ERROR__STOP_GOVERNMENT_SERVICES = 122;
    const DISABILITY_NOT_APPLICABLE = 123;
    const ERROR__ESCALATED = 124;
    const DISABILITY_NOT_FOUND = 125;
    const ERROR__DOMESTIC_LABOR = 126;
    const ERROR__Foreigner_Data_Not_Found = 127;
    const ERROR__ID_EXPIRED = 128;
    const ERROR__NO_BALANCE = 129;
    const DISABILITY_NO_OCCUPATION_EXEMPTION = 130;
    const NO_OCCUPATION_EXEMPTION = 130;
    const ERROR__UNALLOWED_ISSUE_PLACE_FOR_NATIONALITY = 129;
    const ERROR__UNALLOWED_FOR_CHANNEL = 130;
    const ERROR__REQUEST_NOT_FOUND = 201;
    const ERROR__GENERAL = 500;
    const ERROR__TIME_OUT = 511;

    /**
     * @var \Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository
     */
    private $visa;

    private $key;

    private $NicUserInfo;

    private $isVipUser = false;

    private $is_saudi = null;

    private $age;

    /**
     * @var \Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsRepository
     */
    private $issueRequests;
    /**
     * @var int
     */
    private $max_allowed_request = 0;

    /**
     * @var int
     */
    private $min_expected_salary;

    /**
     * @var int
     */
    private $available_visas;

    /**
     * @var int
     */
    private $current_visas;

    /**
     * @var int
     */
    private $current_workers;

    /**
     * @var int
     */
    private $laborsDetails;

    /**
     * @var int
     */
    private $min_expected_bank_balance;
    /**
     * @var string
     */
    private $reason = '';
    /**
     * @var
     */
    private $occupations = [];
    /**
     * @var array
     */
    private $extraFiles = [];
    /**
     * \Tamkeen\Musaned\eVisa\Services\MOSA\MOSAService.
     */

    /**
     * IssueCheckerService constructor.
     *
     * @param \Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository                         $visa
     * @param \Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsRepository $issueRequests
     */
    public function __construct(VisaRepository $visa, IssueRequestsRepository $issueRequests)
    {
        $this->visa = $visa;
        $this->issueRequests = $issueRequests;
    }

    /**
     * @return array
     */
    public function occupationList()
    {
        return Setting::is_nic_automation_enabled() ? $this->occupations : [];
    }

    public function extraFiles()
    {
        return Setting::is_nic_automation_enabled() ? (array) $this->extraFiles : ['EMPLOYER_OR_HAS_COMPANY'];
    }

    /**
     * @param EndUser $user
     *
     * @return bool
     *
     * @throws CannotOpenSponserFile
     * @throws DiedException
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    public function isAllowedToRequestVisa(EndUser $user)
    {
        dispatch(new OpenSponsorFile($user, $this->visa));

        return
            ! Setting::is_nic_automation_enabled() ||
            $this->passesNICAutomation($user);
    }

    /**
     * @param EndUser     $user
     * @param MOSAService $mosaService
     *
     * @return bool
     *
     * @throws EmptyVisaException
     * @throws MOSAConnectException
     * @throws MosaNonSaudiException
     * @throws OrderSupportException
     * @throws PersonNotFoundException
     * @throws SystemException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    public function isAllowedToRequestMosaVisa(EndUser $user, $mosaService, $automationEnabled = true, $validateNic = false, $occupation = null, $nationality = null, $issuePlace = null)
    {
        dispatch(new OpenSponsorFile($user, $this->visa));
        try {
            if ($user->getIdNumber()->isIqamahNumber()) {
                throw new MosaNonSaudiException('NON_SAUDI');
            }

            $mosaInfo = $mosaService->getPersonInfo($user->getIdNumber(), $user->getCheckDate(true));
            if ($mosaInfo['No_of_Visas'] < 1) {
                throw new EmptyVisaException('NO_AVAILABLE_VISAS');
            }
        } catch (PersonNotFoundException $e) { // catch for log
            $this->nic_log($user->getId(), 'NOT_REGISTERED_MOSA', $e);
            throw new PersonNotFoundException('NOT_REGISTERED_MOSA');
        } catch (CannotOpenSponserFile $e) { // catch for log
            $this->nic_log($user->getId(), 'NOT_REGISTERED_MOSA', $e);
            throw new PersonNotFoundException('NOT_REGISTERED_MOSA');
        } catch (MOSAConnectException $e) { // catch for log
            $this->nic_log($user->getId(), 'MOSAConnectException', $e);
            throw new MOSAConnectException($e->getMessage());
        } catch (OrderSupportException $e) { // catch for log
            $this->nic_log($user->getId(), 'Order_Support_MOSA', $e);
            throw new OrderSupportException('NOT_REGISTERED_MOSA');
        } catch (SystemException $e) { // catch for log
            $this->nic_log($user->getId(), 'System_MOSA', $e);
            throw new SystemException('System_MOSA');
        }

        if ( ! $automationEnabled) {
            return true;
        }

        return $this->isAllowedToRequestDisabilityVisa($user, $validateNic, $occupation, $nationality, $issuePlace);
    }

    public function isAllowedToRequestDisabilityVisa(EndUser $user, $validateNic, $occupation, $nationality, $issuePlace)
    {
        return Setting::is_nic_automation_disabled() || $this->passesNICAutomationForDisability($user, $validateNic, $occupation, $nationality, $issuePlace);
    }

    /**
     * @param EndUser $user
     *
     * @return bool
     *
     * @throws CannotOpenSponserFile
     * @throws DiedException
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    private function passesNICAutomationForDisability(EndUser $user, $validateNic = true, $occupation = null, $nationality = null, $issuePlace = null)
    {
        if ($validateNic) {
            $this->isAllowedToIssueVisaByNIC($user, true, $occupation, $nationality, $issuePlace);
        }

        if ( ! is_array($info = $this->getPersonInfo($user))) {
            return true;
        }

        if ($user->getIdNumber()->isIqamahNumber() && $user->isIqamaExpired()) {
            throw new UserIqamaExpired();
        }

        if ($info['status'] == 'ميت') {
            throw new DiedException('died');
        }

        $visas = $this->getVisas($user);
        $labors = $this->getLabors($user);

        $info['isDisable'] = 1;
        $issueChecker = new IssueChecker($info, $visas);

        try {
            $nicAutomationChecker = new NICAutomationChecker($issueChecker->getKey(), $visas, $labors, $user);

            $this->key = $nicAutomationChecker->getKey();
            $this->age = $issueChecker->getAge();
            $this->max_allowed_request = $nicAutomationChecker->getMaxAllowedVisas();
            $this->available_visas = $nicAutomationChecker->getAvailableVisas();
            $this->extraFiles = $nicAutomationChecker->getExtraFiles();
            $this->min_expected_salary = $nicAutomationChecker->getMinExpectedSalary();
            $this->min_expected_bank_balance = $nicAutomationChecker->getMinExpectedBankBalance();
            $this->occupations = $nicAutomationChecker->getOccupations();
            $this->NicUserInfo = $info;

            if ($nicAutomationChecker->getAvailableVisas() <= 0) {
                $this->reason = $issueChecker->reason;
                $this->nic_log($user->getId(), $issueChecker->getKey());

                return false;
            }
        } catch (ErrorException $e) {
            // if user don't have key
            app(ExceptionHandler::class)->report($e);

            return false;
        } catch (ExceededMaximumVisasAllowedNumber $e) {
            return false;
        } catch (\Exception $e) {
            app(ExceptionHandler::class)->report($e);

            return false;
        }

        return true;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function isSaudi()
    {
        return $this->is_saudi;
    }

    public function isDefined()
    {
        return ! ($this->key == 'UndefinedSaudi' || $this->key == 'UndefinedForeign' || is_null($this->key));
    }

    public function getNicUserInfo()
    {
        return $this->NicUserInfo;
    }

    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param $key
     * @param $nic_automation
     * @param $visasIssued
     * @param $laborsInKSA
     *
     * @return mixed
     */
    public function getOccupations($key, $nic_automation, $visasIssued, $laborsInKSA)
    {
        if ($nic_automation['max_allowed_visas'] == 0) {
            return [];
        }
        $occupations = $nic_automation['occupations']['all'];

        if ( ! count($visasIssued) && ! count($laborsInKSA)) {
            return $occupations;
        }

        $males = [];
        $females = [];

        foreach ($visasIssued as $validVisasFromNICRow) {
            if ( ! $this->isDL(trim($validVisasFromNICRow['Occupation']))) {
                continue;
            }

            $validVisasFromNICRow['occupation_id'] = VisaOccupation::fromLabel(trim(str_replace(
                '  ',
                ' ',
                $validVisasFromNICRow['Occupation']
            )))->getCode();

            if ( ! $validVisasFromNICRow['occupation_id']) {
                continue;
            }
            if ('Female' == $validVisasFromNICRow['Sex']) {
                $females[] = $validVisasFromNICRow['occupation_id'];
            } else {
                $males[] = $validVisasFromNICRow['occupation_id'];
            }
        }

        foreach ($laborsInKSA as $laborsInKSARow) {
            if ( ! $laborsInKSARow['Occupation']['Code']) {
                continue;
            }

            if ('ذكر' == $laborsInKSARow['Sex']['Name']) {
                $males[] = $laborsInKSARow['Occupation']['Code'];
            } else {
                $females[] = $laborsInKSARow['Occupation']['Code'];
            }
        }

        $all = array_merge($males, $females);
        $used = [];

        $results = DB::table('lookup_occupations')->select('id', 'label')->get();

        foreach ($results as $index => $result) {
            try {
                $code = VisaOccupation::fromLabel($result->label)->getCode();

                foreach ($all as $result2) {
                    if ($code == $result2) {
                        array_push($used, $result->id);
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // TODO: make the number of males dynamic -from DB-
        switch ($key) {
            case in_array($key, [
                'SaudiManMarriedHasNotChilds',
                'SaudiManDivorcedWidowedHasChilds',
                'SaudiWomanDivorcedWidowedHasChilds',
                'SaudiManMarriedHasChilds',
            ]):
                if (in_array(6132075, $males)) {
                    $occupations = $nic_automation['occupations']['all_except_worker'];
                }

                if (count($males) == 3) {
                    $occupations = $nic_automation['occupations']['all_females'];
                }

                return $occupations;
                break;
            case in_array($key, ['SaudiWomanMarriedHasChilds', 'SaudiWomanMarriedHasNotChilds']):
                if (in_array(6132075, $males)) {
                    $occupations = $nic_automation['occupations']['all_except_worker'];
                }
                if (count($males) == 3) {
                    $occupations = $nic_automation['occupations']['all_females'];
                }

                $counts = array_count_values($females);

                if (sizeof($counts) != 0) {
                    if (sizeof($counts) === 3) {
                        if (in_array(1, $occupations)) {
                            $occupations = $nic_automation['occupations']['all_males'];
                        } else {
                            $occupations = array_diff($nic_automation['occupations']['all_males'], ['1']);
                        }
                    }
                }

                return $occupations;
                break;
            case in_array($key, ['SaudiWomanSingleAndAbove24', 'SaudiWomanDivorcedWidowedHasNotChilds']):

                $occupations = array_diff($occupations, $used);

                return $occupations;
                break;
            case in_array($key, ['SaudiManDivorcedWidowedHasNotChilds', 'SaudiManSingleAndAbove24']):
                if (in_array(6132075, $males)) {
                    $occupations = $nic_automation['occupations']['all_except_worker'];
                    $occupations = array_diff($occupations, $used);

                    return $occupations;
                }
                $occupations = array_diff($occupations, $used);

                return $occupations;
                break;
            case in_array($key, ['ForeignManMarriedHasChilds', 'ForeignManMarriedHasNotChilds']):
                return array_diff($occupations, $used);
                break;
            case in_array($key, ['ForeignWomanDivorcedWidowedHusbandIsAbsentHasChilds', 'ForeignWomanMarriedHerHusbandSaudiHasNotChilds']):
                if (count($males) == 1) {
                    $occupations = $nic_automation['occupations']['all_females'];
                    $occupations = array_diff($occupations, $used);

                    return $occupations;
                }
                $occupations = array_diff($occupations, $used);

                return $occupations;
                break;
            case 'ForeignWomanMarriedHerHusbandSaudiHasChilds':
                if (count($males) == 1) {
                    $occupations = $nic_automation['occupations']['all_females'];
                    $occupations = array_diff($occupations, $used);

                    return $occupations;
                }
                $occupations = array_diff($occupations, $used);

                return $occupations;
                break;

            case 'ForeignWomenMarriedHerHusbandIsForeign':
                if (count($males) == 1) {
                    $occupations = $nic_automation['occupations']['all_females'];
                    $occupations = array_diff($occupations, $used);

                    return $occupations;
                }

                $occupations = array_diff($occupations, $used);

                return $occupations;
                break;
        }

        return $occupations;
    }

    /**
     * @return int
     */
    public function getMaxAllowedNumber()
    {
        return $this->max_allowed_request;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     *
     * @return Collection
     *
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    private function getVisas(EndUser $user)
    {
        $visas = collect();

        try {
            $visas = collect($this->visa->getAllVisas($user->getIdNumber(), $user->getCheckDate()));
        } catch (NicException $e) {
            if ( ! (new Parser($e->getMessage()))->getByTag('ErrorMessage', '') == 'Person is not sponsor!') {
                $this->nic_log($user->getId(), 'NotClassified', $e);
                $this->handleException($user, $e);
            }
        }

        return $visas;
    }

    /**
     * @param \Tamkeen\Musaned\EndUsers\Users\EndUser $user
     *
     * @return Collection
     *
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    private function getLabors(EndUser $user)
    {
        $labors = collect();
        try {
            $labors = collect($this->visa->getLaborInfo($user->getIdNumber(), $user->getCheckDate()));
        } catch (NicException $e) {
            $errorMessage = (new Parser($e->getMessage()))->getByTag('ErrorMessage', '');
            if ( ! $errorMessage == 'No more Data for display') {
                $this->nic_log($user->getId(), 'NotClassified', $e);
                $this->handleException($user, $e);
            }
        }

        $binding = new DLTransferRequestToLaborerBinding();

        return $binding->includeDomesticLaborTransferRequests($user, $labors);
    }

    /**
     * @param                 $user_id
     * @param                 $reason
     * @param \Exception|null $e
     */
    public function nic_log($user_id, $reason, \Exception $e = null)
    {
        $nic_log = new NicLog();
        $nic_log->user_id = $user_id;
        $nic_log->reason = $reason;
        $nic_log->exception = $e == null ? '' : $e->getMessage().json_encode($e->getTrace());
        $nic_log->save();
    }

    /**
     * @param EndUser $user
     * @param         $e
     */
    public function handleException(EndUser $user, \Exception $e)
    {
        if (NicExceptions::hasTrafficViolation($e)) {
            $this->nic_log($user->getId(), 'hasTrafficViolation', $e);
            throw new UserHasTrafficViolationException();
        } elseif (NicExceptions::isStoppedByGovernment($e)) {
            $this->nic_log($user->getId(), 'StoppedByGovernment', $e);
            throw new StoppedByGovernmentException();
        } elseif (NicExceptions::isNotDisable($e)) {
            $this->nic_log($user->getId(), 'notDisable', $e);
            throw new PersonNotFoundException('NOT_REGISTERED_MOSA');
        } elseif (NicExceptions::MOSAServiceDown($e)) {
            $this->nic_log($user->getId(), 'MOSAServiceDown', $e);
            throw new MOSAConnectException('MOSAServiceDown');
        } elseif (NicExceptions::ifNoExemption($e)) {
            $this->nic_log($user->getId(), 'noExemption', $e);
            throw new EmptyVisaException('noExemption');
        } elseif (NicExceptions::NoOccupationExemption($e)) {
            $this->nic_log($user->getId(), 'NoOccupationExemption', $e);
            throw new NoOccupationExemptionException('NoOccupationExemption');
        } elseif (NicExceptions::isTimeout($e)) {
            $this->nic_log($user->getId(), 'Timeout', $e);
            throw new TimeoutException();
        } elseif (NicExceptions::hasInvalidBOD($e)) {
            $this->nic_log($user->getId(), 'BirthOfDate', $e);
            throw new InvalidBOD('Invalid BirthDate');
        }
    }

    /**
     * @param EndUser $user
     *
     * @return bool
     */
    public function isHasFinancialCapacityToRequestVisa(EndUser $user)
    {
        return true;
    }

    public static function DLBALabors($labors)
    {
        return $labors->filter(function ($labor) {
            return in_array(trim($labor['Status']), config('nic.allowed_statuses')) &&
                in_array(trim($labor['TravelStatus']), config('nic.allowed_travel_statuses')) &&
                in_array(str_replace(' ', '', trim($labor['Occupation']['Name'])), config('nic.housing_jobs'));
        })->toArray();
    }

    /**
     * @param EndUser $user
     *
     * @throws InvalidBOD
     */
    private function openSponsorFile(EndUser $user)
    {
        if ( ! $user->isSponsor()) {
            try {
                $this->visa->createSponsor($user->getIdNumber());

                $this->setSponsor($user->getId());
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'This person has been registered as sponsor before')) {
                    $this->setSponsor($user->getId());
                } else {
                    $this->handleException($user, $e);
                }
            }
        }
    }

    public function setSponsor($userId)
    {
        $endUser = EndUser::find($userId);
        $endUser->is_sponsor = 1;
        $endUser->save();
    }

    /**
     * @param EndUser $user
     *
     * @return bool
     *
     * @throws InvalidBOD
     */
    private function isAllowedToIssueVisaByNIC(EndUser $user, $isDisable = false, $occupation = 'سائق خاص', $nationality = 'الفلبين', $issuePlace = 'مانيلا')
    {
        $this->openSponsorFile($user);

        $requestIstikdam = new RequestIstikdam(
            IdNumber::fromString($user->id_number),
            ReferenceNumber::make('14500019999999'),
            [],
            RequestReason::fromCode(1),
            ArrivalLocation::fromLabel($issuePlace),
            VisaNationality::fromLabel($nationality),
            VisaOccupation::fromLabel($occupation),
            $isDisable
        );

        try {
            $this->visa->validateVisaIssuance($requestIstikdam, IdNumber::fromString($user->id_number));
        } catch (\Exception $e) {
            $this->handleException($user, $e);
        }

        return true;
    }

    /**
     * @param EndUser $user
     *
     * @return array
     *
     * @throws CannotOpenSponserFile
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    public function getPersonInfo(EndUser $user)
    {
        $info = null;
        try {
            if ( ! $info = $this->visa->getPersonalInfo($user->getIdNumber(), $user->getCheckDate())) {
                throw new \InvalidArgumentException(sprintf(
                    "This person can't be found NIN: %s, BOF: %s",
                    $user->getIdNumber()->toScalar(),
                    $user->getCheckDate()->toString('/')
                ));
            }
        } catch (NicException $e) {
            $this->handleException($user, $e);
            $this->nic_log($user->getId(), 'NotClassified', $e);
        }

        return $info;
    }

    /**
     * @param EndUser $user
     *
     * @return bool
     *
     * @throws CannotOpenSponserFile
     * @throws DiedException
     * @throws InvalidBOD
     * @throws \Tamkeen\Platform\NIC\Exceptions\FailedToProcessNICException
     * @throws \Tamkeen\Platform\NIC\Repositories\Foreigners\ForeignerDataNotFoundException
     */
    private function passesNICAutomation(EndUser $user)
    {
        $this->isAllowedToIssueVisaByNIC($user);

        if ($user->getIdNumber()->isIqamahNumber() && $user->isIqamaExpired()) {
            throw new UserIqamaExpired();
        }

        if ( ! is_array($info = $this->getPersonInfo($user))) {
            return true;
        }

        if ($info['status'] == 'ميت') {
            throw new DiedException('died');
        }
        if (in_array(preg_replace('/\s*/', '', $info['occupation']), config('nic.housing_jobs'))) {
            throw new DomesticLaborException();
        }

        $visas = $this->getVisas($user);
        $labors = $this->getLabors($user);
        $issueChecker = new IssueChecker($info, $visas);

        $this->isVipUser = $user->isVipUser();

        if ($issueChecker->getAge() < $issueChecker::ALLOWED_AGE && ! $this->isVipUser) {
            throw new UserBelowAllowedAge();
        }

        try {
            $nicAutomationChecker = new NICAutomationChecker($issueChecker->getKey(), $visas, $labors, $user);
            if ($nicAutomationChecker->getAvailableVisas() <= 0) {
                $this->reason = $issueChecker->reason;
                $this->nic_log($user->getId(), $issueChecker->getKey());

                return false;
            }

            $this->key = $nicAutomationChecker->getKey();
            $this->age = $issueChecker->getAge();
            $this->NicUserInfo = $info;
            $this->max_allowed_request = $nicAutomationChecker->getMaxAllowedVisas();
            $this->available_visas = $nicAutomationChecker->getAvailableVisas();
            $this->current_visas = $nicAutomationChecker->getCurrentVisas();
            $this->current_workers = $nicAutomationChecker->getCurrentWorkers();
            $this->extraFiles = $nicAutomationChecker->getExtraFiles();
            $this->min_expected_salary = $nicAutomationChecker->getMinExpectedSalary();
            $this->min_expected_bank_balance = $nicAutomationChecker->getMinExpectedBankBalance();
            $this->occupations = $nicAutomationChecker->getOccupations();
            $this->laborsDetails = $nicAutomationChecker->getLaborsDetails();
            $this->is_saudi = $issueChecker->isTreatedAsSaudi();
        } catch (ErrorException $e) {
            // if user don't have key
            return false;
        } catch (ExceededMaximumVisasAllowedNumber $e) {
            return false;
        } catch (\Exception $e) {
            app(ExceptionHandler::class)->report($e);

            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getMinExpectedSalary()
    {
        return (int) $this->min_expected_salary;
    }

    /**
     * @return int
     */
    public function getMinExpectedBankBalance()
    {
        return (int) $this->min_expected_bank_balance;
    }

    /**
     * @return int
     */
    public function getAvailableVisas()
    {
        return (int) $this->available_visas;
    }

    /**
     * @return int
     */
    public function getCurrentVisas()
    {
        return (int) $this->current_visas;
    }

    /**
     * @return int
     */
    public function getCurrentWorkers()
    {
        return (int) $this->current_workers;
    }

    /**
     * @return int
     */
    public function getLaborDetails()
    {
        return $this->laborsDetails;
    }

    /**
     * @param $name
     *
     * @return bool
     *
     * @internal param $labor
     */
    public function isDL($name)
    {
        return in_array(preg_replace('/\s*/', '', $name), config('nic.housing_jobs'));
    }

    public function isVipUser()
    {
        return $this->isVipUser;
    }

    public function isFinanciallyCapable($user)
    {
        $isFinanciallyCapable = false;
        if (Setting::is_nic_automation_enabled() && $this->getKey()) {
            if (Setting::is_ca_auto_approve_enabled() && ! $user->getIdNumber()->isIqamahNumber()) {
                try {
                    $isFinanciallyCapable = strtolower(app()->make(CitizenCapabilityService::class)->forUser($user)
                        ->isCapable($this->getMinExpectedSalary())) == 'yes';
                } catch (\Exception $e) {
                    app(ExceptionHandler::class)->report($e);
                }
            } elseif (Setting::is_gosi_auto_approve_enabled() && $user->getIdNumber()->isIqamahNumber()) {
                try {
                    $isFinanciallyCapable = app()->make(GOSICapabilityService::class)->forUser($user)
                        ->isCapable($this->getMinExpectedSalary()) == true;
                } catch (\Exception $e) {
                    app(ExceptionHandler::class)->report($e);
                }
            }
        }

        return $isFinanciallyCapable;
    }

    public function refresh()
    {
        $this->key = null;
        $this->age = null;
        $this->NicUserInfo = null;
        $this->max_allowed_request = null;
        $this->available_visas = null;
        $this->current_visas = null;
        $this->current_workers = null;
        $this->extraFiles = null;
        $this->min_expected_salary = null;
        $this->min_expected_bank_balance = null;
        $this->occupations = null;
        $this->laborsDetails = null;
        $this->reason = null;
        $this->isVipUser = false;
    }
}
