<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkModelCollector extends CheckmkCollector
{
    private $brandMap;
    private $modelMap;
    private $typeMap;

    public function Prepare()
    {
        $bRes = parent::Prepare();

        $this->brandMap = new MappingTable('brand_mapping');
        $this->modelMap = new MappingTable('model_mapping');
        $this->typeMap  = new MappingTable('type_mapping');

        return $bRes;
    }

    public function Fetch()
    {
        $data = $this->getData();
        if ($data === null)
            return array('primary_key' => '', 'name' => '', 'brand_id' => '',
                         'type' => '');
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

        $model = '';
        if (isset($data->hardware->system->family))
        {
            $model = $data->hardware->system->family;
            if (strcasecmp($model, self::OEM_STR) == 0)
                $model = '';
        }

        $brand = trim($brand);
        $model = trim($model);
        $ret = array(
            'primary_key' => strtolower($brand).'_'.strtolower($model),
            'name' => $model,
            'brand_id' => $brand,
            'type' => guessObjectType($this->hostname(), $data, 'Server',
                                      $this->typeMap)
        );

        return $ret;
    }

    protected function MustProcessBeforeSynchro()
    {
        return true;
    }

    protected function ProcessLineBeforeSynchro(&$lineData, $lineIdx)
    {
        if ($lineIdx > 0 && ($lineData[1] === '' || $lineData[2] === ''))
            throw new IgnoredRowException('No model was found');
    }
}
?>