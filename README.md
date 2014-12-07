AMFPHP-Binary-Logger
====================

AMFHPBinaryLogger is a production-ready logger plugin for AMFPHP

This logger is intended as an alternative to the AMFPHP Logger plugin supplied as part of the AMFPHP distribution.  It is fast and efficient enough to be used in production on a busy website (Poptropica.com) handling hundreds of AMF calls per second.

The logger stores each call and return value as binary AMF. This form is more compact and efficient than trying to store PHP objects in text form.  To enable you to use Linux tools on the file, however, each request and response is stored as a line, with an LF terminator; thus you can use grep, sort, wc, and other tools to analyze the file.  Newline characters in the binary AMF stream are escaped.

The logger also stores the start time and time taken (with microsecond resolution) by your code to process each request so that you can analyze performance.

Logfiles are rotated automatically.  You can choose to start a new file at hourly or daily intervals.

You can choose to log a client identifier, such as IP address or cookie, with each request; this enables you to select a sequence of requests from a single client.


