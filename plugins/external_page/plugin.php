<?php
/**
 * ExternalPage plugin
 *
 * @category    ExternalPage
 * @package     ExternalPage
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Page;

use XeConfig;
use Xpressengine\Plugin\AbstractPlugin;

/**
 * ExternalPage plugin
 *
 * @category    ExternalPage
 * @package     ExternalPage
 */
class ExternalPagePlugin extends AbstractPlugin
{
    /**
     * install
     *
     * @return void
     */
    public function install()
    {
//        XeLang::putFromLangDataSource(self::getId(), __DIR__.'/lang/lang.php');
    }

    /**
     * activate
     *
     * @param null $installedVersion installed version
     *
     * @return void
     */
    public function activate($installedVersion = null)
    {
        if (XeConfig::get('module/externalPage@externalPage') === null) {
            XeConfig::add('module/externalPage@externalPage', []);
        }
    }

    /**
     * @return boolean
     */
    public function unInstall()
    {
        // TODO: Implement unInstall() method.
    }

    /**
     * @return boolean
     */
    public function checkInstalled($installedVersion = null)
    {
        // TODO: Implement checkInstall() method.

        return true;
    }

    /**
     * @return boolean
     */
    public function checkUpdated($currentVersion = null)
    {
        // TODO: Implement checkUpdate() method.
    }

    /**
     * boot
     *
     * @return void
     */
    public function boot()
    {
    }
}
