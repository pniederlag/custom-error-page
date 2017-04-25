<?php

namespace Bitmotion\CustomErrorPage\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Bitmotion GmbH <typo3-ext@bitmotion.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class Custom404PageUtility
 * @package Bitmotion\CustomErrorPage\Utility
 */
class CustomErrorPageUtility
{
    /**
     * user func for page not found function
     *
     * @param array $param
     * @param TypoScriptFrontendController $ref
     */
    public function showCustom404Page($param, $ref)
    {
        $this->showCustomPage($param['currentUrl'], 404);
    }
    /**
     * user func for page not found function
     *
     * @param array $param
     * @param TypoScriptFrontendController $ref
     */
    public function showCustom503Page($param, $ref)
    {
        $this->showCustomPage($param['currentUrl'], 503);
    }

    /**
     * @param string $currentUrl
     * @param int $pageType
     * @throws \Exception
     */
    private function showCustomPage($currentUrl, $pageType)
    {
        $strOriginalRequestUserAgent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');

        // if the current request contains our User-Agent, our extensions was called while trying to retrieve the 404 page => invalid configuration
        if (strpos($strOriginalRequestUserAgent, 'TYPO3/' . $pageType. '-Handling') === false) {

            $strOriginalRequestIp = GeneralUtility::getIndpEnv('REMOTE_ADDR');
            $configuration = ConfigurationUtility::loadConfiguration($pageType);
            $str404Page = $this->find404Page($currentUrl, $configuration, $pageType);

            // Check if proxy usage is configured
            $this->checkForProxyUsage();

            // The errors of GeneralUtility::getUrl gets stored in this variable
            $report = '';

            // Call the website. cURL is needed for this.
            $strPageContent = GeneralUtility::getUrl($str404Page, 0, [
                'User-Agent: TYPO3/' . $pageType . '-Handling::' . $strOriginalRequestIp . '::' . $strOriginalRequestUserAgent,
                'Referer: ' . $currentUrl,
            ], $report);

            if (($strPageContent === '') || !$strPageContent) {
                // if the request is emtpy or FALSE we were likely calling our self, thus we should prevent an infinite 404 call and throw an Exception instead
                // @TODO try using the last config (wildcard) first
                throw new \Exception($report['lib'] . ': ' . $report['message']);
            } else {
                echo $strPageContent;
            }
        }
    }

    /**
     * This method checks if the current url matches any of the configured regular expressions and return the
     * corresponding page if so.
     *
     * @param $strCurrentUrl
     *
     * @return mixed
     * @throws \Exception
     */
    private function find404Page($strCurrentUrl, $configuration, $type = 404)
    {
        $arrayKey = $type . 'Handling';
        $strHostName = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');

        if (is_array($configuration[$strHostName][$arrayKey])) {
            $strPageNotFoundAllocationArray = $configuration[$strHostName][$arrayKey];
        } elseif (is_array($configuration['_DEFAULT'][$arrayKey])) {
            $strPageNotFoundAllocationArray = $configuration['_DEFAULT'][$arrayKey];
        }

        if (empty($strPageNotFoundAllocationArray)) {
            // throw an exception if no configuration can be found
            throw new \Exception('Could not find a "pageNotFound" that belongs to this hostname. Not even a default configuration.');
        }

        foreach ($strPageNotFoundAllocationArray as $strRegex => $strPage) {
            if (preg_match($strRegex, $strCurrentUrl)) {
                return $strPage;
            }
        }

        // throw an exception if no matching regular expression can be found
        throw new \Exception('Could not find a "pageNotFound" match for the given URL');
    }

    /**
     * This method checks if the TYPO3 is using a proxy server to connect to the internet.
     */
    private function checkForProxyUsage()
    {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['custom_error_page']);

        if (is_array($settings) && $settings['disableProxyUsage']) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer'] = '';
        }
    }
}