<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/sdk/Ajax.php';
WebmaniaNFeAjax::dispatch(Context::getContext(), false);
