AMFPHP-Binary-Logger
====================

AMFHPBinaryLogger is a production-ready logger plugin for AMFPHP, the PHP server library for handling AMF (Actionscript Message Format) messages sent and received by Adobe ActionScript 3.

This logger is an alternative to the AMFPHP Logger plugin supplied as part of the AMFPHP distribution.  It is fast and efficient enough to be used in production on a busy website (Poptropica.com) handling hundreds of AMF calls per second.

The logger stores the start time and time taken (with microsecond resolution) by your code to process each request so that you can analyze performance.

Logfiles are rotated automatically.  You can choose to start a new file at hourly or daily intervals.

You can choose to log a unique client identifier, such as a cookie or username, with each request; this enables you to select a sequence of requests from a single client.

The logger stores each call and return value as binary AMF. This form is more compact and efficient than trying to store PHP objects in text form.  To enable you to use Linux tools on the file, however, each request and response is stored as a line, with an LF terminator; thus you can use grep, sort, wc, and other tools to analyze the file.  Newline characters in the binary AMF stream are escaped.

Usage:

1. Download AmfphpBinaryLogger and put the folder into your AMFPHP/Plugins directory.

2. Disable the default logger in your configuration (normally in your callRouter.php file):

    $config = new Amfphp_Core_Config;
    $config->disabledPlugins[] = 'AmfphpLogger';

3. Optionally configure the logger's directory.  The example below logs to /var/log/amfphp, which you must create with mkdir and make writable by www.

    $config->pluginsConfig['AmfphpBinaryLogger']['dirName'] = '/var/log/amfphp/';

    Notice the trailing slash!

    If you do not set the dirName, it will default to the system temporary directory returned by sys_get_temp_dir(), plus the system DIRECTORY_SEPARATOR.

4. Optionally configure the logfile pattern using the strftime escapes.  E.g., the following fileNamePattern creates logfiles that are rotated hourly, named (e.g.) 20141206-23-bin.log.

    $config->pluginsConfig['AmfphpBinaryLogger']['fileNamePattern'] = '%Y%m%d-%H-bin.log';

    If you do not set the filename pattern, it will default to amfphp-%Y%m%d.log, which rotates daily.

5. Optionally store a unique identifier such as the Apache web server's cookie with each request:

    $config->pluginsConfig['AmfphpBinaryLogger']['clientIdentifier'] = $_COOKIE['Apache'];

6. To read the logfiles, use the AmfphpBinaryLoggerReader class.  Its simplest use is with the php command.  You must supply the filename of the logfile to read.  Example:

    $ php -r 'require "AmfphpBinaryLoggerReader.php"; AmfphpBinaryLoggerReader::show("/tmp/amfphp-20130405.log");'

    For more flexibility, the AmfphpBinaryLoggerReader class has a constructor that takes a filename or a PHP resource and reads its lines, returning an AmfphpBinaryLoggerEntry object for each line.  See the AmfphpBinaryLoggerReader class for its use.  This enables you to analyze the requests and responses more precisely than by using text tools on the output of the AmfphpBinaryLoggerReader::show command.




