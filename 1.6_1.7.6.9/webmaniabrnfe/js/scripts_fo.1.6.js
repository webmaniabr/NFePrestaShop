jQuery(document).ready(function(){

  var input_target = $('input[name="address2"]');
  if(input_target.length > 0){
    $('<sup>*</sup>').appendTo(input_target.siblings('label'));
  }


  /****************************************************************
  ***********************NAMESPACE DEFINITIONS*********************
  *****************************************************************/

  var AccountControllerWmbr = {
    utils: {
      validateFields : function() {

        var tipoPessoa = $('input[name="document_type"]:checked').val();
        var requiredFields = [];
        var emptyFields = [];

        if(tipoPessoa == 'cpf'){
          requiredFields.push('cpf');
        }else if(tipoPessoa == 'cnpj'){
          requiredFields.push('razao_social', 'cnpj', 'cnpj_ie');
        }

        requiredFields.forEach(function(name, index){
          var value = $('input[name="'+name+'"]').val().trim();
          if(!value){
            emptyFields.push(name);
          }
        });

        return emptyFields;

      }

    }
  };


  var AddressControllerWmbr = {
    getHTMLElements: {
      addressNumber: function(){
        var addrNumber = $('<div class="form-group number">'+
        '<label>Número <sup>*</sup></label>'+
        '<input type="text" class="form-control" name="address_number"'+
        '</div>');
        return addrNumber;
      },
      complemento: function(){
        var complementoEl = $('<div class="form-group complemento">'+
        '<label>Complemento </label>'+
        '<input type="text" class="form-control" name="complemento"'+
        '</div>');
        return complementoEl;
      },
      loadingDiv: function(){
        var loading = $('<div id="correios-loading"></div>');
        return loading;
      }
    },
    utils:{

      validateFields: function(){

        var requiredFields = ['address_number', 'address2'];
        var emptyFields = [];

        requiredFields.forEach(function(name, index){
          if(!$('input[name="'+name+'"]').val().trim()){
            emptyFields.push(name);
          }
        });

        return emptyFields;

      },

      maskDocuments: function(){
        $('#cpf-input').mask('999.999.999-99');
        $('#cnpj-input').mask('99.999.999/9999-99');
        return true;
      },

      insertNumberElementInDOM: function(){
        AddressControllerWmbr.getHTMLElements.addressNumber().insertAfter($('#address1').parent('.form-group'));
      },

      insertElementsInDom: function(){

        AddressControllerWmbr.getHTMLElements.complemento().insertAfter($('#address1').parent('.form-group'));
        AddressControllerWmbr.getHTMLElements.loadingDiv().prependTo($('#add_address'));
        $('#postcode').parent('.form-group').insertAfter($('#company').parent('.form-group'));
      },

      isMaskFieldOn: function(){
        if (typeof mask_doc_fields !== 'undefined' && mask_doc_fields == 'on'){
          return true;
        }
        return false;
      },
      isAutoAddressOn: function(){
        if (typeof fill_address !== 'undefined' && fill_address == 'on'){
          return true;
        }
        return false;
      },

      getAjaxValues: function(){

        if($('#add_address input[name="id_address"]').val() != 0){
          var address_id = $('#add_address input[name="id_address"]').val();
          $.ajax({
            type: 'POST',
            url: baseDir + 'modules/webmaniabrnfe/ajax.php',
            data: {
              method: 'getAddressInfo',
              addressID: address_id,
              adminToken: sec_token,
            },
            success: function(json) {
              result = $.parseJSON(json);
              if(typeof result.address_number != 'undefined'){
                $('input[name="address_number"]').val(result.address_number);
              }

            }
          });
        }
      }
    }
  }

  /****************************************************************
  *****************************END DEFINITIONS*********************
  *****************************************************************/





  //Control variable to include script
  var address_element = $('body#address');
  if(address_element.length > 0){

    if(numero_compl_status == 'on'){
      AddressControllerWmbr.utils.insertNumberElementInDOM();
      AddressControllerWmbr.utils.getAjaxValues();
    }
    //AddressControllerWmbr.utils.insertElementsInDom();


    //Initialize auto field addres after zip code input
    if(AddressControllerWmbr.utils.isAutoAddressOn()){
      correios.init( 'qS4SKlmAXR21h7wrBMcs0SZyXauLqo5m', 'nkKkInYJ5QvogYn1xj4lk7w3hkhA8qzruoKzuLf6UyBtSIJL' );
      $('#postcode').correios( '#address1', '#address2', '#city', '#id_state', '#correios-loading' );
    }

    //Allow only numbers in Address Number and Inscricao Estadual
    $('input[name="address_number"]').keyup(function () {
      this.value = this.value.replace(/[^0-9\.]/g,'');
    });

    $('input[name="ieValue"]').keyup(function () {
      this.value = this.value.replace(/[^0-9\.]/g,'');
    });
  }

  if($('#cpf-input').length > 0 && AddressControllerWmbr.utils.isMaskFieldOn() === true){
    AddressControllerWmbr.utils.maskDocuments();
  }

  if($('input[name="postcode"]').length > 0){
    $('input[name="postcode"]').mask('99999-999');
  }

  //Display correct field on radio change (Tipo de Pessoa)
  $(document).on('change', 'input[name="document_type"]', function(){

    var rel = $(this).attr('data-rel');
    var target = $('#'+rel+'-field');
    if(!target.hasClass('active')){
      $('#document-types').find('.active').removeClass('active').fadeToggle('fast', 'swing', function(){
        target.fadeToggle().addClass('active');
      });
    }
  });

  $('#submitAddress').click(function(e){

    if(typeof numero_compl_status !== 'undefined' && numero_compl_status == 'on'){
      var emptyRequiredFields = AddressControllerWmbr.utils.validateFields();

      if(emptyRequiredFields.length > 0){
        e.preventDefault();
        var scrollElement = $('input[name="'+emptyRequiredFields[0]+'"]');
        emptyRequiredFields.forEach(function(name, index){
          $('input[name="'+name+'"]').addClass('required-error');
        });
        
        $('html, body').animate({scrollTop: (scrollElement.offset().top - 50)});
        
      }
    }

  });


  $(document).on('click', '#submitAccount', function(e){


    var emptyRequiredFields = AccountControllerWmbr.utils.validateFields();

    if(emptyRequiredFields.length > 0){
      e.preventDefault();
      var scrollElement = $('input[name="'+emptyRequiredFields[0]+'"]');
      emptyRequiredFields.forEach(function(name, index){
        $('input[name="'+name+'"]').addClass('required-error');
      });

      $('body').scrollTop(scrollElement.offset().top - 100);
    }
  });


});
