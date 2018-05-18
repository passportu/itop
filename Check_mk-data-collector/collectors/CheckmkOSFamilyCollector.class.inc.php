<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkOSFamilyCollector extends CheckmkCollector
{
    private $OSFamilyMap;

    public function Prepare()
    {
        $bRes = parent::Prepare();

        $this->OSFamilyMap = new MappingTable('osfamily_mapping');

        return $bRes;
    }

    public function Fetch()
    {
        $data = $this->getData();
        if ($data === null)
            return array('primary_key' => '', 'name' => '');
        else if ($data === false)
            return false;

        // OS family may be in 'type' field or 'name' field - try both
        $osFamily = '';
        if (isset($data->software->os->type))
        {
            $osFamily = $this->OSFamilyMap->MapValue($data->software->os->type,
                                                     '');
        }

        if (isset($data->software->os->name))
        {
            if ($osFamily === '')
                $osFamily =
                    $this->OSFamilyMap->MapValue($data->software->os->name, '');
        }

        $osFamily = trim($osFamily);
        $ret = array(
            'primary_key' => $osFamily,
            'name' => $osFamily
        );
        
        return $ret;
    }

    protected function MustProcessBeforeSynchro()
    {
        return true;
    }

    protected function ProcessLineBeforeSynchro(&$lineData, $lineIdx)
    {
        if ($lineIdx > 0 && $lineData[1] === '')
            throw new IgnoredRowException('No OS family was found');
    }
}
?>