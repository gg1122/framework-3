<?php
namespace ManaPHP\Cache\Adapter\Db;

/**
 * Class ManaPHP\Cache\Adapter\Db\Model
 *
 * @package cache\adapter
 */
class Model extends \ManaPHP\Db\Model
{
    /**
     * @var string
     */
    public $hash;

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $value;

    /**
     * @var int
     */
    public $expired_time;

    public static function getSource($context = null)
    {
        return 'manaphp_cache';
    }
}