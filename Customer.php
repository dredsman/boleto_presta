<?php

class Customer extends CustomerCore
{
    public $pagamento_padrao;

    public static $definition = array(
        'table' => 'customer',
        'primary' => 'id_customer',
        'fields' => array(
            // Outras definições de campos
            'pagamento_padrao' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
        ),
    );
}
