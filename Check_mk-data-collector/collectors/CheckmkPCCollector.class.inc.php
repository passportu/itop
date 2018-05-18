<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkPCCollector extends CheckmkCollector
{
    private $defaultOrg = 0;
    private $useNetworkHostname = true;

    private $OSFamilyMap;
    private $OSVersionMap;
    private $brandMap;
    private $defaultFields;
    private $OSVersionLookup;
    private $modelLookup;

    // Gathers a list of all inventory files to collect from
    public function Prepare()
    {
        $bRes = parent::Prepare();

        $this->useNetworkHostname = getBooleanConfVal('use_network_hostname',
                                                      false);
        $this->defaultOrg = Utils::GetConfigurationValue('default_org_id');

        $this->OSFamilyMap   = new MappingTable('osfamily_mapping');
        $this->OSVersionMap  = new MappingTable('osversion_mapping');
        $this->brandMap      = new MappingTable('brand_mapping');
        $this->defaultFields = Utils::GetConfigurationValue('default_fields',
                                                            array());

        $this->emptyRow = array('primary_key' => '',
                                'name' => '',
                                'org_id' => '',
                                'cpu' => '',
                                'ram' => '',
                                'osfamily_id' => '',
                                'osversion_id' => '',
                                'serialnumber' => '',
                                'brand_id' => '',
                                'model_id' => '');
        foreach ($this->defaultFields as $fieldName => $value)
        {
            $this->emptyRow[$fieldName] = '';
        }
        return $bRes;
    }

    // Acts as a filter for PC objects using hostname patterns
    // Specify object types in <type_mapping> config parameter
    protected function includeFile($hostName)
    {
        $typeMap = new MappingTable('type_mapping');
        $type = guessObjectType($hostName, null, 'Server', $typeMap);
        if ($type == 'PC') return true;
        else               return false;
    }

    public function Fetch()
    {
        $data = $this->getData();
        if ($data === null)
            return $this->emptyRow;
        else if ($data === false)
            return false;

        $ret = array(
            'primary_key' => strtolower($this->hostname()),
            'name' => $this->hostname(),
            'org_id' => $this->defaultOrg,
            'cpu' => '',
            'ram' => '',
            'osfamily_id' => '',
            'osversion_id' => '',
            'serialnumber' => '',
            'brand_id' => '',
            'model_id' => ''
        );

        if ($this->useNetworkHostname && isset($data->networking->hostname))
            $ret['name'] = $data->networking->hostname;

        if (isset($data->hardware->cpu->model))
            $ret["cpu"] = $data->hardware->cpu->model;
        if (isset($data->hardware->memory->total_ram_usable))
            $ret["ram"] =
                round($data->hardware->memory->total_ram_usable / (1024*1024))
                . " MiB";

        // OS family may be in 'type' field or 'name' field - try both
        $osFamily = '';
        if (isset($data->software->os->type))
        {
            $osFamily = $this->OSFamilyMap->MapValue($data->software->os->type,
                                                     '');
            $ret['osfamily_id'] = trim($osFamily);
        }

        if (isset($data->software->os->name))
        {
            if ($osFamily === '')
            {
                $osFamily =
                    $this->OSFamilyMap->MapValue($data->software->os->name, '');
                $ret['osfamily_id'] = trim($osFamily);
            }
            $osVersion =
                $this->OSVersionMap->MapValue($data->software->os->name, '');
            $ret['osversion_id'] = trim($osVersion);
        }

        if (isset($data->hardware->system->serial))
        {
            $serial = $data->hardware->system->serial;
            if (strcasecmp($serial, self::OEM_STR) != 0 &&
                $serial != '')
            {
                $ret['serialnumber'] = trim($serial);
            }
        }

        if (isset($data->hardware->system->vendor))
        {
            // Map if possible, else just use raw value
            $brand =
                $this->brandMap->MapValue($data->hardware->system->vendor, '');

            if (strcasecmp($brand, self::OEM_STR) != 0)
                $ret['brand_id'] = trim($brand);
        }

        if (isset($data->hardware->system->family) &&
            strcasecmp($data->hardware->system->family, self::OEM_STR) != 0)
            $ret['model_id'] = trim($data->hardware->system->family);

        // Add fields for which a default is specified
        foreach ($this->defaultFields as $fieldName => $value)
        {
            $ret[$fieldName] = $value;
        }

        return $ret;
    }

    public function AttributeIsOptional($sAttCode)
    {
        if ($sAttCode == 'name' || $sAttCode == 'org_id')
            return false;
        else
            return true;
    }

    protected function MustProcessBeforeSynchro()
    {
        // Required for advanced lookup (OS version)
        return true;
    }

    protected function InitProcessBeforeSynchro()
    {
        $this->OSVersionLookup =
            new LookupTable('SELECT OSVersion',
                            array('osfamily_id_friendlyname', 'name'));
        $this->modelLookup =
            new LookupTable('SELECT Model',
                            array('brand_id_friendlyname', 'name'));
    }

    protected function ProcessLineBeforeSynchro(&$data, $lineIdx)
    {
        if ($lineIdx > 0 && $data[0] == '')
            throw new IgnoredRowException('No PC data found');
        $this->OSVersionLookup->Lookup($data,
                                       array('osfamily_id', 'osversion_id'),
                                       'osversion_id',
                                       $lineIdx);
        $this->modelLookup->Lookup($data,
                                   array('brand_id', 'model_id'),
                                   'model_id',
                                   $lineIdx);
    }
}

?>