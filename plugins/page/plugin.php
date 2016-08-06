<?php
/**
 * Page plugin
 *
 * @category    Page
 * @package     Page
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Page;

use XeConfig;
use Xpressengine\Plugin\AbstractPlugin;
use App;
use XeLang;

/**
 * Page plugin
 *
 * @category    Page
 * @package     Page
 */
class PagePlugin extends AbstractPlugin
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
     * @return void
     */
    public function activate($installedVersion = null)
    {
        if (XeConfig::get('module/page@page') === null) {
            XeConfig::add('module/page@page', []);
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
    public function checkInstalled($installedVersion = NULL)
    {
        // TODO: Implement checkInstall() method.

        return true;
    }

    /**
     * @return boolean
     */
    public function checkUpdated($installedVersion = NULL)
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
        $this->bindClasses();
    }

    /**
     * bindClasses
     *
     * @return void
     */
    protected function bindClasses()
    {
        $app = app();

        $app->singleton(
            'xe.page.handler',
            function ($app) {
                return new PageHandler(
                    $app['xe.document'],
                    $app['xe.plugin.comment']->getHandler(),
                    $app['xe.config'],
                    $app['xe.auth']
                );
            }
        );

        $app->bind(
            'Xpressengine\Plugins\Page\PageHandler',
            'xe.page.handler'
        );
    }
}
