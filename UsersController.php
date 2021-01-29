<?php

namespace Tamkeen\Musaned\EndUsers\Http\Users;

use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tamkeen\Musaned\Common\Laravel\ExceptionHandler;
use Tamkeen\Musaned\eContracts\Contracts\CancelVisaReason;
use Tamkeen\Musaned\EndUsers\Commands\Users\ChangeEndUserPasswordCommand;
use Tamkeen\Musaned\EndUsers\Commands\Users\PasswordMismatchException;
use Tamkeen\Musaned\EndUsers\Http\AbstractEndUsersController;
use Tamkeen\Musaned\EndUsers\Http\Visas\IssueRequests\CancelVisa;
use Tamkeen\Musaned\EndUsers\Users\Email;
use Tamkeen\Musaned\EndUsers\Users\EndUser;
use Tamkeen\Musaned\EndUsers\Users\Exceptions\NoEmailException;
use Tamkeen\Musaned\EndUsers\Users\Mobile;
use Tamkeen\Musaned\EndUsers\Users\Password;
use Tamkeen\Musaned\EndUsers\Users\UserDetails;
use Tamkeen\Musaned\EndUsers\Users\Validation\InvalidUserDataException;
use Tamkeen\Musaned\eNotice\Notices\Notice;
use Tamkeen\Musaned\eTawtheeq\TawtheeqContracts\MaritalStatus;
use Tamkeen\Musaned\eVisa\Services\NIC\VisaCancelService;
use Tamkeen\Musaned\eVisa\Services\NIC\VisaCancelServiceException;
use Tamkeen\Musaned\eVisa\Visas\CancellationRequests\CancellationRequest;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\Repositories\IssueRequestsRepository;
use Tamkeen\Musaned\eVisa\Visas\Occupation;
use Tamkeen\Musaned\eVisa\Visas\Visa;
use Tamkeen\Musaned\eVisa\Visas\VisaStatus;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;
use Tamkeen\Platform\Security\Authentication\Users\AnonymousUser;
use Tamkeen\Platform\Security\Services\Auditing\Auditor;
use Tamkeen\Platform\Security\Utils\JWT\NamshiEncoder;

class UsersController extends AbstractEndUsersController
{
    const ERROR__INCORRECT_OLD_PASSWORD = 101;
    const MUSANED_SERVICE_DESK = 3;
    const ERROR__INVALID_USER = 100;
    const ERROR__DUPLICATE_MOBILE_NUMBER = 102;

    public function dashboard(Request $request, IssueRequestsRepository $issueRequests)
    {
        $user = $this->getUser();

        $page = 1;
        $total = $issueRequests->countForUser($this->getUser());
        $collection = $issueRequests->paginateForUser($this->getUser(), $total / 3, 3);

        $paginator = $this->paginate($collection, $total, $request->url(), $page, 3);

        return $this->jsonSuccessResponse([
            'issueRequests' => $paginator->toArray(),
        ]);
    }

    public function info(VisaRepository $visa, MaritalStatus $maritalStatus)
    {
        $response = [];

        if ($this->getUser() instanceof AnonymousUser) {
            return $this->jsonSuccessResponse($response);
        }

        $user = $this->getUser()->load('userDetails');

        try {
            $personCacheKey = $this->getUser()->getIdNumber().'-'.$this->getUser()->getCheckDate().'-personal-info';
            $nicInfo = Cache::remember($personCacheKey, 30, function () use ($visa) {
                return $visa->getPersonalInfo($this->getUser()->getIdNumber(), $this->getUser()->getCheckDate());
            });
        } catch (InvalidUserDataException $e) {
            return $this->jsonErrorResponse('INVALID_USER', self::ERROR__INVALID_USER);
        } catch (ConnectException $e) {
            return $this->jsonErrorResponse('INVALID_USER', self::ERROR__INVALID_USER);
        }

        $nicInfo['marital_status'] = $maritalStatus->getStatus($nicInfo['marital_status']);
        $nicInfo['marital_status_en'] = $maritalStatus->getStatusEn($nicInfo['marital_status']);

        // $user->userDetails->house_type_id = HouseType::make($user->userDetails->house_type_id);
        $response = [
            'username'                                => $user->getIdentifier(),
            'id_number'                               => $user->getIdentifier(),
            'nic'                                     => $nicInfo,
            'name'                                    => $user->getName()->toScalar(),
            'mobile'                                  => $user->getMobileScalar(),
            'secondary_mobile'                        => $user->secondary_mobile,
            'show_current_balance'                    => $user->hasPendingIssueRequests(),
            'show_current_balance_and_refund_request' => $user->hasRefundableBill(new IssueRequest()),
            'show_notice_balance'                     => $this->getUser()->hasPendingNotices(),
            'show_notice_balance_and_refund_request'  => $this->getUser()->hasRefundableBill(new Notice()),
            'personal_details'                        => $user->userDetails,
        ];

        try {
            $response['email'] = $user->getEmail()->toScalar();
        } catch (NoEmailException $e) {
        }

        return $this->jsonSuccessResponse($response);
    }

    public function info_workers(VisaRepository $visa, MaritalStatus $maritalStatus, Occupation $occupation)
    {
        $allWorkers = collect([]);
        try {
            $workersCacheKey = $this->getUser()->getIdNumber().'-'.$this->getUser()->getCheckDate().'-workers';
            $allWorkers = Cache::remember($workersCacheKey, 30, function () use ($visa, $occupation) {
                return $occupation->getUserWorkersSponsor($visa->getLaborInfo($this->getUser()->getIdNumber(), $this->getUser()->getCheckDate()));
            });
        } catch (\Exception $e) {
            if ( ! str_contains($e->getMessage(), 'No more Data for display')) {
                app(ExceptionHandler::class)->report($e);

                return $this->jsonErrorResponse('failed to get user data');
            }
        }
        $allVisas = collect([]);
        try {
            $visasCacheKey = $this->getUser()->getIdNumber().'-'.$this->getUser()->getCheckDate().'-visas';
            $allVisas = Cache::remember($visasCacheKey, 30, function () use ($visa, $occupation) {
                return $occupation->getUserWorkersSponsor(collect($visa->getAllVisas($this->getUser()->getIdNumber(), $this->getUser()->getCheckDate())));
            });
        } catch (\Exception $e) {
            if ( ! str_contains($e->getMessage(), 'Person is not sponsor!')) {
                app(ExceptionHandler::class)->report($e);

                return $this->jsonErrorResponse('failed to get user data');
            }
        }

        $response = [
            'workers'   => $allWorkers,
            'visas'     => $allVisas,
        ];

        return $this->jsonSuccessResponse($response);
    }

    public function keepAlive()
    {
        if ($this->getUser() instanceof AnonymousUser) {
            return $this->jsonSuccessResponse([]);
        }
        $user = $this->getUser();

        return $this->jsonSuccessResponse([
            'username' => $user->getIdentifier(),
            'name'     => $user->getName()->toScalar(),
            'mobile'   => $user->getMobileScalar(),
        ]);
    }

    public function updateDetails(UpdateDetailsRequest $request)
    {
        $details = (new UserDetails())->getConnection()->transaction(function () use ($request) {
            UserDetails::where(['user_id' => $this->getUser()->getId()])->delete();

            $details = new UserDetails();
            $details->user_id = $this->getUser()->getId();
            $details->average_income = $request->average_income;
            $details->job = $request->job;
            $details->domestic_laborers = $request->domestic_laborers;
            $details->family_size = $request->family_size;
            $details->house_type_id = $request->house_type_id;
            $details->coordinates = $request->coordinates;
            $details->street_number = $request->street_number;
            $details->route = $request->route;
            $details->sublocality = $request->sublocality;
            $details->locality = $request->locality;
            $details->postal_code = $request->postal_code;
            $details->postal_code_suffix = $request->postal_code_suffix;
            $details->address = $request->address;
            $details->iban_owner = $request->iban_owner;
            $details->iban = $request->iban;
            $details->children_under_twelve_count = $request->children_under_twelve_count;
            $details->save();

            return $details;
        });
        $this->post('api/funds/sarie_rejection');

        return $this->jsonSuccessResponse($details->toArray());
    }

    public function updateContacts(UpdateContactDetailsRequest $request)
    {
        $email = Email::make($request->request->get('email'));
        $mobile = Mobile::resolveString($request->request->get('mobile'));
        $secondary = '';
        if ($request->secondary_mobile) {
            $secondary = Mobile::resolveString($request->secondary_mobile);
            if ($mobile == $secondary) {
                return $this->jsonErrorResponse('duplicate mobile number', self::ERROR__DUPLICATE_MOBILE_NUMBER);
            }
        }
        $this->dispatch(new UpdateEndUserContactDetails($this->getUser(), $email, $mobile, $secondary));

        return $this->jsonSuccessResponse();
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $oldPassword = Password::make($request->request->get('old_password'));
        $newPassword = Password::make($request->request->get('new_password'));

        try {
            $this->dispatch(new ChangeEndUserPasswordCommand($this->getUser(), $oldPassword, $newPassword));
        } catch (PasswordMismatchException $e) {
            return $this->jsonErrorResponse('INCORRECT_OLD_PASSWORD', self::ERROR__INCORRECT_OLD_PASSWORD);
        }

        return $this->jsonSuccessResponse();
    }

    public function reasons_cancel_visa()
    {
        return $reasons = CancelVisaReason::where('IsEndUser', 1)->get();
    }

    public function cancel_visa(CancelVisa $request, VisaRepository $repository, Auditor $auditor)
    {
        try {
            $visa = Visa::where('status', VisaStatus::issued()->toScalar())
                ->where('user_id', $this->getUser()->getKey())
                ->findOrFail($request->visa);
        } catch (ModelNotFoundException $e) {
            return $this->jsonErrorResponse('NOT_FOUND');
        }

        $visaCancelService = new VisaCancelService($visa, $request->reason, $this->getUser(), $repository, $auditor);
        try {
            $message = $visaCancelService->handle();

            return $this->jsonSuccessResponse(['status' => $message]);
        } catch (VisaCancelServiceException $e) {
            return $this->jsonErrorResponse($e->getMessage());
        }
    }

    private function createCancellationRequest(CancelVisa $request, $status)
    {
        $cancel = new  CancellationRequest();
        $cancel->visa_id = $request->visa;
        $cancel->status = $status;
        $cancel->reason = $request->reason;

        if ($cancel->save()) {
            return true;
        } else {
            return false;
        }
    }

    public function getInfo()
    {
        $user = $this->getUser()->load('userDetails');

        return response()->json([
            'id'                     => $user->id,
            'id_number'              => $user->id_number,
            'name'                   => $user->name,
            'email'                  => $user->email,
            'mobile'                 => $user->mobile,
            'secondary_mobile'       => $user->secondary_mobile,
            'check_date'             => $user->check_date,
            'city'                   => $user->city,
            'region'                 => $user->region,
            'district'               => $user->district,
            'user_details'           => $user->userDetails,
        ], 200);
    }

    public function isHasOutdatediban()
    {
        $hasOutdatedIban = current_user()->userDetails()->value('is_outdated_iban') == 1;

        return response()->json(['isHasOutdatediban' => $hasOutdatedIban]);
    }

    public function isUserIban(Request $request)
    {
        try {
            $userDetails = UserDetails::where('iban', $request->get('iban'))->exists();

            return response()->json(['isUserIban' => $userDetails]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => false]);
        }
    }

    public function setOutdatedUserIban(Request $request)
    {
        try {
            $iban = $request->get('iban');
            $user = EndUser::whereHas('userDetails', function ($q) use ($iban) {
                $q->where('iban', $iban);
            })->firstOrFail();
            $user->userDetails->update(['is_outdated_iban' => 1]);

            return response()->json(['user' => $user]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => false]);
        }
    }

    public function getPersonalDetails()
    {
        return $this->jsonSuccessResponse($user = $this->getUser()->load('userDetails')->toArray());
    }

    public function generateJiraToken()
    {
        $namshiEncoder = new NamshiEncoder(config('services.jira.jwt_secret'));
        $now = Carbon::now('UTC');

        $token = $namshiEncoder->encode([
            'iss' => 'evisa',
            'sub' => $this->getUser()->getIdentifier(),
            'fn'  => $this->getUser()->name,
            'iat' => $now->getTimestamp(),
            'exp' => $now->addMinutes(60)->getTimestamp(),
        ]);

        return response()->json(['token' => $token]);
    }
}
