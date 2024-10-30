<?php

declare(strict_types=1);

namespace App\Service\Integration\Salesforce\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetRoadsideVehiclesQuery extends SalesforceQuery
{

    public function __construct(public ?string $account_id) {}

    public function query(): Builder
    {
        return DB::table('CanaryAMS__Vehicle__c')
            ->select([
                'Id',
                'Name',
                'CanaryAMS__VIN_Number__c',
                'CanaryAMS__Model_Year__c',
                'CanaryAMS__Model__c',
                'CanaryAMS__Make__c',
                'CanaryAMS__Policy__r.CanaryAMS__Carrier_Product__c',
                'CanaryAMS__Policy__r.CanaryAMS__Carrier_Product_Billing_Method__c',
                'CanaryAMS__Policy__r.recordtype.name',
                'CanaryAMS__Policy__r.CanaryAMS__Policy_Number__c',
                'CanaryAMS__Policy__r.CanaryAMS__Effective_Date__c',
                'CanaryAMS__Policy__r.CanaryAMS__Renewal_Date_2__c',

            ])
            ->where('canaryams__policy__r.CanaryAMS__Account__c', '=', $this->account_id)
            ->where('CanaryAMS__Policy__r.recordtype.name', '=', 'Motor Club')
            ->whereIn('CanaryAMS__Policy__r.CanaryAMS__Policy_Status__c', ['Active', 'Cancellation Pending', 'Renewal'])
            ->limit(1);
    }
}