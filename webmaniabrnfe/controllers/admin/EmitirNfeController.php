<?php

namespace webmaniabrnfe\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
// Usar classe legada para máxima compatibilidade
use Module as LegacyModule;

class EmitirNfeController extends FrameworkBundleAdminController {

  public function receiveBulkAction() {
    $ids = $_POST['order_orders_bulk'] ?? $_POST['bulk_action_selected_orders'] ?? array();
    
    // Get module instance with compatibilidade máxima (classe legada)
    $webmaniabrnfe = \Module::getInstanceByName('webmaniabrnfe');
    
    if (!$webmaniabrnfe) {
      $this->addFlash('error', 'Módulo WebmaniaBR NFe não encontrado');
      return $this->redirect($this->getAdminLink('AdminOrders', array()));
    }

    foreach ($ids as $id) {
      try {
        $responses = $webmaniabrnfe->emitirNfe($id);

        foreach ($responses as $response) {
          $this->addFlash($response['type'], $response['msg']);
        }
      } catch (Exception $e) {
        $this->addFlash('error', 'Erro ao emitir NFe para pedido ' . $id . ': ' . $e->getMessage());
      }
    }

    return $this->redirect($this->getAdminLink('AdminOrders', array()));
  }

}