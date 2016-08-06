<?php
/**
 * Page module
 *
 * @category    Page
 * @package     Xpressengine\Plugins\Page
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Page\Module;

use XeConfig;
use XeEditor;
use Xpressengine\Plugins\Page\PageHandler;
use View;
use Xpressengine\Module\AbstractModule;
use Route;
use App;

/**
 * Page module class
 *
 * @category    Page
 * @package     Xpressengine\Plugins\Page
 */
class Page extends AbstractModule
{
    const FILE_UPLOAD_PATH = 'public/plugin/page';
    const THUMBNAIL_TYPE = 'spill';

    /**
     * boot
     *
     * @return void
     */
    public static function boot()
    {
        self::registerManageRoute();
        self::registerInstanceRoute();
    }

    /**
     * getSettingsURI
     *
     * @return null
     */
    public static function getSettingsURI()
    {
        return null;
    }

    /**
     * isRouteAble
     *
     * @return bool
     */
    public static function isRouteAble()
    {
        return true;
    }

    /**
     * Register Plugin Manage Route
     *
     *
     * @return void
     */
    protected static function registerManageRoute()
    {
        Route::settings(self::getId(), function () {
            Route::get('edit/{pageId}', ['as' => 'manage.plugin.page.edit', 'uses' => 'PageManageController@edit']);
            Route::post(
                'update/{pageId}',
                ['as' => 'manage.plugin.page.update', 'uses' => 'PageManageController@update']
            );
        }, ['namespace' => 'Xpressengine\Plugins\Page\Controller']);
    }

    /**
     * Register Plugin Instance Route
     *
     *
     * @return void
     */
    protected static function registerInstanceRoute()
    {
        Route::instance(self::getId(), function () {
            Route::get('/', ['as' => 'index', 'uses' => 'PageUserController@index']);
            Route::post('/preview', ['as' => 'preview', 'uses' => 'PageUserController@preview']);
        }, ['namespace' => 'Xpressengine\Plugins\Page\Controller']);
    }

    /**
     * Return Create Form View
     *
     * @return string
     */
    public function createMenuForm()
    {
        $config = XeConfig::get(self::getId());   // 기본 설정
        return View::file(__DIR__ . '/../../views/menuType/menuCreate.blade.php', [
            'config' => $config,
        ])->render();
    }

    /**
     * Process to Store
     *
     * @param string $instanceId     page instance id
     * @param array  $menuTypeParams for menu type store param array
     * @param array  $itemParams     except menu type param array
     *
     * @return mixed
     * @internal param $inputs
     *
     */
    public function storeMenu($instanceId, $menuTypeParams, $itemParams)
    {
        $this->getPageHandler()->createPageInstance($instanceId, $menuTypeParams, app('xe.translator')->getLocale());

        XeEditor::setInstance($instanceId, 'editor/ckeditor@ckEditor');
    }

    /**
     * Return Edit Form View
     *
     * @param string $instanceId to edit instance id
     *
     * @return mixed
     *
     */
    public function editMenuForm($instanceId)
    {
        $config = $this->getPageHandler()->getPageConfig($instanceId);   // 기본 설정

        $form = View::file(__DIR__ . '/../../views/menuType/menuEdit.blade.php', [
            'pageId' => $instanceId,
            'config' => $config,
        ])->render();
        return $form;
    }

    /**
     * Process to Update
     *
     * @param string $instanceId     to store instance id
     * @param array  $menuTypeParams for menu type store param array
     * @param array  $itemParams     except menu type param array
     *
     * @return mixed
     * @throws \Exception
     */
    public function updateMenu($instanceId, $menuTypeParams, $itemParams)
    {
        $handler = $this->getPageHandler();

        $pageConfig = $handler->getPageConfig($instanceId);
        if ($pageConfig === null) {
            throw new \Exception('PageId-Not-Exist', 100);
        }

        $pageConfig->set('comment', ($menuTypeParams['comment'] === 'true'));
        $pageConfig->set('mobile', ($menuTypeParams['mobile'] === 'true'));

        $handler->updatePageConfig($pageConfig);
        $handler->createCommentInstance($instanceId, $menuTypeParams['comment']);

    }

    /**
     * Process to delete
     *
     * @param string $instanceId to delete instance id
     *
     * @return mixed
     *
     */
    public function deleteMenu($instanceId)
    {
        $this->getPageHandler()->dropPage($instanceId);
    }

    /**
     * summary
     *
     * @param string $instanceId page instance id
     *
     * @return string
     */
    public function summary($instanceId)
    {
        return "{$instanceId} 의 메뉴에서는 하나의 config 와 다수의 문서들을 가지고 있습니다.";
    }

    /**
     * getPageHandler
     *
     * @return PageHandler
     */
    protected function getPageHandler()
    {
        return app('xe.page.handler');
    }

    /**
     * Return URL about module's detail setting
     * getInstanceSettingURI
     *
     * @param string $instanceId page instance id
     *
     * @return string|null
     */
    public static function getInstanceSettingURI($instanceId)
    {
        return route('manage.plugin.page.edit', $instanceId);
    }

    /**
     * Get menu type's item object
     *
     * @param string $id item id of menu type
     * @return mixed
     */
    public function getTypeItem($id)
    {
        $request = app('request');

        $doc = app('xe.document')->get($id);
        $pageId = $doc->instanceId;
        $config = $this->getPageHandler()->getPageConfig($pageId);

        $mode = 'pc';
        if ($config->get('mobile') && $request->isMobile()) {
            $mode = 'mobile';
        }

        return $this->getPageHandler()->getPageEntity($pageId, $mode, \XeLang::getLocale());
    }
}
