<?php

// a-blog cms defines these at runtime (config/app.php setPath()). PHPStan analyzes statically and
// cannot see them, so declare them here to avoid false "Constant not found" errors.
defined('PLUGIN_DIR') || define('PLUGIN_DIR', '/extension/plugins/');
defined('PLUGIN_LIB_DIR') || define('PLUGIN_LIB_DIR', '');
