<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use ProvaSpider\Contract\Spider;
use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Http\HttpTransport;
use ProvaSpider\Http\StreamHttpTransport;
use ProvaSpider\Parser\CadastroHtmlParser;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

/**
 * O formulário de consultaBa.asp envia um POST para result.asp, que devolve o
 * cadastro direto: não há cookie, sessão, captcha ou etapa intermediária.
 */
final class SefazBaSpider implements Spider
{
    public const URL = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp';
    public const URL_RESULTADO = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/result.asp';

    public function __construct(
        private readonly HttpTransport $http = new StreamHttpTransport(),
        private readonly CadastroHtmlParser $parser = new CadastroHtmlParser(),
    ) {
    }

    /**
     * @throws CadastroNaoEncontradoException o portal informou que o CNPJ não tem cadastro
     * @throws PortalIndisponivelException    sem comunicação, 502/503/504 ou aviso de manutenção
     * @throws FalhaHttpException             outro status HTTP de erro
     * @throws RespostaInesperadaException    resposta sem os dados do cadastro e sem aviso conhecido
     */
    public function consultar(Consulta $consulta): CadastroContribuinte
    {
        // Campos enviados pelo formulário ao clicar em "CNPJ ->" (observados no DevTools).
        $resposta = $this->http->post(self::URL_RESULTADO, [
            'sefp' => '1',
            'estado' => 'BA',
            'CGC' => $consulta->cnpj,
            'B1' => 'CNPJ ->',
            'CPF' => '',
            'IE' => '',
        ]);

        $cadastro = $resposta->sucesso() ? $this->parser->parse($resposta->body) : null;
        if ($cadastro !== null) {
            return $cadastro;
        }

        // Sem cadastro na resposta: o texto da página e o status dizem o motivo.
        if ($this->parser->indicaSemCadastro($resposta->body)) {
            throw CadastroNaoEncontradoException::paraCnpj($consulta->cnpj);
        }

        if (in_array($resposta->statusCode, [502, 503, 504], true) || $this->parser->indicaManutencao($resposta->body)) {
            throw PortalIndisponivelException::emManutencao();
        }

        if (!$resposta->sucesso()) {
            echo($resposta->body);
            throw FalhaHttpException::comStatus($resposta->statusCode, self::URL_RESULTADO);
        }

        throw RespostaInesperadaException::estruturaNaoReconhecida('a página não traz CNPJ e razão social do cadastro');
    }
}