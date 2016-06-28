<?php
if(!defined('_MAIN_PS_VERSION_'))
define('_MAIN_PS_VERSION_', substr(_PS_VERSION_, 0, 3));
class Address extends AddressCore{
    public $address_number;

    public function __construct($id_address = null, $id_lang = null){
      if(_MAIN_PS_VERSION_ == '1.4'){
        $this->fieldsRequired[] = 'address_number';
        $this->fieldsRequired[] = 'address2';
        $this->fieldsSize['address_number'] = 5;
        $this->fieldsValidate['address_number'] = 'isGenericName';
      }else{
        self::$definition['fields']['address_number'] = array('type' => self::TYPE_INT, 'validate' => 'isGenericName', 'required' => true);
        self::$definition['fields']['address2']['required'] = true;
      }
    parent::__construct($id_address, $id_lang);
  }

  public function getFields(){
    parent::validateFields();
		if (isset($this->id))
		$fields['id_address'] = (int)($this->id);
    $fields['address_number'] = (int)($this->address_number);
		$fields['id_customer'] = is_null($this->id_customer) ? 0 : (int)($this->id_customer);
		$fields['id_manufacturer'] = is_null($this->id_manufacturer) ? 0 : (int)($this->id_manufacturer);
		$fields['id_supplier'] = is_null($this->id_supplier) ? 0 : (int)($this->id_supplier);
		$fields['id_country'] = (int)($this->id_country);
		$fields['id_state'] = (int)($this->id_state);
		$fields['alias'] = pSQL($this->alias);
		$fields['company'] = pSQL($this->company);
		$fields['lastname'] = pSQL($this->lastname);
		$fields['firstname'] = pSQL($this->firstname);
		$fields['address1'] = pSQL($this->address1);
		$fields['address2'] = pSQL($this->address2);
		$fields['postcode'] = pSQL($this->postcode);
		$fields['city'] = pSQL($this->city);
		$fields['other'] = pSQL($this->other);
		$fields['phone'] = pSQL($this->phone);
		$fields['phone_mobile'] = pSQL($this->phone_mobile);
		$fields['vat_number'] = pSQL($this->vat_number);
		$fields['dni'] = pSQL($this->dni);
		$fields['deleted'] = (int)$this->deleted;
		$fields['date_add'] = pSQL($this->date_add);
		$fields['date_upd'] = pSQL($this->date_upd);
		return $fields;
  }
}
