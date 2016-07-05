jQuery(document).ready(function($){

var WmBRAdminOrdersController = {
  getHTMLElements: {
    checkboxAllElement: function(){
      var checkboxAllOnClick = "checkDelBoxes(this.form, 'orderBox[]', this.checked)";
      var checkboxAll = $('<th class="center">'+
                            '<input type="checkbox" name="checkme" class="noborder" onclick="'+checkboxAllOnClick+'">'+
                         '</th>');
      return checkboxAll;
    },
    addressNumber : function(){
      var addrNumber = $('<div class="form-group number">'+
                            '<label class="control-label col-lg-3 required">Número</label>'+
                            '<div class="margin-form"><input type="text" class="form-control" name="address_number" /></div>'+
                          '</div>');
      return addrNumber;
    },
    checkboxSingleElement: function(orderID){
      var checkboxSingle = $('<td class="center">'+
                               '<input type="checkbox" name="orderBox[]" value="'+orderID+'" class="noborder" />'+
                             '</td>');
      return checkboxSingle;
    },
    submitBulkElement: function(){
      var emitirOnClick = "return confirm('Deseja emitir a Nota Fiscal Eletrônica para os pedidos selecionados?');";
      var emitirButton = $('<p><input type="submit" class="button" name="bulkEmitirNfe" value="Emitir NF-e" onclick="'+emitirOnClick+'"</p>');

      return emitirButton;
    },

    statusNfeHeadElement: function(){
      var statusHead = $('<th class="center extended-width-nfe"><span class="title_box">Status NF-e</span></th>');
      return statusHead;
    },
    statusNfeElement: function(status){
      var statusElement = $('<td class="pointer center"><div class="nfe-status '+status+'">NF-e não emitida</div></td>');
      return statusElement;
    }
  },
  utils: {
    isNfeIssued: function(orderID){
      var nfeIssued = 0;
      $.ajax({
        type: 'POST',
        async: false,
        url: '../modules/webmaniabrnfe/ajax.php',
        data: {
          method: 'getNfeStatus',
          order_id: orderID
        },
        success: function(json) {
          result = $.parseJSON(json);
          nfeIssued = result;
      }
      });
      return nfeIssued;
    }
  }
}

//Insert Address Number in Admin
if($('#address1').length > 0){
  WmBRAdminOrdersController.getHTMLElements.addressNumber().insertAfter($('#address1').parents('.margin-form').next('.clear'));
}

//Get address_number from DB
if($('input[name="address_number"]').length > 0){
    var address_id = $('#id_address').val();
    $.ajax({
      type: 'POST',
      async: true,
      url: '../modules/webmaniabrnfe/ajax.php',
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

  //Control document type
  if($('#cpf').length > 0){
    var active_document = $('input[name="document_type"]:checked').val();
    if(active_document == 'cpf'){
      $('.cnpj-group').each(function(){
        $(this).parents('.margin-form').css('display', 'none').prev('label').css('display','none');
        $('.cpf-group').parents('.margin-form').addClass('active');
      });
    }else if(active_document == 'cnpj'){

      $('.cpf-group').parents('.margin-form').css('display', 'none').prev('label').css('display', 'none');
      $('.cnpj-group').each(function(){
        $(this).parents('.margin-group').addClass('active');
      });
    }
    $('#cpf').mask('999.999.999-99');
    $('#cnpj').mask('99.999.999/9999-99');
  }

  //Control document type display
  $(document).on('change', 'input[name="document_type"]', function(){
    var target = $(this).val();
    if(target == 'cpf'){
      $('.cnpj-group').each(function(){
        $(this).parents('.margin-form').prev('label').fadeToggle('fast', 'swing');
        $(this).parents('.margin-form').fadeToggle('fast', 'swing', function(){
          if(!$('.cpf-group').parents('.margin-form').hasClass('active')){
            $('.cpf-group').parents('.margin-form').fadeToggle().addClass('active').prev('label').fadeToggle();
          }
        }).removeClass('active');
      });
    }else if(target == 'cnpj'){
      $('.cpf-group').parents('.margin-form').prev('label').fadeToggle('fast', 'swing');
      $('.cpf-group').parents('.margin-form').fadeToggle('fast', 'swing', function(){
        $('.cnpj-group').each(function(){
          if(!$(this).parents('.margin-form').hasClass('active')){
            $(this).parents('.margin-form').fadeToggle().addClass('active').prev('label').fadeToggle();
          }
        });
      }).removeClass('active');
    }
  });



  //Create custom Bulk Action In AdminOrders Controller

  var tableHeadRow = $('.table.order').find('thead').find('tr').first();
  var tableHeadRowSearch = $('.table.order').find('thead').find('tr:nth-child(2)');
  var tableBody = $('.table.order').find('tbody');
  tableHeadRow.find('th').first().remove();
  WmBRAdminOrdersController.getHTMLElements.checkboxAllElement().prependTo(tableHeadRow);
  WmBRAdminOrdersController.getHTMLElements.statusNfeHeadElement().insertAfter(tableHeadRow.find('th:nth-child(3)'));
  $('<td class="center">--</td>').insertAfter(tableHeadRowSearch.find('td:nth-child(3)'));

  tableBody.children('tr').each(function(index, element){
    var orderID = $(element).find('td:nth-child(2)').html().trim();
    var nfeIssued = WmBRAdminOrdersController.utils.isNfeIssued(orderID);
    var status = '';
    if(nfeIssued == 1) status = 'emitida'; else status = 'nao-emitida';
    $(element).find('td').first().remove();
    WmBRAdminOrdersController.getHTMLElements.checkboxSingleElement(orderID).prependTo($(element));
    WmBRAdminOrdersController.getHTMLElements.statusNfeElement(status).insertAfter($(element).find('td:nth-child(3)'));
  });

  WmBRAdminOrdersController.getHTMLElements.submitBulkElement().insertAfter($('.table.order'));
});
