<?php

namespace Bitmotion\CustomErrorPage\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Bitmotion GmbH <typo3-ext@bitmotion.de>
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
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class Custom404PageUtility
 * @package Bitmotion\CustomErrorPage\Utility
 */
class CustomErrorPageUtility
{
    const CODE_404 = 404;
    const CODE_403 = 403;
    const CODE_503 = 503;

    /**
     * user func for page not found function
     *
     * @param array $param
     * @param TypoScriptFrontendController $ref
     */
    public function showCustom404Page($param, $ref)
    {
        $pageType = $this->getErrorPageType($param);
        //TYPO3 handles 403 and 404 HTTP Requests in the same way and we want to separate them
        if ($pageType === self::CODE_403) {
            $error403Url = $this->getConfigurationErrorPage($param['currentUrl'], $pageType);

            //Redirect user to the configured 403 page
            HttpUtility::redirect($error403Url . '&redirect_url=' . urlencode($param['currentUrl']));
        } else {
            $this->showCustomErrorPage($param['currentUrl'], $pageType);
        }
    }

    /**
     * user func for page not found function
     *
     * @param array $param
     * @param TypoScriptFrontendController $ref
     */
    public function showCustom503Page($param, $ref)
    {
        $this->showCustomErrorPage($param['currentUrl'], self::CODE_503);
    }

    /**
     * Get 403 or 404 error page type(status code) according to given fe_groups
     *
     * @param array $param
     * @return int
     */
    protected function getErrorPageType($param)
    {
        if (isset($param['pageAccessFailureReasons']) && isset($param['pageAccessFailureReasons']['fe_group'])) {
            return $this->hasUserGroups($param['pageAccessFailureReasons']['fe_group']) ? self::CODE_403 : self::CODE_404;
        }

        return self::CODE_404;
    }

    /**
     * Check if the given array of feGroups contains a valid fe_group
     *
     * @param array $feGroups
     * @return bool
     */
    protected function hasUserGroups($feGroups)
    {
        foreach ($feGroups as $pageUid => $requiredUserGroup) {
            if ($requiredUserGroup > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $currentUrl
     * @param int $pageType
     * @throws \Exception
     */
    private function showCustomErrorPage($currentUrl, $pageType)
    {
        $strOriginalRequestUserAgent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');

        // if the current request contains our User-Agent, our extensions was called while trying to retrieve the 404 page => invalid configuration
        if (strpos($strOriginalRequestUserAgent, 'TYPO3/' . $pageType. '-Handling') === false) {

            $strOriginalRequestIp = GeneralUtility::getIndpEnv('REMOTE_ADDR');
            $report = [];
            $errorUrl = $this->getConfigurationErrorPage($currentUrl, $pageType);

            // Call the website. cURL is needed for this.
            $strPageContent = GeneralUtility::getUrl($errorUrl, 0, [
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
     * Get a url of the error page specified by currentUrl and the pageType(status code)
     *
     * @param string $currentUrl
     * @param int $pageType
     * @return string
     */
    protected function getConfigurationErrorPage($currentUrl, $pageType)
    {
        $configuration = ConfigurationUtility::loadConfiguration($pageType);

        return $this->findErrorPage($currentUrl, $configuration, $pageType);
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
    private function findErrorPage($strCurrentUrl, $configuration, $type = self::CODE_404)
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
}