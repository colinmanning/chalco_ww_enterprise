<?php

require_once dirname(__FILE__) . '/dis/DisHelper.php';
require_once dirname(__FILE__) . '/dis/DisMetadata.php';
require_once dirname(__FILE__) . '/dis/DisConfig.php';

define('ROOT_PATH', dirname(__FILE__));

$disConfig = null;
$disMetadata = null;
$viewDescriptors = null;
$recordViews = null;
$ready = null;

$wwusername = null;
$wwusergroup = null;

function initChalco() {
	global $disConfig, $dbNames, $disMetadata, $viewDescriptors, $recordViews, $ready, $wwusername, $wwusegroup;
	
	if (!is_null($ready) && $ready) return;
	
	$disConfig = new DisConfig(ROOT_PATH . '/woodwing-dis-config.xml');
    define('DIS_OUTPUTDIRECTORY', $disConfig->outputDirectory);
	LogHandler::Log('-CHALCO', 'INFO', 'Initialising Woodwing-Chalco interface');
    LogHandler::Log('-CHALCO', 'INFO', 'output directory "' . $disConfig->outputDirectory . '"');
	
	require_once BASEDIR . '/server/bizclasses/BizUser.class.php';
	require_once BASEDIR . '/server/bizclasses/BizSession.class.php';
	require_once BASEDIR . '/server/bizclasses/BizPublication.class.php';
	$wwusername = BizSession::getShortUserName();
	$wwfullname = BizSession::getUserInfo('fullname');
	//$wwusergroup = DBUser::getUserGroup($wwusername);
	//$wwuser = DBUser::getUser($wwusername);
	//$wwmemberships = BizUser::getMemberships($wwusername);
	// LogHandler::Log('cCHALCO', 'DEBUG', 'user data: '.view_var($wwuser));
	// LogHandler::Log('CHALCO', 'DEBUG', 'user memberships: '.view_var($wwmemberships));
	// LogHandler::Log('-CHALCO', 'DEBUG', 'got user: '.$wwfullname.' with group: '.$wwusegroup.' and username: '.$wwusername);
	
	$viewDescriptors = array();
	$recordViews = array();
	$disMetadata = new DisMetadata();
	$recordViews = array();

	LogHandler::Log('-CHALCO', 'INFO', 'baseUrl from XML: ' . $disConfig->baseUrl);

	$ready = true;
}


function getDisQueries() {
	global $disConfig;
	$result = array();
	//DisLogHandler::Log('config.getDisQueries', 'INFO', 'disConfig: ' . view_var($disConfig));
	foreach ($disConfig->queries as $query) {
		//DisLogHandler::Log('config.getDisQueries', 'INFO', 'setting up query: ' . $query->displayName);
		$dbProperty = buildListPropertyInfo('db', $disConfig->databaseLabel, $query->connections);
		$viewNames = array();
		foreach ($query->connections as $queryConnection) {
			$conn = $disConfig->connections[$queryConnection];
			foreach ($conn->views as $queryView) {
				if (array_search($queryView->displayName, $viewNames) === false) {
					$viewNames[] = $queryView->displayName;
				}
			}
		}
		$dbViewProperty = buildListPropertyInfo('dbView', $disConfig->viewLabel, $viewNames);

		$wwQueryParams = array();
		foreach ($query->params as $queryParam) {
			switch ($queryParam->type) {
				case 'string':
					$wwQueryParams[] = buildStringPropertyInfo($queryParam->name, $queryParam->displayName);
					break;
				case 'int':
					$wwQueryParams[] = buildIntegerPropertyInfo($queryParam->name, $queryParam->displayName, $queryParam->minValue, $queryParam->maxValue);
					break;
				case 'boolean':
					$wwQueryParams[] = buildBooleanPropertyInfo($queryParam->name, $queryParam->displayName);
					break;
				case 'list':
					$listValues = array();
					foreach ($queryParam->items as $displayName=>$value) {
						$listValues[] = $displayName;
					}
					$wwQueryParams[] = buildListPropertyInfo($queryParam->name, $queryParam->displayName, $listValues);
					break;
				case 'date':
					$wwQueryParams[] = buildDatePropertyInfo($queryParam->name, $queryParam->displayName);
					break;
				case 'datetime':
					$wwQueryParams[] = buildDateTimePropertyInfo($queryParam->name, $queryParam->displayName);
					break;
				default:
					break;
			}
		}
		$wwQueryParams[] = $dbProperty;
		$wwQueryParams[] = $dbViewProperty;
		$wwQuery = new DisQueryRunner($disConfig->baseUrl, null, null, $query->displayName,  $query->name, $wwQueryParams);
		$result[$query->displayName] = $wwQuery;
	}

	return $result;
}

// utilities
function buildListPropertyInfo($fieldName=null, $fieldDisplayName=null, $listValues=null) {
	$defaultValue = '';
	if (count($listValues) > 0) $defaultValue = $listValues[0];
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
    'list',
	$defaultValue,
	$listValues,
	null,
	null,
	null,
	null,
	null
	);
}

function buildStringPropertyInfo($fieldName=null, $fieldDisplayName=null) {
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
    'string',
    '',
	null,
	null,
	null,
	null,
	null,
	null
	);
}

function buildBooleanPropertyInfo($fieldName=null, $fieldDisplayName=null) {
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
    'bool',
	true,
	null,
	null,
	null,
	null,
	null,
	null
	);
}

function buildIntegerPropertyInfo($fieldName=null, $fieldDisplayName=null, $minValue, $maxValue) {
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
    'int',
	$minValue,
	null,
	$minValue,
	$maxValue,
	null,
	null,
	null
	);
}

function buildDatePropertyInfo($fieldName=null, $fieldDisplayName=null) {
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
	    'date',
	    '',
	null,
	null,
	null,
	null,
	null,
	null
	);
}

function buildDateTimePropertyInfo($fieldName=null, $fieldDisplayName=null) {
	return new PropertyInfo(
	$fieldName,
	$fieldDisplayName,
	null,
	    'datetime',
	    '',
	null,
	null,
	null,
	null,
	null,
	null
	);
}
