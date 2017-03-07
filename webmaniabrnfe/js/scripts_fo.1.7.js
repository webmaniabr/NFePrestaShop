jQuery(document).ready(function(){

  var input_target = $('input[name="address2"]');
  if(input_target.length > 0){
    $('<sup>*</sup>').appendTo(input_target.siblings('label'));
  }

var cache = {};
  /****************************************************************
  ***********************NAMESPACE DEFINITIONS*********************
  *****************************************************************/

  var WMBRFrontOfficeController = {

    pages : {

      isCustomerFormPage: function() {

        if($('#customer-form').length > 0){
          return true;
        }

        return false;

      },

      isAddressPage: function() {

        if($('.address-form').length > 0 || $('#checkout-addresses-step').length > 0){
          return true;
        }

        return false;

      }

    },

    fields : {

      isNumeroEnabled: function() {

        if(numero_compl_status == 'on'){
          return true;
        }

        return false;

      },

      isBairroEnabled: function() {

        if(bairro_status == 'on'){
          return true;
        }

        return false;

      },

      isDocumentsEnabled: function() {

        if(cpf_cnpj_status == 'on'){
          return true;
        }

        return false;

      },

      getAjaxValues: function(){

          var address_id = $('input[name="id_address"]').val();
          $.ajax({
            type: 'POST',
            url:  './modules/webmaniabrnfe/ajax.php',
            data: {
              method: 'getAddressInfo',
              addressID: address_id,
              adminToken: sec_token,
            },
            success: function(json) {
              result = $.parseJSON(json);

              if(typeof result.address_number != 'undefined'){
                cache.address_number = result.address_number;
                $('input[name="address_number"]').trigger('set_address_number_value');
              }

              if(typeof result.bairro != 'undefined'){
                cache.bairro = result.bairro;
                $('input[name="bairro"]').trigger('set_bairro_value');
              }

            }
          });

      }

    },

    html: {

      getNumero: function() {

        var html = $('<div class="form-group number row">'+
          '<label class="col-md-3 form-control-label required">Número</label>'+
          '<div class="col-md-6">'+
            '<input type="text" class="form-control" name="address_number" required />'+
          '</div>'+
        '</div>');

        return html;

      },

      getBairro: function() {

        var html = $('<div class="form-group row">'+
          '<label class="col-md-3 form-control-label required">Bairro</label>'+
          '<div class="col-md-6">'+
            '<input type="text" class="form-control" name="bairro" required />'+
          '</div>'+
        '</div>');

        return html;

      },

      getLoadingDiv: function() {

        var html = $('<div id="correios-loading" style="z-index:999"></div>');

        return html;

      }
    },

    DOM : {

      insertNumero: function() {

        var numElement = WMBRFrontOfficeController.html.getNumero();
        numElement.insertAfter($('input[name="address1"]').parents('.form-group'));

      },

      insertBairro: function(){

        var bairroElement = WMBRFrontOfficeController.html.getBairro();
        bairroElement.insertAfter($('input[name="address_number"]').parents('.form-group'));

      },

      insertLoadingDiv: function(){

        var loadingElement = WMBRFrontOfficeController.html.getLoadingDiv();
        loadingElement.prependTo('section.form-fields');

      },

      reorderDocumentFields: function() {

        var firstElement = $('input[name="document_type"]').parents('.form-group');
        var reference = $('input[name="firstname"]').parents('.form-group');
        var elements = firstElement.nextAll(':lt(4)');

        firstElement.insertBefore(reference);

        elements.each(function(index, element){
          $(element).insertBefore(reference);
        });


      },

      hideOptionalLabel: function(){

        var elementsNames = ['cpf', 'cnpj', 'razao_social'];

        elementsNames.forEach(function(name, index){
          $('input[name="'+name+'"]').parents('.form-group').find('.form-control-comment').remove();
        });

      },

      getTipoPessoa: function(){

        return $('input[name="document_type"]:checked').val();

      },

      getCnpjFields: function() {

        var elementsNames = ['cnpj', 'razao_social', 'cnpj_ie'];

        return elementsNames;

      },

      getCpfFields: function() {

        var elementsNames = ['cpf'];

        return elementsNames;

      },

      showCnpjFields: function(){

        var elementsNames = WMBRFrontOfficeController.DOM.getCnpjFields();

        elementsNames.forEach(function(name, index){
          $('input[name="'+name+'"]').parents('.form-group').show();
        });

      },

      hideCnpjFields: function() {

        var elementsNames = WMBRFrontOfficeController.DOM.getCnpjFields();

        elementsNames.forEach(function(name, index){
          $('input[name="'+name+'"]').parents('.form-group').hide();
        });

      },

      hideCpfFields: function() {

        var elementsNames = WMBRFrontOfficeController.DOM.getCpfFields();

        elementsNames.forEach(function(name, index){
          $('input[name="'+name+'"]').parents('.form-group').hide();
        });

      },

      showCpfFields: function() {

        var elementsNames = ['cpf'];

        elementsNames.forEach(function(name, index){
          $('input[name="'+name+'"]').parents('.form-group').show();
        });

      },

      setRequiredFields: function(type){

        var addRequired = [];
        var removeRequired = [];
        var cpfFields = WMBRFrontOfficeController.DOM.getCpfFields();
        var cnpjFields = WMBRFrontOfficeController.DOM.getCnpjFields();

        if(type == 'cpf'){
          addRequired = WMBRFrontOfficeController.DOM.getCpfFields();
          removeRequired = WMBRFrontOfficeController.DOM.getCnpjFields();
        }else{
          addRequired = WMBRFrontOfficeController.DOM.getCnpjFields();
          removeRequired = WMBRFrontOfficeController.DOM.getCpfFields();
        }

        addRequired.forEach(function(name, index){
          if(name != 'cnpj_ie'){
            $('input[name="'+name+'"]').attr('required', true);
          }
        });

        removeRequired.forEach(function(name, index){
          $('input[name="'+name+'"]').removeAttr('required');
        });

      },

      manageFields: function(){

        var self = WMBRFrontOfficeController.DOM;
        var tipo_pessoa = self.getTipoPessoa();

        if(tipo_pessoa == 'cpf'){
          self.showCpfFields();
          self.hideCnpjFields();
        }else if(tipo_pessoa == 'cnpj'){
          self.hideCpfFields();
          self.showCnpjFields();
        }

        self.setRequiredFields(tipo_pessoa);

      }


    },

    utils: {

      isMaskOn: function() {

        if(mask_fields == 'on'){
          return true;
        }

        return false;

      },

      isFillAddressOn: function() {

        if(fill_address == 'on'){
          return true;
        }

        return false;

      },

      maskFields: function(){
        $('input[name="cpf"]').mask('999.999.999-99');
        $('input[name="cnpj"]').mask('99.999.999/9999-99');
      },

      initAutoFill: function(){
        correios.init( 'qS4SKlmAXR21h7wrBMcs0SZyXauLqo5m', 'nkKkInYJ5QvogYn1xj4lk7w3hkhA8qzruoKzuLf6UyBtSIJL' );
        $('input[name="postcode"]').correios( 'input[name="address1"]', 'input[name="bairro"]', 'input[name="city"]', 'input[name="id_state"]', '#correios-loading' );
      }

    },

    events: {
      init: function(){
        $('input[name="document_type"]').on('change', WMBRFrontOfficeController.DOM.manageFields);
      }
    },

    init: function(){

      if(this.pages.isCustomerFormPage() && this.fields.isDocumentsEnabled()){
        this.events.init();
        this.DOM.reorderDocumentFields();
        this.DOM.hideOptionalLabel();
        this.DOM.manageFields();

        if(this.utils.isMaskOn()){
          this.utils.maskFields();
        }
      }

      if(this.pages.isAddressPage()){

        if(this.utils.isFillAddressOn()){
          this.DOM.insertLoadingDiv();
          this.utils.initAutoFill();
        }

        if(this.fields.isNumeroEnabled()){
          this.DOM.insertNumero();

          if($('input[name="id_address"]').val()){

            if(typeof(cache.address_number == 'undefined')){
              this.fields.getAjaxValues();
            }

            $('input[name="address_number"]').on('set_address_number_value', function(){
              $('input[name="address_number"]').val(cache.address_number);
            });

          }

        }

        if(this.fields.isBairroEnabled()){
          this.DOM.insertBairro();

          if($('input[name="id_address"]').val()){

            if(typeof(cache.bairro == 'undefined')){
              this.fields.getAjaxValues();
            }

            $('input[name="bairro"]').on('set_bairro_value', function(){
              $('input[name="bairro"]').val(cache.bairro);
            });

          }
        }

      }

    }

  }


  WMBRFrontOfficeController.init();







  /****************************************************************
  *****************************END DEFINITIONS*********************
  *****************************************************************/





  //Control variable to include script
  var address_element = $('body#address');
  if(address_element.length > 0){



    //Allow only numbers in Address Number and Inscricao Estadual
    $('input[name="address_number"]').keyup(function () {
      this.value = this.value.replace(/[^0-9\.]/g,'');
    });

    $('input[name="ieValue"]').keyup(function ()
      this.value = this.value.replace(/[^0-9\.]/g,'');
    });
  }



  /*
  Make sure that users who signed up before the module was installed cannot checkout without
  registering document number and address number
  */

  $('.cart_navigation button[name="processAddress"]').click(function(e){
    e.preventDefault();
    var thisEl = $(this);
    var sameAddress = $('#addressesAreEquals');
    var addressID = null;
    if(sameAddress.is(':checked')){
      addressID = $('#id_address_delivery').val();
    }else{
      addressID = $('#id_address_invoice').val();
    }

    $.ajax({
      type: 'POST',
      async: true,
      url: baseDir + 'modules/webmaniabrnfe/ajax.php',
      data: {
        method: 'checkForDoc',
        address_id: addressID
      },

      success: function(json) {
        var result = $.parseJSON(json);

        if(result.success){
          thisEl.parents('form').submit();
        }else{
          if(result.document_number == 'error'){
            var error = $('#no-document');
            if(error.length == 0){
              var insertElement = '<div class="alert alert-warning" id="no-document" role="alert">'+
              'Você não possui nenhum CPF ou CNPJ cadastrado. Atualize as informações na sua conta.</div>';
              $(insertElement).prependTo('.addresses');
            }
          }

          if(result.nfe_number == 'error'){
            var error = $('#no-address');
            if(error.length == 0){
              var insertElement = '<div class="alert alert-warning" id="no-address" role="alert">'+
              'Se você deseja usar este endereço, atualize-o incluindo o número do enderço. Caso você tenha definido o número juntamente ao endereço separado por vírgula, por favor, atualize-o e insira o número no campo "Número".</div>';
              $(insertElement).prependTo('.addresses');
            }
          }

          $("html, body").animate({ scrollTop: $('.addresses.clearfix').offset().top }, 1000);
        }
      }
    });
  });




});
