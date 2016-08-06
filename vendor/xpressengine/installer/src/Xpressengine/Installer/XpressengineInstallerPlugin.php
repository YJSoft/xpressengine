<?php
/**
 * This file is XpressEngine installer append to Composer installer.
 *
 * PHP version 5
 *
 * @category    Installer
 * @package     Xpressengine\Installer
 * @author      XE Team (jhyeon1010) <cjh1010@xpressengine.com>
 * @copyright   2014 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
namespace Xpressengine\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFormatter;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/**
 * This class is register composer plugin.
 *
 * @category    Installer
 * @package     Xpressengine\Installer
 * @author      XE Team (jhyeon1010) <cjh1010@xpressengine.com>
 * @copyright   2014 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
class XpressengineInstallerPlugin implements PluginInterface, EventSubscriberInterface
{

    protected $path;

    protected $enabled;

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer    $composer composer instance
     * @param IOInterface $io       IO instance
     * @return void
    */
    public function activate(Composer $composer, IOInterface $io)
    {

        if($composer->getPackage()->getName() !== 'xpressengine/xpressengine') {
            $this->enabled = false;
            return;
        } else {
            $this->enabled = true;
        }

        $installer = new XpressengineInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);

        require_once __DIR__.'/helpers.php';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            InstallerEvents::PRE_DEPENDENCIES_SOLVING => 'preDependencySolve',
            InstallerEvents::POST_DEPENDENCIES_SOLVING => 'postDependencySolve',
            //ScriptEvents::PRE_INSTALL_CMD => 'onInstallUpdateOrDump',
            //ScriptEvents::PRE_UPDATE_CMD => 'onInstallUpdateOrDump',
            ScriptEvents::POST_INSTALL_CMD => 'postInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'postInstallOrUpdate',
        );
    }

    public function preDependencySolve(InstallerEvent $event)
    {
        if(!$this->enabled) {
            return;
        }

        // 파일 경로를 읽고, plugin-update 모드인지 검사.
        // plugin-update 모드인데 packages가 xpressengine-plugin이 아니면 Exception
        $data = $this->getPluginComposerData($event);

        $mode = array_get($data, 'xpressengine-plugin.mode');
        if($mode !== 'plugins-fixed') {
            $packages = $GLOBALS['argv'][$GLOBALS['argc'] - 1];
            if(strpos($packages, 'xpressengine-plugin') !== 0) {
                throw new \Exception("xpressengine-installer: check file[".$this->path."]. this file is not correct");
            }
        }

    }


    public function postDependencySolve(InstallerEvent $event)
    {
        if(!$this->enabled) {
            return;
        }

        $extra = $event->getComposer()->getPackage()->getExtra();

        $uninstall = array_get($extra, 'xpressengine-plugin.uninstall', []);
        foreach ($event->getOperations() as $operation) {
            /** @var UpdateOperation $operation */
            if (is_subclass_of($operation, UpdateOperation::class) || is_subclass_of($operation, InstallOperation::class)) {
                $target = $operation->getInitialPackage();
                if(in_array($target->getName(), $uninstall)) {
                    throw new \Exception('xpressengine-installer: To install or update the package requested to delete is not allowed.', 66);
                }
            }
        }
    }

    public function postInstallOrUpdate(Event $event)
    {
        if(!$this->enabled) {
            return;
        }

        $extra = $event->getComposer()->getPackage()->getExtra();

        $path = $extra['xpressengine-plugin']['path'];

        $data = json_decode(file_get_contents($path));

        $data->{"xpressengine-plugin"}->changed = XpressengineInstaller::$changed;

        $dataJson = json_encode($data);

        $dataJson = JsonFormatter::format($dataJson, true, true);

        file_put_contents($path, $dataJson);
    }

    /**
     * getPluginComposerData
     *
     * @param Event $event
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getPluginComposerData(Event $event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();

        if (!isset($extra['xpressengine-plugin']['path'])) {
            throw new \Exception('xpressengine-installer: extra > xpressengine-plugin > path is needed.');
        }

        $path = $extra['xpressengine-plugin']['path'];
        $this->path = $path;

        if (!file_exists($path)) {
            throw new \Exception("file [$path] not exists.");
        }

        return json_decode(file_get_contents($path), true);
    }

}
