<?php
/**
 * Description of view
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */
class WebmaniaBRNFeView15ModuleFrontController extends ModuleFrontController
{
    public $errors;

    public function initContent()
    {

        parent::initContent();
        if (!$this->context->customer->isLogged(true)){
            $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view15');
            $params = array('back' => $redirect_url);
            Tools::redirect($this->context->link->getPageLink('authentication', true, (int)$this->context->language->id, $params));
        }


        $id_customer = $this->context->customer->id;
        $result = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '. _DB_PREFIX_ .'customer WHERE id_customer='.(int)$id_customer);

        if($result){
          $this->context->smarty->assign(array(
              'document_number' => $result['nfe_document_number'],
              'document_type' => $result['nfe_document_type'],
              'razao_social' => $result['nfe_razao_social'],
              'pj_ie' => $result['nfe_pj_ie'],
          ));
        }else{
          $this->context->smarty->assign(array(
              'document_number' => '',
              'document_type' => '',
              'razao_social' => '',
              'pj_ie' => '',
          ));
        }

        $this->setTemplate('documents_view.1.5.tpl');
    }

  public function postProcess(){
    $confirmations = array();
    if(Tools::getValue('document_types')){
      $document_type = Tools::getValue('document_type');
      $valid = true;
      if($document_type != 'cpf' && $document_type != 'cnpj'){
        $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
        $valid = false;
      }elseif($document_type == 'cpf'){
        $document_number = Tools::getValue('cpf');
        $validate = $this->module->is_cpf($document_number);

        if($validate === false){
          $this->errors[] = Tools::displayError('Insira um CPF válido');
          $valid = false;
        }
      }elseif($document_type == 'cnpj'){
        $document_number = Tools::getValue('cnpj');
        $validate = $this->module->is_cnpj($document_number);
        $razao_social = Tools::getValue('razao_social');
        $inscricao_estadual = Tools::getValue('cnpj_ie');
        if($validate === false){
          $this->errors[] = Tools::displayError('Insira um CNPJ válido');
          $valid = false;
        }

        if(empty(trim($razao_social))){
          $this->errors[] = Tools::displayError('Insira a Razão Social');
          $valid = false;
        }

        if(empty(trim($inscricao_estadual))){
          $this->errors[] = Tools::displayError('Insira a Inscrição Estadual');
          $valid = false;
        }
      }

      if($valid === true){
        $customer_id = (int)$this->context->customer->id;
        $DB_data = array();
        $document_type = Tools::getValue('document_type');

        if($document_type == 'cpf'){
          $DB_data = array(
            'nfe_document_type'   => pSQL(Tools::getValue('document_type')),
            'nfe_document_number' => pSQL(preg_replace("/[^0-9]/","", Tools::getValue('cpf'))),
          );
        }elseif($document_type == 'cnpj'){
          $DB_data = array(
            'nfe_document_type'   => pSQL(Tools::getValue('document_type')),
            'nfe_document_number' => pSQL(preg_replace("/[^0-9]/","", Tools::getValue('cnpj'))),
            'nfe_razao_social'    => pSQL(Tools::getValue('razao_social')),
            'nfe_pj_ie'           => pSQL(Tools::getValue('cnpj_ie')),
          );
        }

        if(!Db::getInstance()->update('customer', $DB_data, 'id_customer = ' .$customer_id )){
          $this->errors[] = Tools::displayError('Erro: ').mysql_error();
        }else{
          $this->confirmations[] = Tools::displayError('Informações atualizadas com sucesso');
          $this->context->smarty->assign(array('confirmations' => $confirmations));
        }
        return true;
      }else{
        return false;
      }
    }
  }
}
