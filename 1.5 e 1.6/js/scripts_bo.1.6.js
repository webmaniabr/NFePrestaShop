jQuery(document).ready(function(){

  /*
   * Insert custom bulk action
   */
  var admin_orders_element = $('body.adminorders');
  if(admin_orders_element.length > 0){
    var action = "sendBulkAction($(this).closest('form').get(0), 'submitBulkemitirNfeorder')";
    var nfe_bulk = '<li><a href="#" id="emitirNfe" onclick="'+action+'"><i class="icon-file-text"></i> Emitir NF-e</a><input type="hidden" name="" value="update" /></li>';
    $(nfe_bulk).appendTo('.bulk-actions .dropdown-menu');
  }

  /*
   * Handle bulk action, add a name attr before form submission
   */
  $(document).on('click', '#emitirNfe', function(e){
    e.preventDefault();
    $(this).siblings('input').attr('name', 'bulkEmitirNfe');
    $('#form-order').submit();
  });



  //Control document type when editing customer in admin
  if($('#cpf').length > 0){
    var active_document = $('input[name="document_type"]:checked').val();
    if(active_document == 'cpf'){
      $('.cnpj-group').each(function(){
        $(this).parents('.form-group').css('display', 'none');
        $('.cpf-group').parents('.form-group').addClass('active');
      });
    }else if(active_document == 'cnpj'){
      $('.cpf-group').parents('.form-group').css('display', 'none');
      $('.cnpj-group').each(function(){
        $(this).parents('.form-group').addClass('active');
      });
    }
    $('#cpf').mask('999.999.999-99');
    $('#cnpj').mask('99.999.999/9999-99');
  }

  //Control Document-type display
  $(document).on('change', 'input[name="document_type"]', function(){
    var target = $(this).val();
    if(target == 'cpf'){
      $('.cnpj-group').each(function(){
        $(this).parents('.form-group').fadeToggle('fast', 'swing', function(){
          if(!$('.cpf-group').parents('.form-group').hasClass('active')){
            $('.cpf-group').parents('.form-group').fadeToggle().addClass('active');
          }
        }).removeClass('active');
      });
    }else if(target == 'cnpj'){
      $('.cpf-group').parents('.form-group').fadeToggle('fast', 'swing', function(){
        $('.cnpj-group').each(function(){
          if(!$(this).parents('.form-group').hasClass('active')){
            $(this).parents('.form-group').fadeToggle().addClass('active');
          }
        });
      }).removeClass('active');
    }
  });


  //Add address number to admin
  var addressNumber = function(){
    var addrNumber = $('<div class="form-group number">'+
                          '<label class="control-label col-lg-3 required">Número</label>'+
                          '<div class="col-lg-2"><input type="text" class="form-control" name="address_number" /></div>'+
                        '</div>');
    return addrNumber;
  }

  addressNumber().insertAfter($('#address1').parents('.form-group'));
  if($('#address1').length == 0){
    addressNumber().insertAfter($('#address').parents('.form-group'));
  }

  if($('#address2').length > 0){
    $('#address2').parents('.form-group').find('label').addClass('required').html('Bairro');
  }

});
