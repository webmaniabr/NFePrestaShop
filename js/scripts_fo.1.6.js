jQuery(document).ready(function(){

  var input_target = $('input[name="address2"]');
  if(input_target.length > 0){
    $('<sup>*</sup>').appendTo(input_target.siblings('label'));
  }


  /****************************************************************
  ***********************NAMESPACE DEFINITIONS*********************
  *****************************************************************/

  var AddressControllerWmbr = {
    getHTMLElements: {
      addressNumber: function(){
        var addrNumber = $('<div class="form-group number">'+
        '<label>Número <sup>*</sup></label>'+
        '<input type="text" class="form-control" name="address_number"'+
        '</div>');
        return addrNumber;
      },
      loadingDiv: function(){
        var loading = $('<div id="correios-loading"></div>');
        return loading;
      }
    },
    utils:{
      maskDocuments: function(){
        $('#cpf-input').mask('999.999.999-99');
        $('#cnpj-input').mask('99.999.999/9999-99');
        return true;
      },
      insertElementsInDom: function(){
        AddressControllerWmbr.getHTMLElements.addressNumber().insertAfter($('#address1').parent('.form-group'));
        AddressControllerWmbr.getHTMLElements.loadingDiv().prependTo($('#add_address'));
        $('#postcode').parent('.form-group').insertAfter($('#company').parent('.form-group'));
      },
      isPersonTypeOn: function(){
        if(typeof add_person_fields == 'boolean'){
          return true;
        }
        return false;
      },
      isMaskFieldOn: function(){
        if(typeof mask_doc_fields == 'boolean'){
          return true;
        }
        return false;
      },
      isAutoAddressOn: function(){
        if(typeof auto_address_fill == 'boolean'){
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
              addressID: address_id
            },
            success: function(json) {
              console.log(json);
              result = $.parseJSON(json);
              $('input[name="address_number"]').val(result[0].address_number);

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
    AddressControllerWmbr.utils.insertElementsInDom();
    AddressControllerWmbr.utils.getAjaxValues();

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

  if($('#cpf-input').length > 0){
    AddressControllerWmbr.utils.maskDocuments();
  }

  if($('input[name="postcode"]').length > 0){
    $('input[name="postcode"]').mask('99999-999');
  }

  //Display correct field on radio change (Tipo de Pessoa)
  $(document).on('change', 'input[name="document_type"]', function(){
    console.log($(this).val());
    var rel = $(this).attr('data-rel');
    var target = $('#'+rel+'-field');
    if(!target.hasClass('active')){
      $('#document-types').find('.active').removeClass('active').fadeToggle('fast', 'swing', function(){
        target.fadeToggle().addClass('active');
      });
    }
  });

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
        console.log(result);
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
  })

});
