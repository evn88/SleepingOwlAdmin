<?php

namespace SleepingOwl\Admin\Display\Extension;

use Illuminate\Support\Collection;
use SleepingOwl\Admin\Form\FormElement;
use KodiComponents\Support\HtmlAttributes;
use SleepingOwl\Admin\Contracts\Initializable;
use SleepingOwl\Admin\Contracts\Display\Placable;
use SleepingOwl\Admin\Contracts\Display\Extension\ActionInterface;

class ActionsForm extends Extension implements Initializable, Placable
{
    use HtmlAttributes;

    /**
     * @var ActionInterface[]|Collection
     */
    protected $actions;

    /**
     * @var string|\Illuminate\View\View
     */
    protected $view = 'display.extensions.actions_form';

    /**
     * @var string
     */
    protected $placement = 'panel.footer';

    public function __construct()
    {
        $this->clear();
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->actions = new Collection();

        return $this;
    }

    /**
     * @param Collection|array $actions
     *
     * @return \SleepingOwl\Admin\Contracts\Display\DisplayInterface
     */
    public function set($actions)
    {
        if (! is_array($actions)) {
            $actions = func_get_args();
        }

        $this->clear();

        foreach ($actions as $action) {
            $this->push($action);
        }

        return $this->getDisplay();
    }

    /**
     * @return ActionInterface[]|Collection
     */
    public function all()
    {
        return $this->actions;
    }

    /**
     * @param FormElement $action
     *
     * @return $this
     */
    public function push(FormElement $action)
    {
        $this->actions->push($action);

        return $this;
    }

    /**
     * @return string|\Illuminate\View\View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param string|\Illuminate\View\View $view
     *
     * @return $this
     */
    public function setView($view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * @return string
     */
    public function getPlacement()
    {
        return $this->placement;
    }

    /**
     * @param string $placement
     *
     * @return $this
     */
    public function setPlacement($placement)
    {
        $this->placement = $placement;

        return $this;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $this->all()->each(function ($action) {
            $action->initialize();
        });

        $return = [
            'actions' => $this->actions,
            'placement' => $this->getPlacement(),
            'attributes' => $this->htmlAttributesToString(),
        ];

        return $return;
    }

    /**
     * Initialize class.
     */
    public function initialize()
    {
        if ($this->all()->count() < 1) {
            return;
        }

        $this->all()->each(function ($action) {
            $action->initialize();
        });

        $this->setHtmlAttribute('class', 'display-actions-form-wrapper');
    }
}
