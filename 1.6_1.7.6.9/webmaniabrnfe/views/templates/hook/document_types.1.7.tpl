<div id="document-types">
  <div class="form-group row">
    <label class="col-md-3 form-control-label required">Tipo de pessoa:</label>
    <div class="col-md-6 form-control-valign">
      <label class="radio-inline" style="margin-right: 15px;">
        <span class="custom-radio">
          <input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf" checked/>
          <span></span>
        </span>
          Pessoa Física
      </label>
      <label class="radio-inline">
        <span class="custom-radio">
          <input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj"/>
          <span></span>
        </span>
          Pessoa Jurídica
      </label>
    </div>
  </div>


  <div id="cpf-field" class="form-group row active">
    <label class="col-md-3 form-control-label required">CPF <sup>*</sup></label>
    <div class="col-md-6">
      <input type="text" class="form-control" name="cpf" id="cpf-input"/>
    </div>

  </div>
  <div id="cnpj-field" style="display:none">
    <div class="form-group row">
      <label class="col-md-3 form-control-label required">Razão Social <sup>*</sup></label>
      <div class="col-md-6">
        <input type="text" class="form-control" name="razao_social" />
      </div>
    </div>
    <div class="form-group row">
      <label class="col-md-3 form-control-label required">CNPJ <sup>*</sup></label>
      <div class="col-md-6">
        <input type="text" class="form-control" name="cnpj" id="cnpj-input" />
      </div>
    </div>
    <div class="form-group row">
      <label class="col-md-3 form-control-label required">Inscrição estadual <sup>*</sup></label>
      <div class="col-md-6">
        <input type="text" class="form-control" name="cnpj_ie" id="ie-input"/>
      </div>
    </div>
  </div>
</div>

<script>
jQuery(document).ready(function($){
  var target = $('.account_creation').find('.clearfix').first();

  $('#document-types').insertAfter(target);

    if('{$custom_var}' === 'on'){
      $('#cpf-input').mask('999.999.999-99');
      $('#cnpj-input').mask('99.999.999/9999-99');
    }




});
</script>
