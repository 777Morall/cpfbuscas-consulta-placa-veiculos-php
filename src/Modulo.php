<?php
declare(strict_types=1);

/** Monta parâmetros; não realiza chamadas de rede. */
function prepararConsulta(array $args): array
{
    if (($args[0] ?? '')==='placa') {
        $placa=strtoupper(str_replace(['-',' '],'',$args[1] ?? ''));
        if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/D',$placa)) throw new InvalidArgumentException('Use placa brasileira no formato antigo ou Mercosul.');
        return ['placa',['placa'=>$placa]];
    }
    if (($args[0] ?? '')!=='busca') throw new InvalidArgumentException('Escolha placa ou busca.');
    $marca=trim($args[1] ?? ''); $uf=strtoupper($args[2] ?? '');
    if ($marca==='') throw new InvalidArgumentException('Informe marca.');
    if (!in_array($uf,explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'),true)) throw new InvalidArgumentException('UF inválida.');
    return ['veiculos_busca',['marca'=>$marca,'uf'=>$uf,'limit'=>'5']];
}
