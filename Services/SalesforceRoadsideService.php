<?php

declare(strict_types=1);

namespace App\Service\Integration\Salesforce;


use App\Service\Integration\Salesforce\Enums\SalesforceBillingMethodType;
use App\Service\Integration\Salesforce\Factories\RoadsideResponseFactory;
use App\Service\Integration\Salesforce\Queries\GetCarrierProductFromDirectBillQuery;
use App\Service\Integration\Salesforce\Queries\GetRoadsideByCarrierIdQuery;
use App\Service\Integration\Salesforce\Queries\GetRoadsideVehiclesQuery;
use App\Service\Integration\Views\RoadsideView;
use App\Service\Integration\Views\VehiclesRoadsideView;

class SalesforceRoadsideService
{
    public function __construct(
        private readonly Client $client,
        private readonly RoadsideResponseFactory $roadsideResponseFactory,
    ) {}

    public function getRoadsideByCarrierProduct(GetRoadsideByCarrierIdQuery $query): RoadsideView
    {
        $response = $this->client->query($query);


        return !empty($response['records'])
            ? $this->roadsideResponseFactory->create($response['records'][0])
            : new RoadsideView();
    }

    /**
     * @param GetRoadsideVehiclesQuery $query
     * @return VehiclesRoadsideView[]
     */
    public function getRoadsideVehicles(GetRoadsideVehiclesQuery $query): array
    {
        $response = $this->client->query($query);

        return collect($response['records'])
            ->map(function (array $record) {
                $carrierProduct = match ($record['CanaryAMS__Policy__r']['CanaryAMS__Carrier_Product_Billing_Method__c']) {
                    SalesforceBillingMethodType::DIRECT_BILL->value => (function () use ($record) {
                        $response = $this->client->query(
                            new GetCarrierProductFromDirectBillQuery($record['Id'])
                        );

                        return $response['records'][0] ?? null;
                    })(),
                    default => $record['CanaryAMS__Policy__r']['CanaryAMS__Carrier_Product__c']
                };

                $roadside = $carrierProduct ? $this->getRoadsideByCarrierProduct(
                    new GetRoadsideByCarrierIdQuery($carrierProduct)
                ) : null;

                $view = new VehiclesRoadsideView();
                $view->id = $record['Id'];
                $view->name = $record['Name'];
                $view->vin = $record['CanaryAMS__VIN_Number__c'];
                $view->year = $record['CanaryAMS__Model_Year__c'];
                $view->model = $record['CanaryAMS__Model__c'];
                $view->make = $record['CanaryAMS__Make__c'];
                $view->policy_effective_date = $record['CanaryAMS__Policy__r']['CanaryAMS__Effective_Date__c'];
                $view->policy_renewal_date = $record['CanaryAMS__Policy__r']['CanaryAMS__Renewal_Date_2__c'];
                $view->policy_number = $record['CanaryAMS__Policy__r']['CanaryAMS__Policy_Number__c'];
                $view->roadside_phone = $roadside->roadside_phone;

                return $view;
            })
            ->toArray();
    }
}