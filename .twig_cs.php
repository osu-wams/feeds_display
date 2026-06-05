<?php

declare(strict_types=1);

use FriendsOfTwig\Twigcs\Config\Config;
use FriendsOfTwig\Twigcs\Finder\TemplateFinder;

$templates = TemplateFinder::create()
  ->in(__DIR__ . '/components');
return Config::create()
  ->setName('feeds_display')
  ->setSeverity('error')
  ->setDisplay('blocking')
  ->setRuleSet(FriendsOfTwig\Twigcs\Ruleset\Official::class)
  ->setFinder($templates);
