<?php
if(!defined('_PS_VERSION_')){
  exit;
}
require_once __DIR__ . '/Security.php';
class NFe {
    private $consumerKey = '';
    private $consumerSecret = '';
    private $accessToken = '';
    private $accessTokenSecret = '';

    function __construct( array $vars ){

        $this->consumerKey = isset($vars['consumer_key']) && is_scalar($vars['consumer_key']) ? (string) $vars['consumer_key'] : '';
        $this->consumerSecret = isset($vars['consumer_secret']) && is_scalar($vars['consumer_secret']) ? (string) $vars['consumer_secret'] : '';
        $this->accessToken = isset($vars['oauth_access_token']) && is_scalar($vars['oauth_access_token']) ? (string) $vars['oauth_access_token'] : '';
        $this->accessTokenSecret = isset($vars['oauth_access_token_secret']) && is_scalar($vars['oauth_access_token_secret']) ? (string) $vars['oauth_access_token_secret'] : '';

    }

    function statusSefaz($data = ''){

        $response = self::connect_webmaniabr( 'GET', 'https://webmaniabr.com/api/1/nfe/sefaz/', $data );
        if (isset($response->error)) return $response;
        if (isset($response->status) && $response->status == 'online') return true;
        else return false;

    }

    function validadeCertificado($data = ''){

        $response = self::connect_webmaniabr('GET', 'https://webmaniabr.com/api/1/nfe/certificado/', $data);
        if (isset($response->error)) return $response;
        return isset($response->expiration) && is_scalar($response->expiration) ? $response->expiration : '';

    }

    function emissaoNotaFiscal( array $data ){

        $response = self::connect_webmaniabr( 'POST', 'https://webmaniabr.com/api/1/nfe/emissao/', $data );
        return $response;

    }

    function consultaNotaFiscal( $chave ){

        $data = array();
        $data[WebmaniaNFeSecurity::validUuid($chave) ? 'uuid' : 'chave'] = $chave;
        $response = self::connect_webmaniabr( 'GET', 'https://webmaniabr.com/api/1/nfe/consulta/', $data );
        return $response;

    }

    function cancelarNotaFiscal( $chave, $motivo ){

        $data = array();
        $data[WebmaniaNFeSecurity::validUuid($chave) ? 'uuid' : 'chave'] = $chave;
        $data['motivo'] = $motivo;
        $response = self::connect_webmaniabr( 'PUT', 'https://webmaniabr.com/api/1/nfe/cancelar/', $data );
        return $response;

    }

    function inutilizarNumeracao( $sequencia, $motivo ){

        $data = array();
        $data['sequencia'] = $sequencia;
        $data['motivo'] = $motivo;
        $response = self::connect_webmaniabr( 'PUT', 'https://webmaniabr.com/api/1/nfe/inutilizar/', $data );
        return $response;

    }

    function connect_webmaniabr( $request, $endpoint, $data ){
        return WebmaniaNFeSecurity::jsonRequest($request, $endpoint, $data, array('consumer_key' => $this->consumerKey, 'consumer_secret' => $this->consumerSecret, 'access_token' => $this->accessToken, 'access_token_secret' => $this->accessTokenSecret));
}

    public function curl_get_file_contents($url) {
        return WebmaniaNFeSecurity::download($url, array('consumer_key' => $this->consumerKey, 'consumer_secret' => $this->consumerSecret, 'access_token' => $this->accessToken, 'access_token_secret' => $this->accessTokenSecret));
}

}
?>
