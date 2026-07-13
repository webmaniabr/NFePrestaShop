jQuery(document).ready(function(){

  // PS 8/9: relative "../modules/..." resolves to a bogus Symfony route (404).
  // Use the absolute module URL injected by PHP; fall back to the legacy path.
  var wmbrAjaxUrl = (typeof wmbr_module_path != 'undefined')
    ? wmbr_module_path + 'ajax.php'
    : '../modules/webmaniabrnfe/ajax.php';

  var WMBRBackOfficeController = {

    pages: {

      isCustomerPage: function() {

        // PS 1.6/1.7 used #customer_form; PS 8/9 renders a Symfony form
        // named "customer" (form[name="customer"], inputs #customer_*).
        if($('#customer_form').length > 0 || $('form[name="customer"]').length > 0){
          return true;
        }

        return false;

      },

      isAddressPage: function() {

        // PS 1.6/1.7 used #address_form; PS 8/9 uses form[name="customer_address"].
        if($('#address_form').length > 0 || $('form[name="customer_address"]').length > 0){
          return true;
        }

        return false;

      },

      isEditCustomerPage: function() {

        if(typeof id_customer_wmbr != 'undefined'){
          return true;
        }

        return false;

      },

      isEditAddressPage: function(){
        
        if(typeof id_address_wmbr != 'undefined'){
          return true;
        }

        return false;

      },

      isModuleConfigPage: function() {

        if($('#configuration_form.webmaniabrnfe').length > 0){
          return true;
        }

        return false;

      },

      isAdminOrdersPage:function() {

        if($('body.adminorders').find('.js-bulk-actions-btn').length > 0){
          return true;
        }

        return false;

      }

    },

    fields: {

      isTipoPessoaEnabled : function() {

        if(tipo_pessoa_enabled == 'on'){
          return true;
        }

        return false;

      },

      isNumeroEnabled : function(){

        if(numero_enabled == 'on'){
          return true;
        }

        return false;

      }
    },

    html: {

      getTipoPessoa: function(onLoaded) {

        var $html =  $('<div></div>');
        // PS 8/9: the relative "../modules/..." path resolves to a bogus
        // Symfony route and 404s. Use the absolute module URL injected by PHP
        // (wmbr_module_path) and fall back to the legacy relative path.
        var docsUrl = (typeof wmbr_module_path != 'undefined')
          ? wmbr_module_path + 'assets/templates/docs.html'
          : '../modules/webmaniabrnfe/assets/templates/docs.html';
        // docs.html loads asynchronously; the CPF/CNPJ inputs do not exist in
        // the DOM until this finishes. Callers that need to touch those inputs
        // (mask, prefill) must run in onLoaded, not synchronously after insert.
        $html.load(docsUrl, onLoaded);

        return $html;

      },

      getNumero: function() {

        var addrNumber = $('<div class="form-group row number">'+
                              '<label class="form-control-label required">Número</label>'+
                              '<div class="col-sm input-container"><input type="text" class="form-control" name="address_number" /></div>'+
                            '</div>');

        return addrNumber;

      },

      getBulkAction: function(){

        var action = "sendBulkAction($(body).closest('form').get(0), 'submitBulkemitirNfeorder');";
        var $html = '<li><a href="#" id="emitirNfe" onclick="'+action+'"><i class="icon-file-text"></i> Emitir NF-e</a><input type="hidden" name="" value="update" /></li>';
        $html += '<input type="hidden" name="wmbr_bulk_action" value="emitirNfe" />';

        return $html;
      }

    },

    DOM: {

      insertTipoPessoa: function() {

        // The mask/prefill must wait for docs.html to finish loading (async),
        // otherwise the CPF/CNPJ inputs are not in the DOM yet. Runs for both
        // add-customer and edit-customer pages.
        var html = WMBRBackOfficeController.html.getTipoPessoa(function(){
          WMBRBackOfficeController.eventsHandler.initTipoPessoa();
          if(WMBRBackOfficeController.pages.isEditCustomerPage()){
            WMBRBackOfficeController.ajax.getDocuments();
          }
        });
        // firstname anchor: PS 1.6/1.7 = input[name="firstname"];
        // PS 8/9 Symfony form = input[name="customer[first_name]"] (#customer_first_name).
        var $anchor = $('input[name="firstname"]');
        if($anchor.length === 0){
          $anchor = $('input[name="customer[first_name]"], #customer_first_name');
        }
        var referenceElement = $anchor.parents('.form-group');
        if(referenceElement.length === 0){
          referenceElement = $anchor.closest('.form-group, .form-group-row, .row');
        }

        $(html).insertBefore(referenceElement);

      },

      insertNumero: function(){

        var html = WMBRBackOfficeController.html.getNumero();
        // address1 anchor: PS 1.6/1.7 = #address1; PS 8/9 = #customer_address_address1.
        var $addr1 = $('#address1');
        if($addr1.length === 0){
          $addr1 = $('#customer_address_address1, input[name="customer_address[address1]"]');
        }
        if($addr1.length > 0){
          html.insertAfter($addr1.parents('.form-group'));
        } else {
          html.insertAfter($('#address').parents('.form-group'));
        }

      },

      insertBulkAction: function(){

        var html = WMBRBackOfficeController.html.getBulkAction();
        $(html).appendTo('.dropdown-menu');

      }

    },

    ajax: {

      applyDocument: function(doc){
        if(!doc || typeof doc.document_type == 'undefined') return;
        // Select the right person type, reveal its field (change → fade), then
        // fill. docs.html field names are cpf/cnpj/razao_social/cnpj_ie.
        $('input[name="document_type"][value="'+doc.document_type+'"]').prop('checked', true).trigger('change');
        $('input[name="'+doc.document_type+'"]').val(doc.document_number);
        if(doc.document_type == 'cnpj'){
          $('input[name="razao_social"]').val(doc.razao_social);
          $('input[name="cnpj_ie"]').val(doc.ie);
        }
      },

      getDocuments: function(){

        // PS 8/9: ajax.php is blocked by modules/.htaccess (403). Prefer the
        // document injected server-side via Media::addJsDef; only fall back to
        // the legacy AJAX (PS 1.6/1.7 where the direct call still works).
        if(typeof customer_doc_wmbr != 'undefined'){
          WMBRBackOfficeController.ajax.applyDocument(customer_doc_wmbr);
          return;
        }

        var id_customer = id_customer_wmbr;
        $.ajax({
          type: 'POST',
          url: wmbrAjaxUrl,
          data: {
            method: 'checkForDoc',
            adminToken: sec_token,
            id_customer: id_customer,
          },
          success: function(json) {
            var doc = $.parseJSON(json);
            WMBRBackOfficeController.ajax.applyDocument(doc);
          }
        });

      },

      getAddressNumber: function(){

        // PS 8/9: ajax.php is blocked by modules/.htaccess (403). Prefer the
        // address number injected server-side; only fall back to the legacy
        // AJAX on PS 1.6/1.7 where the direct call still works.
        if(typeof address_doc_wmbr != 'undefined'){
          if(typeof address_doc_wmbr.address_number != 'undefined'){
            $('input[name="address_number"]').val(address_doc_wmbr.address_number);
          }
          return;
        }

        var address_id = id_address_wmbr;
        $.ajax({

          type: 'POST',
          url: wmbrAjaxUrl,
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

    },

    events: {

      init: function(){

        $(document).on('click', '#emitirNfe', WMBRBackOfficeController.eventsHandler.emitirBulkAction);
        $(document).on('change', 'input[name="document_type"]', WMBRBackOfficeController.eventsHandler.changeTipoPessoa);

        // initTipoPessoa (mask + tipo-pessoa toggle) now runs in the docs.html
        // load callback in insertTipoPessoa, once the inputs actually exist.

      }

    },

    eventsHandler:{

      emitirBulkAction: function(e) {
        e.preventDefault();
        $(this).siblings('input').attr('name', 'bulkEmitirNfe');
        $('#form-order').submit();

      },

      changeTipoPessoa: function(e) {

        var target = $(this).val();
        if(target == 'cpf'){
          if($('#cnpj-field').hasClass('active')){
            $('#cnpj-field').fadeToggle('fast', 'swing', function(){
              if(!$('#cpf-field').hasClass('active')){
                $('#cpf-field').fadeToggle('fast').addClass('active');
                // jQuery Mask does not reliably bind on a display:none input;
                // re-apply once the field is actually revealed so masking works.
                $('input[name="cpf"]').mask('999.999.999-99');
              }
            }).removeClass('active');
          }

        }else if(target == 'cnpj'){
          if($('#cpf-field').hasClass('active')){
            $('#cpf-field').fadeToggle('fast', 'swing', function(){
              $('#cnpj-field').fadeToggle('fast').addClass('active');
              // Re-apply the CNPJ mask on reveal (see note above): the field
              // starts hidden, so the mask attached at init may be inert.
              $('input[name="cnpj"]').mask('99.999.999/9999-99');
            }).removeClass('active');
          }

        }

      },

      initTipoPessoa: function() {

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

        // docs.html renders inputs as name="cpf"/#cpf-input and name="cnpj"/#cnpj-input,
        // not #cpf/#cnpj, so target by name to actually apply the mask.
        $('input[name="cpf"]').mask('999.999.999-99');
        $('input[name="cnpj"]').mask('99.999.999/9999-99');

      }
    },


    init: function() {

      this.events.init();

      if(this.pages.isCustomerPage()){

        // insertTipoPessoa loads docs.html async and, in its callback, applies
        // the mask and (on edit) prefills via getDocuments — so both are
        // handled there once the inputs exist. Nothing to call synchronously.
        if (tipo_pessoa_enabled == 'on') this.DOM.insertTipoPessoa();

      }

      if(this.pages.isAddressPage()){

        if (numero_enabled == 'on') this.DOM.insertNumero();

        if(this.pages.isEditAddressPage()){
          this.ajax.getAddressNumber();
        }

      }

      if(this.pages.isAdminOrdersPage()){

        $(".column-nfe_issued").each(function (i) {

          this.className += " extended-width-nfe";
          var div = document.createElement('div');

          if (this.innerHTML.includes('0')) {

            div.setAttribute("class", "nao-emitida nfe-status");
            div.innerHTML = this.innerHTML.replace("0", "NF-e não emitida");

          } else {

            div.setAttribute("class", "emitida nfe-status");
            div.innerHTML = this.innerHTML.replace("1", "NF-e emitida");

          }

          this.innerHTML = "";
          this.appendChild(div);

        });

      }

    },

  };

  WMBRBackOfficeController.init();


  // address2 = Bairro. PS 1.6/1.7 = #address2; PS 8/9 = #customer_address_address2.
  var $address2 = $('#address2');
  if($address2.length === 0){
    $address2 = $('#customer_address_address2, input[name="customer_address[address2]"]');
  }
  if($address2.length > 0){
    $address2.parents('.form-group').find('label').addClass('required').html('Bairro');
  }


  var handleCPFCNPJStatus = function(status) {

    var changingFields = [
      'webmaniabrnfetipo_cliente_field',
      'webmaniabrnfecpf_field',
      'webmaniabrnfecnpj_field',
      'webmaniabrnferazao_social_field',
      'webmaniabrnfecnpj_ie_field',
      'webmaniabrnfevalor_pessoa_fisica',
      'webmaniabrnfevalor_pessoa_juridica'
    ];

    if(status == 'on'){
      
      $('.nfe-docs-table').parents('.form-group').hide();
      
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').hide();
      });
    }else if(status == 'off'){
      
      $('.nfe-docs-table').parents('.form-group').show();
      
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').show();
      });
    }
  };

  var handleNoComplStatus = function(status) {

    var changingFields = [
      'webmaniabrnfenumero_field',
      'webmaniabrnfecomplemento_field',
    ];

    if(status == 'on'){
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').hide();
      });
    }else if(status == 'off'){
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').show();
      });
    }

  }

  var handleBairroStatus = function(status) {

    var changingFields = [
      'webmaniabrnfebairro_field',
    ];

    if(status == 'on'){
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').hide();
      });
    }else if(status == 'off'){
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').show();
      });
    }

  }

  if(WMBRBackOfficeController.pages.isModuleConfigPage()){

    var cpf_cnpj_status = $('input[name="webmaniabrnfecpf_cnpj_status"]:checked').val();
    handleCPFCNPJStatus(cpf_cnpj_status);

    var nO_compl_status = $('input[name="webmaniabrnfenumero_compl_status"]:checked').val();
    handleNoComplStatus(nO_compl_status);

    var bairro_status = $('input[name="webmaniabrnfebairro_status"]:checked').val();
    handleBairroStatus(bairro_status);

    $('input[name="webmaniabrnfecpf_cnpj_status"]').on('change', function(){
      var cpf_cnpj_status = $('input[name="webmaniabrnfecpf_cnpj_status"]:checked').val();
      handleCPFCNPJStatus(cpf_cnpj_status);
    });

    $('input[name="webmaniabrnfenumero_compl_status"]').on('change', function(){
      var nO_compl_status = $('input[name="webmaniabrnfenumero_compl_status"]:checked').val();
      handleNoComplStatus(nO_compl_status);
    });

    $('input[name="webmaniabrnfebairro_status"]').on('change', function(){
      var bairro_status = $('input[name="webmaniabrnfebairro_status"]:checked').val();
      handleBairroStatus(bairro_status);
    });

    $('input[name="webmaniabrnfeintermediador_cnpj').mask("99.999.999/9999-99");

    update_payment_desc_label = function() {
      
      var descs_active = $('.webmaniabrnfepayment-desc').filter(function() { 
        return $(this).css('display') !== 'none'; 
      }).size();

      if (descs_active > 0) {
        $('.payment-desc-title').show();
      }
      else {
        $('.payment-desc-title').hide();
      } 

    }

    update_payment_desc_label();

    // Show payment desc field if payment method is 99
    $('.webmaniabrnfepayment-methods-sel').change(function(element) {
			
			var payment_desc = $(event.target).parent().parent().find('.webmaniabrnfepayment-desc');
			
			if (element.target.value == 99) {
				$(payment_desc).show();
        $('.payment-desc-title').show();
			}
			else {
				$(payment_desc).val('');
				$(payment_desc).hide();
			}

      update_payment_desc_label();
			
		});

  }
  
  
  
  /**** DOC MAP ****/
  
  $('.nfe-docs--column').on('change', function(){
    
    var table_name = $(this).val();
    if(!table_name) return;

    var $parent_tr = $(this).parents('tr');
    var $parent_td = $(this).parents('td');
    var index = $parent_td.index();
    
    var $column_select = $parent_tr.next('tr').find('td:nth-child('+(index+1)+') select')
    
    $column_select.html('<option value="">Carregando...</option>');
    
    $.ajax({

      type: 'POST',
      url: wmbrAjaxUrl,
      data: {
        method: 'getPSColumns',
        table_name: table_name,
        adminToken: sec_token,
      },
      success: function(json) {
        
        var result = $.parseJSON(json);
        $column_select.html('<option value="">Selecionar</option>');
      
        result.results.forEach(function(value, index){
          $column_select.append('<option value="'+value+'">'+value+'</option>');
        });
        
        $column_select.attr('disabled', false);
        
      }

    });
    
  });
  
  /******************* TRANSPORTADORAS ***************************/
  
  $('.btn--confirm-carrier').click(function(){
    
    if( ! validateCarrierForm() ){
      alert('Preencha todas as informações para adicionar a transportadora');
      return;
    }
    
    var inputValue = $('input[name="webmaniabrnfecarriers"]').val();
    
    if(inputValue){
      inputValue = atob(inputValue.replace(/%/g, '='));
    }

    if(!inputValue){
      inputValue = [];
    }else{
      inputValue = JSON.parse(inputValue);
    }
    
    
    var id = $('input[name="current-edit"').val();
    
    var data = {
      method       : $('select[name="webmaniabrnfe_transp_method"]').val(),
      razao_social : $('input[name="webmaniabrnfe_transp_rs"]').val(),
      cnpj         : $('input[name="webmaniabrnfe_transp_cnpj"]').val(),
      ie           : $('input[name="webmaniabrnfe_transp_ie"]').val(),
      address      : $('input[name="webmaniabrnfe_transp_address"]').val(),
      cep          : $('input[name="webmaniabrnfe_transp_cep"]').val(),
      city         : $('input[name="webmaniabrnfe_transp_city"]').val(),
      uf           : $('input[name="webmaniabrnfe_transp_uf"]').val(),
    };
    
    if(id){
      
      for(var i = 0; i < inputValue.length; i++){
        if(inputValue[i].id == id){
          
          inputValue[i].method       = data.method;
          inputValue[i].razao_social = data.razao_social;
          inputValue[i].cnpj         = data.cnpj;
          inputValue[i].ie           = data.ie;
          inputValue[i].address      = data.address;
          inputValue[i].cep          = data.cep;
          inputValue[i].city         = data.city;
          inputValue[i].uf           = data.uf;
          
          $('.carrier-item[data-id="'+id+'"]').find('p').html(data.razao_social+' <br/>(<span>Editar</span>)');
          
        }
      }
      
    }else{
      
      data.id = ID();
      addCarrierToList(data);
      inputValue.push(data);
      
    }
    
    var str64 = btoa(JSON.stringify(inputValue));
    var sub64 = str64.substring(str64.length -2);
    
    str64 = str64.substring(0, str64.length - 2);
  
    
    
    if(sub64[0] == '=' || sub64[1] == '='){
      sub64 = sub64.replace(/=/g, '%');
    }
    
    str64 += sub64;
    
    $('input[name="webmaniabrnfecarriers"]').val(str64);
    $('#add-carrier-modal').modal('hide');
  
  });
  
  $('.carriers-list').on('click', '.carrier-item', function(e){
    
    if($(e.target).hasClass('delete')) return;
    
    var id = $(this).attr('data-id');
    var data = getDataByID(id);
    
    $('select[name="webmaniabrnfe_transp_method"]').val(data.method).change();
    $('input[name="webmaniabrnfe_transp_rs"]').val(data.razao_social);
    $('input[name="webmaniabrnfe_transp_cnpj"]').val(data.cnpj);
    $('input[name="webmaniabrnfe_transp_ie"]').val(data.ie);
    $('input[name="webmaniabrnfe_transp_address"]').val(data.address);
    $('input[name="webmaniabrnfe_transp_cep"]').val(data.cep);
    $('input[name="webmaniabrnfe_transp_city"]').val(data.city);
    $('input[name="webmaniabrnfe_transp_uf"]').val(data.uf);
    
    $('.modal-title').html('Editar Transportadora');
    $('.btn--confirm-carrier').html('Salvar');
    $('input[name="current-edit').val(id);
    
    $('#add-carrier-modal').modal('show');

  });
  
  $('#add-carrier-modal').on('hidden.bs.modal', function(){
    resetModal();
  });
  
  
  $('.carriers-list').on('click', '.delete', function(){
    
    if(!confirm('Tem certeza que deseja remover esta transportadora?')) return;
    
    var id = $(this).parents('.carrier-item').attr('data-id');
    
    var inputValue = $('input[name="webmaniabrnfecarriers"]').val();
    inputValue = inputValue.replace(/%/, '=');
    inputValue = atob(inputValue);
    
    inputValue = $.parseJSON(inputValue);
    
    for(var i = 0; i < inputValue.length; i++){
      if(inputValue[i].id == id){
        inputValue.splice(i, 1);
      }
    }
    
    if(inputValue.length == 0){
      $('input[name="webmaniabrnfecarriers"]').val('');
    }else{
      
      var str64 = btoa(JSON.stringify(inputValue));
      var sub64 = str64.substring(str64.length -2);
      
      str64 = str64.substring(0, str64.length - 2);
    
    
      if(sub64[0] == '=' || sub64[1] == '='){
        sub64 = sub64.replace(/=/g, '%');
      }
      
      str64 += sub64;
      
      $('input[name="webmaniabrnfecarriers"]').val(str64);
    }
    
    $(this).parents('.carrier-item').remove();
    
  });
  
  function resetModal(){
    
    $('select[name="webmaniabrnfe_transp_method"]').val('').change();
    $('input[name="webmaniabrnfe_transp_rs"]').val('');
    $('input[name="webmaniabrnfe_transp_cnpj"]').val('');
    $('input[name="webmaniabrnfe_transp_ie"]').val('');
    $('input[name="webmaniabrnfe_transp_address"]').val('');
    $('input[name="webmaniabrnfe_transp_cep"]').val('');
    $('input[name="webmaniabrnfe_transp_city"]').val('');
    $('input[name="webmaniabrnfe_transp_uf"]').val('');
    $('input[name="current-edit').val('');
    
    $('.modal-title').html('Nova Transportadora');
    $('.btn--confirm-carrier').html('Adicionar');
    
  }
  
  function validateCarrierForm(){
    
    var data = {
      id           : ID(),
      method       : $('select[name="webmaniabrnfe_transp_method"]').val(),
      razao_social : $('input[name="webmaniabrnfe_transp_rs"]').val(),
      cnpj         : $('input[name="webmaniabrnfe_transp_cnpj"]').val(),
      ie           : $('input[name="webmaniabrnfe_transp_ie"]').val(),
      address      : $('input[name="webmaniabrnfe_transp_address"]').val(),
      cep          : $('input[name="webmaniabrnfe_transp_cep"]').val(),
      city         : $('input[name="webmaniabrnfe_transp_city"]').val(),
      uf           : $('input[name="webmaniabrnfe_transp_uf"]').val(),
    };
    
    for(var key in data){
      if(!data[key]){
        return false;
      }
    }
    
    return true;
    
  }
  
  function addCarrierToList(data){
    
    var $carrier = $('<div class="carrier-item" data-id="'+data.id+'"></div>');
    var carrier_name = $('option[value="'+data.method+'"]').html();
    
    $carrier.append('<p>'+data.razao_social+' <br/>(<span>Editar</span>)</p>');
    $carrier.append('<span class="delete">x</span>');
    
    $carrier.appendTo('.carriers-list');
    
  }
  
  function getDataByID(id){
    
    var val = $('input[name="webmaniabrnfecarriers"]').val();
    val = val.replace(/%/g, '=');
    val = atob(val);
    
    var arr = $.parseJSON(val);
    
    
    for(var i = 0; i < arr.length ; i++){
      if(arr[i].id == id){
        return arr[i];
      }  
    }
    
  }
  
  function ID() {
    return '_' + Math.random().toString(36).substr(2, 9);
  };


});
