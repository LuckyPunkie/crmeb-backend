<?php

namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\services\UgcDfaFilter;

class UgcDfaInitListen implements ListenerInterface
{
    public function handle($event): void
    {
        UgcDfaFilter::init();
    }
}