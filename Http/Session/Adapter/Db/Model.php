<?php
namespace ManaPHP\Http\Session\Adapter\Db;

/**
 * Class ManaPHP\Http\Session\Adapter\Db\Model
 *
 * @package session\adapter
 */
class Model extends \ManaPHP\Db\Model
{

    /**
     * @var int
     */
    public $session_id;

    /**
     * @var string
     */
    public $data;

    /**
     * @var int
     */
    public $expired_time;

    public static function getSource($context = null)
    {
        return 'manaphp_session';
    }
}