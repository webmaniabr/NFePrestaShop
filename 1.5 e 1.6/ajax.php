<?php

require_once(dirname(__FILE__).'../../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');
switch (Tools::getValue('method')) {
  case 'getAddressInfo' :
    $address = new Address(Tools::getValue('addressID'));
    $result = Db::getInstance()->executeS('SELECT address_number FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);
    echo json_encode($result);
    break;
    case 'checkForDoc':
      $customer_id = Context::getContext()->customer->id;
      checkForDocument(Tools::getValue('address_id'), $customer_id);
      break;
      case 'getNfeStatus':
        $orderID = Tools::getValue('order_id');
        $result = Db::getInstance()->getValue('SELECT nfe_issued FROM '._DB_PREFIX_.'orders WHERE id_order = ' . (int)$orderID);
        echo json_encode($result);
        break;
      case 'getProductInfo':
        $product_id = Tools::getValue('productID');
        $result = Db::getInstance()->getRow('SELECT nfe_tax_class, nfe_ean_bar_code, nfe_ncm_code, nfe_cest_code, nfe_product_source FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
        echo json_encode($result);
        break;
      case 'storeAddressInfo':
        createNewAddress();
        break;
      case 'updateAddressInfo':
        updateAddressInfo(Tools::getValue('addressID'));
        break;
  default:
    exit;
}


function checkForDocument($address_id, $customer_id){
  $result_customer = Db::getInstance()->executeS('SELECT nfe_document_number FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer_id);
  $result_address = Db::getInstance()->executeS('SELECT address_number FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address_id);

  $result = array();

  if(empty($result_customer[0]['nfe_document_number'])){
    $result['document_number'] = 'error';
  }

  if(empty($result_address[0]['address_number'])){
    $result['nfe_number'] = 'error';
  }

  if(!isset($result['document_number']) && !isset($result['address_number'])){
    $result['success'] = 'success';
  }

  echo json_encode($result);
  die();
}

exit;
