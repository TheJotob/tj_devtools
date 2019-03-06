<?php

namespace TheJotob\TJDevtools\Resource\Driver;


use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExtendedLocalDriver extends LocalDriver
{
    protected $remoteBaseUrl;

    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);

        $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tj_devtools']);
        $this->remoteBaseUrl = $this->sanitizeBaseUrl($extensionConfiguration['remoteBaseUrl']);
    }

    public function fileExists($fileIdentifier)
    {
        if ($fileIdentifier === '')
            return false;

        if (parent::fileExists($fileIdentifier))
            return true;

        if($this->isTempFile($fileIdentifier))
            return false;

        return $this->remoteFileExists($fileIdentifier);
    }

    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        if (!parent::fileExists($fileIdentifier) && !$this->isTempFile($fileIdentifier))
            $this->downloadFile($fileIdentifier, $this->getAbsolutePath($fileIdentifier));

        return parent::getFileForLocalProcessing($fileIdentifier, $writable);
    }

    public function getPermissions($identifier)
    {
        if (!parent::fileExists($identifier) && !$this->isTempFile($identifier))
            $this->downloadFile($identifier, $this->getAbsolutePath($identifier));

        return parent::getPermissions($identifier);
    }

    private function remoteFileExists($fileIdentifier)
    {
        // Initiate the Request Factory, which allows to run multiple requests
        /** @var \TYPO3\CMS\Core\Http\RequestFactory $requestFactory */
        $requestFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\RequestFactory::class);
        $baseUrl = $this->remoteBaseUrl;
        $url = $baseUrl . $fileIdentifier;
        $additionalOptions = [
            // Additional headers for this specific request
            'headers' => ['Cache-Control' => 'no-cache'],
            // Additional options, see http://docs.guzzlephp.org/en/latest/request-options.html
            'allow_redirects' => false
        ];

        $response = $requestFactory->request($url, 'GET', $additionalOptions);

        return $response->getStatusCode() === 200;
    }

    private function sanitizeBaseUrl($baseUrl)
    {
        $baseUrl = trim($baseUrl);
        $baseUrl = filter_var($baseUrl, FILTER_SANITIZE_URL);
        return trim($baseUrl, '/');
    }

    private function downloadFile($fileIdentifier, $downloadPath)
    {
        // Initiate the Request Factory, which allows to run multiple requests
        /** @var \TYPO3\CMS\Core\Http\RequestFactory $requestFactory */
        $requestFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\RequestFactory::class);
        $baseUrl = $this->remoteBaseUrl;
        $url = $baseUrl . $fileIdentifier;

        $pathname = dirname($downloadPath);
        if(!is_dir($pathname))
            mkdir($pathname, 0775, true);

        $resource = fopen($downloadPath, 'w');

        if($resource) {
            $additionalOptions = [
                // Additional headers for this specific request
                'headers' => ['Cache-Control' => 'no-cache'],
                // Additional options, see http://docs.guzzlephp.org/en/latest/request-options.html
                'allow_redirects' => false,
                'sink' => $resource
            ];

            $requestFactory->request($url, 'GET', $additionalOptions);
        }
    }

    private function isTempFile($identifier) {
        return strpos($identifier, '_processed_');
    }
}
