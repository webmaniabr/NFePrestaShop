jQuery(document).ready(function(){

  var WMBRBackOfficeController = {

    pages: {

      isCustomerPage: function() {

        if($('#customer_form').length > 0){
          return true;
        }

        return false;

      },

      isAddressPage: function() {

        if($('#address_form').length > 0){
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

        if($('body.adminorders').find('.bulk-actions').length > 0){
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

      getTipoPessoa: function() {

        var $html =  $('<div></div>');
        $html.load('../modules/webmaniabrnfe/assets/templates/docs.html');

        return $html;

      },

      getNumero: function() {

        var addrNumber = $('<div class="form-group number">'+
                              '<label class="control-label col-lg-3 required"> Número</label>'+
                              '<div class="col-lg-2"><input type="text" class="form-control" name="address_number" /></div>'+
                            '</div>');

        return addrNumber;

      },

      getBulkAction: function(){

        var action = "sendBulkAction($(this).closest('form').get(0), 'submitBulkemitirNfeorder');";
        var $html = '<li><a href="#" id="emitirNfe" onclick="'+action+'"><i class="icon-file-text"></i> Emitir NF-e</a><input type="hidden" name="" value="update" /></li>';
        $html += '<input type="hidden" name="wmbr_bulk_action" value="emitirNfe" />';

        return $html;

      }

    },

    DOM: {

      insertTipoPessoa: function() {

        var html = WMBRBackOfficeController.html.getTipoPessoa();
        var referenceElement = $('input[name="firstname"]').parents('.form-group');

        $(html).insertBefore(referenceElement);

      },

      insertNumero: function(){

        var html = WMBRBackOfficeController.html.getNumero();
        html.insertAfter($('#address1').parents('.form-group'));
        if($('#address1').length == 0){
          html.insertAfter($('#address').parents('.form-group'));
        }

      },

      insertBulkAction: function(){

        var html = WMBRBackOfficeController.html.getBulkAction();
        $(html).appendTo('.bulk-actions .dropdown-menu');

      }

    },

    ajax: {

      getDocuments: function(){

        var id_customer = id_customer_wmbr;
        $.ajax({
          type: 'POST',
          url: '../modules/webmaniabrnfe/ajax.php',
          data: {
            method: 'checkForDoc',
            adminToken: sec_token,
            id_customer: id_customer,
          },
          success: function(json) {
            console.log(json);
            result = $.parseJSON(json);

            if(typeof result.document_type != 'undefined'){
              $('input[value="'+result.document_type+'"]').prop('checked', true).trigger('change');
              $('input[name="'+result.document_type+'"]').val(result.document_number);
              $('input[name="razao_social"]').val(result.razao_social);
              $('input[name="nfe_pj_ie"]').val(result.nfe_pj_ie);
            }
          }
        });

      },

      getAddressNumber: function(){

        var address_id = id_address_wmbr;
        $.ajax({

          type: 'POST',
          url: '../modules/webmaniabrnfe/ajax.php',
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

        if(WMBRBackOfficeController.pages.isEditCustomerPage()){
          WMBRBackOfficeController.eventsHandler.initTipoPessoa();
        }

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
              }
            }).removeClass('active');
          }

        }else if(target == 'cnpj'){
          if($('#cpf-field').hasClass('active')){
            $('#cpf-field').fadeToggle('fast', 'swing', function(){
              $('#cnpj-field').fadeToggle('fast').addClass('active');
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

        $('#cpf').mask('999.999.999-99');
        $('#cnpj').mask('99.999.999/9999-99');

      }
    },


    init: function() {

      this.events.init();

      if(this.pages.isCustomerPage()){
        
        if (tipo_pessoa_enabled == 'on') this.DOM.insertTipoPessoa();

        if(this.pages.isEditCustomerPage()){
          this.ajax.getDocuments();
        }

      }

      if(this.pages.isAddressPage()){

        if (numero_enabled == 'on') this.DOM.insertNumero();

        if(this.pages.isEditAddressPage()){
          this.ajax.getAddressNumber();
        }

      }

      if(this.pages.isAdminOrdersPage()){

        this.DOM.insertBulkAction();

      }

    },

  };

  WMBRBackOfficeController.init();


  if($('#address2').length > 0){
    $('#address2').parents('.form-group').find('label').addClass('required').html('Bairro');
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
      changingFields.forEach(function(name, index){
        $('input[name="'+name+'"]').parents('.form-group').hide();
      });
    }else if(status == 'off'){
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

  }


});
