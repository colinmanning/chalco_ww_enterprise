<?php
/**
 * DisMetadata provides a set of methods to communicate with the Digital Integration Server (DIS)
 *
 */

require_once 'Zend/Http/Client.php';
require_once 'Zend/Http/Response.php';

class DisMetadata {
	
	public function DisMetadata() {
	}

	public function getViewDescriptor($baseUrl, $db, $viewName) {
		$result = null;
		$url = $baseUrl . '/admin/' . $db . '/describe?view=' . $viewName;
        LogHandler::Log('-CHALCO', 'DEBUG', 'url: '.$url);
		try {
			$r = $this->httpRequest($url);
			if ($r->getStatus() == 200) {
				$result = json_decode($r->getBody());
			}
		} catch (HttpException $e) {
			echo $e;
		}
		return $result;
	}

	public function getAllViewDescriptors($baseUrl, $db) {
		$result = null;
		$url = $baseUrl . '/admin/' . $db . '/describe';
        LogHandler::Log('-CHALCO', 'DEBUG', 'url: '.$url);
		try {
			$r = $this->httpRequest($url);
			if ($r->getStatus() == 200) {
				$result = json_decode($r->getBody());
			}
		} catch (HttpException $e) {
			echo $e;
		}
		return $result;
	}

	/**
	 *
	 * @param <type> $baseUrl
	 * @param <type> $db
	 * @param <type> $assetMetadata
	 * @param <type> $file
	 * @param <type> $previews
	 */
	public function insertAsset($baseUrl, $catdbalog, $assetMetadata, $file, $previews) {

	}

	/**
	 *
	 * @param <type> $baseUrl
	 * @param <type> $db
	 * @param <type> $path
	 */
	public function insertCategory($baseUrl, $db, $path) {
	}

	/**
	 * Get metadata for a set of assets, selected via a text search (e.g. Quick Search in Cumulus, or Lucene search etc.
	 * @param <type> $baseUrl
	 * @param <type> $db
	 * @param <type> $viewName
	 * @param <type> $queryName
	 * @param <type> $text
	 */
	public function textSearch($baseUrl, $db, $viewName, $text, $offset, $count) {
		$result = null;
		$url = $baseUrl . '/search/' . $db . '/fulltext?view='. $viewName . '&text=' . urlencode($text);
        if (! is_null($offset)) {
            $url = $url . '&offset=' . $offset;
        }
        if (!is_null($count)) {
            $url = $url . '&count=' . $count;
        }
		$params = null;
        LogHandler::Log('-CHALCO', 'DEBUG', 'textsearch URL is: ' . $url);
		$r = $this->httpRequest($url);
		try {
			// LogHandler::Log('-CHALCO', 'DEBUG', 'textsearch return status is: ' . $r->getStatus());
			if ($r->getStatus() == 200) {
				$result = json_decode($r->getBody());
				// LogHandler::Log('-CHALCO', 'DEBUG', 'returned JSON: ' . $r->getBody());
			}
		} catch (HttpException $e) {
			echo $e;
		}
		return $result;
	}

	/**
	 * Get metadata for a set of assets, selected via a named query
	 * @param <type> $baseUrl
	 * @param <type> $db
	 * @param <type> $viewName
	 * @param <type> $queryName
	 * @param <type> $queryParams
	 */
	public function findAssets($baseUrl, $db, $viewName, $queryName, $queryParams, $offset, $count) {
		$result = null;
		$params = $queryParams;
		$url = $baseUrl . '/search/' . $db . '/query?queryname=' . $queryName . '&view='. $viewName;
        if (is_not_null($offset)) {
            $url = $url . '&offset='+$offset;
        }
        if (is_not_null($count)) {
            $url = $url . '&count='+count;
        }        LogHandler::Log('-CHALCO', 'DEBUG', 'url: '.$url);
        LogHandler::Log('-CHALCO', 'DEBUG', 'query params: '.view_var($params));
		$r = $this->httpRequest($url, $params, Zend_Http_Client::GET);
		try {
			if ($r->getStatus() == 200) {
				$result = json_decode($r->getBody());
			}
		} catch (HttpException $e) {
			echo $e;
		}
		return $result;
	}

	/**
	 * Get metadata for a single asset
	 * @param <type> $baseUrl
	 * @param <type> $db
	 * @param <type> $viewName
	 * @param <type> $id
	 * @return <type>
	 */
	public function findAsset($id, $baseUrl, $db, $viewName) {
		$result = null;
		$url = $baseUrl . '/data/' . $db . '/fetch?id='.$id.'&view='.$viewName;
        LogHandler::Log('-CHALCO', 'DEBUG', 'url: '.$url);
		$r = $this->httpRequest($url);
		try {
			if ($r->getStatus() == 200) {
				$result = json_decode($r->getBody());
			}
		} catch (HttpException $e) {
            LogHandler::Log('-CHALCO', 'DEBUG', 'findAsset exception: '.e);
		}
		return $result;
	}

	private function requestPreviewData($url, $params = null) {
		$result = null;
		$r = $this->httpRequest($url, $params);
		try {
			if ($r->getStatus() == 200) {
				$result['type'] = $r->getHeader('Content-Type');
				$result['body'] = $r->getBody();
			}
		} catch (HttpException $e) {
            LogHandler::Log('-CHALCO', 'DEBUG', 'requestPreviewData exception: '.$e);
		}
		return $result;
	}



	public function previewAssetFull($id, $baseUrl, $db) {
		$result = null;
		$url = $baseUrl.'/preview/'.$db.'/fetch?id='.$id;
		return $this->requestPreviewData($url);
	}

	public function previewAssetMaxSize($id, $baseUrl, $db, $maxSize) {
		$result = null;
		$url = $baseUrl.'/preview/'.$db.'/fetch?id='.$id.'&maxsize='.$maxSize;
		return $this->requestPreviewData($url);
	}

	public function previewAsset($id, $baseUrl, $db, $previewName) {
		$result = null;
		$url = $baseUrl.'/preview/'.$db.'/fetch?id='.$id.'&name=' . $previewName;
		return $this->requestPreviewData($url);
	}

    public function getAttachment($filename, $id, $baseUrl, $db, $previewName, $rendition) {
        $file = null;
        $url = $baseUrl.'/preview/'.$db.'/fetch?id='.$id.'&name=' . $previewName;
        LogHandler::Log('-CHALCO', 'INFO', 'getting attachment for url: '.$url);
        if (!is_null($url)) {
            require_once BASEDIR . '/server/bizclasses/BizTransferServer.class.php';
            require_once BASEDIR . '/server/utils/MimeTypeHandler.class.php';
            $type = MimeTypeHandler::filePath2MimeType($filename);
            LogHandler::Log('-CHALCO', 'INFO', 'get MIME Type for preview: '.$type);
            LogHandler::Log('-CHALCO', 'INFO', 'get rendition for preview: '.$rendition);
            $attachment = new Attachment($rendition, $type);

            $transferServer = new BizTransferServer();
            $transferServer->copyToFileTransferServer($url, $attachment);
            $file = $attachment;
            LogHandler::Log('-CHALCO', 'INFO', 'get attachment for preview: '.view_var($file));
        }

        return $file;
    }

	public function downloadAsset($id, $baseUrl, $db) {
		$result = null;
		$url = $baseUrl.'/file/'.$db.'/get?id='.$id;
		$r = $this->httpRequest($url);
		try {
			if ($r->getStatus() == 200) {
				$result['type'] = $r->getHeader('Content-Type');
				$result['body'] = $r->getBody();
			}
		} catch (HttpException $e) {
            LogHandler::Log('-CHALCO', 'DEBUG', 'downloadAsset exception: '.$e);
		}
		return $result;
	}

	public static function httpRequest($url, $params = null, $method=Zend_Http_Client::GET) {
		//print('httpRequest url:'.$url.' with params ('.$params.') and method '.$method.'<br>');
		try {
			$http = new Zend_Http_Client();
			$http->setUri($url);
			if ($params) {
				if ($method == Zend_Http_Client::GET) {
					foreach($params as $parKey => $parValue) {
						$http->setParameterGet($parKey, $parValue);
					}
				} else if ($method == Zend_Http_Client::POST) {
					foreach($params as $parKey => $parValue) {
						$http->setParameterPost($parKey, $parValue);
					}
				}
			}
		} catch (HttpException $e) {
            LogHandler::Log('-CHALCO', 'DEBUG', 'httpRequest exception: '.$e);
		}
		return $http->request($method);
	}
}
?>
