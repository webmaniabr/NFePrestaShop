<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
/**
 * Description of view
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */
class WebmaniaBRNFeView16ModuleFrontController extends ModuleFrontControllerCore
{
    public $errors;

    public function initContent()
    {

        parent::initContent();
        if (!$this->context->customer->isLogged(true)){
            $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view16');
            $params = array('back' => $redirect_url);
            Tools::redirect($this->context->link->getPageLink('authentication', true, (int)$this->context->language->id, $params));
        }


        $id_customer = $this->context->customer->id;
        $this->context->smarty->assign('wmbr_token', Tools::getToken(false));
        $result = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '. _DB_PREFIX_ .'customer WHERE id_customer='.(int)$id_customer);


        if($result){
          // The template escapes these values: NULL columns become empty strings.
          $this->context->smarty->assign(array(
              'document_number' => (string) $result['nfe_document_number'],
              'document_type' => (string) $result['nfe_document_type'],
              'razao_social' => (string) $result['nfe_razao_social'],
              'pj_ie' => (string) $result['nfe_pj_ie'],
          ));
        }else{
          $this->context->smarty->assign(array(
              'document_number' => '',
              'document_type' => '',
              'razao_social' => '',
              'pj_ie' => '',
          ));
        }

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->setTemplate('module:webmaniabrnfe/views/templates/front/documents_view.1.6.tpl');
        } else {
            $this->setTemplate('documents_view.1.6.tpl');
        }
    }

  public function postProcess(){
    $confirmations = array();
    if(Tools::getValue('document_types')){
      // Fiscal identification changes: logged-in customer, POST and the customer's static token
      // (same token as the address AJAX). postProcess() runs before initContent()'s login check.
      if (!$this->context->customer->isLogged(true) || !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ||
          !WebmaniaNFeSecurity::equals(Tools::getToken(false), Tools::getValue('token'))) {
        $this->errors[] = 'Não foi possível validar a solicitação. Recarregue a página e tente novamente.';
        return false;
      }
      $submitted = array();
      foreach (array('document_type', 'cpf', 'cnpj', 'razao_social', 'cnpj_ie') as $field) {
        $value = Tools::getValue($field);
        $submitted[$field] = is_scalar($value) ? trim((string) $value) : '';
      }
      $document_type = $submitted['document_type'];
      $valid = true;
      if($document_type != 'cpf' && $document_type != 'cnpj'){
        $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
        $valid = false;
      }elseif($document_type == 'cpf'){
        $document_number = $submitted['cpf'];
        $validate = $this->module->is_cpf($document_number);

        if($validate === false){
          $this->errors[] = Tools::displayError('Insira um CPF válido');
          $valid = false;
        }
      }elseif($document_type == 'cnpj'){
        $document_number = $submitted['cnpj'];
        $validate = $this->module->is_cnpj($document_number);
        $razao_social = $submitted['razao_social'];
        $inscricao_estadual = $submitted['cnpj_ie'];
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

        if($document_type == 'cpf'){
          $DB_data = array(
            'nfe_document_type'   => 'cpf',
            'nfe_document_number' => pSQL(preg_replace("/[^0-9]/","", $submitted['cpf'])),
          );
        }elseif($document_type == 'cnpj'){
          $DB_data = array(
            'nfe_document_type'   => 'cnpj',
            'nfe_document_number' => pSQL(WebmaniaNFeSecurity::normalizeDocument($submitted['cnpj'])),
            'nfe_razao_social'    => pSQL($submitted['razao_social']),
            'nfe_pj_ie'           => pSQL($submitted['cnpj_ie']),
          );
        }

        if(!Db::getInstance()->update('customer', $DB_data, 'id_customer = ' .$customer_id )){
          // mysql_error() does not exist on PHP 7+; never show database details to customers.
          $this->errors[] = Tools::displayError('Não foi possível atualizar as informações.');
        }else{
          // Local list: FrontController has no $confirmations property (PHP 8.2 dynamic property).
          $confirmations[] = Tools::displayError('Informações atualizadas com sucesso');
          $this->context->smarty->assign(array('confirmations' => $confirmations));
        }
        return true;
      }else{
        return false;
      }
    }
  }
}
