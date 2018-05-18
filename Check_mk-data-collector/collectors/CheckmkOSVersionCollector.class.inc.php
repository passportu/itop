<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkOSVersionCollector extends CheckmkCollector
{
    private $OSFamilyMap;
    private $OSVersionMap;

    public function Prepare()
    {
        $bRes = parent::Prepare();

        $this->OSFamilyMap  = new MappingTable('osfamily_mapping');
        $this->OSVersionMap = new MappingTable('osversion_mapping');

        return $bRes;
    }

    public function Fetch()
    {
        $data = $this->getData();
        if ($data === null)
            return array('primary_key' => '', 'name' => '', 'osfamily_id' => '');
        else if ($data === false)
            return false;

        // OS family may be in 'type' field or 'name' field - try both
        $osFamily = '';
        $osVersion = '';
        if (isset($data->software->os->type))
        {
            $osFamily = $this->OSFamilyMap->MapValue($data->software->os->type,
                                                     '');
        }

        if (isset($data->software->os->name))
        {
            if ($osFamily === '')
            {
                $osFamily =
                    $this->OSFamilyMap->MapValue($data->software->os->name, '');
            }
            $osVersion =
                $this->OSVersionMap->MapValue($data->software->os->name, '');
        }

        $osFamily = trim($osFamily);
        $osVersion = trim($osVersion);

        $ret = array(
            'primary_key' => strtolower($osFamily)."_".strtolower($osVersion),
            'name' => $osVersion,
            'osfamily_id' => $osFamily
        );

        return $ret;
    }

    protected function MustProcessBeforeSynchro()
    {
        return true;
    }

    protected function ProcessLineBeforeSynchro(&$lineData, $lineIdx)
    {
        if ($lineIdx > 0 && ($lineData[1] === '' || $lineData[2] == ''))
            throw new IgnoredRowException('No OS version was found');
    }
}
?>