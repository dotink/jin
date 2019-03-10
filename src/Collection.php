<?php

namespace Dotink\Jin;

use Adbar\Dot;

class Collection extends Dot
{
	/**
	 *
	 */
	static public function __set_state($data)
    {
        return new static($data['items']);
    }
}
