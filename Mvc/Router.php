<?php

namespace ManaPHP\Mvc;

use ManaPHP\Component;
use ManaPHP\Mvc\Router\NotFoundRouteException;
use ManaPHP\Utility\Text;

/**
 * Class ManaPHP\Mvc\Router
 *
 * @package router
 *
 * @property \ManaPHP\Http\RequestInterface $request
 */
class Router extends Component implements RouterInterface
{
    /**
     * @var string
     */
    protected $_module;

    /**
     * @var string
     */
    protected $_controller;

    /**
     * @var string
     */
    protected $_action;

    /**
     * @var array
     */
    protected $_params = [];

    /**
     * @var array
     */
    protected $_groups = [];

    /**
     * @var array
     */
    protected $_modules = [];

    /**
     * @var bool
     */
    protected $_wasMatched = false;

    /**
     * @var bool
     */
    protected $_removeExtraSlashes = false;

    /**
     * Get rewrite info. This info is read from $_GET['_url'] or _SERVER["REQUEST_URI"].
     *
     * @return string
     * @throws \ManaPHP\Mvc\Router\Exception
     */
    public function getRewriteUri()
    {
        if ($this->request->hasQuery('_url')) {
            return $this->request->getQuery('_url', 'ignore');
        } elseif ($this->request->hasServer('PATH_INFO')) {
            return $this->request->getServer('PATH_INFO');
        } else {
            return '/';
        }
    }

    /**
     * Set whether router must remove the extra slashes in the handled routes
     *
     * @param bool $remove
     *
     * @return static
     */
    public function removeExtraSlashes($remove)
    {
        $this->_removeExtraSlashes = $remove;

        return $this;
    }

    /**
     * Handles routing information received from the rewrite engine
     *
     *<code>
     * //Read the info from the rewrite engine
     * $router->handle();
     *
     * //Manually passing an URL
     * $router->handle('/posts/edit/1');
     *</code>
     *
     * @param string $uri
     * @param string $host
     * @param bool   $silent
     *
     * @return bool
     * @throws \ManaPHP\Di\Exception
     * @throws \ManaPHP\Mvc\Router\Exception
     * @throws \ManaPHP\Mvc\Router\NotFoundRouteException
     */
    public function handle($uri = null, $host = null, $silent = true)
    {
        if ($uri === null) {
            $uri = $this->getRewriteUri();
        }

        if ($this->_removeExtraSlashes) {
            $uri = rtrim($uri, '/');
        }
        $refinedUri = $uri === '' ? '/' : $uri;

        $this->fireEvent('router:beforeCheckRoutes');

        $module = null;
        $routeFound = false;
        for ($i = count($this->_groups) - 1; $i >= 0; $i--) {
            $group = $this->_groups[$i];

            $path = $group['path'];
            $module = $group['module'];

            if ($path === '' || $path[0] === '/') {
                $checkedUri = $refinedUri;
            } else {
                $checkedUri = (strpos($path, '://') ? $this->request->getScheme() . '://' : '') . $_SERVER['HTTP_HOST'] . $refinedUri;
            }

            /**
             * strpos('/','')===false NOT true
             */
            if ($path !== '' && !Text::startsWith($checkedUri, $path)) {
                continue;
            }

            /**
             * substr('a',1)===false NOT ''
             */
            $handledUri = strlen($checkedUri) === strlen($path) ? '/' : substr($checkedUri, strlen($path));

            /**
             * @var \ManaPHP\Mvc\Router\Group $groupInstance
             */
            if ($group['groupInstance'] === null) {
                $group['groupInstance'] = $this->_dependencyInjector->get(class_exists($group['groupClassName']) ? $group['groupClassName'] : 'ManaPHP\Mvc\Router\Group');
            }
            $groupInstance = $group['groupInstance'];

            $parts = $groupInstance->match($handledUri);
            $routeFound = $parts !== false;
            if ($routeFound) {
                break;
            }
        }

        $this->_wasMatched = $routeFound;

        if ($routeFound) {
            $this->_module = $module;
            $this->_controller = isset($parts['controller']) ? basename($parts['controller'], 'Controller') : 'index';
            $this->_action = isset($parts['action']) ? basename($parts['action'], 'Action') : 'index';

            $params = [];
            if (isset($parts['params'])) {
                $params_str = trim($parts['params'], '/');
                if ($params_str !== '') {
                    $params = explode('/', $params_str);
                }
            }

            unset($parts['controller'], $parts['action'], $parts['params']);

            $this->_params = array_merge($params, $parts);
        }

        $this->fireEvent('router:afterCheckRoutes');

        if (!$routeFound && !$silent) {
            throw new NotFoundRouteException('router does not have matched route for `:uri`'/**m0980aaf224562f1a4*/, ['uri' => $uri]);
        }

        return $routeFound;
    }

    /**
     * Mounts a group of routes in the router
     *
     * @param string|\ManaPHP\Mvc\Router\GroupInterface $group
     * @param string                                    $path
     *
     * @return static
     */
    public function mount($group, $path = null)
    {
        if (is_object($group)) {
            $groupClassName = get_class($group);
            $groupInstance = $group;
        } else {
            $groupClassName = strrpos($group, '\\') ? $group : $this->alias->resolve("@ns.app\\$group\\RouteGroup");
            $groupInstance = null;
        }

        $parts = explode('\\', $groupClassName);
        unset($parts[0]);
        array_pop($parts);
        $module = implode('\\', $parts);

        if ($path === null) {
            $path = '/' . $module;
        }

        $path = rtrim($path, '/');

        $this->_groups[] = [
            'path' => $path,
            'module' => $module,
            'groupClassName' => $groupClassName,
            'groupInstance' => $groupInstance
        ];

        $this->_modules[$module] = $path ?: '/';

        return $this;
    }

    /**
     * Returns the processed module name
     *
     * @return string
     */
    public function getModuleName()
    {
        return $this->_module;
    }

    /**
     * Returns the processed controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        return $this->_controller;
    }

    /**
     * Returns the processed action name
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->_action;
    }

    /**
     * Returns the processed parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Checks if the router matches any of the defined routes
     *
     * @return bool
     */
    public function wasMatched()
    {
        return $this->_wasMatched;
    }

    /**
     * @return array
     */
    public function getModules()
    {
        return $this->_modules;
    }
}