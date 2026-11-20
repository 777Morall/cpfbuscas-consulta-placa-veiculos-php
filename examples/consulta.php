<?php
declare(strict_types=1);
require __DIR__.'/../src/Client.php';
require __DIR__.'/../src/Modulo.php';
try {
    $args=array_slice($argv,1);
    if ($args===['--demo']) {
        [$module,$params]=prepararConsulta(['placa','ABC-0A00']);
        echo json_encode(['simulacao'=>true,'metodo'=>'GET','caminho'=>'/api/v1/query/'.$module,'parametros'=>$params],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if (!$args) throw new InvalidArgumentException('Uso: php examples/consulta.php placa PLACA | busca MARCA UF');
    [$module,$params]=prepararConsulta($args);
    // 20 req/min é o menor limite dos planos de API documentados.
    $api=new Client(getenv('CPFBUSCAS_TOKEN') ?: '',20,getenv('CPFBUSCAS_BASE_URL') ?: 'https://cpfbuscas.net/api/v1');
    echo json_encode($api->query($module,$params),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    if ($e instanceof ApiException && $e->retryAfter!==null) fwrite(STDERR,'Retry-After: '.$e->retryAfter.PHP_EOL);
    exit(1);
}
