<?php

namespace Silverorange\Autoloader;

$package = new Package('silverorange/conversion');

$package->addRule(new Rule('', 'Conversion'));

Autoloader::addPackage($package);

?>
