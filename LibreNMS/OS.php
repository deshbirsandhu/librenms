<?php
/**
 * Created by PhpStorm.
 * User: murrant
 * Date: 4/21/17
 * Time: 4:37 PM
 */

namespace LibreNMS;


class OS
{
    public static function getOS($os_name)
    {
        $class = str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $os_name)));
        return new $class();
    }
}
