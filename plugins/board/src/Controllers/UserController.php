<?php
/**
 * UserController
 *
 * @category    Board
 * @package     Xpressengine\Plugins\Board
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Board\Controllers;

use XeDocument;
use XePresenter;
use XeFrontend;
use XeEditor;
use XeStorage;
use XeTag;
use Auth;
use Gate;
use Event;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Xpressengine\Category\Models\Category;
use Xpressengine\Category\Models\CategoryItem;
use Xpressengine\Config\ConfigEntity;
use Xpressengine\Counter\Counter;
use Xpressengine\Counter\Exceptions\GuestNotSupportException;
use Xpressengine\Document\Models\Document;
use Xpressengine\Http\Request;
use Xpressengine\Media\MediaManager;
use Xpressengine\Media\Models\Image;
use Xpressengine\Permission\Instance;
use Xpressengine\Plugins\Board\ConfigHandler;
use Xpressengine\Plugins\Board\Exceptions\HaveNoWritePermissionHttpException;
use Xpressengine\Plugins\Board\Exceptions\InvalidRequestException;
use Xpressengine\Plugins\Board\Exceptions\InvalidRequestHttpException;
use Xpressengine\Plugins\Board\Exceptions\NotFoundDocumentException;
use Xpressengine\Plugins\Board\Exceptions\NotFoundUploadFileException;
use Xpressengine\Plugins\Board\Exceptions\NotMatchedCertifyKeyException;
use Xpressengine\Plugins\Board\Exceptions\RequiredValueHttpException;
use Xpressengine\Plugins\Board\Exceptions\SecretDocumentHttpException;
use Xpressengine\Plugins\Board\Handler;
use Xpressengine\Plugins\Board\IdentifyManager;
use Xpressengine\Plugins\Board\Models\Board;
use Xpressengine\Plugins\Board\Models\BoardFavorite;
use Xpressengine\Plugins\Board\Modules\Board as BoardModule;
use Xpressengine\Plugins\Board\BoardPermissionHandler;
use Xpressengine\Plugins\Board\Models\BoardSlug;
use Xpressengine\Plugins\Board\UrlHandler;
use Xpressengine\Plugins\Board\Validator;
use Xpressengine\Routing\InstanceConfig;
use Xpressengine\Storage\File;
use Xpressengine\Storage\Storage;
use Xpressengine\Support\Exceptions\AccessDeniedHttpException;
use Xpressengine\Tag\TagHandler;
use Xpressengine\User\Models\User;
use Xpressengine\User\UserInterface;
use Xpressengine\User\Models\Guest;

/**
 * UserController
 *
 * @category    Board
 * @package     Xpressengine\Plugins\Board
 */
class UserController extends Controller
{
    /**
     * @var string
     */
    protected $instanceId;

    /**
     * @var Handler
     */
    public $handler;

    /**
     * @var ConfigHandler
     */
    public $configHandler;

    /**
     * @var UrlHandler
     */
    public $urlHandler;

    /**
     * @var ConfigEntity
     */
    public $config;

    /**
     * @var bool
     */
    public $isManager = false;

    /**
     * UserController constructor.
     * @param Handler $handler
     * @param ConfigHandler $configHandler
     * @param UrlHandler $urlHandler
     * @param BoardPermissionHandler $boardPermission
     */
    public function __construct(
        Handler $handler,
        ConfigHandler $configHandler,
        UrlHandler $urlHandler,
        BoardPermissionHandler $boardPermission
    ) {
        $instanceConfig = InstanceConfig::instance();
        $this->instanceId = $instanceConfig->getInstanceId();

        $this->handler = $handler;
        $this->configHandler = $configHandler;
        $this->urlHandler = $urlHandler;

        $this->config = $configHandler->get($this->instanceId);
        if ($this->config !== null) {
            $urlHandler->setConfig($this->config);

            $this->isManager = false;
            if (Gate::allows(
                BoardPermissionHandler::ACTION_MANAGE,
                new Instance($boardPermission->name($this->instanceId)))
            ) {
                $this->isManager = true;
            };
        }

        // set Skin
        XePresenter::setSkinTargetId(BoardModule::getId());
        XePresenter::share('handler', $handler);
        XePresenter::share('configHandler', $configHandler);
        XePresenter::share('urlHandler', $urlHandler);
        XePresenter::share('isManager', $this->isManager);
        XePresenter::share('instanceId', $this->instanceId);
        XePresenter::share('config', $this->config);
    }

    /**
     * index
     *
     * @param Request                $request         request
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @return \Xpressengine\Presenter\RendererInterface
     * @throws AccessDeniedHttpException
     */
    public function index(Request $request, BoardPermissionHandler $boardPermission)
    {
        if (Gate::denies(
            BoardPermissionHandler::ACTION_LIST,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        return XePresenter::makeAll('index', $this->listDataImporter($request));
    }

    /**
     * get list data
     *
     * @param Request $request request
     * @return array
     */
    protected function listDataImporter(Request $request)
    {
        $query = $this->handler->getModel($this->config)
            ->where('instanceId', $this->instanceId)->visible();

        if ($this->config->get('category') === true) {
            $query->leftJoin(
                'board_category',
                sprintf('%s.%s', $query->getQuery()->from, 'id'),
                '=',
                sprintf('%s.%s', 'board_category', 'targetId')
            );
        }

        if ($request->has('favorite') === true) {
            $query->leftJoin(
                'board_favorites',
                sprintf('%s.%s', $query->getQuery()->from, 'id'),
                '=',
                sprintf('%s.%s', 'board_favorites', 'targetId')
            );
            $query->where('board_favorites.userId', Auth::user()->getId());
        }

        $this->handler->makeWhere($query, $request, $this->config);
        $this->handler->makeOrder($query, $request, $this->config);

        // eager loading
        $query->with(['favorite' => function($favoriteQuery) {
            $favoriteQuery->where('userId', Auth::user()->getId());
        }, 'slug', 'data']);

        Event::fire('xe.plugin.board.list', [$query]);

        $paginate = $query->paginate($this->config->get('perPage'))->appends($request->except('page'));

        $fieldTypes = (array)$this->configHandler->getDynamicFields($this->config);

        $categories = [];
        if ($this->config->get('category') === true) {
            $categoryItems = Category::find($this->config->get('categoryId'))->items;
            foreach ($categoryItems as $categoryItem) {
                $categories[] = [
                    'value' => $categoryItem->id,
                    'text' => $categoryItem->word,
                ];
            }
        }

        $terms = [
            ['value' => '1week', 'text' => 'board::1week'],
            ['value' => '2week', 'text' => 'board::2week'],
            ['value' => '1month', 'text' => 'board::1month'],
            ['value' => '3month', 'text' => 'board::3month'],
            ['value' => '6month', 'text' => 'board::6month'],
            ['value' => '1year', 'text' => 'board::1year'],
        ];

        XeFrontend::translation([
            'board::selectPost',
            'board::selectBoard',
        ]);

        return compact('notices', 'paginate', 'fieldTypes','categories', 'terms');
    }

    /**
     * show
     *
     * @param Request                $request         request
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @param string                 $menuUrl         first segment
     * @param string                 $id              document id
     * @return mixed
     */
    public function show(Request $request, BoardPermissionHandler $boardPermission, $menuUrl, $id)
    {
        if (Gate::denies(
            BoardPermissionHandler::ACTION_READ,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        return XePresenter::make('show', array_merge($this->showDataImporter($id), $this->listDataImporter($request)));
    }

    protected function showDataImporter($id)
    {
        /** @var UserInterface $user */
        $user = Auth::user();
        /** @var Board $item */
        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        $visible = false;
        if ($item->display == Document::DISPLAY_VISIBLE) {
            $visible = true;
        }
        if ($item->display == Document::DISPLAY_SECRET) {
            if ($this->isManager == true) {
                $visible = true;
            } elseif ($user->getId() == $item->getAuthor()->getId()) {
                $visible = true;
            }
            if ($visible === false) {
                throw new SecretDocumentHttpException;
            }
        }

        if ($visible === true) {
            $this->handler->incrementReadCount($item, $user);
        }

        $showCategoryItem = null;
        if ($this->config->get('category')) {
            $boardCategory = $item->boardCategory;
            if ($boardCategory) {
                $showCategoryItem = $boardCategory->categoryItem;
            }
        }

        return compact('item', 'visible', 'showCategoryItem');
    }

    /**
     * show by slug
     *
     * @param Request                $request         request
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @param string                 $menuUrl         first segment
     * @param string                 $strSlug         document slug
     * @return \Xpressengine\Presenter\RendererInterface
     * @throws Exception
     */
    public function slug(Request $request, BoardPermissionHandler $boardPermission, $menuUrl, $strSlug)
    {
        $slug = BoardSlug::where('slug', $strSlug)->where('instanceId', $this->instanceId)->first();

        if ($slug === null) {
            throw new NotFoundDocumentException;
        }

        return $this->show($request, $boardPermission, $menuUrl, $slug->targetId);
    }

    /**
     * create
     *
     * @param Request                $request         request
     * @param Validator              $validator       validator
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @return mixed
     */
    public function create(Request $request, Validator $validator, BoardPermissionHandler $boardPermission)
    {
        if (Gate::denies(
            BoardPermissionHandler::ACTION_CREATE,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        $head = '';

        $categories = [];
        if ($this->config->get('category') === true) {
            $categoryItems = Category::find($this->config->get('categoryId'))->items;
            foreach ($categoryItems as $categoryItem) {
                $categories[] = [
                    'value' => $categoryItem->id,
                    'text' => $categoryItem->word,
                ];
            }
        }

        /** @var UserInterface $user */
        $user = Auth::user();
        $rules = $validator->getCreateRule($user, $this->config);

        return XePresenter::makeAll('create', [
            'action' => 'create',
            'handler' => $this->handler,
            'head' => $head,
            'categories' => $categories,
            'rules' => $rules,
        ]);
    }

    /**
     * create
     *
     * @param Request                $request         request
     * @param Validator              $validator       validator
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @param IdentifyManager        $identifyManager identify manager
     * @return mixed
     */
    public function store(
        Request $request,
        Validator $validator,
        BoardPermissionHandler $boardPermission,
        IdentifyManager $identifyManager
    ) {
//        dd($request->all());


        if (Gate::denies(
            BoardPermissionHandler::ACTION_CREATE,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        $user = Auth::user();

        $this->validate($request, $validator->getCreateRule($user, $this->config));

        $inputs = $request->all();
        $inputs['instanceId'] = $this->instanceId;
        $inputs['content'] = $request->originAll()['content'];
        $inputs['title'] = htmlspecialchars($request->originAll()['title'], ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

        if ($request->get('status') == Board::STATUS_NOTICE && $this->isManager === false) {
            throw new HaveNoWritePermissionHttpException(['name' => xe_trans('xe::notice')]);
        }

        // 암호 설정
        if (empty($inputs['certifyKey']) === false) {
            $inputs['certifyKey'] = $identifyManager->hash($inputs['certifyKey']);
        }
        
        /** @var \Xpressengine\Editor\AbstractEditor $editor */
        $editor = XeEditor::get($this->instanceId);
        $inputs['format'] = $editor->htmlable() ? Board::FORMAT_HTML : Board::FORMAT_NONE;

        $board = $this->handler->add($inputs, $user, $this->config);
        
        // file 처리
        XeStorage::sync($board->getKey(), array_get($inputs, $editor->getFileInputName(), []));
        // tag 처리
        XeTag::set($board->getKey(), array_get($inputs, $editor->getTagInputName(), []), $this->instanceId);

        return redirect()->to($this->urlHandler->getShow($board, $request->query->all()));
    }

    public function hasSlug(Request $request)
    {
        $slug = BoardSlug::convert('', $request->get('slug'));
        $slug = BoardSlug::make($slug, $request->get('id'));

        return XePresenter::makeApi([
            'slug' => $slug,
        ]);
    }

    /**
     * edit
     *
     * @param Request                $request         request
     * @param Validator              $validator       validator
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @param IdentifyManager        $identifyManager identify manager
     * @param string                 $menuUrl         first segment
     * @param string                 $id              document id
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function edit(
        Request $request,
        Validator $validator,
        BoardPermissionHandler $boardPermission,
        IdentifyManager $identifyManager,
        $menuUrl,
        $id
    ) {
        $user = Auth::user();

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        if ($item === null) {
            throw new NotFoundDocumentException;
        }

        // 비회원이 작성 한 글일 때 인증페이지로 이동
        if (
            $item->isGuest() === true &&
            $identifyManager->identified($item) === false &&
            $user->getRating() != 'super'
        ) {
            return $this->identify($item);
        }

        // 접근 권한 확인
        if (Gate::denies(
            BoardPermissionHandler::ACTION_CREATE,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        $categories = [];
        if ($this->config->get('category') === true) {
            $categoryItems = Category::find($this->config->get('categoryId'))->items;
            foreach ($categoryItems as $categoryItem) {
                $categories[] = [
                    'value' => $categoryItem->id,
                    'text' => $categoryItem->word,
                ];
            }
        }

        /** @var \Xpressengine\Plugins\Board\Validator $validator */
        $validator = app('xe.board.validator');
        $rules = $validator->getEditRule($user, $this->config);

        $parent = null;

        return XePresenter::make('edit', [
            'config' => $this->config,
            'handler' => $this->handler,
            'item' => $item,
            'parent' => $parent,
            'categories' => $categories,
            'rules' => $rules,
        ]);
    }

    /**
     * update
     *
     * @param Request                $request         request
     * @param Validator              $validator       validator
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @param IdentifyManager        $identifyManager identify manager
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function update(
        Request $request,
        Validator $validator,
        BoardPermissionHandler $boardPermission,
        IdentifyManager $identifyManager
    ) {
        $user = Auth::user();
        $id = $request->get('id');

        if ($id === null) {
            throw new RequiredValueHttpException(['key' => 'id']);
        }

        // 글 수정 시 게시판 설정이 아닌 글의 상태에 따른 처리가 되어야 한다.
        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        // 비회원이 작성 한 글 인증
        // 비회원이 작성 한 글일 때 인증페이지로 이동
        // ?? edit 과 동일한 코드로 처리해야하는것 아닌가? 문제 있어 보임
        if (
            $item->isGuest() === true &&
            $identifyManager->identified($item) === false &&
            $user->getRating() != 'super'
        ) {
            return $this->identify($item, $this->urlHandler->get('edit', ['id' => $item->id]));
        }


        $rules = $validator->getEditRule($user, $this->config);
        $this->validate($request, $rules);

        $inputs = $request->all();
        // replace purifying content to origin content value
        $inputs['content'] = $request->originAll()['content'];
        $inputs['title'] = htmlspecialchars($request->originAll()['title'], ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

        if ($request->get('status') == Board::STATUS_NOTICE && $this->isManager === false) {
            throw new HaveNoWritePermissionHttpException(['name' => xe_trans('xe::notice')]);
        }

        if ($request->get('status') == Board::STATUS_NOTICE) {
            $item->status = Board::STATUS_NOTICE;
        } else if ($request->get('status') != Board::STATUS_NOTICE && $item->status == Board::STATUS_NOTICE) {
            $item->status = Board::STATUS_PUBLIC;
        }

        // 암호 설정
        $oldCertifyKey = $item->certifyKey;
        if ($item->certifyKey != '' && isset($inputs['certifyKey']) === true && $inputs['certifyKey'] == '') {
            $inputs['certifyKey'] = $item->certifyKey;
        } elseif ($item->certifyKey != '' && isset($inputs['certifyKey']) === true && $inputs['certifyKey'] != '') {
            $inputs['certifyKey'] = $identifyManager->hash($inputs['certifyKey']);
        }

        /** @var \Xpressengine\Editor\AbstractEditor $editor */
        $editor = XeEditor::get($this->instanceId);
        $inputs['format'] = $editor->htmlable() ? Board::FORMAT_HTML : Board::FORMAT_NONE;

        $board = $this->handler->put($item, $inputs);

        // file 처리
        XeStorage::sync($board->getKey(), array_get($inputs, $editor->getFileInputName(), []));
        // tag 처리
        XeTag::set($board->getKey(), array_get($inputs, $editor->getTagInputName(), []), $this->instanceId);

        // 비회원 비밀번호를 변경 한 경우 세션 변경
        if ($oldCertifyKey != '' && $oldCertifyKey != $board->certifyKey) {
            $identifyManager->destroy($board);
            $identifyManager->create($board);
        }

        return redirect()->to(
            $this->urlHandler->getSlug(
                $item->boardSlug->slug,
                $this->urlHandler->queryStringToArray($request->get('queryString'))
            )
        );
    }

    /**
     * 비회원 인증 페이지
     *
     * @param Board       $board    board model
     * @param null|string $referrer referrer url (return page url)
     * @return \Xpressengine\Presenter\RendererInterface
     * @internal param DocumentEntity $doc document entity
     */
    public function identify(Board $board, $referrer = null)
    {
        // 레퍼러는 현재 url
        if ($referrer == null) {
            $referrer = app('url')->current();
        }
        return XePresenter::make('identify', [
            'board' => $board,
            'referrer' => $referrer,
        ]);
    }

    /**
     * 비회원 인증 처리
     *
     * @param Request         $request         request
     * @param IdentifyManager $identifyManager identify manager
     * @return mixed
     */
    public function identificationConfirm(Request $request, IdentifyManager $identifyManager)
    {
        $item = $this->handler->getModel($this->config)->find($request->get('id'));

        if ($item->certifyKey == '') {
            throw new InvalidRequestException;
        }

        if ($request->get('email') == '') {
            throw new RequiredValueHttpException(['name' => xe_trans('xe::email')]);
        }

        if ($request->get('certifyKey') == '') {
            throw new RequiredValueHttpException(['name' => xe_trans('xe::password')]);
        }

        if ($identifyManager->verify($item, $request->get('email'), $request->get('certifyKey')) === false) {
            throw new NotMatchedCertifyKeyException;
        }

        // 인증 되었다면 DB의 인증키를 세션에 저장
        $identifyManager->create($item);

        return redirect()->to($request->get('referrer', 'edit'));
    }

    /**
     * 미리보기
     *
     * @param Request                $request         request
     * @param Validator              $validator       validator
     * @param BoardPermissionHandler $boardPermission board permission handler
     * @return mixed
     */
    public function preview(Request $request, Validator $validator, BoardPermissionHandler $boardPermission)
    {
        if (
            Gate::denies(
            BoardPermissionHandler::ACTION_CREATE,
            new Instance($boardPermission->name($this->instanceId)))
        ) {
            throw new AccessDeniedHttpException;
        }

        /** @var UserInterface $user */
        $user = Auth::user();

        // get rules
        $this->validate($request, $validator->getCreateRule($user, $this->config));

        $content = $request->originAll()['content'];
        $title = htmlspecialchars($request->originAll()['title'], ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

        $writer = $user->getDisplayName();
        if ($request->get('writer', '') !== '') {
            $writer = $request->get('writer');
        }
        if ($this->config->get('anonymity') === true) {
            $writer = $this->config->get('anonymityName');
        }

        if ($request->get('categoryItemId', '') !== '') {

        }

        $showCategoryItem = null;
        if ($request->get('categoryItemId', '') !== '') {
            $showCategoryItem = CategoryItem::find($request->get('categoryItemId'));
        }

        /** @var \Xpressengine\Editor\AbstractEditor $editor */
        $editor = XeEditor::get($this->instanceId);
        $format = $editor->htmlable() ? Board::FORMAT_HTML : Board::FORMAT_NONE;

        return XePresenter::make('preview', [
            'config' => $this->config,
            'handler' => $this->handler,
            'currentDate' => date('Y-m-d H:i:s'),
            'title' => $title,
            'content' => $content,
            'writer' => $writer,
            'format' => $format,
            'showCategoryItem' => $showCategoryItem,
        ]);
    }

    /**
     * destroy
     *
     * @param Request         $request         request
     * @param IdentifyManager $identifyManager identify manager
     * @param string          $menuUrl         first segment
     * @param string          $id              document id
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function destroy(Request $request, IdentifyManager $identifyManager, $menuUrl, $id)
    {
        $user = Auth::user();

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        // 비회원이 작성 한 글 인증
        if (
            $item->isGuest() === true &&
            $identifyManager->identified($item) === false &&
            $user->getRating() != 'super'
        ) {
            return $this->identify($item, $this->urlHandler->get('edit', ['id' => $item->id]));
        }

        $this->handler->trash($item, $this->config);

        $identifyManager->destroy($item);

        return redirect()->to($this->urlHandler->get('index', $request->all()));
    }

    /**
     * trash
     *
     * @param Request $request request
     * @return mixed
     */
    public function trash(Request $request)
    {
        $user = Auth::user();
        $id = $request->get('id');

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        if ($user->getRating() != 'super' && $user->getId() != $item->id) {
            throw new AccessDeniedHttpException;
        }

        $id = $request->get('id');
        $author = Auth::user();

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        // 관리자 또는 본인 글이 아니면 접근 할 수 없음
        if ($author->getRating() !== 'super' && $author->getId() != $item->id) {
            throw new NotFoundDocumentException;
        }

        $this->handler->trash($item, $this->config);

        return redirect()->to($this->urlHandler->get('index'))->with(
            ['alert' => ['type' => 'success', 'message' => xe_trans('xe::complete')]]
        );
    }

    /**
     * 즐겨찾기 등록, 삭제
     *
     * @param string $menuUrl first segment
     * @param string $id      document id
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function favorite($menuUrl, $id)
    {
        if (Auth::check() === false) {
            throw new AccessDeniedHttpException;
        }
        $board = $this->handler->getModel($this->config)->find($id);

        $userId = Auth::user()->getId();
        $favorite = false;
        if ($this->handler->hasFavorite($board->id, $userId) === false) {
            $this->handler->addFavorite($board->id, $userId);
            $favorite = true;
        } else {
            $this->handler->removeFavorite($board->id, $userId);
        }

        return XePresenter::makeApi(['favorite' => $favorite]);
    }

    /**
     * 투표 정보
     *
     * @param Request $request request
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function showVote(Request $request, $id)
    {
        // display 설정
        $display =['assent' => true, 'dissent' => true];
        if ($this->config->get('assent') !== true) {
            $display['assent'] = false;
        }

        if ($this->config->get('dissent') !== true) {
            $display['dissent'] = false;
        }

        $user = Auth::user();

        $board = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($board, $this->config);

        $voteCounter = $this->handler->getVoteCounter();
        $vote = $voteCounter->getByName($id, $user);

        return XePresenter::makeApi([
            'display' => $display,
            'id' => $id,
            'counts' => [
                'assent' => $board->assentCount,
                'dissent' => $board->dissentCount,
            ],
            'voteAt' => $vote['counterOption'],
        ]);
    }

    /**
     * 좋아요 추가, 삭제
     *
     * @param Request $request request
     * @param string  $menuUrl first segment
     * @param string  $option  options
     * @return \Xpressengine\Presenter\RendererInterface
     */
    public function vote(Request $request, $menuUrl, $option, $id)
    {
        $author = Auth::user();

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        try {
            $this->handler->vote($item, $author, $option);
        } catch (GuestNotSupportException $e) {
            throw new AccessDeniedHttpException;
        }

        return $this->showVote($request, $id);
    }

    /**
     * get voted user list
     *
     * @param Request $request request
     * @param string  $menuUrl first segment
     * @param string  $option  options
     */
    public function votedUsers(Request $request, $menuUrl, $option, $id)
    {
        $limit = $request->get('limit', 10);

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        $counter = $this->handler->getVoteCounter();
        $logModel = $counter->newModel();
        $logs = $logModel->where('counterName', $counter->getName())->where('targetId', $id)
            ->where('counterOption', $option)->take($limit)->get();

        return apiRender('votedUsers', [
            'urlHandler' => $this->urlHandler,
            'option' => $option,
            'item' => $item,
            'logs' => $logs,
        ]);
    }

    /**
     * get voted user list
     *
     * @param Request $request request
     * @param string  $menuUrl first segment
     * @param string  $option  options
     */
    public function votedModal(Request $request, $menuUrl, $option, $id)
    {
        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        $counter = $this->handler->getVoteCounter();
        $logModel = $counter->newModel();
        $count = $logModel->where('counterName', $counter->getName())->where('targetId', $id)
            ->where('counterOption', $option)->count();

        return apiRender('votedModal', [
            'urlHandler' => $this->urlHandler,
            'option' => $option,
            'item' => $item,
            'count' => $count,
        ]);
    }

    public function votedUserList(Request $request, $menuUrl, $option, $id)
    {
        $startId = $request->get('startId');
        $limit = $request->get('limit', 10);

        $item = $this->handler->getModel($this->config)->find($id);
        $this->handler->setModelConfig($item, $this->config);

        $counter = $this->handler->getVoteCounter();
        $logModel = $counter->newModel();
        $query = $logModel->where('counterName', $counter->getName())->where('targetId', $id)
            ->where('counterOption', $option);

        if ($startId != null) {
            $query->where('id', '<', $startId);
        }

        $logs = $query->orderBy('id', 'desc')->take($limit)->get();
        $list = [];
        foreach ($logs as $log) {
//            if (Auth::user()->getId() == $log->userId) {
//                continue;
//            }

            $user = $log->user;
            $profilePage = '#';
            if ($user->getId() != '') {
                $profilePage = route('member.profile', ['member' => $user->getId()]);
            }
            $list[] = [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'profileImage' => $user->getProfileImage(),
                'createdAt' => (string)$log->createdAt,
                'profilePage' => $profilePage,
            ];
        }

        $nextStartId = 0;
        if (count($logs) == $limit) {
            $nextStartId = $logs->last()->id;
        }

        return XePresenter::makeApi([
            'item' => $item,
            'list' => $list,
            'nextStartId' => $nextStartId,
        ]);
    }

//    /**
//     * file upload
//     *
//     * @param Request $request request
//     * @param Storage $storage storage
//     * @return mixed
//     */
//    public function fileUpload(Request $request, Storage $storage)
//    {
//        $uploadedFile = null;
//        if ($request->file('file') !== null) {
//            $uploadedFile = $request->file('file');
//        } elseif ($request->file('image') !== null) {
//            $uploadedFile = $request->file('image');
//        }
//
//        if ($uploadedFile === null) {
//            throw new NotFoundUploadFileException;
//        }
//
//        $file = $storage->upload($uploadedFile, BoardModule::FILE_UPLOAD_PATH);
//
//        /** @var \Xpressengine\Media\MediaManager $mediaManager */
//        $mediaManager = \App::make('xe.media');
//        $media = null;
//        $thumbnails = null;
//        if ($mediaManager->is($file) === true) {
//            $media = $mediaManager->make($file);
//            $thumbnails = $mediaManager->createThumbnails($media, BoardModule::THUMBNAIL_TYPE);
//
//            $media = $media->toArray();
//
//            if (!empty($thumbnails)) {
//                $info['thumbnails'] = $thumbnails;
//            }
//        }
//
//        return XePresenter::makeApi([
//            'file' => $file->toArray(),
//            'media' => $media,
//            'thumbnails' => $thumbnails,
//        ]);
//    }
//
//    /**
//     * get file source
//     *
//     * @param BoardPermissionHandler $boardPermission board permission handler
//     * @param string                 $menuUrl         first segment
//     * @param string                 $id              document id
//     */
//    public function fileSource(BoardPermissionHandler $boardPermission, $menuUrl, $id)
//    {
//        if (Gate::denies(
//            BoardPermissionHandler::ACTION_READ,
//            new Instance($boardPermission->name($this->instanceId)))
//        ) {
//            throw new AccessDeniedHttpException;
//        }
//
//        $file = File::find($id);
//
//        /** @var \Xpressengine\Media\MediaManager $mediaManager */
//        $mediaManager = \App::make('xe.media');
//        if ($mediaManager->is($file) === true) {
//            $dimension = 'L';
//            if (\Agent::isMobile() === true) {
//                $dimension = 'M';
//            }
//            $media = Image::getThumbnail(
//                $mediaManager->make($file),
//                BoardModule::THUMBNAIL_TYPE,
//                $dimension
//            );
//        }
//
//        header('Content-type: ' . $media->mime);
//        echo $media->getContent();
//    }
//
//    public function fileDownload(BoardPermissionHandler $boardPermission, $menuUrl, $id)
//    {
//        if (Gate::denies(
//            BoardPermissionHandler::ACTION_READ,
//            new Instance($boardPermission->name($this->instanceId)))
//        ) {
//            throw new AccessDeniedHttpException;
//        }
//
//        $file = File::find($id);
//
//        /** @var \Xpressengine\Storage\Storage $storage */
//        $storage = \App::make('xe.storage');
//        $storage->download($file);
//    }
//
//    /**
//     * 해시태그 suggestion 리스트
//     *
//     * @param Request    $request request
//     * @param TagHandler $tag     tag handler
//     * @param string     $menuUrl first segment
//     * @param string     $id      document id
//     * @return mixed
//     */
//    public function suggestionHashTag(Request $request, TagHandler $tag, $menuUrl, $id = null)
//    {
//        $tags = $tag->similar($request->get('string'));
//
//        $suggestions = [];
//        foreach ($tags as $tag) {
//            $suggestions[] = [
//                'id' => $tag->id,
//                'word' => $tag->word,
//            ];
//        }
//
//        return XePresenter::makeApi($suggestions);
//    }
//
//    /**
//     * 멘션 suggestion 리스트
//     *
//     * @param Request $request request
//     * @param string  $menuUrl first segment
//     * @param string  $id      document id
//     * @return mixed
//     */
//    public function suggestionMention(Request $request, $menuUrl, $id = null)
//    {
//        $suggestions = [];
//
//        $string = $request->get('string');
//        $users = User::where('displayName', 'like', $string . '%')->where('id', '<>', Auth::user()->getId())->get();
//        foreach ($users as $user) {
//            $suggestions[] = [
//                'id' => $user->getId(),
//                'displayName' => $user->getDisplayName(),
//                'profileImage' => $user->profileImage,
//            ];
//        }
//
//        return XePresenter::makeApi($suggestions);
//    }
}
