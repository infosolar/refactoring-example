<?php


class Before_Policies3Controller extends Controller
{


    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        /* Check if you have TOKEN. */
        if (!$request->header('authorization')) {
            return response()->json([
                'error' => '401',
                'message' => 'Unauthorized access',
                'data' => [],
            ], 401);
        }
        /* END: Check if you have TOKEN. */

        $token = $request->header('Authorization');
        $isValidToken = $this->isValidToken($token);

        $result = [
            'error' => '0',
            'message' => 'No data to show.',
            'data' => [],
        ];

        if ($isValidToken['error'] == 0) {
            $this->connect();
            $accountNumber = $isValidToken['accountNumber'];

            $finished_at = date('Y-m-d', strtotime(now() . ' + 2 days'));
            $query = "SELECT 
            Id, 
            Name, 
            CanaryAMS__Account__c, 
            CanaryAMS__Carrier__c, 
            CanaryAMS__Producer__c, " .
                "CanaryAMS__Effective_Date__c,
                 CanaryAMS__Policy_Number__c, 
                 CanaryAMS__Policy_Term__c, " .
                "CanaryAMS__Renewal_Date_2__c, 
               CanaryAMS__Policy_Status__c, 
               CanaryAMS__Carrier_Product__c,
                CanaryAMS__Carrier_Product_Billing_Method__c, 
                CanaryAMS__General_Liability_Each_O__c, 
                CanaryAMS__Damage_to_Rented_Premises__c, 
                CanaryAMS__Medical_Payments__c, 
                CanaryAMS__Personal_Advertising_Injury__c, 
                CanaryAMS__General_Liability_General_Aggregate__c,
                 CanaryAMS__Products_COMP_OP_Agg__c,
                  CanaryAMS__DiseaseEachEmployee__c,
                   CanaryAMS__DiseasePolicyLimit__c,
                    CanaryAMS__EachAccident__c,
                     CanaryAMS__Per_Statute_Coverage__c, 
                     CanaryAMS__carrier__r.name " .
                "FROM CanaryAMS__Policy__c " .
                "WHERE Id = '$id' AND CanaryAMS__Account__c = '$accountNumber' " .
                "LIMIT 1";

            $crud = new \bjsmasth\Salesforce\CRUD();
            $data = $crud->query($query);

            if ($data['totalSize'] != '0') {
                foreach ($data['records'] as $record) {
                    $policy_type = $this->getPolicyType(
                        $record['CanaryAMS__Carrier_Product__c'],
                        $record['CanaryAMS__Carrier__c']
                    );
                    $id_card = false;

                    // Switch Policy Type	////////////////////

                    $data_obj = (object)[
                        'id' => $record['Id'],
                        'name' => $record['Name'],
                        'account' => $record['CanaryAMS__Account__c'],
                        'policy_number' => $record['CanaryAMS__Policy_Number__c'],
                        'policy_type' => $policy_type,
                        'effective_date' => $record['CanaryAMS__Effective_Date__c'],
                        'renewal_date' => $record['CanaryAMS__Renewal_Date_2__c'],
                        'status' => $record['CanaryAMS__Policy_Status__c'],
                        'carrier' => $record['CanaryAMS__Carrier__c'],
                        'carrier_name' => $record['CanaryAMS__Carrier__r']['Name'],
                        'email_carrier' => $this->getEmailCarrier($record['CanaryAMS__Carrier__c']),
                        'policy_term' => $record['CanaryAMS__Policy_Term__c'],
                        'agent' => $this->getAgent($record['CanaryAMS__Producer__c']),
                    ];

                    // Agents + AgentImage

                    switch ($policy_type) {
                        case 'personal_auto':

                            if ($record['CanaryAMS__Carrier_Product_Billing_Method__c'] == "Direct Bill") {
                                $carrierProduct = $this->getCarrierProductFromDirectBill($id);
                            } else {
                                $carrierProduct = $record['CanaryAMS__Carrier_Product__c'];
                            }

                            $data_obj->vehicles = $this->getVehicles($id);
                            $data_obj->drivers = $this->getDrivers($id);
                            $data_obj->roadside = $this->getRoadside($carrierProduct); // No mostrar en detalle poliza
                            $data_obj->id_card = true;
                            break;

                        case 'commercial_auto':

                            //Roadside
                            if ($record['CanaryAMS__Carrier_Product_Billing_Method__c'] == "Direct Bill") {
                                $carrierProduct = $this->getCarrierProductFromDirectBill($id);
                            } else {
                                $carrierProduct = $record['CanaryAMS__Carrier_Product__c'];
                            }

                            $data_obj->vehicles = $this->getCommercialVehicle($id);
                            $data_obj->drivers = $this->getDrivers($id);
                            $data_obj->roadside = $this->getRoadside($carrierProduct);
                            $data_obj->id_card = true;

                            break;

                        case 'general_liability':

                            $data_obj->general_liability_each = $record['CanaryAMS__General_Liability_Each_O__c'];
                            $data_obj->damage_to_rented_premises = $record['CanaryAMS__Damage_to_Rented_Premises__c'];
                            $data_obj->medical_payments = $record['CanaryAMS__Medical_Payments__c'];
                            $data_obj->personal_advertising_injury = $record['CanaryAMS__Personal_Advertising_Injury__c'];
                            $data_obj->general_liability_general_aggregate = $record['CanaryAMS__General_Liability_General_Aggregate__c'];
                            $data_obj->products_comp_op_agg = $record['CanaryAMS__Products_COMP_OP_Agg__c'];
                            $data_obj->id_card = false;

                            break;

                        case 'workers_compensation':
                            $data_obj->each_accident = $record['CanaryAMS__EachAccident__c'];
                            $data_obj->disease_each_employee = $record['CanaryAMS__DiseaseEachEmployee__c'];
                            $data_obj->disease_policy_limit = $record['CanaryAMS__DiseasePolicyLimit__c'];

                            $data_obj->per_statute_coverage = $record['CanaryAMS__Per_Statute_Coverage__c'];
                            $data_obj->id_card = false;

                            break;

                        case 'dental_individual':
                        case 'vision_individual':
                        case 'life_individual':

                            $data_obj->id_card = false;

                            break;

                        case 'renters':
                        case 'condo':
                        case 'home_dwelling':
                        case 'homeowners':
                        case 'dwelling_fire':

                            // Buscar Coverage
                            $data_obj->coverage = $this->getCoverage($id);
                            $data_obj->property = $this->getProperty($id);
                            $data_obj->id_card = false;

                            break;

                        case 'motorcycle_atv':
                        case 'mexico_policies':

                            $data_obj->vehicles = $this->getVehicles($id);
                            $data_obj->drivers = $this->getDrivers($id);
                            $data_obj->id_card = true;

                            break;
                    }
                    // End Switch Policy Type	////////////////

                    $result = [
                        'error' => '0',
                        'message' => 'Successful operation.',
                        'data' => $data_obj,
                    ];
                }
            } else {
                $result = [
                    'error' => '1',
                    'message' => 'Without authorization to see the data.',
                    'data' => [],
                ];
            }
        } else {
            $result = [
                'error' => '1',
                'message' => $isValidToken['message'],
                'data' => [],
            ];
        }

        return response()->json($result, 200);
    }


    private function getPolicyType($carrierProductId, $carrierId)
    {
        $result = '';


        //, CanaryAMS_Line_Of_Business_Code_c
        $query = "SELECT MAG_Majorline__c, CanaryAMS__Line_Of_Business_Code__c " .
            "FROM CanaryAMS__Carrier_Product__c " .
            "WHERE Id = '$carrierProductId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            $record = $data['records'][0];

            $result = 'personal_auto';

            // Policy Header, Account Details, Property, Coverage

            // condo
            if ($record['MAG_Majorline__c'] == 'Home' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'HOME') {
                $result = 'condo';
            }
            // renters
            if ($record['MAG_Majorline__c'] == 'Home' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'HOME') {
                $result = 'renters';
            }
            // homeowners
            if ($record['MAG_Majorline__c'] == 'Home' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'HOME') {
                $result = 'homeowners';
            }
            //home_dwelling
            if ($record['MAG_Majorline__c'] == 'Home' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'DFIRE') {
                $result = 'home_dwelling';
            }

            //Policy Header, Account Details, Policy Details

            // workers_compensation
            if ($record['MAG_Majorline__c'] == 'Commercial' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'WORK') {
                $result = 'workers_compensation';
            }
            // general_liability
            if ($record['MAG_Majorline__c'] == 'Commercial' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'CGL') {
                $result = 'general_liability';
            }

            // Policy Header, Account Details, Drivers, Vehicles, Coverage

            // commercial_auto
            if ($record['MAG_Majorline__c'] == 'Commercial' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'AUTOB') {
                $result = 'commercial_auto';
            }
            // personal_auto
            if ($record['MAG_Majorline__c'] == 'Auto Personal' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'AUTOP') {
                $result = 'personal_auto';
            }
            // motorcycle_atv
            if ($record['MAG_Majorline__c'] == 'Auto Personal' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'CYCL') {
                $result = 'motorcycle_atv';
            }
            // mexico_policies
            if ($record['MAG_Majorline__c'] == 'Auto Personal' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'MEX') {
                $result = 'mexico_policies';
            }

            // Policy Header, Account Details

            // dental_individual
            if ($record['MAG_Majorline__c'] == 'Health' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'HLTH') {
                $result = 'dental_individual';
            }
            // vision_individual
            if ($record['MAG_Majorline__c'] == 'Health' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'HLTH') {
                $result = 'vision_individual';
            }
            // life_individual
            if ($record['MAG_Majorline__c'] == 'Life' && $record['CanaryAMS__Line_Of_Business_Code__c'] == 'LIFE') {
                $result = 'life_individual';
            }
        }

        return $result;
    }


    /*
    private function getPolicyType($carrierProductId,$carrierId){
        $result = '';



        $query = "SELECT MAG_Majorline__c " .
            "FROM CanaryAMS__Carrier_Product__c " .
            "WHERE Id = '$carrierProductId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query( $query );

        if ( $data['totalSize'] != '0' ) {
            foreach ( $data['records'] as $record ) {
                // switch  default personal auto
                switch($record['MAG_Majorline__c']){
                    case 'Auto Personal':
                        $result = 'personal_auto';
                        break;
                    case 'Commercial':
                        $result = 'commercial_auto';
                        break;
                    case 'Health':
                        $result = 'general_liability';
                        break;
                    case 'Home':
                        $result = 'homeowners';
                        break;
                    case 'Life':
                        $result = 'life_individual';
                        break;
                    case 'Other':
                        $result = 'motorcycle_atv';
                        break;
                    case 'Supplements':
                        $result = 'workers_compensation';
                        // Roadside - Motor Club
                        // Carrier Name: Nation Safe Drivers
                        if ($carrierId == 'a024100000OdervAAB'){
                            $result = 'personal_auto';
                        }
                        break;
                    default:
                        $result = 'personal_auto';
                }
            }
        }

        return $result;
    }*/

    /**
     * @param $carrierId
     * @return mixed
     */
    private function getEmailCarrier($carrierId = null)
    {
        if (empty($carrierId)) {
            return '';
        }

        $result = '';

        $query = "SELECT Id, CanaryAMS__Email__c " .
            "FROM CanaryAMS__Carrier__c " .
            "WHERE Id = '$carrierId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result = $record['CanaryAMS__Email__c'];
            }
        }

        return $result;
    }

    private function getCarrierProductFromDirectBill($policyId = null)
    {
        $result = '';

        $query = "SELECT MAG_Membership_Name__c " .
            "FROM Direct_Bill_Memberships2__c " .
            "WHERE Policy__c = '$policyId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result = $record['MAG_Membership_Name__c'];
            }
        }

        return $result;
    }

    private function getRoadside($carrierProductId = null)
    {
        $result = [];
        $result['roadside_phone'] = '';
        $result['includes_roadside'] = false;

        $query = "SELECT MAG_Roadside_Phone_Number_AB__c, MAG_Includes_Roadside__c " .
            "FROM CanaryAMS__Carrier_Product__c " .
            "WHERE Id = '$carrierProductId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();

        try {
            $data = $crud->query($query);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            ddd(
                $e->getResponse()
                    ->getBody()
                    ->getContents()
            );
        }


        if ($data['totalSize'] != '0') {
            $result['roadside_phone'] = $this->formatPhone($data['records'][0]['MAG_Roadside_Phone_Number_AB__c']);
            $result['includes_roadside'] = $data['records'][0]['MAG_Includes_Roadside__c'];
        }

        return $result;
    }

    /**
     * @param $policyId
     * @param null $vehicleId
     * @return mixed
     */
    private function getCommercialVehicle($policyId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, CanaryAMS__Make__c, CanaryAMS__Model__c, CanaryAMS__Model_Year__c, CanaryAMS__Policy__c, CanaryAMS__VIN__c, CanaryAMS__isActive__c " .
            "FROM CanaryAMS__Commercial_Vehicle__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' " .
            "LIMIT 1";
        // , isActive

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $coverage = $this->getCoverageCommercialVehicle($policyId, $record['Id']);

                $isActive = false;
                if ($record['CanaryAMS__isActive__c'] == 1) {
                    $isActive = true;
                }
                $result[] = [
                    'name' => $record['Name'],
                    'make' => $record['CanaryAMS__Make__c'],
                    'model' => $record['CanaryAMS__Model__c'],
                    'model_year' => $record['CanaryAMS__Model_Year__c'],
                    'policy' => $record['CanaryAMS__Policy__c'],
                    'vin' => $record['CanaryAMS__VIN__c'],
                    'isActive' => $isActive,
                    'coverage' => $coverage,
                ];
            }
        }

        return $result;
    }


    /**
     * @param $policyNumber
     * @return mixed
     */
    private function getVehicles($policyId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, CanaryAMS__VIN_Number__c, CanaryAMS__Policy__c, " .
            "CanaryAMS__Model_Year__c, CanaryAMS__Model__c, CanaryAMS__Make__c " .
            "FROM CanaryAMS__Vehicle__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' " .
            "AND CanaryAMS__isActive__c = TRUE " .
            "LIMIT 25";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $coverage = $this->getCoverageVehicle($record['CanaryAMS__Policy__c'], $record['Id']);
                $result[] = [
                    'id' => $record['Id'],
                    'vehicle' => $record['Name'],
                    'vin' => $record['CanaryAMS__VIN_Number__c'],
                    'policy' => $record['CanaryAMS__Policy__c'],
                    'model_year' => $record['CanaryAMS__Model_Year__c'],
                    'model' => $record['CanaryAMS__Model__c'],
                    'make' => $record['CanaryAMS__Make__c'],
                    'coverage' => $coverage,
                ];
            }
        }

        return $result;
    }


    /**
     * @param $policyId
     * @param null $vehicleId
     * @return mixed
     */
    private function getProperty($policyId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, CanaryAMS__City__c, CanaryAMS__isActive__c, CanaryAMS__Policy__c, CanaryAMS__Property_Address_2__c, CanaryAMS__State__c, CanaryAMS__Zip_Code__c " .
            "FROM CanaryAMS__Property__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'city' => $record['CanaryAMS__City__c'],
                    'isActive' => $record['CanaryAMS__isActive__c'],
                    'policy' => $record['CanaryAMS__Policy__c'],
                    'property_address' => $record['CanaryAMS__Property_Address_2__c'],
                    'state' => $record['CanaryAMS__State__c'],
                    'zip_code' => $record['CanaryAMS__Zip_Code__c'],
                    'name' => $record['Name'],
                ];
            }
        }

        return $result;
    }


    /**
     * @param $policyId
     * @param null $vehicleId
     * @return mixed
     */
    private function getCoverage($policyId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, CanaryAMS__Limit_Format_Integer__c, CanaryAMS__Coverage_Code__c, CanaryAMS__Deductible_Format_Integer__c, CanaryAMS__Coverage_Limit_2__c " .
            "FROM CanaryAMS__Coverage__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' " .
            "LIMIT 25";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'name' => $record['Name'],
                    'limit' => $record['CanaryAMS__Limit_Format_Integer__c'],
                    'coverage_code' => $record['CanaryAMS__Coverage_Code__c'],
                    'deductible' => $record['CanaryAMS__Deductible_Format_Integer__c'],
                    'coverage_limit' => $record['CanaryAMS__Coverage_Limit_2__c'],
                ];
            }
        }

        return $result;
    }

    private function getCoverageVehicle($policyId = null, $vehicleId = null)
    {
        $result = [];

        $query = "SELECT Id, Name,
         CanaryAMS__Limit_Format_Integer__c,
          CanaryAMS__Coverage_Code__c,
           CanaryAMS__Deductible_Format_Integer__c,
            CanaryAMS__Coverage_Limit_2__c " .
            "FROM CanaryAMS__Coverage__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' and CanaryAMS__Vehicle__c = '$vehicleId' " .
            "LIMIT 25";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'coverage' => $record['Name'],
                    'limit' => $record['CanaryAMS__Limit_Format_Integer__c'],
                    'coverage_code' => $record['CanaryAMS__Coverage_Code__c'],
                    'deductible' => $record['CanaryAMS__Deductible_Format_Integer__c'],
                    'coverage_limit' => $record['CanaryAMS__Coverage_Limit_2__c'],
                ];
            }
        }

        return $result;
    }

    private function getCoverageCommercialVehicle($policyId = null, $commercialVehicleId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, CanaryAMS__Limit_Format_Integer__c, CanaryAMS__Coverage_Code__c, CanaryAMS__Deductible_Format_Integer__c, CanaryAMS__Coverage_Limit_2__c " .
            "FROM CanaryAMS__Coverage__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' and CanaryAMS__Commercial_Vehicle__c = '$commercialVehicleId' " .
            "LIMIT 25";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'coverage' => $record['Name'],
                    'limit' => $record['CanaryAMS__Limit_Format_Integer__c'],
                    'coverage_code' => $record['CanaryAMS__Coverage_Code__c'],
                    'deductible' => $record['CanaryAMS__Deductible_Format_Integer__c'],
                    'coverage_limit' => $record['CanaryAMS__Coverage_Limit_2__c'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param $policyId
     * @return mixed
     */
    private function getDrivers($policyId = null, $insured = false)
    {
        $result = [];
        $where = "";

        if ($insured) {
            $where = " AND CanaryAMS__Applicant__c = 'Applicant' ";
        }

        $query = "SELECT Id, Name, CanaryAMS__Account__c, CanaryAMS__Policy__c, " .
            "CanaryAMS__Relationship__c, CanaryAMS__Gender__c, CanaryAMS__Drivers_License_Number__c, " .
            "CanaryAMS__Date_of_Birth__c, CanaryAMS__Company_Driver_Number__c, CanaryAMS__Applicant__c, CanaryAMS__State__c " .
            "FROM CanaryAMS__Insured__c " .
            "WHERE CanaryAMS__Policy__c = '$policyId' " . $where .
            "LIMIT 10";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'id' => $record['Id'],
                    'name' => $record['Name'], //added for compatibility with getInsured
                    'driver' => $record['Name'],
                    'state' => $record['CanaryAMS__State__c'], //added for compatibility with getInsured
                    'account_number' => $record['CanaryAMS__Account__c'],
                    'policy_number' => $record['CanaryAMS__Policy__c'],
                    'relationship' => $record['CanaryAMS__Relationship__c'],
                    'gender' => $record['CanaryAMS__Gender__c'],
                    'drivers_license_number' => $record['CanaryAMS__Drivers_License_Number__c'],
                    'date_of_birth' => $record['CanaryAMS__Date_of_Birth__c'],
                    'driver_number' => $record['CanaryAMS__Company_Driver_Number__c'],
                    'applicant' => $record['CanaryAMS__Applicant__c'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param $insuredId
     * @return mixed
     */
    private function getInsured($id)
    {
        $query = "SELECT Name, CanaryAMS__State__c " .
            "FROM CanaryAMS__Insured__c " .
            "WHERE Id = '$id' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        $result = [];
        $result['name'] = '';
        $result['state'] = '';

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $d) {
                $result['name'] = $d['Name'];
                $result['state'] = $d['CanaryAMS__State__c'];
            }
        }

        return $result;
    }

    /**
     * @param $accountId
     * @return mixed
     */
    private function getContacts($accountId = null)
    {
        $result = [];

        $query = "SELECT Id, Name, AccountId,  Email, Phone, FirstName, LastName, MailingStreet, " .
            "MailingState, MailingPostalCode, MailingCity " .
            "FROM Contact " .
            "WHERE AccountId = '$accountId' " .
            "LIMIT 25";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result[] = [
                    'id' => $record['Id'],
                    'contact' => $record['Name'],
                    'account_number' => $record['AccountId'],
                    'email' => $record['Email'],
                    'phone' => $this->formatPhone($record['Phone']),
                    'first_name' => $record['FirstName'],
                    'last_name' => $record['LastName'],
                    'street' => $record['MailingStreet'],
                    'state' => $record['MailingState'],
                    'postal_code' => $record['MailingPostalCode'],
                    'city' => $record['MailingCity'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param $accountId
     * @return mixed
     */
    private function getAgent($agentId = null)
    {
        if (empty($agentId)) {
            return [];
        }

        $result = [];

        $query = "SELECT Id,Name, CanarySeed__CS_Account__c, CanaryAMS__Users_Profile__c, " .
            "CanaryAMS__Title__c, CanaryAMS__Phone__c, CanaryAMS__Office_Location_State__c, " .
            "CanaryAMS__Office_Location_Postal_Code__c, CanaryAMS__Office_Location_Phone__c, CanaryAMS__Office_Location_Fax__c, " .
            "CanaryAMS__Office_Location_City__c, CanaryAMS__Office_Location_Address__c, CanaryAMS__Location__c " .
            "FROM CanaryAMS__Producers__c " .
            "WHERE Id = '$agentId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);
        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $userData = $this->getUserData($record['CanaryAMS__Users_Profile__c']);
                $location = $this->getLocation($record['CanaryAMS__Location__c']);

                $result = [
                    'id' => $record['Id'],
                    'producer_csr' => $record['Name'],
                    'account' => $record['CanarySeed__CS_Account__c'],
                    'user' => $record['CanaryAMS__Users_Profile__c'],
                    'title' => $record['CanaryAMS__Title__c'],
                    'phone' => $this->formatPhone($record['CanaryAMS__Phone__c']),
                    'office_location_state' => $location['state'],
                    'office_location_postal_code' => $location['zip_code'],
                    'office_location_phone' => $this->formatPhone($record['CanaryAMS__Office_Location_Phone__c']),
                    'office_location_fax' => $this->formatPhone($record['CanaryAMS__Office_Location_Fax__c']),
                    'office_location_city' => $location['city'],
                    'office_location_address' => $location['address'],
                    'office' => $record['CanaryAMS__Location__c'],
                    'userData' => $userData,
                    'image' => $this->getAgentImageUrl($agentId),
                ];
            }
        }

        return $result;
    }

    /**
     * @param $userId
     * @return mixed
     */
    private function getUserData($userId = null)
    {
        if (empty($userId)) {
            return [];
        }

        $result = [];

        $query = "SELECT Id,Name, Alias, CompanyName, Email, FirstName, LastName, " .
            "FullPhotoUrl, MediumPhotoUrl, Phone, SmallPhotoUrl, Title " .
            "FROM User " .
            "WHERE Id = '$userId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $result = [
                    'id' => $record['Id'],
                    'full_name' => $record['Name'],
                    'alias' => $record['Alias'],
                    'company_name' => $record['CompanyName'],
                    'email' => $record['Email'],
                    'first_name' => $record['FirstName'],
                    'last_name' => $record['LastName'],
                    'full_photo_url' => $record['FullPhotoUrl'],
                    'medium_photo_url' => $record['MediumPhotoUrl'],
                    'phone' => $this->formatPhone($record['Phone']),
                    'small_photo_url' => $record['SmallPhotoUrl'],
                    'title' => $record['Title'],
                ];
            }
        }

        return $result;
    }

    private function getLocation($locationId = null)
    {
        $result = [
            'address' => '',
            'city' => '',
            'zip_code' => '',
            'state' => '',
        ];

        if (empty($locationId)) {
            return $result;
        }

        $query = "SELECT CanaryAMS__Address_2__c, CanaryAMS__City__c, CanaryAMS__State__c, CanaryAMS__Zip_Code__c " .
            "FROM CanaryAMS__Location__c " .
            "WHERE Id = '$locationId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            $result = [
                'address' => $data['records'][0]['CanaryAMS__Address_2__c'],
                'city' => $data['records'][0]['CanaryAMS__City__c'],
                'zip_code' => $data['records'][0]['CanaryAMS__Zip_Code__c'],
                'state' => $data['records'][0]['CanaryAMS__State__c'],
            ];
        }

        return $result;
    }

    /**
     * @param $userId
     * @return mixed
     */
    private function getAgentImageUrl($producerId = null)
    {
        if (empty($producerId)) {
            return '';
        }

        $url = '';
        $result = '';

        //Get SF data
        $query = "SELECT CanaryAMS__S3_File_Url__c " .
            "FROM CanaryAMS__Documents__c " .
            "WHERE CanaryAMS__Producer_CSR__c = '$producerId' " .
            "LIMIT 1";

        $crud = new \bjsmasth\Salesforce\CRUD();
        $data = $crud->query($query);

        if ($data['totalSize'] != '0') {
            foreach ($data['records'] as $record) {
                $url = $record['CanaryAMS__S3_File_Url__c'];
            }
        }

        if (!empty($url)) {
            $url_data = parse_url($url);
            $key = substr($url_data['path'], 1);

            //S3 create presigned URL
            $s3Client = new S3Client([

            ]);

            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => ' ',
                'Key' => $key,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+24 hours');

            // Get the actual presigned-url
            $result = (string)$request->getUri();
        }

        return $result;
    }


    public function roadside(Request $request)
    {
        /* Check if you have TOKEN. */
        if (!$request->header('authorization')) {
            return response()->json([
                'error' => '401',
                'message' => 'Unauthorized access',
                'data' => [],
            ], 401);
        }
        /* END: Check if you have TOKEN. */

        $token = $request->header('Authorization');
        $isValidToken = $this->isValidToken($token);

        if ($isValidToken['error'] == 0) {
            $this->connect();
            $accountNumber = $isValidToken['accountNumber'];

            $query = "select id, name, CanaryAMS__VIN_Number__c, CanaryAMS__Model_Year__c, CanaryAMS__Model__c, CanaryAMS__Make__c, CanaryAMS__Policy__r.CanaryAMS__Carrier_Product__c, CanaryAMS__Policy__r.CanaryAMS__Carrier_Product_Billing_Method__c, CanaryAMS__Policy__r.recordtype.name, CanaryAMS__Policy__r.CanaryAMS__Policy_Number__c, CanaryAMS__Policy__r.CanaryAMS__Effective_Date__c, CanaryAMS__Policy__r.CanaryAMS__Renewal_Date_2__c
from CanaryAMS__Vehicle__c
where canaryams__policy__r.CanaryAMS__Account__c = '$accountNumber'
and CanaryAMS__Policy__r.recordtype.name = 'Motor Club'
and CanaryAMS__Policy__r.CanaryAMS__Policy_Status__c in ('Active', 'Cancellation Pending', 'Renewal')";

            $crud = new \bjsmasth\Salesforce\CRUD();
            $data = $crud->query($query);
            $d = [];

            foreach ($data['records'] as $record) {
                //Roadside
                if ($record['CanaryAMS__Policy__r']['CanaryAMS__Carrier_Product_Billing_Method__c'] == "Direct Bill") {
                    $carrierProduct = $this->getCarrierProductFromDirectBill($record['Id']);
                } else {
                    $carrierProduct = $record['CanaryAMS__Policy__r']['CanaryAMS__Carrier_Product__c'];
                }
                $roadside = $this->getRoadside($carrierProduct);

                $d[] = [
                    'id' => $record['Id'],
                    'name' => $record['Name'],
                    'vin' => $record['CanaryAMS__VIN_Number__c'],
                    'year' => $record['CanaryAMS__Model_Year__c'],
                    'model' => $record['CanaryAMS__Model__c'],
                    'make' => $record['CanaryAMS__Make__c'],
                    'policy_effective_date' => $record['CanaryAMS__Policy__r']['CanaryAMS__Effective_Date__c'],
                    'policy_renewal_date' => $record['CanaryAMS__Policy__r']['CanaryAMS__Renewal_Date_2__c'],
                    'policy_number' => $record['CanaryAMS__Policy__r']['CanaryAMS__Policy_Number__c'],
                    'roadside_phone' => $roadside['roadside_phone'],
                ];
            }

            $result = [
                'error' => '0',
                'message' => 'Successful operation.',
                'data' => $d,
            ];
        } else {
            $result = [
                'error' => '1',
                'message' => $isValidToken['message'],
                'data' => [],
            ];
        }

        return response()->json($result, 200);
    }
}
