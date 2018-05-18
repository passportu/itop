<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
require_once(APPROOT.'collectors/CheckmkCollector.class.inc.php');
class CheckmkPhysicalInterfaceCollector extends CheckmkCollector
{
    private $idx = -1;
    private $adapters = null;
    public function Prepare()
    {
        $this->emptyRow = array('primary_key' => '',
                                'connectableci_id' => '',
                                'name' => '',
                                'ipaddress' => '',
                                'macaddress' => '',
                                'ipgateway'=> '',
                                'speed' => '',
                                'comment' => ''
        );
        return parent::Prepare();
    }

    // Note: each host may have multiple adapters, to handle this an array
    // ($this->adapters) and index ($this->idx) are maintained and linked
    // to the host by the host's primary key (hostname)
    public function Fetch()
    {
        // Try to get adapter list as required/available
        if ($this->adapters === null || $this->idx == -1)
        {
            $data = $this->getData();
            if ($data === null)       // Couldn't get any data
                return $this->emptyRow;
            else if ($data === false) // End of data
                return false;

            if (isset($data->hardware->networkadapter))
            {
                $this->idx = 0;
                $this->adapters = $data->hardware->networkadapter;
            }
            else // Couldn't get network adapter data
                return $this->emptyRow;
        }

        if ($this->idx >= count($this->adapters))
        {
            $this->adapters = null;
            $this->idx = -1;
            return $this->emptyRow;
        }

        $adapter = $this->adapters[$this->idx++];

        return array('primary_key'      => strtolower($adapter->name),
                     'connectableci_id' => strtolower($this->hostname()),
                     'name'             => trim($adapter->name),
                     'ipaddress'        => trim($adapter->address),
                     'macaddress'       => trim($adapter->macaddress),
                     'ipgateway'        => trim($adapter->gateway),
                     'speed'            => trim($adapter->speed),
                     'comment'          => trim($adapter->adaptertype),
        );
    }

    protected function MustProcessBeforeSynchro()
    {
        return true;
    }

    protected function ProcessLineBeforeSynchro(&$lineData, $lineIdx)
    {
        if ($lineIdx > 0 && $lineData[0] === '')
            throw new IgnoredRowException('No physical interface was found');
    }
}
?>