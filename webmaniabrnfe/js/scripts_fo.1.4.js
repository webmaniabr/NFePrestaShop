jQuery(document).ready(function($){


  var input_target = $('input[name="address2"]');
  if(input_target.length > 0){
    $('<sup>*</sup>').insertAfter(input_target);
    input_target.parents('p.text').addClass('required');
  }
/****************************************************************
***********************NAMESPACE DEFINITIONS*********************
*****************************************************************/

var AddressControllerWmbr = {
  getHTMLElements: {
    addressNumber: function(){
      var addrNumber = $('<div class="form-group number required" style="margin-bottom:10px;padding: 0.3em 1em;padding-left: 0.7em">'+
                            '<label class="main-label-1_4">Número</label> '+
                            '<input type="text" class="form-control input-text-1_4" id="address-number" name="address_number" />  <sup>*</sup>'+
                          '</div>');
      return addrNumber;
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
      if($('#address .address_navigation input[name="id_address"]').val() != 0){
        var address_id = $('#address .address_navigation input[name="id_address"]').val();
        $.ajax({
          type: 'POST',
          async: true,
          url: baseDir + 'modules/webmaniabrnfe/ajax.php',
          data: {
            method: 'getAddressInfo',
            addressID: address_id
          },
          success: function(json) {
            result = $.parseJSON(json);
            $('input[name="address_number"]').val(result[0].address_number);
        }
        });
      }
    },
  }
}

/****************************************************************
*******************************END DEF***************************
*****************************************************************/




//Insert elements
$target_docs = $('#address').find('form.std fieldset').children('h3');
AddressControllerWmbr.getHTMLElements.addressNumber().insertAfter($('#address1').parent('.text'));
AddressControllerWmbr.getHTMLElements.loadingDiv().appendTo($('#address form.std'));

//

if($('#address-number').length > 0){
  AddressControllerWmbr.utils.getAjaxValues();
}

if($('#cpf-input').length > 0){
  AddressControllerWmbr.utils.maskDocuments();
}

if($('input[name="postcode"]').length > 0){
  $('input[name="postcode"]').mask('99999-999');
}


//Change position of postcode input
$('#postcode').parent('.postcode').insertBefore($('#address1').parent('.text'));

//Start autofill address
correios.init( 'qS4SKlmAXR21h7wrBMcs0SZyXauLqo5m', 'nkKkInYJ5QvogYn1xj4lk7w3hkhA8qzruoKzuLf6UyBtSIJL' );
$('#postcode').correios( '#address1', '#address2', '#city', '#id_state', '#correios-loading' );


//Events

$(document).on('change', '#document-types input[name="document_type"]', function(){
  var rel = $(this).attr('data-rel');
  var target = $('#'+rel+'-field');
  if(!target.hasClass('active')){
    $('#document-types').find('.active').removeClass('active').fadeToggle('fast', 'swing', function(){
      target.fadeToggle().addClass('active');
    });
  }
});

$('input[name="address_number"]').keyup(function () {
  this.value = this.value.replace(/[^0-9\.]/g,'');
});
$('input[name="ieValue"]').keyup(function () {
  this.value = this.value.replace(/[^0-9\.]/g,'');
});




$(document).on('focusout', 'input[name="address_number"]', function(){
  if(AddressControllerWmbr.utils.isEmptyInput($(this)) === true){
    AddressControllerWmbr.utils.addFormResponse($(this), 'error');
  }else{
    AddressControllerWmbr.utils.addFormResponse($(this), 'success');
  }
});

$(document).on('focusout', 'input[name="address2"]', function(){
  if(AddressControllerWmbr.utils.isEmptyInput($(this)) === true){
    AddressControllerWmbr.utils.addFormResponse($(this), 'error');
  }else{
    AddressControllerWmbr.utils.addFormResponse($(this), 'success');
  }
});

$('input[name="processAddress"]').click(function(e){
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

        if(result.nfe_number == 'error'){
          var error = $('#no-address');
          if(error.length == 0){
            var insertElement = '<div class="alert alert-warning warning_1_5" id="no-address" role="alert">'+
            'Se você deseja usar este endereço, atualize-o incluindo o número do endereço. Caso você tenha definido o número juntamente ao endereço separado por vírgula, por favor, atualize-o e insira o número no campo "Número".</div>';
            $(insertElement).prependTo('.addresses');
          }
        }
        $("html, body").animate({ scrollTop: $('#no-address').offset().top }, 1000);
        console.log('error');
      }
  }
  });
});

});
