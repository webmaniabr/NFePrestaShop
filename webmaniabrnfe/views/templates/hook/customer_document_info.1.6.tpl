<div class="col-lg-6">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-user"></i>
            Informações do Documento
        </div>
        <div class="form-horizontal">
            <div class="row">
                <label class="control-label col-lg-3">Tipo de pessoa</label>
                <div class="col-lg-9"><p class="form-control-static">{if $customer_info['nfe_document_type'] == 'cpf'}Física{else}Jurídica{/if}</p></div>
            </div>
            <div class="row">
                <label class="control-label col-lg-3"> {if $customer_info['nfe_document_type'] == 'cpf'}CPF{else}CNPJ{/if} </label>
                <div class="col-lg-9"><p class="form-control-static">{$customer_info['nfe_document_number']}</p></div>
            </div>
            {if $customer_info['nfe_document_type'] == 'cnpj'}
                <div class="row">
                    <label class="control-label col-lg-3"> Razão Social </label>
                    <div class="col-lg-9"><p class="form-control-static">{$customer_info['nfe_razao_social']}</p></div>
                </div>
                <div class="row">
                    <label class="control-label col-lg-3"> IE </label>
                    <div class="col-lg-9"><p class="form-control-static">{$customer_info['nfe_pj_ie']}</p></div>
                </div>
            {/if}
        </div>
    </div>
</div>