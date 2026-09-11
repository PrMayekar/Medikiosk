<?php

/**
 * AI Intake Module — Bootstrap
 *
 * Loaded by OpenEMR's module manager when the module is enabled.
 * Registers the Symfony event listener that injects the "AI Intake" card
 * into the patient demographics page via RenderEvent (direct HTML output).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\AiIntake\Bootstrap;

// Register our namespace so PHP can autoload our classes.
$classLoader = new ModulesClassLoader(OEGlobalsBag::getInstance()->getProjectDir());
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\AiIntake\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

// Wire up the Symfony event listener.
$eventDispatcher = OEGlobalsBag::getInstance()->getKernel()->getEventDispatcher();
$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();
