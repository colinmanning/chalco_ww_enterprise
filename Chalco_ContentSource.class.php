<?php

require_once BASEDIR . '/server/bizclasses/BizObject.class.php';
require_once BASEDIR . '/server/bizclasses/BizSession.class.php';
require_once BASEDIR . '/server/bizclasses/BizPublication.class.php';

require_once BASEDIR . '/server/interfaces/plugins/connectors/ContentSource_EnterpriseConnector.class.php';
require_once BASEDIR . '/server/interfaces/services/wfl/WflNamedQueryResponse.class.php';
require_once dirname(__FILE__) . '/config.php';

define('RENDITION_NONE', 'none');
define('RENDITION_THUMB', 'thumb');
define('RENDITION_PREVIEW', 'preview');
define('RENDITION_NATIVE', 'native');
define('RENDITION_PLACEMENT', 'placement');
define('SHADOW_PUB', 'Our Globe-Daily');
define('SHADOW_CAT', 'Breaking News');

class Chalco_ContentSource extends ContentSource_EnterpriseConnector
{

    private $namedQueries = null;
    private $ready = false;
    private $pubId = null;
    private $disQueries = null;

    const CONTENTSOURCEID = 'CHALCO';
    const CONTENTSOURCEPREFIX = '_CHALCO_';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        global $wwusername;

        LogHandler::Log('-CHALCO', 'INFO', 'init called');
        if (!$this->ready) {
            initChalco();
            $this->namedQueries = array();
            $this->setupNamedQueries();
            $this->ready = true;
            LogHandler::Log('-CHALCO', 'INFO', 'init done for user: ' . $wwusername);
        }
    }

    private function setupNamedQueries()
    {
        $this->namedQueries = array();
        $this->disQueries = getDisQueries();
        foreach ($this->disQueries as $query) {
            LogHandler::Log('-CHALCO', 'INFO', 'Got named query: ' . $query->disName . ' for DIS query: ' . $query->name);
            $this->namedQueries[] = new NamedQueryType($query->name, $query->parameters);
        }
    }

    /**
     * getContentSourceId
     *
     * Return unique identifier for this content source implementation. Each alien object id needs
     * to start with _<this id>_
     *
     * @return string    unique identifier for this content source, without underscores.
     */
    public function getContentSourceId()
    {
        return self::CONTENTSOURCEID;
    }

    /**
     * getQueries
     *
     * Returns available queries for the content source. These will be shown as named queries.
     * It's Ok to return an empty array, which means the content source is not visible in the
     * Enterprise (content) query user-interface.
     *
     * @return array of NamedQuery
     */
    public function getQueries()
    {
        return $this->namedQueries;
    }

    /**
     * doNamedQuery
     *
     * Execute query on content source.
     *
     * @param string $query Query name as obtained from getQueries
     * @param array of Property    $params        Query parameters as filled in by user
     * @param unsigned int            $firstEntry    Index of first requested object of total count (TotalEntries)
     * @param unsigned int            $maxEntries Max count of requested objects (zero for all, nil for default)
     * @param array of QueryOrder    $order
     *
     * @return WflNamedQueryResponse
     */
    public function doNamedQuery($query, $params, $firstEntry, $maxEntries, $order)
    {
        global $disConfig;

        $disQuery = $disConfig->queries[$query];
        // LogHandler::Log('-CHALCO', 'DEBUG', 'query name: "'.$query.'" with params: '.view_var($params));
        $db = null;
        $viewName = null;
        $viewDescriptor = null;
        $mandatoryFields = null;
        $queryParams = array();
        foreach ($params as $param) {
            if ($param->Property == 'db') {
                $db = $param->Value;
            } else if ($param->Property == 'dbView') {
                $viewDisplayName = $param->Value;
            } else {
                // first check for date parameters, as formatting will be required
                $paramName = $param->Property;
                $disQueryParam = $disQuery->params[$paramName];
                if ($disQueryParam->type == 'date' || $disQueryParam->type == 'datetime') {
                    // LogHandler::Log('-CHALCO', 'DEBUG', 'INFO', 'date query param: "'.$param->Value);
                    // LogHandler::Log('-CHALCO', 'DEBUG', 'INFO', 'dis query format: "'.$disQueryParam->format);
                    $t = strtotime($param->Value);
                    // LogHandler::Log('-CHALCO', 'DEBUG', 'INFO', 'time for query: "'.$t);
                    $queryParams[$param->Property] = date($disQueryParam->format, $t);
                } else {
                    $queryParams[$param->Property] = $param->Value;
                }
            }
        }
        $queryConnection = $disConfig->connections[$db];
        $thisView = null;
        $dbName = $queryConnection->name;
        foreach ($queryConnection->views as $view) {
            if ($view->displayName == $viewDisplayName) {
                $thisView = $view;
                $viewName = $view->name;
                $viewDescriptor = $queryConnection->views[$viewName]->descriptor;
                $mandatoryFields = $queryConnection->views[$viewName]->mandatoryFields;
                break;
            }
        }
        LogHandler::Log('-CHALCO', 'DEBUG', 'view descriptor: "' . view_var($viewDescriptor));

        $records = null;
        LogHandler::Log('-CHALCO', 'DEBUG', 'query param is: ' . $query);
        LogHandler::Log('-CHALCO', 'DEBUG', 'query name is: ' . $disQuery->name);
        LogHandler::Log('-CHALCO', 'DEBUG', 'disQuery: ' . view_var($disQuery));
        $result = null;
        if ($disQuery->name == "textsearch") {
            $result = $disConfig->metadata->textSearch($disConfig->baseUrl, $dbName, $viewName, $queryParams['text'], 0, 5);
        } else {
            $result = $disConfig->metadata->findAssets($disConfig->baseUrl, $dbName, $viewName, $disQuery->name, $queryParams, 0, 5);
        }
        if ($result != null) {
            $records = $result->records;
        }
        LogHandler::Log('-CHALCO', 'DEBUG', 'view fields: "' . view_var($viewDescriptor->fields));
        LogHandler::Log('-CHALCO', 'DEBUG', 'mandatory fields: "' . view_var($mandatoryFields));
        $cols = $this->buildColumns($viewDescriptor->fields, $thisView);
        $rows = $this->buildRows($viewDescriptor->fields, $db, $cols, $records, $thisView);
        LogHandler::Log('-CHALCO', 'DEBUG', 'cols: ' . view_var($cols));
        LogHandler::Log('-CHALCO', 'DEBUG', 'rows: ' . view_var($rows));
        LogHandler::Log('-CHALCO', 'DEBUG', '$firstEntry: ' . $firstEntry . ' - total - ' . $result->total);
        return new WflNamedQueryResponse($cols, $rows, null, null, null, null, $firstEntry, count($rows), $result->total, null);
    }

    /**
     * getAlienObject
     *
     * Gets alien object. In case of rendition 'none' the lock param can be set to true, this is the
     * situation that Properties dialog is shown. If content source allows this, return the object
     * on failure the dialog will be read-only. If Property dialog is ok-ed, a shadow object will
     * be created. The object is assumed NOT be locked, hence there is no unlock sent to content source.
     *
     * @param string $alienId Alien object id, so include the _<ContentSourceId>_ prefix
     * @param string $rendition 'none' (to get properties only), 'thumb', 'preview' or 'native'
     * @param boolean $lock See method comment.
     *
     * @return Object
     */

    public function getAlienObject($alienId, $rendition, $lock)
    {
        global $disConfig;

        $result = null;
        LogHandler::Log('-CHALCO', 'INFO', 'getAlienObject - called with rendition: ' + $rendition);
        $alienId = $alienId;
        $rendition = $rendition;
        $lock = $lock;
        LogHandler::Log('-CHALCO', 'INFO', 'getAlienObject - alienId: ' . $alienId);
        $fullId = substr($alienId, strlen(self::CONTENTSOURCEPREFIX));
        $p = strpos($fullId, '_');
        $db = substr($fullId, 0, $p);
        $id = substr($fullId, $p + 1);

        $queryConnection = $disConfig->connections[$db];
        $dbName = $queryConnection->name;

        $disMeta = $disConfig->metadata->findAsset($id, $disConfig->baseUrl, $dbName, $queryConnection->propertiesView->name);
        LogHandler::Log('-CHALCO', 'INFO', 'getAlienObject - metadata: ' . view_var($disMeta));
        //$this->fillMetadata($woodwingMeta, $alienId, $disMeta);
        //LogHandler::Log('-CHALCO', 'INFO', 'getAlienObject - woodwingMeta: ' . view_var($woodwingMeta));
        $isPreview = false;
        $isNative = false;
        $previewName = '';
        $content = null;
        switch ($rendition) {
            case RENDITION_NONE:
                // just metadata
                break;
            case RENDITION_THUMB:
                // a thumbnail
                $isPreview = true;
                $previewName = $disConfig->thumbnail;
                break;
            case RENDITION_PREVIEW:
                // a preview
                $isPreview = true;
                $previewName = $disConfig->preview;
                break;
            case RENDITION_NATIVE:
            case RENDITION_PLACEMENT:
                // the asset
                $isNative = true;
                break;
        }
        $files = array();
        if ($isPreview) {
            LogHandler::Log('-CHALCO', 'INFO', 'generating preview for filename: ' . $disMeta->FILENAME . ' using preview name: ' . $previewName);
            $files[] = $disConfig->metadata->getAttachment($disMeta->FILENAME, $id, $disConfig->baseUrl, $dbName, $previewName, $rendition);

            //$preview = $disConfig->metadata->previewAsset($id, $disConfig->baseUrl, $dbName, $previewName);
            //LogHandler::Log('-CHALCO', 'INFO', 'preview returned by DIS: ' . view_var($preview));

            //$files[] = new Attachment($rendition, $preview['type'], new SOAP_Attachment('Content', 'application/octet-stream', null, $preview['body']));
            //$files[] = new Attachment($rendition, $preview['type'], $preview['body']);
            //LogHandler::Log('-CHALCO', 'INFO', 'file attachments: ' . view_var($files));
        } else if ($isNative) {
            $fileData = $disConfig->metadata->downloadAsset($id, $disConfig->baseUrl, $dbName);
            $files[] = new Attachment($rendition, $fileData['type'], new SOAP_Attachment('Content', 'application/octet-stream', null, $fileData['body']));
        }

            /*
            $result = new Object($woodwingMeta, // meta data
                array(), null, // relations, pages
                $files, // Files array of attachment
                null, null, null // messages, elements, targets
            );
            */

            $result = new Object ();
            $result->MetaData = new MetaData();
            $result->Relations = array ();
            $result->Files = $files;
            $this->fillMetadata($result->MetaData, $alienId, $disMeta);

        //LogHandler::Log('-CHALCO', 'INFO', 'finally alien object is: ' . view_var($result));

        LogHandler::Log('-CHALCO', 'DEBUG', 'Woodwing MetaData object: ' . view_var($result));
        return $result;
    }


    /**
     * deleteAlienObject
     *
     * Deletion of alien object.
     *
     * Default implementation throws an invalid operation exception
     *
     * @param string $alienId Alien id
     *
     * @return <nothing>
     */
    public function deleteAlienObject($alienId)
    {
        // keep code analyzer happy for unused params:
        LogHandler::Log('-CHALCO', 'DEBUG', 'deleteAlienObject - called with alienId: ' . $alienId);
        $alienId = $alienId;
        throw new BizException('ERR_INVALID_OPERATION', 'Server', "Chalco_ContentSource doesn't implement deleteAlienObject");
    }

    /**
     * listAlienObjectVersions
     *
     * Returns versions of alien object
     *
     * Default implementation returns an empty array, which makes client show an empty dialog
     * and also prevents that get/restoreAlienObjectVerison will be called
     *
     * @param string $alienId Alien id
     * @param string $rendition Rendition to include in the version info
     *
     * @return array of VersionInfo
     */
    public function listAlienObjectVersions($alienId, $rendition)
    {
        // No versioning on file system
        // return an empty array, which will show empty version dialog.

        // keep code analyzer happy for unused params:
        $alienId = $alienId;
        $rendition = $rendition;
        return array();
    }

    /**
     * getAlienObjectVersion
     *
     * Returns versions of alien object
     *
     * Default implementation throws invalid operation exception, but this should never be called
     * if listAlientObjectVersions returns an empty array.
     *
     * @param string $alienId Alien id
     * @param string $version Version to get as returned by listAlienVersons
     * @param string $rendition Rendition to get
     *
     * @return VersionInfo
     */
    public function getAlienObjectVersion($alienId, $version, $rendition)
    {
        // keep code analyzer happy for unused params:
        $alienId = $alienId;
        $version = $version;
        $rendition = $rendition;
        throw new BizException('ERR_INVALID_OPERATION', 'Server', "Chalco_ContentSource doesn't implement getAlienObjectVersion");
    }

    /**
     * restoreAlienObjectVersion
     *
     * Restores versions of alien object
     *
     * Default implementation throws invalid operation exception, but this should never be called
     * if listAlientObjectVersions returns an empty array.
     *
     * @param string $alienId Alien id
     * @param string $version Version to get as returned by listAlienVersons
     *
     * @return <nothing>
     */
    public function restoreAlienObjectVersion($alienId, $version)
    {
        // keep code analyzer happy for unused params:
        $alienId = $alienId;
        $version = $version;
        throw new BizException('ERR_INVALID_OPERATION', 'Server', "Chalco_ContentSource doesn't implement restoreAlienObjectVersion");
    }

    /**
     * createShadowObject
     *
     * Create shadow object for specified alien object. The actual creation is done by Enterprise,
     * the Content Sources needs to instantiate and fill in an object of class Object.
     * When an empty name is filled in, autonaming will be used.
     * It's up to the content source implementation if any renditions (like thumb/preview) are stored
     * inside Enterprise. If any rendition is stored in Enterprise it's the content source implementation's
     * responsibility to keep these up to date. This could for example be checked whenever the object
     * is requested via getShadowObject
     *
     * @param string $alienId Alien object id, so include the _<ContentSourceId>_ prefix
     * @param Object $destObject In saome cases (CopyObject, SendToNext, Create relatio)
     * this can be partly filled in by user, in other cases this is null.
     *    In some cases this is mostly empty, so be aware.
     *
     * @return Object filled in with all fields, the actual creation of the Enterprise object is done by Enterprise.
     */
    public function createShadowObject($alienId, $destObject)
    {
        global $disConfig;
        $result = null;

        $fullId = substr($alienId, strlen(self::CONTENTSOURCEPREFIX));
        $p = strpos($fullId, '_');
        $db = substr($fullId, 0, $p);
        $id = substr($fullId, $p + 1);
        LogHandler::Log('-CHALCO', 'DEBUG', 'createShadowObject - called with alienId: ' . $alienId . ' mapped to id: ' . $id);

        $queryConnection = $disConfig->connections[$db];
        $dbName = $queryConnection->name;

        $files = array();
        $fileData = $disConfig->metadata->downloadAsset($id, $disConfig->baseUrl, $dbName);
        $files[] = new Attachment('native', $fileData['type'], $fileData['body']);

        $fileData = $disConfig->metadata->previewAssetMaxSize($id, $disConfig->baseUrl, $dbName, $disConfig->thumbnailSize);
        $files[] = new Attachment('thumb', $fileData['type'], $fileData['body']);

        $fileData = $disConfig->metadata->previewAssetMaxSize($id, $disConfig->baseUrl, $dbName, $disConfig->previewSize);
        $files[] = new Attachment('preview', $fileData['type'], $fileData['body']);

        $dbMeta = $disConfig->metadata->findAsset($id, $disConfig->baseUrl, $dbName, $queryConnection->propertiesView->name);
        $meta = null;
        if ($destObject) {
            $meta = $destObject->MetaData;
        } else {
            $meta = new MetaData();
            $destObject = new Object($meta, array(), null, null, null, null, null);
        }
        $this->fillMetadata($meta, $alienId, $dbMeta);
        $destObject->Files = $files;
        $destObject->Targets = array();
        return $destObject;
    }

    /**
     * getShadowObject
     *
     * Get shadow object. Meta data is all set already, access rights have been set etc.
     * All that is required is filling in the files for the requested object.
     * Furthermore the meta data can be adjusted if needed.
     * If Files is null, Enterprise will fill in the files
     *
     * Default implementation does nothing, leaving it all up to Enterpruse
     *
     * @param string $alienId Alien object id
     * @param string $object Shadow object from Enterprise
     * @param array $objprops Array of all properties, both the public (also in Object) as well as internals
     * @param boolean $lock Whether object should be locked
     * @param string $rendition Rendition to get
     *
     * @return Object
     */
    public function getShadowObject($alienId, &$object, $objprops, $lock, $rendition)
    {
        LogHandler::Log('-CHALCO', 'DEBUG', 'getShadowObject called');
        // keep code analyzer happy:
        $alienId = $alienId;
        $object = $object;
        $objprops = $objprops;
        $lock = $lock;
        $rendition = $rendition;
        LogHandler::Log('-CHALCO', 'DEBUG', 'getShadowObject - called for ' . $object->MetaData->BasicMetaData->ID . '(' . $object->MetaData->BasicMetaData->DocumentID . ')');
    }

    /**
     * saveShadowObject
     *
     * Saves shadow object. This is called after update of DB records is done in Enterprise, but
     * before any files are stored. This allows content source to save the files externally in
     * which case Files can be cleared. If Files not cleared, Enterprise will save the files
     *
     * Default implementation does nothing, leaving it all up to Enterpruse
     *
     * @param string $alienId Alien id of shadow object
     * @param string $object
     *
     * @return Object
     */
    public function saveShadowObject($alienId, &$object)
    {
        LogHandler::Log('-CHALCO', 'DEBUG', 'saveShadowObject called');
        // keep code analyzer happy:
        $alienId = $alienId;
        $object = $object;
        LogHandler::Log('-CHALCO', 'DEBUG', 'saveShadowObject - called for ' . $object->MetaData->BasicMetaData->ID . '(' . $object->MetaData->BasicMetaData->DocumentID . ')');
    }

    /**
     * deleteShadowObject
     *
     * Deletion of shadow object, called just before the shadow object record is deleted
     * or after the object is restored from trash.
     *
     * Default implementation does nothing
     *
     * @param string $alienId Alien id of shadow object
     * @param string $shadowId Enterprise id of shadow object
     * @param boolean $permanent Whether object will be permanently deleted
     * @param boolean $restore if object is restored from trash
     *
     * @return void <nothing>
     */
    public function deleteShadowObject($alienId, $shadowId, $permanent, $restore)
    {
        // keep code analyzer happy:
        $alienId = $alienId;
        $shadowId = $shadowId;
        $permanent = $permanent;
        $restore = $restore;
        LogHandler::Log('-CHALCO', 'DEBUG', 'deleteShadowObject called for ' . $shadowId);
    }

    /**
     * listShadowObjectVersions
     *
     * Returns versions of show object or null if Enterprise should handle this
     *
     * Default implementation returns null to have Enterprise handle this.
     *
     * @param string $alienId Alien id of shadow object
     * @param string $shadowId Enterprise id of shadow object
     * @param string $rendition Rendition to include in the version info
     *
     * @return array of VersionInfo or null if Enterprise should handle this
     */
    public function listShadowObjectVersions($alienId, $shadowId, $rendition)
    {
        // keep code analyzer happy:
        $alienId = $alienId;
        $shadowId = $shadowId;
        $rendition = $rendition;
        LogHandler::Log('-CHALCO', 'DEBUG', 'istShadowObjectVersions called for ' . $shadowId);
        return null;
    }

    /**
     * getShadowObjectVersion
     *
     * Returns versions of shadow object or null if Enterprise should handle this
     *
     * Default implementation returns null to have Enterprise handle this.
     *
     * @param string $alienId Alien id of shadow object
     * @param string $shadowId Enterprise id of shadow object
     * @param string $version Version to get as returned by listShadowVersons
     * @param string $rendition Rendition to get
     *
     * @return VersionInfo or null if Enterprise should handle this
     */
    public function getShadowObjectVersion($alienId, $shadowId, $version, $rendition)
    {
        // keep code analyzer happy:
        $alienId = $alienId;
        $shadowId = $shadowId;
        $version = $version;
        $rendition = $rendition;
        LogHandler::Log('-CHALCO', 'DEBUG', 'getShadowObjectVersion called for ' . $shadowId);
        return null;
    }

    /**
     * restoreShadowObjectVersion
     *
     * Restores versions of alien object, true when handled or null if Enterprise should handle this
     *
     * Default implementation returns null to have Enterprise handle this.
     *
     * @param string $alienId Alien id of shadow object
     * @param string $shadowId Enterprise id of shadow object
     * @param string $version Version to get as returned by listAlienVersons
     *
     * @return true when handled or null if Enterprise should handle this
     */
    public function restoreShadowObjectVersion($alienId, $shadowId, $version)
    {
        // keep code analyzer happy:
        $alienId = $alienId;
        $shadowId = $shadowId;
        $version = $version;
        LogHandler::Log('-CHALCO', 'DEBUG', 'restoreShadowObjectVersion called for ' . $shadowId);
        return null;
    }

    /**
     * copyShadowObject
     *
     * Copies a shadow object.
     * All that is required is filling in the files for the copied object.
     * Furthermore the meta data can be adjusted if needed.
     * If Files is null, Enterprise will fill in the files
     *
     * Default implementation creates a new shadow object.
     *
     * @param string $alienId Alien id of shadow object
     * @param Object $srcObject Source Enterprise object (only metadata filled)
     * @param Object $destObject Destination Enterprise object
     *
     * @return Object    filled in with all fields, the actual creation of the Enterprise object is done by Enterprise.
     */
    public function copyShadowObject($alienId, $srcObject, $destObject)
    {
        // keep analyzer happy
        $srcObject = $srcObject;

        LogHandler::Log('-CHALCO', 'DEBUG', 'copyShadowObject called for ' . $alienId);

        $shadowObject = $this->createShadowObject($alienId, $destObject);

        return $shadowObject;
    }

    // ===================================================================================

    // Generic connector methods that can be overruled by a content source implementation:
    public function getPrio()
    {
        return self::PRIO_DEFAULT;
    }

    // Helper methods which don't have to be implemented by concrete content sources:
    public function implementsQuery($query)
    {
        $queries = $this->getQueries();
        foreach ($queries as $q) {
            if ($q->Name == $query) return true;
        }
        return false;
    }

    // Helper methods which don't have to be implemented by concrete content sources:
    // Returns true if the specified content source id is from this content source
    public function isContentSourceId($contentSourceId)
    {
        return $this->getContentSourceId() == $contentSourceId;
    }

    public function isInstalled()
    {
        return $this->ready;
    }

    /**
     * Assumes a view called 'woodwing-properties' with a certain set of fields defined
     * TODO drive this from the config file of the plugin
     * @param <type> $meta the Woodwing metadata to be returned
     * @param <type> $alienID the Woodwing alien id, combines DIS content source id, and DIS record id
     * @param <type> $metaData the DIS metadata
     */
    private function fillMetadata(&$meta, $alienId, $disMetadata)
    {
        global $disConfig;
        LogHandler::Log('-CHALCO', 'DEBUG', 'called');

        $fullId = substr($alienId, strlen(self::CONTENTSOURCEPREFIX));
        $p = strpos($fullId, '_');
        $db = substr($fullId, 0, $p);
        $id = substr($fullId, $p + 1);

        $queryConnection = $disConfig->connections[$db];
        $dbName = $queryConnection->name;

        $propertiesViewFields = $queryConnection->propertiesView->fields;

        // LogHandler::Log('-CHALCO', 'DEBUG', 'DIS metadata: '. view_var($disMetadata));
        // LogHandler::Log('-CHALCO', 'DEBUG', 'DIS propertiesViewField: '. view_var($propertiesViewFields));
        $metaData = get_object_vars($disMetadata);
        LogHandler::Log('-CHALCO', 'DEBUG', 'metadata: ' . view_var($metaData));

        $publication = null;
        $category = null;
        $status = null;
        $article = null;
        $this->getEnterpriseContext($publication, $category, $status, $article);
        // LogHandler::Log('-CHALCO', 'DEBUG', 'publication:'. view_var($publication));
        // LogHandler::Log('-CHALCO', 'DEBUG', 'status:'. view_var($status));
        // LogHandler::Log('-CHALCO', 'DEBUG', 'category:'. view_var($category));
        //if (!$meta) {
        //    $meta = new Object();
        //}
        if (!property_exists($meta, 'BasicMetaData')) {
            $meta->BasicMetaData = new BasicMetaData();
        }

        //$meta->BasicMetaData->Type = 'Image';
        $meta->BasicMetaData->Type = self::getWoodwingTypeFromFileName($metaData[$propertiesViewFields['BasicMetaData-Name']]);
        $meta->BasicMetaData->Publication = $publication;
        $meta->BasicMetaData->Category = new Category();
        $meta->BasicMetaData->Category->Id = $category->Id;
        $meta->BasicMetaData->Category->Name = $category->Name;
        $meta->BasicMetaData->ContentSource = $this->getContentSourceId();
        $meta->BasicMetaData->ID = $alienId;
        $meta->BasicMetaData->DocumentID = $metaData[$propertiesViewFields['BasicMetaData-DocumentID']];
        $meta->BasicMetaData->Name = substr($metaData[$propertiesViewFields['BasicMetaData-Name']], 0, 27); // name, limit to 27 characters, just to be safe;

        if (!property_exists($meta, 'ContentMetaData')) {
            $meta->ContentMetaData = new ContentMetaData();
        }

        // ensure the following content fields are set, also when contentmeta data already available:
        require_once BASEDIR . '/server/utils/MimeTypeHandler.class.php';
        $meta->ContentMetaData->Format = MimeTypeHandler::filePath2MimeType($metaData[$propertiesViewFields['BasicMetaData-Name']]);
        //if (!empty($propertiesViewFields['ContentMetaData-Format'])) $meta->ContentMetaData->Format = $metaData[$propertiesViewFields['ContentMetaData-Format']];
        if (!empty($propertiesViewFields['ContentMetaData-Width'])) $meta->ContentMetaData->Width = $metaData[$propertiesViewFields['ContentMetaData-Width']];
        if (!empty($propertiesViewFields['ContentMetaData-Height'])) $meta->ContentMetaData->Height = $metaData[$propertiesViewFields['ContentMetaData-Height']];
        if (!empty($propertiesViewFields['ContentMetaData-FileSize'])) $meta->ContentMetaData->FileSize = $metaData[$propertiesViewFields['ContentMetaData-FileSize']];
        if (!empty($propertiesViewFields['ContentMetaData-Dpi'])) $meta->ContentMetaData->Dpi = $metaData[$propertiesViewFields['ContentMetaData-Dpi']];
        if (!empty($propertiesViewFields['ContentMetaData-ColorSpace'])) $meta->ContentMetaData->ColorSpace = $metaData[$propertiesViewFields['ContentMetaData-ColorSpace']];
        if (!empty($propertiesViewFields['ContentMetaData-Description'])) $meta->ContentMetaData->Description = $metaData[$propertiesViewFields['ContentMetaData-Description']];
        if (!empty($propertiesViewFields['ContentMetaData-DescriptionAuthor'])) $meta->ContentMetaData->DescriptionAuthor = $metaData[$propertiesViewFields['ContentMetaData-DescriptionAuthor']];
        if (!empty($propertiesViewFields['ContentMetaData-Keywords'])) $meta->ContentMetaData->Keywords = $metaData[$propertiesViewFields['ContentMetaData-Keywords']];
        if (!empty($propertiesViewFields['ContentMetaData-Slugline'])) $meta->ContentMetaData->Slugline = $metaData[$propertiesViewFields['ContentMetaData-Slugline']];

        if (!property_exists($meta, 'RightsMetaData')) {
            $meta->RightsMetaData = new RightsMetaData();
        }
        if (!empty($propertiesViewFields['RightsMetaData-Copyright'])) $meta->RightsMetaData->Copyright = $metaData[$propertiesViewFields['RightsMetaData-Copyright']];
        if (!empty($propertiesViewFields['RightsMetaData-CopyrightMarked'])) $meta->RightsMetaData->CopyrightMarked = $metaData[$propertiesViewFields['RightsMetaData-CopyrightMarked']];
        if (!empty($propertiesViewFields['RightsMetaData-CopyrightURL'])) $meta->RightsMetaData->CopyrightURL = $metaData[$propertiesViewFields['RightsMetaData-CopyrightURL']];

        if (!property_exists($meta, 'SourceMetaData')) {
            $meta->SourceMetaData = new SourceMetaData();
        }
        if (!empty($propertiesViewFields['SourceMetaData-Credit'])) $meta->SourceMetaData->Credit = $propertiesViewFields['SourceMetaData-Credit'];
        if (!empty($propertiesViewFields['SourceMetaData-Source'])) $meta->SourceMetaData->Source = $propertiesViewFields['SourceMetaData-Source'];
        if (!empty($propertiesViewFields['SourceMetaData-Author'])) $meta->SourceMetaData->Author = $metaData[$propertiesViewFields['SourceMetaData-Author']];

        if (!property_exists($meta, 'WorkflowMetaData')) {
            $meta->WorkflowMetaData = new WorkflowMetaData();
        }
        if (!empty($propertiesViewFields['WorkflowMetaData-Rating'])) {
            $r = $metaData[$propertiesViewFields['WorkflowMetaData-Rating']];
            if (is_object($r) && isset($r->DisplayString)) {
                $meta->WorkflowMetaData->Rating = $r->DisplayString;
            } else {
                $meta->WorkflowMetaData->Rating = $r;
            }
        }
        if (!empty($propertiesViewFields['WorkflowMetaData-Urgency'])) {
            $u = $metaData[$propertiesViewFields['WorkflowMetaData-Urgency']];
            if (is_object($u) && isset($u->DisplayString)) {
                $meta->WorkflowMetaData->Urgency = $u->DisplayString;
            } else {
                $meta->WorkflowMetaData->Urgency = $u;
            }
        }
        if (!empty($propertiesViewFields['WorkflowMetaData-Modified'])) $meta->WorkflowMetaData->Modified = decodeJsonDate($metaData[$propertiesViewFields['WorkflowMetaData-Modified']], $disConfig->dateFieldFormat);
        if (!empty($propertiesViewFields['WorkflowMetaData-Created'])) $meta->WorkflowMetaData->Created = decodeJsonDate($metaData[$propertiesViewFields['WorkflowMetaData-Created']], $disConfig->dateFieldFormat);
        $meta->WorkflowMetaData->State = $status;

        if(!$meta->ExtraMetaData) {
            $meta->ExtraMetaData = array();
        }


        //if (!$meta->TargetMetaData) {
        //	$meta->TargetMetaData =  new TargetMetaData();
        //}

        //if (!$meta->ExtraMetaData) {
        //	$meta->ExtraMetaData =  new ExtraMetaData();
        //}

    }

    private function getEnterpriseContext(&$publication, &$category, &$status, &$article)
    {
        global $disConfig;

        require_once 'Zend/Registry.php';
        /*
        if (Zend_Registry::isRegistered('CHALCO-Publication')) {
            $category = Zend_Registry::delete('CHALCO-Publication');
        }
        if (Zend_Registry::isRegistered('CHALCO-Category')) {
            $category = Zend_Registry::delete('CHALCO-Category');
        }
        if (Zend_Registry::isRegistered('CHALCO-Status')) {
            $category = Zend_Registry::delete('CHALCO-Status');
        }
        */

        // Get list of publications from Enterpise. If available we use WW News
        require_once BASEDIR . '/server/bizclasses/BizSession.class.php';
        require_once BASEDIR . '/server/bizclasses/BizPublication.class.php';
        $username = BizSession::getShortUserName();
        //LogHandler::Log('-CHALCO', 'DEBUG', 'User: ' . view_var($username));
        // Get all publication info is relatively expensive. In case a thumbnail overview is used this method is called
        // once per thumbnail, adding up to significant time. Hence we cache the results for the session:

        if (Zend_Registry::isRegistered('CHALCO-Publication')) {
            $publication = Zend_Registry::get('CHALCO-Publication');
        } else {
            $pubs = BizPublication::getPublications($username);
            // LogHandler::Log('-CHALCO', 'DEBUG', 'User publications: ' . view_var($pubs));
            // Default to first, look next if we can find one with the configured name:
            $pubFound = $pubs[0];
            foreach ($pubs as $pub) {
                // LogHandler::Log('-CHALCO', 'DEBUG', 'got publication name: "'. $pub->Name . '"');
                if ($pub->Name == $disConfig->defaultBrand) {
                    $pubFound = $pub;
                    break;
                }
            }
            //$publication = new Publication($pubFound->Id);
            //$publication->Name = $pubFound->Name;
            $publication = $pubFound;
            // LogHandler::Log('-CHALCO', 'DEBUG', 'got pub: ' . view_var($pubFound));
            Zend_Registry::set('CHALCO-Publication', $publication);
        }

        if (Zend_Registry::isRegistered('CHALCO-Category')) {
            $category = Zend_Registry::get('CHALCO-Category');
        } else {
            $categories = BizPublication::getSections($username, $publication->Id);
            // LogHandler::Log('-CHALCO', 'DEBUG', 'User categories: ' . view_var($categories));
            // Default to first, look next if we can find one with the configured name:
            $catFound = $categories[0];
            foreach ($categories as $cat) {
                // LogHandler::Log('-CHALCO', 'DEBUG', 'got category name: "'. $cat->Name . '"');
                if ($cat->Name == $disConfig->defaultCategory) {
                    $catFound = $cat;
                    break;
                }
            }
            //$category = new Category($catFound->Id);
            //$category->Name = $catFound->Name;
            $category = $catFound;
            Zend_Registry::set('CHALCO-Category', $category);
        }

        if (Zend_Registry::isRegistered('CHALCO-Status')) {
            $status = Zend_Registry::get('CHALCO-Status');
        } else {
            require_once BASEDIR . '/server/bizclasses/BizWorkflow.class.php';
            $states = BizWorkflow::getStates($username, $publication->Id, null /*issue*/, $category->Id, $article ? 'Article' : 'Image');
            // LogHandler::Log('-CHALCO', 'DEBUG', 'User states: ' . view_var($states));
            // Default to first, look next if we can find one with the configured name:
            $statFound = $states[0];
            foreach ($states as $stat) {
                //LogHandler::Log('-CHALCO', 'DEBUG', 'got status name: "' . $stat->Name . '"');
                if ($stat->Name == $disConfig->defaultStatus) {
                    $statFound = $stat;
                    break;
                }
            }

            //$status = new State($statFound->Id);
            //$status->Name = $statFound->Name;
            $status = $statFound;
            Zend_Registry::set('CHALCO-Status', $status);
            //LogHandler::Log('-CHALCO', 'DEBUG', 'Got publication: ' . view_var($pub));
            //LogHandler::Log('-CHALCO', 'DEBUG', 'Got category: ' . view_var($category));
            //LogHandler::Log('-CHALCO', 'DEBUG', 'Got state: ' . view_var($status));
        }
    }

    private function getPublication()
    {
        // do we have pub id already cached?
        if (!$this->pubId) {
            $dum1 = '';
            $dum2 = '';
            $dum3 = '';
            $this->getEnterpriseContext($this->pubId, $dum1, $dum2, $dum3);
        }
        return $this->pubId->Id;
    }

    /*
     * Build an array of Woodwing properties for DIS field names
    * The fieldDescriptors are build form the field Names, so the array indexing maps correctly
    */
    private function buildColumns($fieldDescriptors, $view)
    {
        $result = array();
        $result[] = new Property('ID', 'ID', 'string'); // Required as 1st
        $result[] = new Property('Type', 'Type', 'string'); // Required as 2nd
        $result[] = new Property('Name', 'Name', 'string'); // Required as 3rd
        //if (self::calledByContentStation()) {
        $result[] = new Property('Format', 'Format', 'string'); // Required by Content Station
        $result[] = new Property('PublicationId', 'PublicationId', 'string'); // Required by Content Station
        $result[] = new Property('thumbUrl', 'thumbUrl', 'string'); // Thumb URL for Content Station
        $result[] = new Property('Slugline', 'Slugline', 'string'); // Slugline for Content Station
        //}
        for ($i = 0; $i < sizeof($fieldDescriptors); $i += 1) {
            if ($fieldDescriptors[$i]->name == 'ID' ||
                $fieldDescriptors[$i]->name == $view->assetNameField ||
                $fieldDescriptors[$i]->name == $view->assetFormatField ||
                $fieldDescriptors[$i]->name == $view->assetSluglineField
            )
                continue;

            $fieldType = getWoodwingType($fieldDescriptors[$i]->dataType, $fieldDescriptors[$i]->valueInterpretation);
            $prop = new Property($fieldDescriptors[$i]->name, $fieldDescriptors[$i]->name, $fieldType);
            $result[] = $prop;
        }

        return $result;
    }

    private function buildRows($fieldDescriptors, $db, $cols, $records, $view)
    {
        global $disConfig;
        // LogHandler::Log('-CHALCO', 'DEBUG', 'called');
        // LogHandler::Log('-CHALCO', 'INFO', 'view : "'.view_var($view));
        $result = array();
        foreach ($records as $record) {
            $fields = get_object_vars($record);
            // LogHandler::Log('-CHALCO', 'DEBUG', '$fields: '. view_var($fields));
            $rec = array();
            $rec[] = self::CONTENTSOURCEPREFIX . $db . '_' . $fields['ID'];
            //$rec[] = 'Image';
            $rec[] = self::getWoodwingTypeFromFileName($fields[$view->assetNameField]);
            // LogHandler::Log('-CHALCO', 'DEBUG', 'file '. $fields[$view->assetNameField] . " has Woodwing Type: " . $rec[1]);
            $rec[] = '' . $fields[$view->assetNameField];
            //if (self::calledByContentStation()) {
            $rec[] = '' . $fields[$view->assetFormatField];
            $rec[] = '' . $this->getPublication(); // PublicationId
            $dbName = $disConfig->connections[$db]->name;
            $rec[] = $disConfig->baseUrl . '/preview/' . $dbName . '/fetch?id=' . $fields['ID'] . '&name=' . $disConfig->thumbnail;
            $rec[] = '' . $fields[$view->assetSluglineField];

            for ($i = 7; $i < count($cols); $i += 1) {
                if ($fieldDescriptors[$i]->name == 'ID' ||
                    $fieldDescriptors[$i]->name == $view->assetNameField ||
                    $fieldDescriptors[$i]->name == $view->assetFormatField ||
                    $fieldDescriptors[$i]->name == $view->assetSluglineField
                )
                    continue;

                $f = $fields[$cols[$i]->Name];

                if (is_object($f) && isset($f->DisplayString)) {
                    LogHandler::Log('-CHALCO', 'DEBUG', 'field is object: ' . view_var($f));
                    LogHandler::Log('-CHALCO', 'DEBUG', 'field DisplayString: ' . $f->DisplayString);
                }


                if ($cols[$i]->Type == "datetime") {
                    $rec[] = decodeJsonDate($fields[$cols[$i]->Name], $disConfig->dateFieldFormat);
                } else if (is_object($f) && isset($f->DisplayString)) {
                    $rec[] = $f->DisplayString;
                } else {
                    $rec[] = $f;
                }
                // LogHandler::Log('-CHALCO', 'DEBUG', 'field: '.$cols[$i]->Name.' has value: '.$fields[$cols[$i]->Name]);
            }
            $result[] = $rec;
        }
        // LogHandler::Log('-CHALCO', 'DEBUG', 'result: '.view_var($result));
        // LogHandler::Log('-CHALCO', 'DEBUG', 'columns: '.view_var($cols));

        return $result;
    }

    private static function getWoodwingTypeFromFileName($filename)
    {
        require_once BASEDIR . '/server/utils/MimeTypeHandler.class.php';

        $mimeType = '';
        $result = MimeTypeHandler::filename2ObjType($mimeType, $filename);
        if (!$result) {
            $result = 'Other';
        }
        return $result;
    }


    /**
     * calledByContentStation
     *
     * Returns true if the client is Content Station
     */
    static final public function calledByContentStation()
    {
        require_once BASEDIR . '/server/bizclasses/BizSession.class.php';
        require_once BASEDIR . '/server/dbclasses/DBTicket.class.php';

        $app = DBTicket::DBappticket(BizSession::getTicket());

        return stristr($app, 'content station');
    }

}
