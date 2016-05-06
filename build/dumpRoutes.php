<?php

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';

$yaml = file_get_contents(__DIR__.'/../res/badgekit.yml');
$routes = Yaml::parse($yaml);
$json = json_encode($routes);
file_put_contents(__DIR__.'/../res/badgekit.json', $json);
