<?php

declare(strict_types=1);

namespace App\Service\Integration\Salesforce;

use App\Service\Integration\Salesforce\Queries\SalesforceQuery;
use App\Service\Integration\Salesforce\Requests\SalesforceRequest;
use EHAERER\Salesforce\Authentication\PasswordAuthentication;
use EHAERER\Salesforce\SalesforceFunctions;

class Client
{
    private SalesforceFunctions $salesforceFunctions;

    public function __construct()
    {

        $salesforce = new PasswordAuthentication();
        $salesforce->setEndpoint();
        $salesforce->authenticate();

        $this->salesforceFunctions = new \EHAERER\Salesforce\SalesforceFunctions(
            $salesforce->getInstanceUrl(),
            $salesforce->getAccessToken(),
            "v52.0"
        );
    }

    public function query(SalesforceQuery $query): mixed
    {
        return $this->salesforceFunctions->query($query->toRawSql());
    }

    public function raw(string $query): mixed
    {
        return $this->salesforceFunctions->query($query);
    }

    public function update(SalesforceRequest $request): mixed
    {
        return $this->salesforceFunctions->update($request->object, $request->id, $request->toData());
    }

    public function create(SalesforceRequest $request): mixed
    {
        return $this->salesforceFunctions->create($request->object, $request->toData());
    }
}