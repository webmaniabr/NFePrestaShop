<?php

namespace webmaniabrnfe\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShop\PrestaShop\Adapter\Module\Module;

class BulkActionsController extends FrameworkBundleAdminController {

  public function emitirNfe() {
    $this->denyAccessUnlessGranted('update', 'AdminOrders');


    $ids = isset($_POST['order_orders_bulk']) && is_array($_POST['order_orders_bulk']) ? array_filter(array_map('intval', $_POST['order_orders_bulk'])) : array();
    $webmaniabrnfe = \Module::getInstanceByName('webmaniabrnfe');

    foreach ($ids as $id) {
      $responses = $webmaniabrnfe->emitirNfe($id);

      foreach ($responses as $response) {
          $this->addFlash($response['type'], $response['msg']);
      }

    }

    return $this->redirect($this->getAdminLink('AdminOrders', array()));
  }

  public function imprimirDanfe() {
    $this->denyAccessUnlessGranted('read', 'AdminOrders');

    
    $ids = isset($_POST['order_orders_bulk']) && is_array($_POST['order_orders_bulk']) ? array_filter(array_map('intval', $_POST['order_orders_bulk'])) : array();
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

    $webmaniabrnfe = \Module::getInstanceByName('webmaniabrnfe');
    $response = $webmaniabrnfe->get_nfe_urls($ids, $type);

    if (!$response['result'] || empty($response['file']) || !file_exists($response['file'])) {
      return $this->redirect($this->getAdminLink('AdminOrders', array()));
    }

    $webmaniabrnfe->showDanfe($response['file'], $response['filename'], true);
  
  }

}