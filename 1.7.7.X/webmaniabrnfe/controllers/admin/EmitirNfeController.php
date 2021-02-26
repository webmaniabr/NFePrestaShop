<?php

namespace webmaniabrnfe\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShop\PrestaShop\Adapter\Module\Module;

class EmitirNfeController extends FrameworkBundleAdminController {

  public function receiveBulkAction() {
    $ids = $_POST['order_orders_bulk'];
    $modules = Module::getModulesInstalled();
    $key = array_search('webmaniabrnfe', array_column($modules, 'name'));
    $module_id = $modules[$key]['id_module'];
    $webmaniabrnfe = Module::getInstanceById($module_id);

    foreach ($ids as $id) {
      $responses = $webmaniabrnfe->emitirNfe($id);

      foreach ($responses as $response) {
          $this->addFlash($response['type'], $response['msg']);
      }

    }

    return $this->redirect($this->getAdminLink('AdminOrders', array()));
  }

}