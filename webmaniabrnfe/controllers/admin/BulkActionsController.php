<?php

namespace webmaniabrnfe\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class BulkActionsController extends FrameworkBundleAdminController {

  public function emitirNfe() {

    $ids = $_POST['order_orders_bulk'];
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

    $webmaniabrnfe = \Module::getInstanceByName('webmaniabrnfe');
    $response = $webmaniabrnfe->get_nfe_urls($ids, $type);

    if (!$response['result'] || empty($response['file']) || !file_exists($response['file'])) {
      return $this->redirect($this->getAdminLink('AdminOrders', array()));
    }

    $webmaniabrnfe->showDanfe($response['file'], $response['filename'], true);
  
  }

}