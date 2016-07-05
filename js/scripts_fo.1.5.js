jQuery(document).ready(function($){
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
      var addrNumber = $('<div class="form-group number" style="margin-bottom:10px">'+
                            '<label class="main-label-1_5">Número <sup>*</sup></label> '+
                            '<input type="text" class="form-control input-text-1_5" name="address_number"'+
                          '</div>');
      return addrNumber;
    },
    complemento: function(){
      var complementoEl = $('<div class="form-group complemento" style="margin-bottom:10px">'+
      '<label class="main-label-1_5">Complemento </label>'+
      '<input type="text" class="form-control input-text-1_5" name="complemento"'+
      '</div>');
      return complementoEl;
    },
    loadingDiv: function(){
      var loading = $('<div id="correios-loading"></div>');
      return loading;
    }
  },
  utils: {
    maskDocuments: function(){
      $('#cpf-input').mask('999.999.999-99');
      $('#cnpj-input').mask('99.999.999/9999-99');
      return true;
    },
    getAjaxValues: function(){
      if($('#add_address input[name="id_address"]').val() != 0){
        var address_id = $('#add_address input[name="id_address"]').val();
        $.ajax({
          type: 'POST',
          async: true,
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
    },
    isMaskFieldOn: function(){
      if(typeof mask_fields == 'boolean'){
        return true;
      }
      return false;
    },

  }
}

/****************************************************************
*******************************END DEF***************************
*****************************************************************/




//Insert elements
AddressControllerWmbr.getHTMLElements.addressNumber().insertAfter($('#address1').parent('.text'));
AddressControllerWmbr.getHTMLElements.complemento().insertAfter($('#address1').parent('.text'));
AddressControllerWmbr.getHTMLElements.loadingDiv().appendTo($('#add_address'));

if($('input[name="address_number"]').length > 0){
  AddressControllerWmbr.utils.getAjaxValues();
}

if($('#cpf-input').length > 0 && typeof mask_fields != 'undefined' && mask_fields == 'on'){
  AddressControllerWmbr.utils.maskDocuments();
}

if($('input[name="postcode"]').length > 0){
  $('input[name="postcode"]').mask('99999-999');
}


//Change position of postcode input
$('#postcode').parent('.postcode').insertBefore($('#address1').parent('.text'));

//Start autofill address
if(typeof fill_address != 'undefined' && fill_address == 'on'){
  correios.init( 'qS4SKlmAXR21h7wrBMcs0SZyXauLqo5m', 'nkKkInYJ5QvogYn1xj4lk7w3hkhA8qzruoKzuLf6UyBtSIJL' );
  $('#postcode').correios( '#address1', '#address2', '#city', '#id_state', '#correios-loading' );
}


//Events

//Allow only number in address_number and Inscrição estadual fields
$('input[name="address_number"]').keyup(function () {
  this.value = this.value.replace(/[^0-9\.]/g,'');
});
$('input[name="ieValue"]').keyup(function () {
  this.value = this.value.replace(/[^0-9\.]/g,'');
});

//Control document type display
$(document).on('change', '#document-types input[name="document_type"]', function(){
  var rel = $(this).attr('data-rel');
  var target = $('#'+rel+'-field');
  if(!target.hasClass('active')){
    $('#document-types').find('.active').removeClass('active').fadeToggle('fast', 'swing', function(){
      target.fadeToggle().addClass('active');
    });
  }
});



$('.cart_navigation input[name="processAddress"]').click(function(e){
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
            var insertElement = '<div class="alert alert-warning warning_1_5" id="no-document" role="alert">'+
            'Você não possui nenhum CPF ou CNPJ cadastrado. Atualize as informações na sua conta.</div>';
            $(insertElement).prependTo('.addresses');
          }
        }

        if(result.address_number == 'error'){
          var error = $('#no-address');
          if(error.length == 0){
            var insertElement = '<div class="alert alert-warning warning_1_5" id="no-address" role="alert">'+
            'Se você deseja usar este endereço, atualize-o incluindo o número do enderço. Caso você tenha definido o número juntamente ao endereço separado por vírgula, por favor, atualize-o e insira o número no campo "Número".</div>';
            $(insertElement).prependTo('.addresses');
          }
        }
      }
  }
  });
});


});
