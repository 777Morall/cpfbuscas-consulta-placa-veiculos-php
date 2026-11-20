<?php
declare(strict_types=1);
require __DIR__.'/../src/Client.php';
require __DIR__.'/../src/Modulo.php';
$count=0;
function check(bool $condition): void { global $count; if (!$condition) throw new RuntimeException('Teste falhou.'); $count++; }
$cases=json_decode(file_get_contents(__DIR__.'/cases.json'),true,512,JSON_THROW_ON_ERROR);
foreach ($cases['valid'] as $args) {
    [$module,$params]=prepararConsulta($args);
    $calls=[];
    $client=new Client('fake-token',20,'https://cpfbuscas.com/api/v1',static function($method,$url,$headers) use (&$calls) { $calls[]=[$method,$url,$headers]; return [200,'{"fixture":true}',null]; });
    check($client->query($module,$params)===['fixture'=>true]);
    check(count($calls)===1 && $calls[0][0]==='GET');
    $parts=parse_url($calls[0][1]); parse_str($parts['query'] ?? '',$actual);
    check($parts['path']==='/api/v1/query/'.$module && $actual===$params);
    check(in_array('Authorization: Bearer fake-token',$calls[0][2],true));
}
foreach ($cases['invalid'] as $args) {
    try { prepararConsulta($args); check(false); } catch (InvalidArgumentException) { check(true); }
}
foreach ([401,403,422,429,503] as $status) {
    $calls=0;
    $client=new Client('fake-token',20,'https://cpfbuscas.com/api/v1',static function() use (&$calls,$status) { $calls++; return [$status,'{}','60']; });
    try { $client->me(); check(false); } catch (ApiException $e) { check($e->status===$status && $calls===1 && $e->retryAfter==='60'); }
}
echo "$count verificações passaram; nenhuma chamada real à API.\n";
