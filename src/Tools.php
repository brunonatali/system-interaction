<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;
class Tools implements ToolsInterface
{

    public static function checkSocketFolder(): bool
    {
        if (!file_exists(self::SOCK_FOLDER)) {
            mkdir(self::SOCK_FOLDER, 0755, true );
            chmod(self::SOCK_FOLDER, 0755);
            return false;
        }
        return true;
    } 

    public static function checkSocket($sockName)
    {
        $path = self::SOCK_FOLDER . $sockName;
        if (file_exists($path)) unlink($path);

        return $path;
    }

    public static function getSocketName($serviceName)
    {
        switch ($serviceName) {
            case 'runas-root':
                return self::R_AS_SOCKET;
                break;
        }

        return false;
    }
}