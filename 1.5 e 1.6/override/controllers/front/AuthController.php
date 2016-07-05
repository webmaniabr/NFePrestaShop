<?php
class AuthController extends AuthControllerCore{

    protected function processSubmitAccount(){

        $document_type = Tools::getValue('document_type');
        $module_nfe = Module::getInstanceByName('webmaniabrnfe');
        if($document_type != 'cpf' && $document_type != 'cnpj'){
          $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
        }elseif($document_type == 'cpf'){
          $document_number = Tools::getValue('cpf');
          $validate = $module_nfe->is_cpf($document_number);

          if($validate === false){
            $this->errors[] = Tools::displayError('Insira um CPF válido');
          }
        }elseif($document_type == 'cnpj'){
          $document_number = Tools::getValue('cnpj');
          $validate = $module_nfe->is_cnpj($document_number);
          $razao_social = Tools::getValue('razao_social');
          $inscricao_estadual = Tools::getValue('cnpj_ie');
          if($validate === false){
            $this->errors[] = Tools::displayError('Insira um CNPJ válido');
          }

          if(empty(trim($razao_social))){
            $this->errors[] = Tools::displayError('Insira a Razão Social');
          }

          if(empty(trim($inscricao_estadual))){
            $this->errors[] = Tools::displayError('Insira a Inscrição Estadual');
          }
        }
        parent::processSubmitAccount();
    }
}
