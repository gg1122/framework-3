<?php
namespace ManaPHP\Mvc\Action;

use ManaPHP\Component;
use ManaPHP\Mvc\Action\Exception as ActionException;

class Filter extends Component implements FilterInterface
{
    /**
     * Filter constructor.
     *
     * @param array $parameters
     *
     * @throws \ManaPHP\Mvc\Action\Exception
     */
    public function __construct($parameters)
    {
        foreach ($parameters as $k => $v) {
            $property = '_' . $k;
            if (!isset($this->$property)) {
                throw new ActionException('invalid `:parameter` parameter for `:filter` filter', ['parameter' => $k, 'filter' => get_called_class()]);
            }

            $this->$property = $v;
        }
    }

    public function beforeAction($action)
    {
        return true;
    }

    public function afterAction($action)
    {
        return true;
    }

    /**
     * @param string $action
     *
     * @return  bool
     */
    public function _isActive($action)
    {

    }
}