<?php


require_once(dirname(__FILE__).'../../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');



if( Tools::getValue('adminToken') != Tools::getAdminToken('7Br2ZZwaRD') ){
  exit;
}


switch (Tools::getValue('method')) {
  
  case 'getPSColumns':
    
    $table_name = pSql(Tools::getValue('table_name'));
    $results = Db::getInstance()->executeS("SELECT column_name from information_schema.columns where table_name = '$table_name'");
    
    $columns = array_map(function($element){
      return $element['column_name'];
    }, $results);
    
    echo json_encode(array('results' => $columns));
    die();
    break;
  
  case 'getAddressInfo' :

    $address = new Address(Tools::getValue('addressID'));
    $result = Db::getInstance()->getRow('SELECT address_number, bairro FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);

    echo json_encode($result);
    break;
    case 'checkForDoc':

      $customer_id = Context::getContext()->customer->id;
      if(is_null($customer_id)) $customer_id = Tools::getValue('id_customer');

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
      case 'getCategoryNcm':
        getCategoryNcm( Tools::getValue('id_category') );
        break;
  default:
    exit;
}


function getCategoryNcm($id_category){

  $sql = 'SELECT nfe_category_ncm FROM '._DB_PREFIX_.'category WHERE id_category = '.(int)$id_category.'';
  $ncm = Db::getInstance()->getValue($sql);

  if($ncm){
    echo json_encode(array('ncm' => $ncm, 'result' => 'success'));
  }else{
    echo json_encode(array('result' => 'error'));
  }


  die();
}

function checkForDocument($address_id, $customer_id){
  $result_customer = Db::getInstance()->executeS('SELECT nfe_document_number, nfe_document_type, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer_id);

  $result = array();

  if(empty($result_customer[0]['nfe_document_number'])){
    $result['document_number'] = 'error';
  }else{
    $result['document_number'] = $result_customer[0]['nfe_document_number'];
    $result['document_type'] = $result_customer[0]['nfe_document_type'];
    $result['razao_social'] = $result_customer[0]['nfe_razao_social'];
    $result['ie'] = $result_customer[0]['nfe_pj_ie'];
  }

  echo json_encode($result);
  die();
}

exit;
