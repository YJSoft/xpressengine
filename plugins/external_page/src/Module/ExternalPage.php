<?php
/**
 * ExternalPage module
 *
 * @category    Page
 * @package     Xpressengine\Plugins\ExternalPage
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\ExternalPage\Module;

use Xpressengine\Module\AbstractModule;

use View;
use XeDB;
use Xpressengine\Config\ConfigManager;
use Xpressengine\Plugins\ExternalPage\FileFilter;
use Route;

/**
 * ExternalPage module class
 *
 * @category    ExternalPage
 * @package     Xpressengine\Plugins\ExternalPage
 */
class ExternalPage extends AbstractModule
{
    /**
     * @var ConfigManager $configManager
     */
    public $configManager;

    /**
     * ExternalPage constructor.
     */
    public function __construct()
    {
        $this->configManager = app('xe.config');
    }

    /**
     * boot
     *
     * @return void
     */
    public static function boot()
    {
        self::registerInstanceRoute();
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
            Route::get('/', ['as' => 'index', 'uses' => 'ExternalPageUserController@index']);
        }, ['namespace' => 'Xpressengine\Plugins\ExternalPage\Controller']);
    }

    /**
     * getSettingsURI
     *
     * @return null
     */
    public static function getSettingsURI()
    {
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
     * Return Create Form View
     *
     * @return mixed
     */
    public function createMenuForm()
    {
        $config = $this->configManager->get(self::getId());
        $form = View::file(__DIR__ . '/../../views/menuType/menuCreate.blade.php', [
            'config' => $config,
        ]);
        return $form;
    }

    /**
     * Process to Store
     *
     * @param string $instanceId
     * @param array  $menuTypeParams
     * @param array  $itemParams
     *
     * @return mixed
     * @internal param $inputs
     *
     */
    public function storeMenu($instanceId, $menuTypeParams, $itemParams)
    {
        $filePath = $menuTypeParams['includePath'];
        $this->checkFileSecurity($filePath);
        $configName = $this->getPageConfigKeyString($instanceId);
        $this->configManager->add($configName, $menuTypeParams);
    }

    /**
     * Return Edit Form View
     *
     * @param $instanceId
     *
     * @return mixed
     */
    public function editMenuForm($instanceId)
    {
        $config = $this->configManager->get($this->getPageConfigKeyString($instanceId));

        $form = View::file(__DIR__ . '/../../views/menuType/menuEdit.blade.php', [
            'instanceId' => $instanceId,
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
        $configName = $this->getPageConfigKeyString($instanceId);

        XeDB::beginTransaction();

        try {
            $affected = $this->configManager->put($configName, $menuTypeParams);
        } catch (\Exception $e) {
            XeDB::rollback();
            throw $e;
        }

        XeDB::commit();
        return $affected;
    }

    /**
     * Process to delete
     *
     * @param $instanceId
     *
     * @return mixed
     */
    public function deleteMenu($instanceId)
    {
        $configName = $this->getPageConfigKeyString($instanceId);

        $this->configManager->removeByName($configName);
    }

    /**
     * summary
     *
     * @param string $instanceId
     *
     * @return string
     */
    public function summary($instanceId)
    {
        return "이 메뉴를 삭제하여도 연결되어 있는 external php file은 별도로 삭제해 주어야 합니다. ";
    }

    /**
     * getPageConfigKeyString
     *
     * @param $instanceId
     *
     * @return string
     */
    protected function getPageConfigKeyString($instanceId)
    {
        return sprintf('%s.%s', self::getId(), $instanceId);
    }

    /**
     * checkFileSecurity
     *
     * @param $filePath
     *
     * @return void
     * @throws \Exception
     */
    protected function checkFileSecurity($filePath)
    {
        $validation = FileFilter::check($filePath);
        if (!$validation) {
            throw new \Exception('file validation exception ' . $filePath);
        }
    }

    /**
     * Return URL about module's detail setting
     * getInstanceSettingURI
     *
     * @param $instanceId
     *
     * @return mixed
     */
    public static function getInstanceSettingURI($instanceId)
    {
        return null;
    }

    /**
     * Get menu type's item object
     *
     * @param string $id item id of menu type
     * @return mixed
     */
    public function getTypeItem($id)
    {
        return null;
    }
}
