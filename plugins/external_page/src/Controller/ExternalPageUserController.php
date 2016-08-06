<?php
/**
 * ExternalPage User Controller
 *
 * @category    Page
 * @package     Xpressengine\Plugins\ExternalPage\Controller
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\ExternalPage\Controller;

use App\Http\Controllers\Controller;
use XePresenter;
use Xpressengine\Config\ConfigManager;
use Xpressengine\Presenter\RendererInterface;
use Xpressengine\Routing\InstanceConfig;
use Xpressengine\Plugins\ExternalPage\Module\ExternalPage;

/**
 * ExternalPage User Controller
 *
 * @category    ExternalPage
 * @package     Xpressengine\Plugins\ExternalPage\Controller
 */
class ExternalPageUserController extends Controller
{
    /**
     * @var ConfigManager $configManager
     */
    protected $configManager;
    /**
     * @var string default config prefix
     */
    protected $configName = 'external';

    /**
     * ExternalPageUserController constructor.
     */
    public function __construct()
    {
        $this->configManager = app('xe.config');
        XePresenter::setSkinTargetId(ExternalPage::getId());
    }

    /**
     * index
     *
     * @return RendererInterface
     */
    public function index()
    {
        $instanceConfig = InstanceConfig::instance();
        $instanceId = $instanceConfig->getInstanceId();

        $configName = $this->getConfigKeyString($instanceId);

        $includePath = $this->configManager->getVal($configName);

        return XePresenter::make('show', [
            'includePath' => $includePath
        ]);
    }

    protected function getConfigKeyString($instanceId)
    {
        return sprintf('%s.%s.%s', ExternalPage::getId(), $instanceId, 'includePath');
    }
}
