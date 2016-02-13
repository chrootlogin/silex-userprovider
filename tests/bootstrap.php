<?php

if (!$loader = @include dirname(__DIR__) . '/vendor/autoload.php') {
    die("curl -s http://getcomposer.org/installer | php; php composer.phar install");
}

use Doctrine\Common\Annotations\AnnotationRegistry;

AnnotationRegistry::registerLoader(array($loader, 'loadClass'));