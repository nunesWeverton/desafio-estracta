<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use RuntimeException;

/**
 * Transporte com as funções nativas do PHP, sem dependências externas.
 *
 * Guarda os cookies recebidos e os reenvia nas requisições seguintes, inclusive
 * nos redirecionamentos, que por isso são seguidos aqui e não pelo PHP.
 */
final class StreamHttpTransport implements HttpTransport
{
    /** @var array<string, string> nome => valor */
    private array $cookies = [];

    public function __construct(
        private readonly float $timeoutSegundos = 20,
        private readonly int $maxRedirecionamentos = 5,
    ) {
    }

    public function get(string $url): HttpResponse
    {
        return $this->enviar('GET', $url, null);
    }

    public function post(string $url, array $campos): HttpResponse
    {
        return $this->enviar('POST', $url, http_build_query($campos));
    }

    private function enviar(string $metodo, string $url, ?string $corpo): HttpResponse
    {
        for ($saltos = 0; ; $saltos++) {
            [$status, $location, $conteudo] = $this->executar($metodo, $url, $corpo);

            if ($status < 300 || $status >= 400 || $location === null) {
                return new HttpResponse($status, $conteudo);
            }

            if ($saltos >= $this->maxRedirecionamentos) {
                throw FalhaHttpException::comStatus($status, $url);
            }

            // Como o navegador: segue o Location com GET, sem reenviar o formulário.
            [$metodo, $url, $corpo] = ['GET', $this->resolver($url, $location), null];
        }
    }

    /**
     * @return array{0: int, 1: ?string, 2: string} status, Location e corpo
     */
    private function executar(string $metodo, string $url, ?string $corpo): array
    {
        $cabecalhos = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
            'Accept-Language: pt-BR,pt;q=0.9',
        ];
        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        if ($this->cookies !== []) {
            $cabecalhos[] = 'Cookie: ' . implode('; ', array_map(
                static fn (string $nome, string $valor): string => "{$nome}={$valor}",
                array_keys($this->cookies),
                $this->cookies,
            ));
        }

        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $cabecalhos),
            'content' => $corpo ?? '',
            'timeout' => $this->timeoutSegundos,
            'follow_location' => 0,
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
            $conteudo = stream_get_contents($stream);
            $meta = stream_get_meta_data($stream);
            fclose($stream);
        } finally {
            restore_error_handler();
        }

        if ($meta['timed_out'] || $conteudo === false) {
            throw PortalIndisponivelException::porFalhaDeComunicacao(
                new RuntimeException("tempo limite de {$this->timeoutSegundos}s excedido durante a leitura"),
            );
        }

        $status = 0;
        $location = null;
        foreach ($meta['wrapper_data'] ?? [] as $linha) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $linha, $m) === 1) {
                $status = (int) $m[1];
            } elseif (preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $linha, $m) === 1) {
                $this->cookies[$m[1]] = $m[2];
            } elseif (preg_match('/^Location:\s*(.+)$/i', $linha, $m) === 1) {
                $location = trim($m[1]);
            }
        }

        return [$status, $location, $conteudo];
    }

    /** Resolve um Location relativo ("aviso.asp", "/scripts/aviso.asp") a partir da URL atual. */
    private function resolver(string $url, string $location): string
    {
        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }

        $origem = (string) preg_replace('~^(https?://[^/]+).*$~i', '$1', $url);
        if (str_starts_with($location, '/')) {
            return $origem . $location;
        }

        $caminho = (string) parse_url($url, PHP_URL_PATH);

        return $origem . substr($caminho, 0, (int) strrpos($caminho, '/') + 1) . $location;
    }
}