<?php

namespace App\Exceptions;

use Exception;

/** Thrown by CsvPunchParser; `getCode()` carries a machine-readable reason. */
class CsvFormatException extends Exception {}
