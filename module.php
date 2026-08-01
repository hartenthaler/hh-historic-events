<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClientFactory;

$loader = new ClassLoader();

$loader->addPsr4('Hartenthaler\\WebtreesModules\\History\\HhHistoricEvents\\', __DIR__ . '/src');
$loader->addPsr4('Hartenthaler\\Webtrees\\Helpers\\', __DIR__ . '/vendor/Hartenthaler/Webtrees/Helpers');

$loader->register();

return new HhHistoricEvents(
    HttpGetClientFactory::create(),
    Registry::container()->get(ModuleService::class)
);
