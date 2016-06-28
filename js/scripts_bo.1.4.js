console.log('working');
jQuery(document).ready(function($){


  $(document).on('change', 'input[name="document_type"]', function(){
    var rel = $(this).attr('data-rel');
    var target = $('#'+rel+'-field');
    console.log(target);
    if(!target.hasClass('active')){
      $('#document-types').find('.active').removeClass('active').fadeToggle('fast', 'swing', function(){
        target.fadeToggle().addClass('active');
      });
    }
  });

  if($('input[name="id_address"]').val() != 0){
    var address_id = $('input[name="id_address"]').val();
    $.ajax({
      type: 'POST',
      async: true,
      url:  '../modules/webmaniabrnfe/ajax.php',
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





var WebmaniaBRController = {
  getHTMLElements: {
    checkboxAllElement: function(){
      var checkboxAllOnClick = "checkDelBoxes(this.form, 'orderBox[]', this.checked)";
      var checkboxAll = $('<th class="center">'+
                            '<input type="checkbox" name="checkme" class="noborder" onclick="'+checkboxAllOnClick+'">'+
                         '</th>');
      return checkboxAll;
    },
    addressNumber: function(){
      var addrNumber = $('<div class="form-group number required" style="margin-bottom:10px;padding: 0.3em 1em;padding-left: 0.7em">'+
                            '<label class="main-label-1_4">Número</label> '+
                            '<input type="text" class="form-control input-text-1_4" id="address-number" name="address_number" />  <sup>*</sup>'+
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
          console.log(json);
          result = $.parseJSON(json);
          nfeIssued = result;
      }
      });
      return nfeIssued;
    }
  }
}

if($('input[name="address1"]').length > 0){
  WebmaniaBRController.getHTMLElements.addressNumber().insertAfter($('input[name="address1"]').parent('.margin-form'));
}

  //Create custom Bulk Action In AdminOrders Controller
  if($('#submitFilterorder').length > 0){
    var tableHeadRow = $('.table.table').find('thead').find('tr').first();
    var tableHeadRowSearch = $('.table.table').find('thead').find('tr:nth-child(2)');
    var tableBody = $('.table.table').find('tbody');
    tableHeadRow.find('th').first().remove();
    WebmaniaBRController.getHTMLElements.checkboxAllElement().prependTo(tableHeadRow);
    WebmaniaBRController.getHTMLElements.statusNfeHeadElement().insertAfter(tableHeadRow.find('th:nth-child(3)'));
    $('<td class="center">--</td>').insertAfter(tableHeadRowSearch.find('td:nth-child(3)'));

    tableBody.children('tr').each(function(index, element){
      var orderID = $(element).find('td:nth-child(2)').html().trim();
      var nfeIssued = WebmaniaBRController.utils.isNfeIssued(orderID);
      var status = '';
      if(nfeIssued == 1) status = 'emitida'; else status = 'nao-emitida';
      $(element).find('td').first().remove();
      WebmaniaBRController.getHTMLElements.checkboxSingleElement(orderID).prependTo($(element));
      WebmaniaBRController.getHTMLElements.statusNfeElement(status).insertAfter($(element).find('td:nth-child(3)'));
    });

    WebmaniaBRController.getHTMLElements.submitBulkElement().insertAfter($('.table.table'));
  }


  if($('form#product').length > 0){
    var customTab = '<h4 class="tab" id="custom-nfe-tab"><a href="#">WebmaniaBR NF-e</a></h4>';

    var customTabPage = $('<div class="tab-page" style="display:none" id="custom-nfe-content">'+
                            '<fieldset>'+
                              '<legend>Informações Fiscais (Opcional)</legend>'+
                              '<label>Classe de Imposto</label>'+
                              '<div class="margin-form"><input type="text" name="nfe_tax_class"/></div>'+
                              '<div class="clear"></div>'+
                              '<label>Código de barras EAN</label>'+
                              '<div class="margin-form"><input type="text" name="nfe_ean_barcode"/></div>'+
                              '<div class="clear"></div>'+
                              '<label>Código NCM</label>'+
                              '<div class="margin-form"><input type="text" name="nfe_ncm_code"/></div>'+
                              '<div class="clear"></div>'+
                              '<label>Código CEST</label>'+
                              '<div class="margin-form"><input type="text" name="nfe_cest_code"/></div>'+
                              '<div class="clear"></div>'+
                              '<label>Origem</label>'+
                              '<div class="margin-form"><select name="nfe_product_source" style="max-width:300px"><option value="-1">Selecionar Origem'+
                              '<option value="0">0 - Nacional, exceto as indicadas nos códigos 3, 4, 5, e 8</option>'+
                              '<option value="1">1 - Estrangeira - Importação direta, exceto a indicada no código 6</option>'+
                              '<option value="2">2 - Estrangeira - Adquirida no mercado interno, exceto a indica no código 7'+
                              '<option value="3">3 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40% e inferior ou igual a 70%</option>'+
                              '<option value="4">4 - Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos de que tratam as legislações citadas nos Ajustes</option>'+
                              '<option value="5">5 - Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%</option>'+
                              '<option value="6">6 - Estrangeira - Importação direta, sem similar nacional, constante em lista da CAMEX e gás natural</option>'+
                              '<option value="7">7 - Estrangeira - Adquirida no mercado interno, sem similar nacional, constante lista CAMEX e gás natural</option>'+
                              '<option value="8">8 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%</option>'+
                              '</select></div>'+

                              '<input type="submit" value="Salvar Alterações" class="button" name="submitCustomProduct14"/>'+
                            '</fieldset>'+
                            '</div>');
    customTabPage.load('product-config.1.4.html');

    customTabPage.insertAfter($('.tab-page').last());
    setTimeout(function(){
      $(customTab).insertAfter($('form#product .tab-row .tab').last());
    }, 1500);


    $(document).on('click', '.tab', function(){
      $(this).siblings('.selected').removeClass('selected');
      $(this).addClass('selected');
      $('#custom-nfe-content').css('display', 'none');
      if($(this).attr('id') == 'custom-nfe-tab'){
        $('.tab-page').css('display', 'none');
        $('#custom-nfe-content').css('display', 'block');
      }
    });



      var id_product = getUrlVars()["id_product"];
      console.log(id_product);
      $.ajax({
        type: 'POST',
        async: true,
        url:  '../modules/webmaniabrnfe/ajax.php',
        data: {
          method: 'getProductInfo',
          productID: id_product
        },
        success: function(json) {
          result = $.parseJSON(json);
          console.log(result);
          $('input[name="nfe_tax_class"]').val(result.nfe_tax_class);
          $('input[name="nfe_ean_barcode"]').val(result.nfe_ean_bar_code);
          $('input[name="nfe_ncm_code"]').val(result.nfe_ncm_code);
          $('input[name="nfe_cest_code"]').val(result.nfe_cest_code);
          $('select[name="nfe_product_source"]').val(result.nfe_product_source).trigger('change');
      }
      });





  }

  if($('body > .module_error').length > 0){
    $('body > .module_error').prependTo('#content');
  }

  if($('body > .module_confirmation').length > 0){
    $('body > .module_confirmation').prependTo('#content');
  }

function getUrlVars() {
var vars = {};
var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
vars[key] = value;
});
return vars;
}

});
