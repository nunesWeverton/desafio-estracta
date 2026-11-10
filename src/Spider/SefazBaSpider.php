<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use ProvaSpider\Contract\Spider;
use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Http\HttpTransport;
use ProvaSpider\Http\StreamHttpTransport;
use ProvaSpider\Parser\CadastroHtmlParser;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

/**
 * Faz o que o navegador faz: abre consultaBa.asp, que inicia a sessão do portal,
 * e envia o formulário para result.asp com o mesmo transporte (mesmos cookies).
 * Sem a sessão, o portal responde 404 nas páginas de aviso, como acontece no
 * navegador quando o formulário fica muito tempo aberto.
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
        $formulario = $this->http->get(self::URL);
        if (!$formulario->sucesso()) {
            throw $this->falhaDeStatus($formulario, self::URL);
        }

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

        if ($this->parser->indicaManutencao($resposta->body)) {
            throw PortalIndisponivelException::emManutencao();
        }

        if (!$resposta->sucesso()) {
            throw $this->falhaDeStatus($resposta, self::URL_RESULTADO);
        }
        
        throw RespostaInesperadaException::estruturaNaoReconhecida(
            sprintf('a página não traz o cadastro. O portal respondeu: "%s"', $this->parser->resumo($resposta->body)),
        );
    }

    private function falhaDeStatus(HttpResponse $resposta, string $url): PortalIndisponivelException|FalhaHttpException
    {
        return in_array($resposta->statusCode, [502, 503, 504], true)
            ? PortalIndisponivelException::emManutencao()
            : FalhaHttpException::comStatus($resposta->statusCode, $url);
    }
}