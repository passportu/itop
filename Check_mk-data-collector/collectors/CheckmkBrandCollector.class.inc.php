<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkBrandCollector extends CheckmkCollector
{
    private $brandMap;

    public function Prepare()
    {
        $bRes = parent::Prepare();

        $this->brandMap = new MappingTable('brand_mapping');

        return $bRes;
    }

    public function Fetch()
    {
        $data = $this->getData();
        if ($data === null)
            return array('primary_key' => '', 'name' => '');
        else if ($data === false)
            return false;

        $brand = '';
        if (isset($data->hardware->system->vendor))
        {
            // Map if possible, else just use raw value
            $brand =
                $this->brandMap->MapValue($data->hardware->system->vendor, '');
            if (strcasecmp($brand, self::OEM_STR) == 0)
                $brand = '';
        }

        $brand = trim($brand);
        $ret = array(
            'primary_key' => strtolower($brand),
            'name' => $brand
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
            throw new IgnoredRowException('No brand was found');
    }
}
?>