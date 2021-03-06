<?php

namespace ZippyERP\System\Pages;

class Error extends \ZippyERP\System\Pages\Base
{

    public function __construct($error = '')
    {
        parent::__construct();

        $this->add(new \Zippy\Html\Label('msg', $error));
    }

}
