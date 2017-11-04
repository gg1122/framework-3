<?php

namespace ManaPHP;

/**
 * Class ManaPHP\Loader
 *
 * @package loader
 */
class Loader
{
    /**
     * @var array
     */
    protected $_classes = [];

    /**
     * @var array
     */
    protected $_namespaces = [];

    /**
     * Loader constructor.
     */
    public function __construct()
    {
        $this->_namespaces['ManaPHP'] = DIRECTORY_SEPARATOR === '\\' ? strtr(__DIR__, '\\', '/') : __DIR__;
        spl_autoload_register([$this, '_autoload']);
    }

    /**
     * Register namespaces and their related directories
     *
     * <code>
     * $loader->registerNamespaces(array(
     *        ’Example\\Base’ => ’vendor/example/base/’,
     *        ’Example\\Adapter’ => ’vendor/example/adapter/’,
     *        ’Example’ => ’vendor/example/’
     *        ));
     * </code>
     *
     * @param array $namespaces
     * @param bool  $merge
     *
     * @return static
     */
    public function registerNamespaces($namespaces, $merge = true)
    {
        foreach ($namespaces as $namespace => $path) {
            $path = rtrim($path, '\\/');
            if (DIRECTORY_SEPARATOR === '\\') {
                $namespaces[$namespace] = strtr($path, '\\', '/');
            }
        }

        $this->_namespaces = $merge ? array_merge($this->_namespaces, $namespaces) : $namespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getRegisteredNamespaces()
    {
        return $this->_namespaces;
    }

    /**
     * Register classes and their locations
     *
     * @param array $classes
     * @param bool  $merge
     *
     * @return static
     */
    public function registerClasses($classes, $merge = true)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach ($classes as $key => $path) {
                $classes[$key] = strtr($path, '\\', '/');
            }
        }

        $this->_classes = $merge ? array_merge($this->_classes, $classes) : $classes;

        return $this;
    }

    /**
     * If a file exists, require it from the file system.
     *
     * @param string $file The file to require.
     *
     * @return true
     */
    protected function _requireFile($file)
    {
        if (PHP_EOL !== "\n" && strpos($file, 'phar://') !== 0) {
            $realPath = strtr(realpath($file), '\\', '/');
            if ($realPath !== $file) {
                trigger_error("File name ($realPath) case mismatch for .$file", E_USER_ERROR);
            }
        }
        /** @noinspection PhpIncludeInspection */
        require $file;
        return true;
    }

    /**
     * Makes the work of autoload registered classes
     *
     * @param string $className
     *
     * @return bool
     */
    public function _autoload($className)
    {
        if (isset($this->_classes[$className])) {
            $file = $this->_classes[$className];
            if (!is_file($file)) {
                trigger_error(strtr('load `:class` class failed: `:file` is not exists.', [':class' => $className, ':file' => $file]), E_USER_ERROR);
            }

            //either linux or phar://
            if (PHP_EOL === "\n" || $file[0] === 'p') {
                /** @noinspection PhpIncludeInspection */
                require $file;
                return true;
            } else {
                return $this->_requireFile($file);
            }
        }

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($this->_namespaces as $namespace => $path) {
            if (strpos($className, $namespace) !== 0) {
                continue;
            }

            $file = $path . strtr(substr($className, strlen($namespace)), '\\', '/') . '.php';
            if (is_file($file)) {
                //either linux or phar://
                if (PHP_EOL === "\n" || $file[0] === 'p') {
                    /** @noinspection PhpIncludeInspection */
                    require $file;
                    return true;
                } else {
                    return $this->_requireFile($file);
                }
            }
        }

        return false;
    }

    public function dump()
    {
        return get_object_vars($this);
    }
}
