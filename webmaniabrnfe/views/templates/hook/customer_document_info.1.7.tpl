<div class="col">
    <div class="card">
        <h3 class="card-header">
            <i class="material-icons">person</i>
            Informações do Documento
        </h3>
        <div class="card-body">
            <div class="row mb-1">
                <div class="col-4 text-right"> Tipo de pessoa </div>
                <div class="col-8"> {if $customer_info['nfe_document_type'] == 'cpf'}Física{else}Jurídica{/if} </div>
            </div>
            <div class="row mb-1">
                <div class="col-4 text-right"> {if $customer_info['nfe_document_type'] == 'cpf'}CPF{else}CNPJ{/if} </div>
                <div class="col-8"> {$customer_info['nfe_document_number']} </div>
            </div>
            {if $customer_info['nfe_document_type'] == 'cnpj'}
                <div class="row mb-1">
                    <div class="col-4 text-right"> Razão Social </div>
                    <div class="col-8"> {$customer_info['nfe_razao_social']} </div>
                </div>
                <div class="row mb-1">
                    <div class="col-4 text-right"> IE </div>
                    <div class="col-8"> {$customer_info['nfe_pj_ie']} </div>
                </div>
            {/if}
        </div>
    </div>
</div>