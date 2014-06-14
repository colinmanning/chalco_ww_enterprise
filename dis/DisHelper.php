<?php
require_once BASEDIR.'/server/interfaces/services/BizDataClasses.php';

// Woodwing Property Types
define('PROPERTY_STRING', 'string');
define('PROPERTY_MULTISTRING', 'multistring');
define('PROPERTY_MULTILINE', 'multiline');
define('PROPERTY_BOOL', 'bool');
define('PROPERTY_INT', 'int');
define('PROPERTY_DOUBLE', 'double');
define('PROPERTY_DATE', 'date');
define('PROPERTY_DATETIME', 'datetime');
define('PROPERTY_LIST', 'list');
define('PROPERTY_ARRAY', 'array');

class DisFieldTypes {
	public static $FieldTypeAudio = 8;
	public static $FieldTypeBinary = 5;
	public static $FieldTypeBool = 1;
	public static $FieldTypeDate = 4;
	public static $FieldTypeDouble = 3;
	public static $FieldTypeEnum = 7;
	public static $FieldTypeInteger = 2;
	public static $FieldTypeLong = 9;
	public static $FieldTypePicture = 6;
	public static $FieldTypeString = 0;
	public static $FieldTypeTable = 10;

	public static $VALUE_INTERPRETATION_ASSETREFERENCE = 1;
	public static $VALUE_INTERPRETATION_DATA_SIZE = 4;
	public static $VALUE_INTERPRETATION_DATE_ONLY = 5;
	public static $VALUE_INTERPRETATION_DEFAULT = 0;
	public static $VALUE_INTERPRETATION_LENGTH_IN_INCH = 3;
	public static $VALUE_INTERPRETATION_RESOLUTION = 2;
	public static $VALUE_INTERPRETATION_STRING_ENUM_LABEL = 8;
	public static $VALUE_INTERPRETATION_STRING_ENUM_MULTIPLE_VALUES = 7;
	public static $VALUE_INTERPRETATION_STRING_ENUM_RATING = 9;
	public static $VALUE_INTERPRETATION_STRING_USER_UID = 10;
	public static $VALUE_INTERPRETATION_TIME_ONLY = 6;
}

class DisQueryRunner {
	public $name;
	public $disName;
	public $parameters;
	public $baseUrl;
	public $db;
	public $view;

	public function __construct($baseUrl = null, $db = null, $view = null, $name=null, $disName=null, $parameters=null) {
		$this->baseUrl = $baseUrl;
		$this->db = $db;
		$this->view = $view;
		$this->name = $name;
		$this->disName = $disName;
		$this->parameters = $parameters;
        LogHandler::Log('-CHALCO', 'DEBUG', 'disName: "'.$disName.'" $this->disName: ' . $this->disName);
	}
}

function view_var($var) {
	ob_start();
	print_r($var);
	return ob_get_clean();
}

/*
 * Map DIS types to Woodwing types
*/
function getWoodwingType($disType, $disValueInterpetation) {
	$result = "string";
	switch ($disType) {
		case DisFieldTypes::$FieldTypeString:
			$result = "string";
			break;
		case DisFieldTypes::$FieldTypeInteger:
			if ($disValueInterpetation == DisFieldTypes::$VALUE_INTERPRETATION_DATA_SIZE) {
				$result = "string";
			} else {
				$result = "int";
			}
			break;
		case DisFieldTypes::$FieldTypeDate:
			$result = "datetime";
			break;
		case DisFieldTypes::$FieldTypeLong:
			$result = "long";
			break;
		case DisFieldTypes::$FieldTypeBool:
			$result = "boolean";
			break;
		case DisFieldTypes::$FieldTypeEnum:
			$result = "string";
			break;
	}
	return $result;
}

function startsWith($haystack, $needle, $case=true) {
	if ($case)
		return strncmp($haystack, $needle, strlen($needle)) == 0;
	else
		return strncasecmp($haystack, $needle, strlen($needle)) == 0;
}

/**
 *  Process a Json date returned by DIS.
 * e.g. /Date(1323517335000) - note do not use 13 characters, as JSON is milliseconds, PHP is seconds
 */
function decodeJsonDate($s, $dateFormat) {
	return date($dateFormat, (int) substr($s, 6, 10));
}


function endsWith($haystack, $needle, $case=true) {
	return startsWith(strrev($haystack),strrev($needle),$case);
}














