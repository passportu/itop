<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkPCCollector.class.inc.php');
// Reuses PC collector code
class CheckmkServerCollector extends CheckmkPCCollector
{
    // Gathers a list of all inventory files to collect from
    public function Prepare()
    {
        $bRes = parent::Prepare();

        return $bRes;
    }

    // Acts as a filter for Server objects using hostname patterns
    // Specify object types in <type_mapping> config parameter
    protected function includeFile($hostName)
    {
        $typeMap = new MappingTable('type_mapping');
        $type = guessObjectType($hostName, null, '', $typeMap);
        if ($type == 'Server') return true;
        else                   return false;
    }

    public function Fetch()
    {
        return parent::Fetch();
    }
}
?>