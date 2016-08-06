<?php
/**
 * Page Entity
 *
 * @category    Page
 * @package     Xpressengine\Plugins\Page
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Page;

use Xpressengine\Plugins\Comment\CommentUsable;
use Xpressengine\Routing\InstanceRoute;
use Xpressengine\Support\Entity;
use Xpressengine\User\UserInterface;

/**
 * Page Entity
 *
 * @property string id
 * @property string pcContent
 * @property string pcUid
 * @property string mobileContent
 * @property string mobileUid
 *
 * @category    Page
 * @package     Xpressengine\Plugins\Page
 */
class PageEntity extends Entity implements CommentUsable
{
    /**
     * Returns unique identifier
     *
     * @return string
     */
    public function getUid()
    {
        return $this->__get('uid');
    }

    /**
     * Returns instance identifier
     *
     * @return string
     */
    public function getInstanceId()
    {
        return $this->__get('pageId');
    }

    /**
     * Returns author
     *
     * @return UserInterface
     */
    public function getAuthor()
    {
        return $this->__get('content')->user;
    }

    /**
     * Returns the link
     *
     * @param InstanceRoute $route route instance
     * @return string
     */
    public function getLink(InstanceRoute $route)
    {
        return $route->url;
    }
}

