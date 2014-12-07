<?php
/**
 *
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @version SVN: $Revision: 131296 $
 */
$lib = dirname(dirname(dirname(dirname(__DIR__))));
set_include_path(get_include_path() . PATH_SEPARATOR . $lib);

require_once dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'ClassLoader.php';
require_once 'AmfphpBinaryLoggerTestUtils.php';

