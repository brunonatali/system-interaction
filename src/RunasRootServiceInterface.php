<?php

namespace BrunoNatali\SystemInteraction;
interface RunasRootServiceInterface extends MainInterface
{

    const R_AS_SOCKET = 'runas-root.sock';

    public function start();
}
