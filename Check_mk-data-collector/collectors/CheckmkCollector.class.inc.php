<?php
require_once(APPROOT.'collectors/SlackJSONParser.php');
require_once(APPROOT.'collectors/utils.php');
class CheckmkCollector extends Collector
{    
    private $invPath = '';
    private $skipGz = true;
    private $skipDot = true;

    private $invFiles = array();
    private $hostNames = array();
    private $curr = 0;
    private $currHostname = '';

    const OEM_STR = "To be filled by O.E.M.";

    public function Prepare()
    {
        $bRes = parent::Prepare();
        $this->invPath = Utils::GetConfigurationValue('check_mk_dir');
        if (!file_exists($this->invPath))
        {
            Utils::Log(LOG_ERROR, 'Couldn\'t open check_mk inventory data ' .
                                  'directory ' . $this->invPath);
            return false;
        }
        $allFiles = scandir($this->invPath);
        if ($allFiles === false)
        {
            Utils::Log(LOG_ERROR, 'check_mk inventory data director specified' .
                                  ' is not a directory: ' . $this->invPath);
            return false;
        }

        $this->skipGz             = getBooleanConfVal('skip_gz', true);
        $this->skipDot            = getBooleanConfVal('skip_dot', true);

        $lastHost = '';
        foreach($allFiles as $f)
        {
            // Skip dotfiles, else just . and ..
            // and .gz files if desired
            if (($this->skipDot && $f[0] == '.') ||
                (!$this->skipDot && ($f == '.' || $f == '..')) ||
                ($this->skipGz && $f == $lastHost . '.gz') ||
                !$this->includeFile($f))
                continue;
            $lastHost = $f;
            $this->invFiles[] = $this->invPath . '/' . $f;
            $this->hostNames[] = $f; // Host name is file name
        }

        return $bRes;
    }

    public function hostname()
    {
        return $this->currHostname;
    }

    // Override to specify files which should be parsed by collector
    protected function includeFile($hostName)
    {
        return true;
    }

    protected function getData()
    {
        if ($this->curr >= count($this->invFiles)) return false;
        $this->currHostname = $this->hostNames[$this->curr];
        $invFile = $this->invFiles[$this->curr];
        $data = cmk_inv_decode($invFile, 'os');
        $this->curr++;

        if ($data == null)
        {
            Utils::Log(LOG_ERR, 'Error occurred when parsing file '.$invFile.
                                "\n".cmk_inv_last_error_msg() .
                                'This row will be ignored');
        }

        return $data;
    }
}
?>