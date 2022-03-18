<?php

namespace Dotink\Jin;

use Adbar\Dot;

class Collection extends Dot
{
	protected $callbacks = array();


	/**
	 *
	 */
	static public function __set_state($data)
	{
		return new static($data['items']);
	}


	/**
	 *
	 */
	public function delete($keys)
	{
		if (!is_array($keys)) {
			foreach ($this->callbacks[__FUNCTION__] ?? [] as $callback) {
				$callback($keys);
			}
		}

		return parent::delete($keys);
	}



	/**
	 *
	 */
	public function on($method, callable $callback)
	{
		$method = strtolower($method);

		if (!isset($this->callbacks[$method])) {
			$this->callbacks[$method] = array();
		}

		$this->callbacks[$method][] = $callback;

		return $this;
	}


	/**
	 *
	 */
	public function set($keys, $value = NULL)
	{
		if (!is_array($keys)) {
			foreach ($this->callbacks[__FUNCTION__] ?? [] as $callback) {
				$callback($keys, $value);
			}
		}

		return parent::set($keys, $value);
	}
}
