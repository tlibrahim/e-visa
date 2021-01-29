<?php

namespace Tamkeen\Musaned\EndUsers\Http\Visas;

use Illuminate\Http\Request;
use Tamkeen\Musaned\Common\Laravel\ExceptionHandler;
use Tamkeen\Musaned\EndUsers\Http\AbstractEndUsersController;
use Tamkeen\Musaned\eVisa\Visas\CancellationRequests\CancellationRequest;
use Tamkeen\Musaned\eVisa\Visas\CancellationRequests\Repositories\CancellationRequestsRepository;
use Tamkeen\Musaned\eVisa\Visas\Repositories\VisasRepository;
use Tamkeen\Musaned\eVisa\Visas\VisaId;
use Tamkeen\Musaned\eVisa\Visas\Repositories\OccupationsRepository;
use Tamkeen\Musaned\eVisa\Visas\Visa;
use Tamkeen\Musaned\Takamol\Setting;
use Tamkeen\Platform\NIC\Repositories\Visas\VisaRepository;
use Tamkeen\Musaned\EndUsers\Users\IdNumber;
use Tamkeen\Platform\NIC\Exceptions\NicException;
use Tamkeen\Platform\Model\Common\HijriDate;
use Tamkeen\Musaned\eVisa\Visas\VisaStatus;
use Tamkeen\Musaned\eVisa\Visas\IssueRequests\IssueRequest;

class VisasController extends AbstractEndUsersController
{
    const ERROR__PENDING_CANCELLATION = 100;

    public function index(Request $request, VisasRepository $visas, VisaRepository $nic)
    {
        $page = $request->query->get('page', 1);
        $withUnissued = $request->query->get('unissued', false);

        $perPage = 15;

        if ($page == 0) {
            $perPage = 20000;
            $page = 1;
        }

        $collection = $visas->paginateUserVisas($this->getUser(), $page, $perPage, $withUnissued);

        $statuses = [];
        try {
            $allVisas = $nic->getAllVisas($this->getUser()->getIdNumber(), $this->getUser()->getCheckDate());

            foreach ($collection as &$visa) {
                foreach ($allVisas as $nic_visa) {
                    if ($visa->followup_number == $nic_visa['VisaNo']) {
                        $statuses[$nic_visa['VisaNo']] = $nic_visa['VisaStatus'];
                        $visa->status_label = $nic_visa['VisaStatus'];
                    }
                }
            }

            $this->updateVisasFromNic($this->getUser(), $allVisas);
        } catch (\Exception $exception) {
            app(ExceptionHandler::class)->report($exception);
        }

        $paginator = $this->paginate(
            $collection,
            $visas->countForUser($this->getUser(), $withUnissued),
            $request->url(),
            $page,
            $perPage
        );

        return $this->jsonSuccessResponse([
            'paginator' => $paginator->toArray(),
            'statuses'  => $statuses,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getVisasByIds(Request $request)
    {
        if ( ! $request->ids) {
            return response('Invalid parameter', 422);
        }
        $builder = Visa::where('user_id', $this->getUser()->getKey())
            ->whereIn('id', $request->ids);

        return response($builder->get()->keyBy('id')->toArray(), 200);
    }

    public function show($visaId, Request $request, VisasRepository $visas, VisaRepository $repository)
    {
        $withUnissued = $request->query->get('unissued', false);
        $visa = $visas->getById(VisaId::fromString($visaId), $this->getUser(), $withUnissued);
        try {
            $visas = $repository->getAllVisas(
                IdNumber::fromString($visa->owner->id_number),
                HijriDate::resolveString($visa->owner->getCheckDate()->toString('-'))
            );

            $nic_visa = collect($visas)->filter(function ($nic_visa) use ($visa) {
                return $visa->followup_number == $nic_visa['VisaNo'];
            })->first();
            $status = trim(data_get($nic_visa, 'VisaStatus'));

            if ($status == 'استخدمت') {
                $visa->status = VisaStatus::used()->toScalar();
                $visa->save();
            }
        } catch (NicException $e) {
            app(ExceptionHandler::class)->report($exception);
        }

        return $this->jsonSuccessResponse(['visa' => $visa->toArray()]);
    }

    public function processCancel(
        $visaId,
        VisasRepository $visas,
        CancellationRequestsRepository $cancellationRequests
    ) {
        $visa = $visas->getUserVisa($this->getUser(), VisaId::make($visaId));

        if ($cancellationRequests->hasPendingRequestFor($visa)) {
            return $this->jsonErrorResponse('CANCELLATION_REQUEST.PENDING', self::ERROR__PENDING_CANCELLATION);
        }

        $cancellationRequest = CancellationRequest::open($visa);

        $cancellationRequests->persist($cancellationRequest);

        return $this->jsonSuccessResponse([
            'visa' => $visa->toArray(),
        ]);
    }

    public function available()
    {
        return $this->jsonSuccessResponse([
            'visas' => $this->getUser()->getNotUsedVisas(
                $this->get('/api/visas')
            ),
        ]);
    }

    public function setting()
    {
        return [
            'is_closed'       => Setting::is_evisa_disabled(),
            'closing_message' => Setting::eVisa_disable_message(),
        ];
    }

    public function occupation(OccupationsRepository $occupations, $visaId)
    {
        $visa = Visa::query()->find($visaId);
        $occupationsList = $occupations->all([$visa->issueRequest->occupation_id])->toJson();

        return $this->jsonSuccessResponse([
            'occupations' => json_decode($occupationsList),
        ]);
    }

    /**
     * @param $user
     * @param $new_visas
     */
    private function updateVisasFromNic($user, $new_visas)
    {
        $old_visas = Visa::where('user_id', '=', $user->id)->get()->each(function ($old_visa, $key) use ($new_visas) {
            foreach ($new_visas as $new_visa) {
                if ($old_visa->followup_number == $new_visa['VisaNo']) {
                    Visa::where('followup_number', '=', $old_visa->followup_number)
                        ->update(['status' => VisaStatus::mapToNicStatus($new_visa['VisaStatus'])]);
                }
            }
        });
    }

    public function getVisaByIssueRequest($issueReuestID, VisasRepository $visas, VisaRepository $repository)
    {
        $visa = IssueRequest::where('id', $issueReuestID)->with('visa')->first();

        return $this->jsonSuccessResponse(['visa' => $visa->visa]);
    }
}
