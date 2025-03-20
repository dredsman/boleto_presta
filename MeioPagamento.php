<?php

class MeioPagamento extends ObjectModel
{
    public $id_pagamento;
    public $descricao;
    public $dias;

    public static $definition = [
        'table' => 'meios_pagamento',
        'primary' => 'id_pagamento',
        'fields' => [
            'descricao' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'dias' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
        ],
    ];
}
