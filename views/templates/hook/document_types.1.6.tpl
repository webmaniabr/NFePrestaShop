<div id="document-types">
  <div class="clearfix">
    <label>Tipo de pessoa:</label><br/>
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf" checked/></span>
        Pessoa Física
      </label>
    </div>
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj"/></span>
        Pessoa Jurídica
      </label>
    </div>
  </div>
  <div id="cpf-field" class="form-group active">
    <label>CPF <sup>*</sup></label>
    <input type="text" class="form-control" name="cpf" id="cpf-input"/>
  </div>
  <div id="cnpj-field" style="display:none">
    <div class="form-group">
      <label>Razão Social <sup>*</sup></label>
      <input type="text" class="form-control" name="razao_social" />
    </div>
    <div class="form-group">
      <label>CNPJ <sup>*</sup></label>
      <input type="text" class="form-control" name="cnpj" id="cnpj-input" />
    </div>
    <div class="form-group">
      <label>Inscrição estadual <sup>*</sup></label>
      <input type="text" class="form-control" name="cnpj_ie" id="ie-input"/>
    </div>
  </div>
</div>

<script>
jQuery(document).ready(function($){
  var target = $('.account_creation').find('.clearfix').first();
  $('#document-types').insertAfter(target);

  $('#cpf-input').mask('999.999.999-99');
  $('#cnpj-input').mask('99.999.999/9999-99');
});
</script>
