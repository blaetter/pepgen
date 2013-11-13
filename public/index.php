<?php

require_once('../vendor/autoload.php');

require_once('../app/modules/epub/epub.class.php');
require_once('../app/modules/epub/config.class.php');

$epub = new epub();

$epub->run();

?>