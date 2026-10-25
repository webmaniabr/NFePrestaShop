<p align="center">
  <img src="docs/assets/webmania-logo.png" width="396" alt="Webmania">
</p>

# Nota Fiscal Eletrônica para PrestaShop

Através do emissor de Nota Fiscal da Webmania®, você conta com a emissão e arquivamento das suas notas fiscais, cálculo automático de impostos, geração do Danfe para impressão e envio automático de e-mails para os clientes.

- **Módulo 3.0.3 para PrestaShop 1.6, 1.7, 8 e 9; consulte os ambientes verificados abaixo.**
- **Faça download da última versão do módulo: [Clique aqui](https://github.com/webmaniabr/NFePrestaShop/releases)**
- Emissor de Nota Fiscal Webmania®: [Saiba mais](https://webmaniabr.com/nota-fiscal-eletronica/)
- Documentação da REST API: [Visualizar](https://webmaniabr.com/docs/rest-api-nfe/)

## Compatibilidade verificada

| PrestaShop | PHP verificado |
| --- | --- |
| 1.6.1.24 | 5.6 |
| 1.7.8.11 | 7.4 |
| 8.2.8 | 7.4 |
| 9.0.3 e 9.2.0 | 8.3 |

O módulo está na versão **3.0.3**. As verificações adicionais do código em PHP 8.5 não substituem os requisitos de PHP de cada versão do PrestaShop.

A tabela registra os ambientes exercitados. Use o PHP exigido pela versão da sua plataforma e valide as integrações da loja em homologação antes de atualizar.

## Requisitos

- Contrate um dos planos de Nota Fiscal Eletrônica da Webmania®: [Assine agora mesmo](https://webmaniabr.com/nota-fiscal-eletronica/).
- Instale o módulo do PrestaShop da Webmania® e configure conforme instruções.

## Instalação

Baixe o arquivo [webmaniabrnfe.zip do release mais recente](https://github.com/webmaniabr/NFePrestaShop/releases/latest/download/webmaniabrnfe.zip) e envie-o no gerenciador de módulos do PrestaShop. O ZIP deve conter a pasta `webmaniabrnfe/` com o arquivo principal `webmaniabrnfe.php` e os demais arquivos do módulo.

Para atualizar uma instalação existente, substitua os arquivos do módulo e preserve sua configuração no banco de dados; não é necessário desinstalá-lo. Após a atualização, limpe o cache do PrestaShop.

<p align="center">
<img src="https://webmaniabr.com/atendimento/wp-content/uploads/sites/4/2016/06/img_5771a725955f6.png">
</p>

## Ativação

- Acesse o menu Extensões > Módulos. Localize o módulo chamado **WebmaniaBR NF-e** e clique no botão Instalar.

<p align="center">
<img src="https://webmaniabr.com/atendimento/wp-content/uploads/sites/4/2016/06/1467066294-aee7d6d4db7dafea269d6414ab320844.png">
</p>

<p align="center">
<img src="https://webmaniabr.com/atendimento/wp-content/uploads/sites/4/2016/06/img_5771a771d14d1.png">
</p>

## Configuração

Consulte o nosso guia passo a passo para começar a emitir as notas fiscais com apenas um clique na sua Loja Virtual:

**PrestaShop 1.6**
- [Configurar credenciais](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013116532-Configurar-credenciais-no-PrestaShop-1-6)
- [Configurar impostos](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013122172-Configurar-impostos-no-PrestaShop-1-6)
- [Emitir Nota Fiscal](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013127712-Emiss%C3%A3o-de-NF-e-no-PrestaShop-1-6)

**PrestaShop 1.7**
- [Configurar credenciais](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013116872-Configurar-credenciais-no-PrestaShop-1-7)
- [Configurar impostos](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013122332-Configurar-impostos-no-PrestaShop-1-7)
- [Emitir Nota Fiscal](https://ajuda.webmaniabr.com/hc/pt-br/articles/360013383592-Emiss%C3%A3o-de-NF-e-no-PrestaShop-1-7)

**PrestaShop 8 e 9**
- Configure as credenciais e os dados fiscais no gerenciador de módulos do painel atual.
- Os fluxos foram exercitados nas versões indicadas na tabela de compatibilidade.

## Campos Obrigatórios

Para emitir uma NFe, é obrigatório informar o Tipo de Pessoa (CPF/CNPJ), número e bairro do Endereço.
O módulo possui a opção para habilitar estes campos:

<p align="center">
<img src="https://webmaniabr.com/wp-content/uploads/2017/03/1488915583.png">
</p>

Caso você já possua outro módulo que insira estes campos, é necessário configurar o nome dos mesmos para que os dados sejam salvos de forma correta:

<p align="center">
<img src="https://webmaniabr.com/wp-content/uploads/2017/03/1488915600.png">
</p>

Para descobrir o nome dos campos é preciso inspecionar o elemento na página de Checkout (ou de Endereço) e verificar o atributo "name".

## Recuperar uma emissão pendente

Na tela do pedido, um administrador com permissão de edição pode consultar e recuperar uma nota cujo UUID já foi salvo. Se a tentativa não tiver UUID, a liberação exige esperar ao menos 10 minutos e confirmar que ela não gerou uma nota no painel da Webmania. A recuperação não emite outra nota. Uma rejeição libera a tentativa para revisão dos dados, preservando notas anteriores.

## Controle das Notas Fiscais

Você pode gerenciar todas as Notas Fiscais e realizar a impressão do Danfe no painel da Webmania®. Simples e fácil.

<p align="center">
<img src="https://wmbr.s3.amazonaws.com/img/dashboard_webmaniabr_01.jpg">
</p>

## Atualizações desta versão

- Aceita CNPJ numérico e alfanumérico nos campos e documentos fiscais.
- Protege credenciais, operações administrativas, notificações e PDFs.
- Impede emissões simultâneas para o mesmo pedido e permite recuperar tentativas com resultado pendente.
- Notas rejeitadas na recuperação ou no callback não são registradas como novas emissões; a tentativa é liberada para correção e repetição.
- Preserva notas anteriores e campos fiscais omitidos em atualizações parciais.

## Suporte

Qualquer dúvida acesse o [Painel Webmania®](https://webmaniabr.com/painel/) para *abrir um chamado* ou conversar em tempo real no *chat*.

