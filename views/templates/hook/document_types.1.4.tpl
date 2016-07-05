<div id="document-types" style="overflow:hidden;">
  <div class="clearfix">
    <label class="main-label-1_4">Tipo de pessoa:</label>
    <div class="radio-inline radio-1_4">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf" checked/></span>
        Pessoa Física
      </label>
    </div>
    <div class="radio-inline radio-1_4">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj"/></span>
        Pessoa Jurídica
      </label>
    </div>
  </div>
  <div id="cpf-field" class="form-group active">
    <label class="main-label-1_4">CPF <sup>*</sup></label>
    <input type="text" class="form-control input-text-1_4 text" name="cpf" id="cpf-input"/>
  </div>
  <div id="cnpj-field" style="display:none">
    <div class="form-group">
      <label class="main-label-1_4">Razão Social <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_4 text" name="razao_social" id="razao-social0input" style="margin-bottom:10px"/>
    </div>
    <div class="form-group">
      <label class="main-label-1_4">CNPJ <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_4 text" name="cnpj" id="cnpj-input" style="margin-bottom:10px"/>
    </div>

    <div class="form-group">
      <label class="main-label-1_4">Inscrição estadual <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_4 text" name="cnpj_ie" id="ie-input"/>
    </div>
  </div>
</div>

<style>
#document-types .main-label-1_4{
  display: inline-block;
    padding: 6px 15px;
    text-align: right;
    padding:0;
    padding-left:2px;
}

#document-types .radio-1_4{
  display:inline-block;
  vertical-align: middle;
}

#document-types .radio-1_4 .top{
      float: none;
}

#cpf-field,
#cnpj-field{
  margin-top:10px;
}

</style>
<script>
jQuery(document).ready(function($){
  var target = $('.account_creation').find('input[name="customer_firstname"]').parents('p.required');
  $('#document-types').insertBefore(target);
  $('#cpf-input').mask('999.999.999-99');
  $('#cnpj-input').mask('99.999.999/9999-99');
});
</script>
