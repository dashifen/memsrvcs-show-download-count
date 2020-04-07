<?php

/**
 * Plugin Name: Show Download Count
 * Description: Displays the download count for PDFs on the Memoriam Services site.
 * Author URI: mailto:dashifen@dashifen.com
 * Author: David Dashifen Kees
 * Version: 1.0.0
 *
 * @noinspection PhpStatementHasEmptyBodyInspection
 * @noinspection PhpIncludeInspection
 */

use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\MemSrvcs\ShowDownloadCount\ShowDownloadCount;

// the following snippet finds the appropriate autoloader starting from a
// where Dash likes to put it and ending at a reasonable (but unlikely)
// default.  note that the $autoloader variable is set in the if-conditionals,
// so they don't need statement bodies; all their work is done during each
// test.  then, we try requiring what we end up with.

if (file_exists($autoloader = dirname(ABSPATH) . '/deps/vendor/autoload.php'));
elseif ($autoloader = file_exists(dirname(ABSPATH) . '/vendor/autoload.php'));
elseif ($autoloader = file_exists(ABSPATH . 'vendor/autoload.php'));
else $autoloader = 'vendor/autoload.php';
require_once $autoloader;

(function() {
    try {
        $showDownloadCount = new ShowDownloadCount();
        $showDownloadCount->initialize();
    } catch (HandlerException $e) {
        wp_die($e->getMessage());
    }
})();
