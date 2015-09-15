<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Calls functions on the global scope, so we can mock out Moodle's global functions when testing
 * Class moodle_functions
 */
class moodle_functions {

    /**
     * @var array
     */
    protected $_attached = [];

    /**
     * attach a function
     * @param string $name
     * @param callable $method
     */
    public function attach($name, $method) {
        if (!array_key_exists($name, $this->_attached)) {
            $this->_attached[$name] = $method;
        }
    }

    /**
     * call functions that have been attached or, failing that, a global function
     * @throws moodle_exception
     * @param string $name
     * @param array $fargs
     * @return mixed
     */
    public function __call($name, $fargs) {
        if (array_key_exists($name, $this->_attached)) {
            return call_user_func_array($this->_attached[$name], $fargs);
        }
        if (!function_exists($name)) {
            throw new moodle_exception('No such global function ' . $name);
        }
        return call_user_func_array($name, $fargs);
    }

}
