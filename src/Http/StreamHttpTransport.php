<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use RuntimeException;

/**
 * Transporte com as funções nativas do PHP, sem dependências externas.
 * A consulta é um POST simples: não há cookie nem sessão a manter.
 */
final class StreamHttpTransport implements HttpTransport
{
    public function __construct(
        private readonly float $timeoutSegundos = 20,
        private readonly int $maxRedirecionamentos = 5,
    ) {
    }

    public function post(string $url, array $campos): HttpResponse
    {
        $contexto = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                'Accept-Language: pt-BR,pt;q=0.9',
            ]),
            'content' => http_build_query($campos),
            'timeout' => $this->timeoutSegundos,
            'follow_location' => 1,
            // No PHP, max_redirects = N segue no máximo N - 1 redirecionamentos.
            'max_redirects' => $this->maxRedirecionamentos + 1,
            // Devolve o corpo também em respostas 4xx/5xx; o spider decide o que é erro.
            'ignore_errors' => true,
        ]]);

        $erro = null;
        set_error_handler(static function (int $nivel, string $mensagem) use (&$erro): bool {
            $erro = $mensagem;

            return true;
        });

        $inicio = microtime(true);

        try {
            $stream = fopen($url, 'rb', false, $contexto);
            if ($stream === false) {
                $motivo = microtime(true) - $inicio >= $this->timeoutSegundos
                    ? "tempo limite de {$this->timeoutSegundos}s excedido"
                    : ($erro ?? 'falha de conexão');

                throw PortalIndisponivelException::porFalhaDeComunicacao(new RuntimeException($motivo));
            }
            $corpo = stream_get_contents($stream);
            $meta = stream_get_meta_data($stream);
            fclose($stream);
        } finally {
            restore_error_handler();
        }

        if ($meta['timed_out'] || $corpo === false) {
            throw PortalIndisponivelException::porFalhaDeComunicacao(
                new RuntimeException("tempo limite de {$this->timeoutSegundos}s excedido durante a leitura"),
            );
        }

        // Com follow_location, os cabeçalhos de todos os saltos vêm juntos; vale o último status.
        $status = 0;
        foreach ($meta['wrapper_data'] ?? [] as $linha) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $linha, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        // Ainda em 3xx: o limite de redirecionamentos acabou antes de chegar ao destino.
        if ($status >= 300 && $status < 400) {
            throw FalhaHttpException::comStatus($status, $url);
        }

        return new HttpResponse($status, $corpo);
    }
}