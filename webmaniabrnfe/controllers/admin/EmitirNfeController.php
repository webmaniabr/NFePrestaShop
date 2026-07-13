<?php

namespace webmaniabrnfe\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class EmitirNfeController extends FrameworkBundleAdminController {

  public function receiveBulkAction() {
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

}