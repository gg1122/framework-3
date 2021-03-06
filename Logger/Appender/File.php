<?php

namespace ManaPHP\Logger\Appender;

use ManaPHP\Component;
use ManaPHP\Logger\AppenderInterface;

/**
 * Class ManaPHP\Logger\Appender\File
 *
 * @package logger
 */
class File extends Component implements AppenderInterface
{
    /**
     * @var string
     */
    protected $_file;

    /**
     * @var string
     */
    protected $_format = '[{date}][{level}][{category}][{location}] {message}';

    /**
     * @var bool
     */
    protected $_firstLog = true;

    /**
     * \ManaPHP\Logger\Adapter\File constructor.
     *
     * @param string|array|\ConfManaPHP\Logger\Adapter\File $options
     */
    public function __construct($options = [])
    {
        if (is_object($options)) {
            $options = (array)$options;
        } elseif (is_string($options)) {
            $options = ['file' => $options];
        }

        if (!isset($options['file'])) {
            $options['file'] = '@data/logger/' . date('ymd') . '.log';
        }

        $this->_file = $options['file'];

        if (isset($options['format'])) {
            $this->_format = $options['format'];
        }
    }

    /**
     * @param array $logEvent
     *
     * @return void
     */
    public function append($logEvent)
    {
        if ($this->_firstLog) {
            $this->_file = $this->alias->resolve($this->_file);

            $dir = dirname($this->_file);

            /** @noinspection NotOptimalIfConditionsInspection */
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                /** @noinspection ForgottenDebugOutputInspection */
                trigger_error('Unable to create \'' . $dir . '\' directory: ' . error_get_last()['message'], E_USER_WARNING);
            }

            $this->_firstLog = false;
        }

        $logEvent['date'] = date('Y-m-d H:i:s', $logEvent['timestamp']);

        $logEvent['message'] .= PHP_EOL;

        $replaced = [];
        foreach ($logEvent as $k => $v) {
            $replaced['{' . $k . '}'] = $v;
        }

        if (file_put_contents($this->_file, strtr($this->_format, $replaced), FILE_APPEND | LOCK_EX) === false) {
            /** @noinspection ForgottenDebugOutputInspection */
            trigger_error('Write log to file failed: ' . $this->_file, E_USER_WARNING);
        }
    }
}