<?php

require_once dirname(__FILE__) . '/DisConnection.php';
require_once dirname(__FILE__) . '/DisView.php';
require_once dirname(__FILE__) . '/DisQuery.php';
require_once dirname(__FILE__) . '/DisQueryParam.php';
require_once dirname(__FILE__) . '/DisField.php';

class DisConfig {

	public $outputDirectory = null;

	public $baseUrl = null;
	public $thumbnail = null;
	public $thumbnailSize = null;
	public $preview = null;
	public $previewSize = null;
	public $connections = null;
	public $queries = null;
	public $metadata = null;
	public $databaseLabel = null;
	public $viewLabel = null;
	public $assetNameField = null;
	public $assetTypeField = null;

	/*
	 * Defaults for linking found asses
	*/
	public $defaultBrand = null;
	public $defaultCategory = null;
	public $defaultStatus = null;

	/*
	 * Views
	*/
	public $showViews = true;
	public $defaultView = null;

	public function __construct($configFilePath) {
		$this->metadata = new DisMetadata();

		$configXml = simplexml_load_file($configFilePath);
		//LogHandler::Log('-CHALCO', 'DEBUG', 'config file root: ' . view_var($configXml));

		$this->outputDirectory = (string) $configXml->outputDirectory;

		$this->defaultBrand = (string) $configXml->defaultBrand;
		$this->defaultCategory = (string) $configXml->defaultCategory;
		$this->defaultStatus = (string) $configXml->defaultStatus;

		$this->showViews = (boolean) $configXml->showViews;
		$this->defaultView = (string) $configXml->defaultView;
		$this->dateFieldFormat = (string) $configXml->dateFieldFormat;

		$this->baseUrl = (string) $configXml->baseurl;
		$this->databaseLabel = (string) $configXml->databaseLabel;
		$this->viewLabel = (string) $configXml->viewLabel;
		$this->thumbnail = (string) $configXml->thumbnail['name'];
		$this->thumbnailSize = (int) $configXml->thumbnail['size'];
		$this->preview = (string) $configXml->preview['name'];
		$this->previewSize = (int) $configXml->preview['size'];

		$this->connections = array();
		//LogHandler::Log('-CHALCO', 'INFO', 'about to process connections');
		foreach ($configXml->connections->connection as $connection) {
			$disConnection = new DisConnection();
			$disConnection->name = (string) $connection['name'];
			$disConnection->displayName = (string) $connection['displayName'];
			$connectionViews = array();
			foreach ($connection->view as $view) {
				//LogHandler::Log('-CHALCO', 'INFO', 'about to process connection views');
			   
				$disView = new DisView();
				$disView->name = (string) $view['name'];
				$disView->displayName = (string) $view['displayName'];
				$disView->mandatoryFields = $view->mandatoryFields;
				foreach ($disView->mandatoryFields->mandatoryField as $mandatoryField) {
				    //LogHandler::Log('-CHALCO', 'INFO', 'got mandatory field: ' . view_var($mandatoryField));
					if (!empty($mandatoryField['assetName'])) {
						$disView->assetNameField = (string) $mandatoryField['assetName'][0];
					} else if (!empty($mandatoryField['assetFormat'])) {
						$disView->assetFormatField = (string) $mandatoryField['assetFormat'][0];
					} else if (!empty($mandatoryField['assetSlugline'])) {
						$disView->assetSluglineField = (string) $mandatoryField['assetSlugline'][0];
					}
				}
                LogHandler::Log('-CHALCO', 'INFO', 'call getViewDescriptor: ' . $this->baseUrl . ', ' . $disConnection->name . ', ' . $disView->name);
				$disView->descriptor = $this->metadata->getViewDescriptor($this->baseUrl, $disConnection->name, $disView->name);
                // LogHandler::Log('-CHALCO', 'INFO', '$disView: ' . view_var($disView));
				$connectionViews[$disView->name] = $disView;
			}
			$disConnection->views = $connectionViews;

			$propertiesView = $connection->propertiesview;
			$disPropertiesView = new DisView();
			$disPropertiesView->name = (string) $propertiesView['name'];
			$propertyFields = array();
			foreach ($propertiesView->field as $propertyField) {
				foreach ($propertyField->attributes() as $key => $value) {
					// should only be one, but does not matter if not , just add them (wwfield="disfield")
					$propertyFields[(string) $key] = (string) $value;
				}
			}
			$disPropertiesView->fields = $propertyFields;
            //LogHandler::Log('-CHALCO', 'INFO', 'propertiesFields: ' . view_var($disPropertiesView->fields));
			$disConnection->propertiesView = $disPropertiesView;

			$this->connections[$disConnection->displayName] = $disConnection;
		}

		$this->queries = array();
		foreach ($configXml->queries->query as $query) {
			$disQuery = new DisQuery();
			$disQuery->name = (string) $query['name'];
			$disQuery->displayName = (string) $query['displayname'];
			$queryParams = array();
			foreach ($query->param as $param) {
				$disParam = new DisQueryParam();
				$disParam->name = (string) $param['name'];
				$disParam->displayName = (string) $param['displayname'];
				$disParam->type = (string) $param['type'];
				if ($disParam->type == 'date' || $disParam->type == 'datetime') {
					if ($param['format']) $disParam->format = (string) $param['format'];
				}
				if ($disParam->type == 'int') {
					if ($param['min']) $disParam->minValue = (int) $param['min'];
					if ($param['max']) $disParam->maxValue = (int) $param['max'];
				}
				if ($disParam->type == 'list') {
					// look for list items
					$items = array();
					foreach ($param->item as $item) {
						$displayName = (string) $item['displayName'];
						switch ($param->listDataType) {
							case 'string':
								$items[$displayName] = (string) $item['value'];
								break;
							case 'int':
								$items[$displayName] = (int) $item['value'];
								break;
							default:
								$items[$displayName] = (string) $item['value'];
							break;
						}
					}
					$disParam->items = $items;
				}
				$queryParams[$disParam->name] = $disParam;
			}
			$disQuery->params = $queryParams;

			$queryConnections = array();
			foreach ($query->connection as $queryConnection) {
				$queryConnections[] = (string) $queryConnection;
			}
			$disQuery->connections = $queryConnections;

			$this->queries[$disQuery->displayName] = $disQuery;
		}
	}
}