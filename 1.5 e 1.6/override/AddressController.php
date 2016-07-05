<?php

class AddressController extends AddressControllerCore{
  public function preProcess(){
    parent::preProcess();

    if (Tools::isSubmit('submitAddress')){
      if (!Tools::getValue('address_number')){
        $this->errors[] = Tools::displayError('<strong>Número</strong> é obrigatório');
      }

      if (!Tools::getValue('address2')){
        $this->errors[] = Tools::displayError('<strong>Bairro</strong> é obrigatório');
      }
    }
  }

  public function proccess(){
    parent::process();
  }
}
