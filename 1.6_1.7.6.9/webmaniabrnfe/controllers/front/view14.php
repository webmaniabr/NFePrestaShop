<?php

class WebmaniaBrNFeViewController14{

  public function __construct(){
  }

  public function postProcess(){
    global $smarty;
    $module = new WebmaniabrNfe();
    $confirmations = array();
    $errors = array();
    if(Tools::getValue('document_types')){
      $document_type = Tools::getValue('document_type');
      $valid = true;
      if($document_type != 'cpf' && $document_type != 'cnpj'){
        $errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
        $valid = false;
      }elseif($document_type == 'cpf'){
        $document_number = Tools::getValue('cpf');
        $validate = $module->validaCPF($document_number);

        if($validate === false){
          $errors[] = Tools::displayError('Insira um CPF válido');
          $valid = false;
        }
      }elseif($document_type == 'cnpj'){
        $document_number = Tools::getValue('cnpj');
        $validate = $module->validaCNPJ($document_number);
        $razao_social = Tools::getValue('razao_social');
        $inscricao_estadual = Tools::getValue('cnpj_ie');
        if($validate === false){
          $errors[] = Tools::displayError('Insira um CNPJ válido');
          $valid = false;
        }

        if(empty(trim($razao_social))){
          $errors[] = Tools::displayError('Insira a Razão Social');
          $valid = false;
        }

        if(empty(trim($inscricao_estadual))){
          $errors[] = Tools::displayError('Insira a Inscrição Estadual');
          $valid = false;
        }
      }

      if($valid === true){
        global $cookie;
        $customer_id = $cookie->id_customer;

        $document_type = Tools::getValue('document_type');

        if($document_type == 'cpf'){
          $document_type = pSQL(Tools::getValue('document_type'));
          $document_number = pSQL(preg_replace("/[^0-9]/","",Tools::getValue('cpf')));
          $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."' WHERE id_customer = " . $customer_id;
          $result = Db::getInstance()->executeS($query);

        }elseif($document_type == 'cnpj'){
          $document_type = pSQL(Tools::getValue('document_type'));
          $document_number = pSQL(preg_replace("/[^0-9]/","",Tools::getValue('cnpj')));
          $razao_social = pSQL(Tools::getValue('razao_social'));
          $cnpj_ie = pSQL(Tools::getValue('cnpj_ie'));
          $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."', nfe_razao_social = '".$razao_social."', nfe_pj_ie = '".$cnpj_ie."' WHERE id_customer = " . $customer_id;
          $result = Db::getInstance()->executeS($query);
        }

        if($result === false){
          print_r(Db::getInstance()->getMsgError());
          $errors[] = Tools::displayError('Erro: '.Db::getInstance()->getMsgError());
          $smarty->assign(array('errors' => $errors));
        }else{
          $confirmations[] = Tools::displayError('Informações atualizadas com sucesso');
          $smarty->assign(array('confirmations' => $confirmations));
        }
        return true;
      }else{
        $smarty->assign(array('errors' => $errors));
        return false;
      }
    }
  }

  public function initContent(){
    global $cookie, $smarty;
    $customer_id = $cookie->id_customer;
    $result = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '. _DB_PREFIX_ .'customer WHERE id_customer='.(int)$customer_id);
    if($result){
      $smarty->assign(array(
        'document_number' => $result['nfe_document_number'],
        'document_type' => $result['nfe_document_type'],
        'razao_social' => $result['nfe_razao_social'],
        'pj_ie' => $result['nfe_pj_ie'],
      ));
    }else{
      $smarty->assign(array(
        'document_number' => '',
        'document_type' => '',
        'razao_social' => '',
        'pj_ie' => '',
      ));
    }
  }
}

?>
