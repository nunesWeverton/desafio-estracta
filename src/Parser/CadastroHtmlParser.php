<?php

declare(strict_types=1);

namespace ProvaSpider\Parser;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ProvaSpider\Support\Normalizador;
use ProvaSpider\ValueObject\CadastroContribuinte;

/**
 * Lê a página result.asp da SEFAZ-BA e monta o CadastroContribuinte.
 *
 * O portal publica os dados em tabelas, com três tipos de célula:
 *  - rótulo e valor juntos:  <td><b>CNPJ:</b>&nbsp;00.000.000/0000-00</td>
 *  - título de seção:        <th><b>Informações Complementares</b></th> ou <td bgcolor=...><b>Endereço</b></td>
 *  - rótulo com item abaixo: <td><b>Atividade Econômica Principal:</b></td>, valor na linha seguinte
 *
 * Os valores são guardados por seção porque o portal repete rótulos (Endereço,
 * Número, CEP, Município) nos blocos do estabelecimento, da correspondência e do contador.
 *
 * O parser não decide qual exceção lançar: devolve null quando a página não traz
 * um cadastro e oferece indicadores para o spider classificar a resposta.
 */
final class CadastroHtmlParser
{
    private const SEM_CADASTRO = '/n[aã]o (foi|foram) (encontrad|localizad)|nenhum (registro|contribuinte|cadastro)'
        . '|n[aã]o (existe|h[aá]) (registro|contribuinte|cadastro)|n[aã]o (est[aá] )?cadastrad|inexistente/u';

    private const MANUTENCAO = '/manuten[cç][aã]o|fora do ar|temporariamente indispon/u';

    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];

    /** Cadastro da página, ou null se ela não trouxer CNPJ e razão social na identificação. */
    public function parse(string $html): ?CadastroContribuinte
    {
        [$valores, $itens] = $this->extrair(new DOMXPath($this->carregar($html)));

        $identificacao = $valores['dadosdaempresa/identificacao'] ?? [];
        $complementares = $valores['informacoescomplementares'] ?? [];

        if (!isset($identificacao['cnpj'], $identificacao['razaosocial'])) {
            return null;
        }

        return new CadastroContribuinte(
            // Publicada como "026.641.885 NO": o número vem antes do código de condição.
            inscricaoEstadual: Normalizador::somenteDigitos(explode(' ', $identificacao['inscricaoestadual'] ?? '')[0]),
            cnpj: Normalizador::somenteDigitos($identificacao['cnpj']),
            razaoSocial: $identificacao['razaosocial'],
            nomeFantasia: $identificacao['nomefantasia'] ?? null,
            situacaoCadastral: $complementares['situacaocadastralvigente'] ?? null,
            endereco: $this->endereco($valores['dadosdaempresa/endereco'] ?? []),
            atividadeEconomica: $itens['informacoescomplementares']['atividadeeconomicaprincipal'][0] ?? null,
            regimeApuracao: $complementares['formadepagamento'] ?? null,
        );
    }

    /** A página informa que o CNPJ não tem cadastro. */
    public function indicaSemCadastro(string $html): bool
    {
        return preg_match(self::SEM_CADASTRO, $this->textoDaPagina($html)) === 1;
    }

    /** A página informa que o portal está em manutenção ou fora do ar. */
    public function indicaManutencao(string $html): bool
    {
        return preg_match(self::MANUTENCAO, $this->textoDaPagina($html)) === 1;
    }

    private function textoDaPagina(string $html): string
    {
        return mb_strtolower((string) Normalizador::texto($this->carregar($html)->textContent));
    }

    private function carregar(string $html): DOMDocument
    {
        // A página declara UTF-8; se os bytes não forem UTF-8 válido, trata como Windows-1252.
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        }

        // Entidades numéricas evitam que o libxml reinterprete os acentos pelo <meta charset>.
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html !== '' ? $html : '<html></html>');
        libxml_clear_errors();

        return $dom;
    }

    /**
     * @return array{0: array<string, array<string, string>>, 1: array<string, array<string, list<string>>>}
     */
    private function extrair(DOMXPath $xpath): array
    {
        $valores = [];
        $itens = [];
        $grupo = '';
        $subsecao = '';
        $rotuloSemValor = null;

        // Só células sem tabelas dentro, na ordem em que aparecem.
        foreach ($xpath->query('//td[not(.//table)] | //th[not(.//table)]') as $celula) {
            $texto = (string) Normalizador::texto($celula->textContent);
            $negrito = $xpath->query('.//b', $celula)->item(0);
            $rotulo = $negrito !== null ? (string) Normalizador::texto($negrito->textContent) : null;
            $secao = $subsecao === '' ? $grupo : "{$grupo}/{$subsecao}";

            if ($texto === '') {
                continue;
            }

            if ($rotulo !== null && str_ends_with($rotulo, ':') && str_starts_with($texto, $rotulo)) {
                $chave = $this->chave($rotulo);
                $valor = Normalizador::texto(substr($texto, strlen($rotulo)));
                if ($valor !== null) {
                    $valores[$secao][$chave] ??= $valor;
                }
                $rotuloSemValor = $valor === null ? $chave : null;
            } elseif ($rotulo === $texto && $celula->nodeName === 'th') {
                [$grupo, $subsecao, $rotuloSemValor] = [$this->chave($rotulo), '', null];
            } elseif ($rotulo === $texto && $this->destacada($celula)) {
                [$subsecao, $rotuloSemValor] = [$this->chave($rotulo), null];
            } elseif ($rotulo === $texto) {
                $rotuloSemValor = $this->chave($rotulo);
            } elseif ($rotuloSemValor !== null) {
                $itens[$secao][$rotuloSemValor][] = $texto;
            }
        }

        return [$valores, $itens];
    }

    /** Subtítulos são células (ou linhas) com cor de fundo, ex.: "Identificação", "Endereço". */
    private function destacada(DOMElement $celula): bool
    {
        $linha = $celula->parentNode;

        return $celula->hasAttribute('bgcolor') || ($linha instanceof DOMElement && $linha->hasAttribute('bgcolor'));
    }

    /** @param array<string, string> $campos */
    private function endereco(array $campos): ?string
    {
        $logradouro = implode(', ', array_filter([
            $campos['logradouro'] ?? null,
            $campos['numero'] ?? null,
            $campos['complemento'] ?? null,
        ]));
        $cidade = implode('/', array_filter([$campos['municipio'] ?? null, $campos['uf'] ?? null]));
        $cep = isset($campos['cep']) ? 'CEP ' . preg_replace('/^(\d{5})-?(\d{3})$/', '$1-$2', $campos['cep']) : null;

        $partes = array_filter([$logradouro, $campos['bairrodistrito'] ?? null, $cidade, $cep]);

        return $partes === [] ? null : implode(' - ', $partes);
    }

    /** "Situação Cadastral Vigente:" → "situacaocadastralvigente" */
    private function chave(string $texto): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($texto), self::ACENTOS));
    }
}