<?php

function getBooleanConfVal($element, $default = false)
{
    $val = Utils::GetConfigurationValue($element,
    $default ? 'true' : 'false');
    if (strcasecmp($val, 'true') == 0)
        return true;
    else if (strcasecmp($val, 'false') == 0)
        return false;
    else
        return $default;
}

function guessObjectType($hostname, $data, $default, $typeMap)
{
    // Check OS for Server in the name
    if (isset($data->software->os->name))
    {
        if (strpos(strtolower($data->software->os->name), 'server') !== false)
            return 'Server';
    }

    if ($typeMap != null)
    {
        $type = $typeMap->MapValue($hostname, 0);
        if ($type !== 0) return $type;
    }

    return $default;
}

?>