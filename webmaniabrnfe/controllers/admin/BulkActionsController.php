<?php

namespace webmaniabrnfe\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShop\PrestaShop\Adapter\Module\Module;

class BulkActionsController extends FrameworkBundleAdminController {

  public function emitirNfe() {

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

  public function imprimirDanfe() {
    
    $ids = $_POST['order_orders_bulk'];
    $uri = $_SERVER['REQUEST_URI'];

    if (strpos($uri, 'imprimir_danfe_etiqueta') !== false) {
      $type = 'etiqueta';
    }
    else if (strpos($uri, 'imprimir_danfe_simples') !== false) {
      $type = 'simples';
    }
    else {
      $type = 'normal';
    }   

    $modules = Module::getModulesInstalled();
    $key = array_search('webmaniabrnfe', array_column($modules, 'name'));
    $module_id = $modules[$key]['id_module'];
    $webmaniabrnfe = Module::getInstanceById($module_id);
    $response = $webmaniabrnfe->get_nfe_urls($ids, $type);

    if (!$response['result'] || empty($response['file']) || !file_exists($response['file'])) {
      return $this->redirect($this->getAdminLink('AdminOrders', array()));
    }

    $webmaniabrnfe->showDanfe($response['file'], $response['filename'], true);
  
  }

}