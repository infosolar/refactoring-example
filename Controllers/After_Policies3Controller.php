<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Factories\ResponseFactory;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Service\Integration\Salesforce\Queries\GetPoliciesQuery;
use App\Service\Integration\Salesforce\Queries\GetPolicyQuery;
use App\Service\Integration\Salesforce\Queries\GetRoadsideVehiclesQuery;
use App\Service\Integration\Salesforce\SalesforcePoliciesService;
use App\Service\Integration\Salesforce\SalesforceRoadsideService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class After_Policies3Controller extends Controller
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly SalesforcePoliciesService $salesforcePoliciesService,
        private readonly SalesforceRoadsideService $salesforceRoadsideService,
    ) {}

    /**
     * @api
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $accountNumber = $account->account_id;

        $query = new GetPoliciesQuery();
        $query->account_number = $accountNumber;
        $data = $this->salesforcePoliciesService->index($query);

        if (empty($data)) {
            return $this->responseFactory->responseOk('Data Policy is empty.', []);
        }

        return $this->responseFactory->responseOk('Successful operation.', $data);
    }

    /**
     * @api
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show($id, Request $request): JsonResponse
    {
        $account = $request->user();
        $accountNumber = $account->account_id;

        $query = new GetPolicyQuery();
        $query->account_number = $accountNumber;
        $query->policy_id = $id;

        $policy = $this->salesforcePoliciesService->show($query);

        if (!$policy) {
            return $this->responseFactory->responseNotFound('Policy not found', []);
        }

        return $this->responseFactory->responseOk('Successful operation', $policy);
    }

    /**
     * @api
     * @param Request $request
     * @return JsonResponse
     */
    public function roadside(Request $request): JsonResponse
    {
        $account = $request->user();
        $accountNumber = $account->account_id;


        $data = $this->salesforceRoadsideService
            ->getRoadsideVehicles(new GetRoadsideVehiclesQuery(account_id: $accountNumber));


        return $this->responseFactory->responseOk('Successful operation.', $data);
    }
}
