<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;

/**
 * Comunicação HTTP separada do spider, para que os testes usem respostas locais.
 *
 * As requisições feitas pela mesma instância compartilham os cookies recebidos,
 * como as abas de um navegador: é assim que a sessão do portal é mantida.
 */
interface HttpTransport
{
    /**
     * @throws PortalIndisponivelException quando não há resposta (rede, DNS, tempo limite)
     * @throws FalhaHttpException quando o limite de redirecionamentos é excedido
     */
    public function get(string $url): HttpResponse;

    /**
     * Envia os campos como formulário (application/x-www-form-urlencoded) e
     * devolve a resposta final, qualquer que seja o status.
     *
     * @param array<string, string> $campos
     *
     * @throws PortalIndisponivelException quando não há resposta (rede, DNS, tempo limite)
     * @throws FalhaHttpException quando o limite de redirecionamentos é excedido
     */
    public function post(string $url, array $campos): HttpResponse;
}