<?php

namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Representation of a specific usage of a file with possibilities to override certain
 * properties of the original file just for this usage of the file.
 *
 * It acts as a decorator over the original file in the way that most method calls are
 * directly passed along to the original file object.
 *
 * All file related methods are directly passed along; only meta data functionality is adopted
 * in this decorator class to priorities possible overrides for the metadata for this specific usage
 * of the file.
 */
class FileReference extends \TYPO3\CMS\Core\Resource\FileReference
{
    /*****************
     * SPECIAL METHODS
     *****************/
    /**
     * Returns a publicly accessible URL for this file
     *
     * WARNING: Access to the file may be restricted by further means, e.g.
     * some web-based authentication. You have to take care of this yourself.
     *
     * @return string|null NULL if file is missing or deleted, the generated url otherwise
     */
    public function getPublicUrl()
    {
        $file = $this->originalFile;
        if(str_starts_with($file->getMimeType(), 'admiralCloud/')){
            $fe_group = PermissionUtility::getPageFeGroup();
            if($this->getProperty('tablenames') == 'tt_content' && $this->getProperty('uid_foreign') && !$fe_group){
                $fe_group = PermissionUtility::getContentFeGroupFromReference($this->getProperty('uid_foreign'));
            }
            $GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()] = $fe_group;
        }
        $publicUrl = $file->getPublicUrl();
        unset($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()]);
        return $publicUrl;
    }

    

}