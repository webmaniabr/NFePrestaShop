<?php
class AuthController extends AuthControllerCore
{

    /*
    * module: webmaniabrnfe
    * date: 2016-07-14 09:00:00
    * version: 2.9.0
    */
    public function process(){
      $document_type = Tools::getValue('document_type');
      $module_nfe = Module::getInstanceByName('webmaniabrnfe');
      if(Tools::getValue('document_type')){
      if($document_type != 'cpf' && $document_type != 'cnpj'){
        $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
      }elseif($document_type == 'cpf'){
        $document_number = Tools::getValue('cpf');
        $validate = $module_nfe->validaCPF($document_number);

        if($validate === false){
          array_unshift($this->errors, Tools::displayError('<strong>CPF</strong> inválido'));
        }
      }elseif($document_type == 'cnpj'){
        $document_number = Tools::getValue('cnpj');
        $validate = $module_nfe->validaCNPJ($document_number);
        $razao_social = Tools::getValue('razao_social');
        $inscricao_estadual = Tools::getValue('cnpj_ie');
        if($validate === false){
          array_unshift($this->errors, Tools::displayError('<strong>CNPJ</strong> inválido'));
        }

        if(empty(trim($razao_social))){
          array_unshift($this->errors, Tools::displayError('<strong>Razão Social</strong> é obrigatório'));
        }

        if(empty(trim($inscricao_estadual))){
          array_unshift($this->errors, Tools::displayError('<strong>Inscrição Estadual</strong> é obrigatório'));
        }
      }
    }

      parent::process();
    }
}
