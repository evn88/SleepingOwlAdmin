<?php

namespace SleepingOwl\Admin\Display;

use Exception;
use SleepingOwl\Admin\Traits\Assets;
use SleepingOwl\Admin\Form\FormElements;
use SleepingOwl\Admin\Traits\Renderable;
use KodiComponents\Support\HtmlAttributes;
use SleepingOwl\Admin\Display\Extension\Apply;
use SleepingOwl\Admin\Display\Extension\Links;
use SleepingOwl\Admin\Display\Extension\Scopes;
use SleepingOwl\Admin\Display\Extension\Actions;
use SleepingOwl\Admin\Display\Extension\Filters;
use SleepingOwl\Admin\Display\Extension\ActionsForm;
use SleepingOwl\Admin\Contracts\Display\DisplayInterface;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use SleepingOwl\Admin\Contracts\Repositories\RepositoryInterface;
use SleepingOwl\Admin\Contracts\Display\DisplayExtensionInterface;
use SleepingOwl\Admin\Contracts\Display\Extension\ActionInterface;
use SleepingOwl\Admin\Contracts\Display\Extension\FilterInterface;

/**
 * Class Display.
 *
 * @method Actions getActions()
 * @method $this setActions(ActionInterface|array $action, ...$actions)
 *
 * @method ActionsForm getActionsForm()
 * @method $this setActionsForm(ActionInterface|array|FormElements $action, ...$actions)
 *
 * @method Filters getFilters()
 * @method $this setFilters(FilterInterface $filter, ...$filters)
 *
 * @method Apply getApply()
 * @method $this setApply(\Closure $apply, ...$applies)
 *
 * @method Scopes getScopes()
 * @method $this setScopes(array $scope, ...$scopes)
 */
abstract class Display implements DisplayInterface
{
    use HtmlAttributes, Assets, Renderable;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $repositoryClass = RepositoryInterface::class;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @var DisplayExtensionInterface[]|ExtensionCollection
     */
    protected $extensions;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * Display constructor.
     */
    public function __construct()
    {
        $this->extensions = new ExtensionCollection();

        $this->extend('actions', new Actions());
        $this->extend('actions_form', new ActionsForm());
        $this->extend('filters', new Filters());
        $this->extend('apply', new Apply());
        $this->extend('scopes', new Scopes());
        $this->extend('links', new Links());

        $this->initializePackage();
    }

    /**
     * @return bool
     */
    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * @param string $name
     * @param DisplayExtensionInterface $extension
     *
     * @return DisplayExtensionInterface
     */
    public function extend($name, DisplayExtensionInterface $extension)
    {
        $this->extensions->put($name, $extension);

        $extension->setDisplay($this);

        return $extension;
    }

    /**
     * @return ExtensionCollection|DisplayExtensionInterface[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * @return RepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param $repositoryClass
     *
     * @return $this
     */
    public function setRepositoryClass($repositoryClass)
    {
        $this->repositoryClass = $repositoryClass;

        return $this;
    }

    /**
     * @param array|string[] ...$relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $this->with = array_flatten(func_get_args());

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function initialize()
    {
        if ($this->isInitialized()) {
            return;
        }

        $this->repository = $this->makeRepository();
        $this->repository->with($this->with);

        $this->extensions->initialize();

        $this->includePackage();

        $this->initialized = true;
    }

    /**
     * @param string $modelClass
     *
     * @return $this
     */
    public function setModelClass($modelClass)
    {
        if (is_null($this->modelClass)) {
            $this->modelClass = $modelClass;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $titles = [
            $this->title,
        ];

        $this->getExtensions()->each(function (DisplayExtensionInterface $extension) use (&$titles) {
            if (method_exists($extension, $method = 'getTitle')) {
                $titles[] = call_user_func([$extension, $method]);
            }
        });

        return implode(' | ', array_filter($titles));
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'title' => $this->getTitle(),
            'extensions' => $this->getExtensions()->toArray(),
            'attributes' => $this->htmlAttributesToString(),
        ];
    }

    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render()
    {
        $view = app('sleeping_owl.template')->view($this->getView(), $this->toArray());

        $blocks = $this->getExtensions()->placableBlocks();

        foreach ($blocks as $block => $data) {
            foreach ($data as $html) {
                if (! empty($html)) {
                    $view->getFactory()->startSection($block);
                    echo $html;
                    $view->getFactory()->yieldSection();
                } else {
                    $view->getFactory()->flushSections();
                }
            }
        }

        return $view;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return DisplayExtensionInterface
     */
    public function __call($name, $arguments)
    {
        $method = snake_case(substr($name, 3));

        if (starts_with($name, 'get') && $this->extensions->has($method)) {
            return $this->extensions->get($method);
        } elseif (starts_with($name, 'set') && $this->extensions->has($method)) {
            $extension = $this->extensions->get($method);

            if (method_exists($extension, 'set')) {
                return call_user_func_array([$extension, 'set'], $arguments);
            }
        }

        throw new \BadMethodCallException("Call to undefined method [{$name}]");
    }

    /**
     * @return ModelConfigurationInterface
     */
    public function getModelConfiguration()
    {
        return app('sleeping_owl')->getModel($this->modelClass);
    }

    /**
     * @return \Illuminate\Foundation\Application|mixed
     * @throws \Exception
     */
    protected function makeRepository()
    {
        $repository = app($this->repositoryClass);
        if (! ($repository instanceof RepositoryInterface)) {
            throw new Exception('Repository class must be instanced of [RepositoryInterface]');
        }

        $repository->setClass($this->modelClass);

        return $repository;
    }
}
