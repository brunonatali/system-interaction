<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

class Tools implements MainInterface
{

    public static function checkSocketFolder()
    {
        if (!file_exists(SOCK_FOLDER))
            mkdir(SOCK_FOLDER, 0766, true );
    } 

    public static function checkSocket($sockName)
    {
        $path = SOCK_FOLDER . $sockName;
        if (file_exists($path)) unlink($path);

        return $path;
    }
}