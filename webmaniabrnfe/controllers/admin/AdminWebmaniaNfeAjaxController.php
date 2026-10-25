<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/../../sdk/Ajax.php';

/** Native employee session/token checks plus the permission of the requested resource. */
class AdminWebmaniaNfeAjaxController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
        $resource = WebmaniaNFeAjax::resource(Tools::getValue('method'));
        $this->tabAccess = $resource === false ? array('view' => 0) : Profile::getProfileAccess(
            (int) $this->context->employee->id_profile, (int) Tab::getIdFromClassName($resource));
    }

    public function postProcess()
    {
        WebmaniaNFeAjax::dispatch($this->context, true);
    }
}
