<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly ?string $retryAfter = null)
    {
        $messages = [401=>'Chave ausente, inválida ou revogada',403=>'Assinatura ou acesso sem permissão',404=>'Endpoint não encontrado',422=>'Parâmetros inválidos',429=>'Cota atingida: aguarde a renovação',503=>'Serviço temporariamente indisponível'];
        parent::__construct($messages[$status] ?? 'Falha HTTP na API', $status);
    }
}

final class Client
{
    private float $nextRequest = 0.0;
    private array $catalog;
    public function __construct(
        private string $token,
        private int $rpm,
        private string $base = 'https://cpfbuscas.net/api/v1',
        private ?Closure $transport = null,
    ) {
        if (trim($token)==='' || preg_match('/[\r\n]/', $token)) throw new InvalidArgumentException('Defina CPFBUSCAS_TOKEN com uma chave válida.');
        if ($rpm < 1) throw new InvalidArgumentException('RPM deve ser positivo.');
        if (!in_array($base,['https://cpfbuscas.net/api/v1'],true)) throw new InvalidArgumentException('Use uma URL base oficial HTTPS.');
        $this->catalog=json_decode(file_get_contents(__DIR__.'/../catalog.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public function me(): array { return $this->request('GET','/me',[]); }
    public function query(string $module, array $params): array
    {
        $spec=$this->catalog[$module] ?? throw new InvalidArgumentException('Módulo desconhecido. Consulte catalog.json.');
        $allowed=array_column($spec['params'],'name');
        foreach ($params as $key=>$value) {
            if (!in_array($key,$allowed,true) || !is_scalar($value)) throw new InvalidArgumentException('Parâmetro desconhecido ou não escalar.');
        }
        foreach ($spec['params'] as $param) {
            if ($param['required'] && (!isset($params[$param['name']]) || trim((string)$params[$param['name']])==='')) throw new InvalidArgumentException('Parâmetro obrigatório: '.$param['name']);
        }
        // A documentação usa query string inclusive no único POST do catálogo.
        return $this->request($spec['method'],'/query/'.$module,$params);
    }
    private function request(string $method,string $path,array $params): array
    {
        $delay=$this->nextRequest-microtime(true);
        if ($delay>0) usleep((int)ceil($delay*1000000));
        $this->nextRequest=microtime(true)+60/$this->rpm+0.01;
        $url=$this->base.$path.($params ? '?'.http_build_query($params,'','&',PHP_QUERY_RFC3986) : '');
        $headers=['Authorization: Bearer '.$this->token,'Accept: application/json'];
        $retryAfter=null;
        if ($this->transport) {
            [$status,$body,$retryAfter]=($this->transport)($method,$url,$headers);
        } else {
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HEADERFUNCTION=>static function($ch,string $line) use (&$retryAfter): int {
                if (stripos($line,'Retry-After:')===0) $retryAfter=trim(substr($line,12));
                return strlen($line);
            }]);
            $body=curl_exec($ch);
            $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            if ($body===false) throw new RuntimeException('Falha de rede; não houve repetição automática.');
        }
        if ($status<200 || $status>=300) throw new ApiException($status,$retryAfter);
        try { $data=json_decode($body,true,512,JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new RuntimeException('Resposta JSON inválida.'); }
        if (!is_array($data)) throw new RuntimeException('Esperado objeto ou array JSON.');
        return $data;
    }
}
