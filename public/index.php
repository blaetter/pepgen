<?php

require_once('../vendor/autoload.php');

require_once('../app/config/config.php');

require_once('../app/modules/epub/epub.class.php');

$epub = new epub();

$epub->run();

?>