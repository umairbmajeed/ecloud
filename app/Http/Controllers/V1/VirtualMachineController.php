<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\V1\ApplianceServerLicenseNotFoundException;
use App\Exceptions\V1\TemplateNotFoundException;
use App\Rules\V1\IsValidUuid;
use Illuminate\Http\Request;
use UKFast\DB\Ditto\QueryTransformer;

use App\Models\V1\VirtualMachine;
use App\Resources\V1\VirtualMachineResource;

use App\Models\V1\Pod;
use App\Models\V1\Tag;

use App\Models\V1\Solution;
use App\Exceptions\V1\SolutionNotFoundException;

use App\Models\V1\SolutionNetwork;
use App\Models\V1\SolutionSite;

use App\Models\V1\Datastore;
use App\Exceptions\V1\DatastoreNotFoundException;
use App\Exceptions\V1\DatastoreInsufficientSpaceException;

use App\Kingpin\V1\KingpinService as Kingpin;
use App\Exceptions\V1\KingpinException;

use App\Services\IntapiService;
use App\Exceptions\V1\IntapiServiceException;

use UKFast\Api\Exceptions;
use App\Exceptions\V1\ServiceTimeoutException;
use App\Exceptions\V1\ServiceResponseException;
use App\Exceptions\V1\ServiceUnavailableException;
use App\Exceptions\V1\InsufficientResourceException;
use Log;

use Mustache_Engine;

class VirtualMachineController extends BaseController
{
    /**
     * List all VM's
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $virtualMachinesQuery = $this->getVirtualMachines();

        (new QueryTransformer($request))
            ->config(VirtualMachine::class)
            ->transform($virtualMachinesQuery);

        return $this->respondCollection(
            $request,
            $virtualMachinesQuery->paginate($this->perPage)
        );
    }

    /**
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     */
    public function show(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachines = $this->getVirtualMachines(null, [$vmId]);
        $virtualMachine = $virtualMachines->first();
        if (!$virtualMachine) {
            throw new Exceptions\NotFoundException("Virtual Machine '$vmId' Not Found");
        }

        return $this->respondItem(
            $request,
            $virtualMachine,
            200,
            VirtualMachineResource::class
        );
    }

    /**
     * @param Request $request
     * @param IntapiService $intapiService
     * @return \Illuminate\Http\Response
     * @throws Exceptions\BadRequestException
     * @throws Exceptions\ForbiddenException
     * @throws Exceptions\UnauthorisedException
     * @throws InsufficientResourceException
     * @throws IntapiServiceException
     * @throws ServiceResponseException
     * @throws ServiceUnavailableException
     * @throws SolutionNotFoundException
     * @throws \App\Exceptions\V1\TemplateNotFoundException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResourceException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResponseException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidRouteException
     * @throws \App\Exceptions\V1\ApplianceNotFoundException
     */
    public function create(Request $request, IntapiService $intapiService)
    {
        // todo remove when public/burst VMs supported
        // - template validation issue on public
        // - need `add_billing` step on create_vm automation
        if (in_array($request->input('environment'), ['Public', 'Burst'])) {
            throw new Exceptions\ForbiddenException(
                $request->input('environment') . ' VM creation is temporarily disabled'
            );
        }

        // default validation
        $rules = [
            'environment' => ['required', 'in:Public,Hybrid,Private,Burst'],
             // User must either specify a vm template or an appliance_id
            'template' => ['required_without:appliance_id'],
            'appliance_id' => ['required_without:template', new IsValidUuid()],

            'cpu' => ['required', 'integer'],
            'ram' => ['required', 'integer'],
            'hdd' => ['required_without:hdd_disks', 'integer'],
            'hdd_disks' => ['required_without:hdd', 'array'],

            'datastore_id' => ['nullable', 'integer'],
            'network_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],

            'tags' => ['nullable', 'array'],

            'name' => ['nullable', 'regex:/' . VirtualMachine::NAME_FORMAT_REGEX . '/'],

            'ssh_keys' => ['nullable', 'array']
        ];

        // Check we either have template or appliance_id but not both
        if (!($request->has('template') xor $request->has('appliance_id'))) {
            throw new Exceptions\BadRequestException(
                'Virtual machines must be launched with either the appliance_id or template parameter'
            );
        }

        if ($request->input('environment') == 'Public') {
            $rules['hdd_iops'] = ['nullable', 'integer'];
            // todo public iops
        } else {
            $rules['solution_id'] = ['required', 'integer', 'min:1'];
        }

        if ($request->has('tags')) {
            $rules['tags.*.key'] = [
                'required', 'regex:/' . Tag::KEY_FORMAT_REGEX . '/'
            ];
            $rules['tags.*.value'] = [
                'required', 'string'
            ];
        }

        if ($request->input('monitoring') === true) {
            $rules['monitoring-contacts'] = ['required', 'array'];
            $rules['monitoring-contacts.*'] = ['integer'];
        }

        $this->validate($request, $rules);

        // environment specific validation
        $minCpu = VirtualMachine::MIN_CPU;
        $maxCpu = VirtualMachine::MAX_CPU;
        $minRam = VirtualMachine::MIN_RAM;
        $maxRam = VirtualMachine::MAX_RAM;
        $minHdd = VirtualMachine::MIN_HDD;
        $maxHdd = VirtualMachine::MAX_HDD;

        if ($request->input('environment') == 'Public') {
            $solution = null;
            $pod = Pod::find(14);
        } else {
            $solution = SolutionController::getSolutionById($request, $request->input('solution_id'));
            $pod = $solution->pod;

            if ($request->input('environment') != 'Burst') {
                // get available compute
                $maxRam = min($maxRam, $solution->ramAvailable());
                if ($maxRam < 1) {
                    throw new InsufficientResourceException($intapiService->getFriendlyError(
                        'host has insufficient ram, ' . $maxRam . 'GB remaining'
                    ));
                }

                // get available storage
                if ($request->has('datastore_id')) {
                    $datastore = Datastore::find($request->input('datastore_id'));
                } else {
                    $datastore = Datastore::getDefault($solution->getKey(), $request->input('environment'));
                }

                $maxHdd = min(
                    $datastore->usage->available,
                    VirtualMachine::MAX_HDD
                );

                if ($maxHdd < 1) {
                    throw new InsufficientResourceException($intapiService->getFriendlyError(
                        'datastore has insufficient space, ' . $maxHdd . 'GB remaining'
                    ));
                }
            }

            if ($solution->isMultiSite()) {
                $rules['site_id'] = ['required', 'integer'];
            }

            if ($solution->isMultiNetwork()) {
                $rules['network_id'] = ['required', 'integer'];

                if (!$solution->hasMultipleNetworks()) {
                    unset($rules['network_id']);

                    $defaultNetwork = SolutionNetwork::withSolution($solution->getKey())->first();
                    $request->request->add(['network_id' => $defaultNetwork->getKey()]);
                }
            }
        }


        $rules['cpu'] = array_merge($rules['cpu'], [
            'min:' . $minCpu, 'max:' . $maxCpu
        ]);

        $rules['ram'] = array_merge($rules['ram'], [
            'min:' . $minRam, 'max:' . $maxRam
        ]);

        // single disk vm requested
        if ($request->has('hdd')) {
            $rules['hdd'] = array_merge($rules['hdd'], [
                'min:' . $minHdd, 'max:' . $maxHdd
            ]);
        }

        // multi-disk vm requested
        if ($request->has('hdd_disks')) {
            // validate disk names
            $rules['hdd_disks.*.name'] = [
                'required', 'regex:/' . VirtualMachine::HDD_NAME_FORMAT_REGEX . '/'
            ];

            // todo check numbers are sequential?


            // validate disk capacity
            $rules['hdd_disks.*.capacity'] = [
                'required', 'integer', 'min:' . $minHdd, 'max:' . $maxHdd
            ];

            $capacityRequested = array_sum(array_column($request->input('hdd_disks'), 'capacity'));
            if ($capacityRequested > $datastore->usage->available) {
                throw new InsufficientResourceException($intapiService->getFriendlyError(
                    'datastore has insufficient space, ' . $datastore->usage->available . 'GB remaining'
                ));
            }
        }

        $this->validate($request, $rules);

        /**
         * Launch VM from Appliance
         */
        if ($request->has('appliance_id')) {
            $scriptRules = [];

            //Validate the appliance exists
            $appliance = ApplianceController::getApplianceById($request, $request->input('appliance_id'));

            $applianceVersion = $appliance->getLatestVersion();

            // Load the VM template from the appliance version specification
            if (empty($applianceVersion->vm_template)) {
                throw new TemplateNotFoundException('Invalid Virtual Machine Template for Appliance');
            }
            $templateName = $applianceVersion->getTemplateName();

            // Sort the Appliance params from the Request (user input) into key => value and add back
            // onto our Request for easy validation
            $requestApplianceParams = [];
            foreach ($request->parameters as $requestParam) {
                $requestApplianceParams[trim($requestParam['key'])] = $requestParam['value'];
                //Add prefixed param to request (to avoid conflicts)
                $request['appliance_param_'.trim($requestParam['key'])] = $requestParam['value'];
            }

            // Get the script parameters that we need from the latest version of teh appliance
            $parameters = $applianceVersion->getParameters();

            // For each of the script parameters build some validation rules
            foreach ($parameters as $parameterKey => $parameter) {
                $key = 'appliance_param_' . $parameterKey;
                $scriptRules[$key][] = ($parameter->required == 'Yes') ? 'required' : 'nullable';
                //validation rules regex
                if (!empty($parameters[$parameterKey]->validation_rule)) {
                    $scriptRules[$key][] = 'regex:' . $parameters[$parameterKey]->validation_rule;
                }

                // For data types String,Numeric,Boolean we can use Laravel validation
                $scriptRules[$key][] = strtolower($parameters[$parameterKey]->type);
            }

            $this->validate($request, $scriptRules);

            // Attempt to build the script
            $Mustache_Engine = new Mustache_Engine;

            $mustacheTemplate = $Mustache_Engine->loadTemplate($applianceVersion->script_template);

            $applianceScript = $mustacheTemplate->render($requestApplianceParams);

            // Try to load the server license associated with th appliance version
            try {
                $serverLicense = $applianceVersion->getLicense();
            } catch (ApplianceServerLicenseNotFoundException $exception) {
                if ($this->isAdmin) {
                    throw new ApplianceServerLicenseNotFoundException(
                        $exception->getMessage()
                    );
                }

                Log::critical(
                    "Unable to launch VM using Appliance '" . $appliance->getKey() . "'': Appliance version '"
                    . $applianceVersion->getKey() . "' has no server license."
                );
                throw new ServiceUnavailableException(
                    "Unable to launch Appliance '" . $appliance->getKey() . "' at this time."
                );
            }

            $platform = $serverLicense->server_license_category;
            $license = $serverLicense->server_license_name;
        }

        if ($request->has('template')) {
            $templateName = $request->input('template');
            // check template is valid
            $template = TemplateController::getTemplateByName(
                $templateName,
                $pod,
                $solution
            );

            $platform = $template->platform;
            $license = $template->license;
        }
        
        if ($request->has('computername')) {
            if ($platform == 'Linux') {
                $rules['computername'] = [
                    'regex:/' . VirtualMachine::HOSTNAME_FORMAT_REGEX . '/'
                ];
            } elseif ($platform == 'Windows') {
                $rules['computername'] = [
                    'regex:/' . VirtualMachine::NETBIOS_FORMAT_REGEX . '/'
                ];
            }
        }

        $this->validate($request, $rules);

        //If admin reseller scope is 0, we won't know the reseller id for Public VM's
        if (empty($solution) && empty($request->user->resellerId)) {
            if ($request->user->isAdmin) {
                throw new Exceptions\BadRequestException('Missing Reseller scope');
            }
            throw new Exceptions\UnauthorisedException('Unable to determine reseller id');
        }

        $post_data = array(
            'reseller_id' => !empty($solution) ? $solution->ucs_reseller_reseller_id : $request->user->resellerId,
            'ecloud_type' => $request->input('environment'),
            'ucs_reseller_id' => $request->input('solution_id'),
            'server_active' => true,

            'name' => $request->input('name'),
            'netbios' => $request->input('computername'),

            'submitted_by_type' => 'API Client',
            'submitted_by_id' => $request->user->applicationId,
            'launched_by' => '-5',
        );

        if ($request->has('ssh_keys')) {
            if ($platform != 'Linux') {
                throw new Exceptions\BadRequestException("ssh_keys only supported for Linux VM's at this time");
            }
            $post_data['ssh_keys'] = $request->input('ssh_keys');
        }

        // set template
        $post_data['platform'] = $platform;
        $post_data['license'] = $license;

        if ($request->has('appliance_id')) {
            $post_data['template'] = $templateName;
            $post_data['template_type'] = 'system';
        }

        if ($request->has('template')) {
            if ($template->type != 'Base') {
                $post_data['template'] = $templateName;

                if ($template->type != 'Solution') {
                    $post_data['template_type'] = 'system';
                }
            }
        }

        if ($request->has('template_password')) {
            $post_data['template_password'] = $request->input('template_password');
        }

        // set compute
        $post_data['cpus'] = $request->input('cpu');
        $post_data['ram_gb'] = $request->input('ram');


        // set storage
        if ($request->has('hdd')) {
            $post_data['hdd_gb'] = $request->input('hdd');
        } elseif ($request->has('hdd_disks')) {
            $post_data['hdd_gb'] = [];

            foreach ($request->input('hdd_disks') as $disk) {
                $post_data['hdd_gb'][$disk['name']] = $disk['capacity'];
            }
        }

        if ($request->has('datastore_id')) {
            $post_data['reseller_lun_id'] = $request->input('datastore_id');
        }


        // todo check template disks not larger than request

        // set networking
        if ($request->has('network_id')) {
            $network = SolutionNetwork::withSolution($request->input('solution_id'))
                ->find($request->input('network_id'));

            if (is_null($network)) {
                throw new Exceptions\BadRequestException(
                    "A network matching the requested ID was not found",
                    'network_id'
                );
            }

            $post_data['internal_vlan'] = $network->vlan_number;
        }

        if ($request->has('external_ip_required')) {
            $post_data['external_ip_required'] = $request->input('external_ip_required');
        }

        // set nameservers
        if ($request->has('nameservers')) {
            $post_data['nameservers'] = $request->input('nameservers');
        }

        if ($request->has('site_id')) {
            $site = SolutionSite::withSolution($request->input('solution_id'))
                ->find($request->input('site_id'));

            if (is_null($site)) {
                throw new Exceptions\BadRequestException(
                    "A site matching the requested ID was not found",
                    'site_id'
                );
            }

            $post_data['ucs_site_id'] = $site->getKey();
        }


        // set support
        if ($request->input('support') === true) {
            $post_data['advanced_support'] = true;
        }

        if ($request->input('monitoring') === true) {
            $post_data['monitoring_enabled'] = true;
            $post_data['monitoring_contacts'] = $request->input('monitoring_contacts');
        }

        //set tags
        if ($request->has('tags')) {
            $post_data['tags'] = $request->input('tags');
        }

        // Do we have an appliance script?
        if (!empty($applianceScript)) {
            $post_data['bootstrap_script'] = json_encode($applianceScript);
            $post_data['is_appliance'] = true;
        }

        // todo remove debugging when ready to retest
//        print_r($post_data);
//        exit;

        // schedule automation
        try {
            $intapiService->request('/automation/create_ucs_vmware_vm', [
                'form_params' => $post_data,
                'headers' => [
                    'Accept' => 'application/xml',
                ]
            ]);

            $intapiData = $intapiService->getResponseData();
        } catch (\Exception $exception) {
            throw new ServiceUnavailableException('Failed to create new virtual machine', null, 502);
        }

        if (!$intapiData->result) {
            $error_msg = $intapiService->getFriendlyError(
                end($intapiData->errorset)
            );

            throw new ServiceResponseException($error_msg);
        }

        $virtualMachine = new VirtualMachine();
        $virtualMachine->servers_id = $intapiData->data->server_id;
        $virtualMachine->servers_status = $intapiData->data->server_status;

        $headers = [];
        if ($request->user->isAdmin) {
            $headers = [
                'X-AutomationRequestId' => $intapiData->data->automation_request_id
            ];
        }

        return $this->respondSave($request, $virtualMachine, 202, null, $headers);
    }

    /**
     * @param Request $request
     * @param IntapiService $intapiService
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\ForbiddenException
     * @throws ServiceUnavailableException
     */
    public function destroy(Request $request, IntapiService $intapiService, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachines($request->user->resellerId)->find($vmId);

        //cant delete vm if its doing something that requires it to exist
        if (!$virtualMachine->canBeDeleted()) {
            throw new Exceptions\ForbiddenException(
                'VM cannot be deleted with status of: ' . $virtualMachine->servers_status
            );
        }

        //server is in contract
        if (!$request->user->isAdmin && $virtualMachine->inContract()) {
            throw new Exceptions\ForbiddenException(
                'VM cannot be deleted, in contract until ' .
                date('d/m/Y', strtotime($virtualMachine->servers_contract_end_date))
            );
        }

        //server is a managed device
        if (!$request->user->isAdmin && $virtualMachine->isManaged()) {
            throw new Exceptions\ForbiddenException(
                'VM cannot be deleted, device is managed by UKFast'
            );
        }

        //schedule automation
        try {
            $automationRequestId = $intapiService->automationRequest(
                'delete_vm',
                'server',
                $virtualMachine->getKey(),
                [],
                'ecloud_ucs_' . $virtualMachine->pod->getKey(),
                $request->user->applicationId
            );
        } catch (IntapiServiceException $exception) {
            throw new ServiceUnavailableException('Unable to schedule deletion request');
        }

        $virtualMachine->servers_status = 'Pending Deletion';
        if (!$virtualMachine->save()) {
            //Log::critical('');
        }

        $headers = [];
        if ($request->user->isAdmin) {
            $headers = [
                'X-AutomationRequestId' => $automationRequestId
            ];
        }

        return $this->respondEmpty(202, $headers);
    }


    /**
     * Clone a VM
     * @param Request $request
     * @param IntapiService $intapiService
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws DatastoreInsufficientSpaceException
     * @throws DatastoreNotFoundException
     * @throws Exceptions\ForbiddenException
     * @throws Exceptions\NotFoundException
     * @throws ServiceUnavailableException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResourceException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResponseException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidRouteException
     */
    public function clone(Request $request, IntapiService $intapiService, $vmId)
    {
        //Validation
        $rules = [
            'name' => ['nullable', 'regex:/' . VirtualMachine::NAME_FORMAT_REGEX . '/']
        ];

        $this->validateVirtualMachineId($request, $vmId);
        $this->validate($request, $rules);

        //Load the vm to clone
        $virtualMachine = $this->getVirtualMachine($vmId);

        // VM cloning isn't available to Public/Burst VMs
        if (in_array($virtualMachine->type(), ['Public', 'Burst'])) {
            throw new Exceptions\ForbiddenException(
                $virtualMachine->type() . ' VM cloning is currently disabled'
            );
        }

        //Load the default datastore and check there's enough space
        //For Hybrid the default is the available datastore with the most free space
        $datastore = Datastore::getDefault($virtualMachine->servers_ecloud_ucs_reseller_id, $virtualMachine->type());

        if (!$datastore instanceof Datastore) {
            throw new DatastoreNotFoundException('Unable to load datastore');
        }

        if ($datastore->usage->available < $virtualMachine->servers_hdd) {
            $message = 'Insufficient free space on selected datastore.' .
                ' Request required ' . $virtualMachine->servers_hdd . 'GB, datastore has '
                . $datastore->usage->available . 'GB remaining';
            throw new DatastoreInsufficientSpaceException($message);
        }

        //OK, start the clone process ==

        //create new server record
        $postData['reseller_id'] = $virtualMachine->servers_reseller_id;
        $postData['reseller_lun_id'] = $datastore->getKey();
        $postData['ucs_reseller_id'] = $virtualMachine->servers_ecloud_ucs_reseller_id;
        $postData['launched_by'] = '-5';
        $postData['server_id'] = $virtualMachine->getKey();
        $postData['datastore'] = $datastore->reseller_lun_name;
        $postData['name'] = $request->input('name');
        $postData['server_active'] = true;

        try {
            $clonedVirtualMacineId = $intapiService->cloneVM($postData);
        } catch (IntapiServiceException $exception) {
            throw new ServiceUnavailableException('Currently unable to clone virtual machines');
        }

        if (empty($clonedVirtualMacineId)) {
            throw new ServiceUnavailableException('Failed to prepare virtual machine for cloning');
        }

        //Load the cloned virtual machine
        try {
            $clonedVirtualMacine = $this->getVirtualMachine($clonedVirtualMacineId);
        } catch (Exceptions\NotFoundException $exception) {
            throw new ServiceUnavailableException('Cloned virtual machine failed to initialise');
        }

        $responseData = $intapiService->getResponseData();
        $automationRequestId = $responseData->data->automation_request_id;

        // Respond with the new machine id
        $headers = [];
        if ($request->user->isAdmin) {
            $headers = ['X-AutomationRequestId' => $automationRequestId];
        }

        $respondSave = $this->respondSave(
            $request,
            $clonedVirtualMacine,
            202,
            null,
            $headers,
            [],
            '/' . $request->segment(1) . '/vms/{vmId}'
        );

        $originalLocation = $respondSave->original['meta']['location'];

        //Set the meta location to point to the new clone instead of the current resource
        $respondSave->original['meta']['location'] = substr($originalLocation, 0, strrpos($originalLocation, '/') + 1)
            . $clonedVirtualMacineId;

        $respondSave->setContent($respondSave->original);

        return $respondSave;
    }


    /**
     * Update virtual machine
     * @param Request $request
     * @param IntapiService $intapiService
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\BadRequestException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\ForbiddenException
     * @throws Exceptions\NotFoundException
     * @throws ServiceUnavailableException
     */
    public function update(Request $request, IntapiService $intapiService, $vmId)
    {
        /**
         * This endpoint should be using HTTP PATCH, log if we detect any PUT requests.
         */
        if ($request->method() == 'PUT') {
            Log::notice('Call to update VM endpoint using PUT detected. Request should be using PATCH');
        }

        $rules = [
            'name' => ['nullable', 'regex:/' . VirtualMachine::NAME_FORMAT_REGEX . '/'],
            'cpu' => ['nullable', 'integer'],
            'ram' => ['nullable', 'integer'],
            'hdd_disks' => ['nullable', 'array'],
        ];

        $this->validateVirtualMachineId($request, $vmId);

        //Load the VM
        $virtualMachine = $this->getVirtualMachine($vmId);

        if ($virtualMachine->isManaged()) {
            throw new Exceptions\ForbiddenException('Access to modify UKFast managed devices is restricted');
        }

        // todo remove when public/burst VMs supported, missing billing step on automation
        if (($virtualMachine->type() == 'Public' && !$this->isAdmin) || $virtualMachine->type() == 'Burst') {
            throw new Exceptions\ForbiddenException(
                $virtualMachine->type() . ' VM updates are temporarily disabled'
            );
        }

        $this->validate($request, $rules);

        //Define the min/max default sizes
        $minCpu = VirtualMachine::MIN_CPU;
        $maxCpu = VirtualMachine::MAX_CPU;
        $minRam = VirtualMachine::MIN_RAM;
        $maxRam = VirtualMachine::MAX_RAM;
        $minHdd = VirtualMachine::MIN_HDD;
        $maxHdd = VirtualMachine::MAX_HDD;

        switch ($virtualMachine->type()) {
            case 'Hybrid':
            case 'Private':
                $maxRam = intval($virtualMachine->servers_memory)
                    + min(VirtualMachine::MAX_RAM, $virtualMachine->solution->ramAvailable());

                $datastore = Datastore::getDefault($virtualMachine->solution->getKey(), $virtualMachine->type());

                $maxHdd = $datastore->usage->available;
                //TODO: Is this still right? should this be VirtualMachine::MIN_HDD
//                $minHdd = $virtualMachine->servers_hdd;
                break;

            case 'Public':
                if ($virtualMachine->isContract()) {
                    //Determine contract specific limits
                    $contractCpuTrigger = $virtualMachine->trigger('ecloud_cpu');
                    $contractRamTrigger = $virtualMachine->trigger('ecloud_ram');
                    $contractHddTrigger = $virtualMachine->trigger('ecloud_hdd');

                    $minCpu = $this->extractTriggerNumeric($contractCpuTrigger);
                    $minRam = $this->extractTriggerNumeric($contractRamTrigger);
                    $minHdd = $this->extractTriggerNumeric($contractHddTrigger);
                }
                break;

            case 'Burst':
            default:
        }

        $automationData = [];

        // Name
        // We can change the server name in realtime but Compute and Storage changes via automation.
        // If we are making other changes return 202 otherwise return 200
        if ($request->has('name')) {
            $virtualMachine->servers_friendly_name = $request->input('name');
            if (!$virtualMachine->save()) {
                throw new Exceptions\DatabaseException('Failed to update virtual machine: name');
            }
        }


        // CPU
        if ($request->has('cpu')) {
            if ($request->input('cpu') < $minCpu) {
                throw new Exceptions\ForbiddenException('cpu value must be ' . $minCpu . ' or larger');
            }

            if ($request->input('cpu') > $maxCpu) {
                throw new Exceptions\ForbiddenException('cpu value must be ' . $maxCpu . ' or smaller');
            }
        }
        $automationData['cpu'] = $request->input('cpu', $virtualMachine->servers_cpu);

        // RAM
        if ($request->has('ram')) {
            if ($request->input('ram') < $minRam) {
                throw new Exceptions\ForbiddenException('ram value must be ' . $minRam . ' or larger');
            }

            if ($request->input('ram') > $maxRam) {
                throw new Exceptions\ForbiddenException('ram value must be ' . $maxRam . ' or smaller');
            }
        }
        $automationData['ram'] = $request->input('ram', $virtualMachine->servers_memory);

        // Get the VM's active disks from vmware
        $disks = $virtualMachine->getActiveHDDs();
        $existingDisks = [];
        if ($disks !== false) {
            foreach ($disks as $disk) {
                $existingDisks[$disk->uuid] = $disk;
            }
        }

        // HDD
        $automationData['hdd'] = [];
        $totalCapacity = 0;

        if ($request->has('hdd_disks')) {
            $newDisksCount = 0;
            foreach ($request->input('hdd_disks') as $hdd) {
                $hdd = (object) $hdd;

                $isExistingDisk = false;
                if (isset($hdd->uuid)) {
                    // existing disks
                    $isExistingDisk = array_key_exists($hdd->uuid, $existingDisks);
                    if (!$isExistingDisk) {
                        throw new Exceptions\BadRequestException("HDD with UUID '" . $hdd->uuid . "' was not found");
                    }
                }

                if ($isExistingDisk) {
                    $hdd->name = $existingDisks[$hdd->uuid]->name;

                    //Add disks marked as deleted (state = 'absent') to automation data
                    if (isset($hdd->state) && $hdd->state == 'absent') {
                        if ($hdd->name == 'Hard disk 1' || ($hdd->uuid == $disks[0]->uuid)) {
                            // Don't allow deletion of the primary hard disk
                            $message = 'Primary hard disk (Hard disk 1) can not be deleted';
                            throw new Exceptions\ForbiddenException($message);
                        }

                        // Don't allow deletion of Hard disk 2 on VM's with legacy LVM
                        if ($hdd->name == 'Hard disk 2' && $virtualMachine->hasLegacyLVM()) {
                            $message = 'Unable to delete Hard Disk 2 on VMs with legacy LVM';
                            throw new Exceptions\ForbiddenException($message);
                        }

                        $hdd->capacity = 'deleted';
                        $automationData['hdd'][$hdd->name] = $hdd;
                        continue;
                    }

                    //Non-deleted disks
                    if (!is_numeric($hdd->capacity)) {
                        throw new Exceptions\BadRequestException("Invalid capacity for HDD '" . $hdd->uuid . "'");
                    }

                    if ($hdd->capacity < $existingDisks[$hdd->uuid]->capacity) {
                        $message = 'We are currently unable to shrink HDD capacity, ';
                        $message .= "HDD '" . $hdd->uuid . "' value must be larger than";
                        $message .= $existingDisks[$hdd->uuid]->capacity . "GB";
                        throw new Exceptions\ForbiddenException($message);
                    }

                    // Prevent expand of Hard disk 1 for VM's with legacy LVM
                    if ($virtualMachine->hasLegacyLVM()
                        && $hdd->name == 'Hard disk 1'
                        && $hdd->capacity > $existingDisks[$hdd->uuid]->capacity) {
                        $message = 'Unable to expand Hard Disk 1 on VMs with legacy LVM';
                        throw new Exceptions\ForbiddenException($message);
                    }

                    //disk isn't changed
                    if ($hdd->capacity == $existingDisks[$hdd->uuid]->capacity) {
                        $totalCapacity += $hdd->capacity;
                        $hdd->state = 'present'; // For when we update the automation
                        $automationData['hdd'][$hdd->name] = $hdd;
                        continue;
                    }
                }

                // New disks must be prefixed with 'New '
                if (!$isExistingDisk) {
                    // For now, we still need hdd in the automation data  to be prefixed with 'New '
                    // The number does not indicate future designation for the disk & is just used
                    // for logging in the automation process.
                    $hdd->name = 'New disk ' . ++$newDisksCount;
                }

                if ($hdd->capacity < $minHdd) {
                    throw new Exceptions\ForbiddenException(
                        "HDD '" . $hdd->uuid . "' value must be {$minHdd}GB or larger"
                    );
                }

                if ($hdd->capacity > $maxHdd) {
                    throw new Exceptions\ForbiddenException(
                        "HDD '" . $hdd->uuid . "' value must be {$maxHdd}GB or smaller"
                    );
                }

                $totalCapacity += $hdd->capacity;

                $hdd->state = 'present'; // For when we update the automation
                $automationData['hdd'][$hdd->name] = $hdd;
            }
        }

        $unchangedDisks = $existingDisks;
        if (!empty($automationData['hdd'])) {
            // Add any unspecified disks to our automation data as we want to send the complete required Storage state
            $unchangedDisks = array_diff_key($existingDisks, array_flip(array_column($automationData['hdd'], 'uuid')));
        }

        foreach ($unchangedDisks as $disk) {
            $diskData = new \stdClass();
            $diskData->name = $disk->name;
            $diskData->capacity = $disk->capacity;
            $diskData->uuid = $disk->uuid;
            $diskData->state = 'present';
            $automationData['hdd'][$disk->name] = $diskData;
            $totalCapacity += $diskData->capacity;
        }

        if ($totalCapacity < $virtualMachine->servers_hdd) {
            throw new Exceptions\ForbiddenException(
                'HDD capacity for virtual machine must be '
                . $virtualMachine->servers_hdd . "GB or greater (proposed:{$totalCapacity}GB)"
            );
        }
        // Fire off automation request
        try {
            $intapiService->automationRequest(
                'resize_vm',
                'server',
                $virtualMachine->getKey(),
                $automationData,
                !empty($virtualMachine->solution) ? 'ecloud_ucs_' . $virtualMachine->solution->pod->getKey() : null,
                $request->user->applicationId
            );
        } catch (IntapiServiceException $exception) {
            throw new ServiceUnavailableException('Unable to schedule virtual machine changes');
        }

        return $this->respondEmpty(202);
    }


    /**
     * Extract the numeric value from a trigger description
     * @param $trigger
     * @return int
     */
    protected function extractTriggerNumeric($trigger)
    {
        $noLabel = str_replace(
            'eCloud VM #' . $trigger->trigger_reference_id,
            '',
            $trigger->trigger_description
        );

        $noPg = preg_replace("/(- PG[0-9]*)/", "", $noLabel);

        $numeric = intval(preg_replace("/[^0-9,.]/", "", $noPg));

        return intval($numeric);
    }

    /**
     * Hard Power-on or Resume a virtual machine
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     */
    public function powerOn(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);

        $result = $this->powerOnVirtualMachine($virtualMachine);
        if (!$result) {
            throw new KingpinException('Failed to power on virtual machine');
        }

        return $this->respondEmpty();
    }

    /**
     * Hard Power-off a virtual machine
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     */
    public function powerOff(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);

        $result = $this->powerOffVirtualMachine($virtualMachine);
        if (!$result) {
            throw new KingpinException('Failed to power off virtual machine');
        }

        return $this->respondEmpty();
    }

    /**
     * Gracefully shut down a virtual machine
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     * @throws ServiceTimeoutException
     */
    public function shutdown(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);

        $result = $this->shutDownVirtualMachine($virtualMachine);
        if (!$result) {
            throw new KingpinException('Failed to shut down virtual machine');
        }

        return $this->respondEmpty();
    }

    /**
     * Restart the virtual machine.
     * Gracefully shutdown from guest, then power on again.
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     * @throws ServiceTimeoutException
     */
    public function restart(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);
        //Shut down
        $shutDownResult = $this->shutDownVirtualMachine($virtualMachine);
        if (!$shutDownResult) {
            throw new KingpinException('Failed to power down virtual machine');
        }
        //Power up
        $powerOnResult = $this->powerOnVirtualMachine($virtualMachine);
        if (!$powerOnResult) {
            throw new KingpinException('Failed to power on virtual machine');
        }

        return $this->respondEmpty();
    }

    /**
     * Reset the virtual machine.
     * Hard power-off, then power on again.
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     */
    public function reset(Request $request, $vmId)
    {
        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);

        //Hard power-off
        $powerOffResult = $this->powerOffVirtualMachine($virtualMachine);
        if (!$powerOffResult) {
            throw new KingpinException('Failed to power off virtual machine');
        }
        //Power up
        $powerOnResult = $this->powerOnVirtualMachine($virtualMachine);
        if (!$powerOnResult) {
            throw new KingpinException('Failed to power on virtual machine');
        }

        return $this->respondEmpty();
    }

    /**
     * Suspend virtual machine (Admin Only)
     * Customers don't need to suspend and resume, it eats resources on the datastore(dumps memory onto disk)
     * @param Request $request
     * @param $vmId
     * @return \Illuminate\Http\Response
     * @throws Exceptions\NotFoundException
     * @throws KingpinException
     * @throws Exceptions\ForbiddenException
     */
    public function suspend(Request $request, $vmId)
    {
        if (!$this->isAdmin) {
            throw new Exceptions\ForbiddenException();
        }

        $this->validateVirtualMachineId($request, $vmId);
        $virtualMachine = $this->getVirtualMachine($vmId);

        $result = $this->suspendVirtualMachine($virtualMachine);
        if (!$result) {
            $errorMessage = 'Failed to suspend virtual machine';
            throw new KingpinException($errorMessage);
        }

        return $this->respondEmpty();
    }

    /**
     * @param VirtualMachine $virtualMachine
     * @return bool
     * @throws KingpinException
     */
    protected function suspendVirtualMachine(VirtualMachine $virtualMachine)
    {
        $kingpin = $this->loadKingpinService($virtualMachine);

        $powerOnResult = $kingpin->powerSuspend(
            $virtualMachine->getKey(),
            $virtualMachine->solutionId()
        );

        if (!$powerOnResult) {
            return false;
        }

        return true;
    }

    /**
     * Power on a virtual machine
     * @param VirtualMachine $virtualMachine
     * @return bool
     * @throws KingpinException
     */
    protected function powerOnVirtualMachine(VirtualMachine $virtualMachine)
    {
        $kingpin = $this->loadKingpinService($virtualMachine);

        $powerOnResult = $kingpin->powerOnVirtualMachine(
            $virtualMachine->getKey(),
            $virtualMachine->solutionId()
        );

        if (!$powerOnResult) {
            return false;
        }

        return true;
    }

    /**
     * Power off a virtual machine
     * @param VirtualMachine $virtualMachine
     * @return bool
     * @throws KingpinException
     */
    protected function powerOffVirtualMachine(VirtualMachine $virtualMachine)
    {
        $kingpin = $this->loadKingpinService($virtualMachine);

        $powerOffResult = $kingpin->powerOffVirtualMachine(
            $virtualMachine->getKey(),
            $virtualMachine->solutionId()
        );

        if (!$powerOffResult) {
            return false;
        }

        return true;
    }

    /**
     * @param VirtualMachine $virtualMachine
     * @return bool
     * @throws KingpinException
     * @throws ServiceTimeoutException
     */
    protected function shutDownVirtualMachine(VirtualMachine $virtualMachine)
    {
        $kingpin = $this->loadKingpinService($virtualMachine);

        $shutDownResult = $kingpin->shutDownVirtualMachine(
            $virtualMachine->getKey(),
            $virtualMachine->solutionId()
        );

        if (!$shutDownResult) {
            return false;
        }

        $startTime = time();

        do {
            sleep(10);
            $isOnline = $kingpin->checkVMOnline($virtualMachine->getKey(), $virtualMachine->solutionId());
            if ($isOnline === false) {
                return true;
            }
        } while (time() - $startTime < 120);

        throw new ServiceTimeoutException('Timeout waiting for Virtual Machine to power off.');
    }

    /**
     * Load and configure the Kingpin service for a Virtual Machine
     * @param VirtualMachine $virtualMachine
     * @return mixed
     * @throws KingpinException
     */
    protected function loadKingpinService(VirtualMachine $virtualMachine)
    {
        try {
            $kingpin = app()->makeWith(
                'App\Kingpin\V1\KingpinService',
                [$virtualMachine->getPod(), $virtualMachine->type()]
            );
        } catch (\Exception $exception) {
            throw new KingpinException('Unable to connect to Virtual Machine');
        }

        return $kingpin;
    }

    /**
     * Get a VM (Model, not query builder - use for updates etc)
     * @param $vmId int ID of the VM to return
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|object
     * @throws Exceptions\NotFoundException
     */
    protected function getVirtualMachine($vmId)
    {
        // Load the VM
        $virtualMachineQuery = $this->getVirtualMachines(null, [$vmId]);
        $VirtualMachine = $virtualMachineQuery->first();
        if (!$VirtualMachine) {
            throw new Exceptions\NotFoundException("The Virtual Machine '$vmId' Not Found");
        }
        return $VirtualMachine;
    }

    /**
     * List VM's
     * For admin list all except when $resellerId is passed in
     * @param null $resellerId
     * @param array $vmIds
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getVirtualMachines($resellerId = null, $vmIds = [])
    {
        $virtualMachineQuery = VirtualMachine::query();
        if (!empty($vmIds)) {
            $virtualMachineQuery->whereIn('servers_id', $vmIds);
        }
        if ($this->isAdmin) {
            if (!is_null($resellerId)) {
                $virtualMachineQuery->withResellerId($resellerId);
            }
            // Return ALL VM's
            return $virtualMachineQuery;
        }

        $virtualMachineQuery->where('servers_active', '=', 'y');

        //For non-admin filter on reseller ID
        return $virtualMachineQuery->withResellerId($this->resellerId);
    }

    /**
     * Validates the solution id
     * @param Request $request
     * @param $vmId
     * @return void
     */
    protected function validateVirtualMachineId(&$request, $vmId)
    {
        $request['vmId'] = $vmId;
        $this->validate($request, ['vmId' => 'required|integer']);
    }

    /**
     * List all VM's for a Solution
     *
     * @param Request $request
     * @param $solutionId
     * @return \Illuminate\Http\Response
     */
    public function getSolutionVMs(Request $request, $solutionId)
    {
        $collection = VirtualMachine::withResellerId($request->user->resellerId)->withSolutionId($solutionId);

        if (!$this->isAdmin) {
            $collection->where('servers_active', '=', 'y');
        }

        (new QueryTransformer($request))
            ->config(VirtualMachine::class)
            ->transform($collection);

        return $this->respondCollection(
            $request,
            $collection->paginate($this->perPage)
        );
    }

    /**
     * get VM by ID
     * @param Request $request
     * @param $vmId
     * @return mixed
     * @throws Exceptions\NotFoundException
     */
    public static function getVirtualMachineById(Request $request, $vmId)
    {
        $collection = VirtualMachine::withResellerId($request->user->resellerId);

        if ($request->user->resellerId != 0) {
            $collection->where('servers_active', '=', 'y');
        }

        $VirtualMachine = $collection->find($vmId);
        if (!$VirtualMachine) {
            throw new Exceptions\NotFoundException('Virtual Machine ID #' . $vmId . ' not found');
        }

        return $VirtualMachine;
    }
}
