<?php
namespace ManaPHP\Mvc\Action;

interface InvokerInterface
{
    /**
     * @param \ManaPHP\Mvc\ControllerInterface $controller
     * @param string                           $action
     * @param array                            $params
     *
     * @return mixed
     */
    public function invokeAction($controller, $action, $params);
}