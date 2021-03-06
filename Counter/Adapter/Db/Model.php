<?php
namespace ManaPHP\Counter\Adapter\Db;

/**
 * Class ManaPHP\Counter\Adapter\Db\Model
 *
 * @package counter\adapter
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
    public $type;

    /**
     * @var string
     */
    public $id;

    /**
     * @var int
     */
    public $value;

    public static function getSource($context = null)
    {
        return 'manaphp_counter';
    }
}