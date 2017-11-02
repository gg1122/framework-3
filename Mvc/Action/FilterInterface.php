<?php
namespace ManaPHP\Mvc\Action;

interface FilterInterface
{
    /**
     * Filter constructor.
     *
     * @param array $parameters
     */
    public function __construct($parameters);

    /**
     * @param string $action
     *
     * @return mixed
     */
    public function beforeAction($action);

    /**
     * @param string $action
     *
     * @return mixed
     */
    public function afterAction($action);
}