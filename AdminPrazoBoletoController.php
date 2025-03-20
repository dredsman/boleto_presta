<?php

require_once _PS_MODULE_DIR_ . 'prazoboleto/classes/MeioPagamento.php';

class AdminPrazoBoletoController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->table = 'meios_pagamento';
        $this->className = 'MeioPagamento';
        $this->identifier = 'id_pagamento';
        $this->bootstrap = true;

        $this->fields_list = [
            'id_pagamento' => ['title' => 'ID', 'align' => 'center', 'class' => 'fixed-width-xs'],
            'descricao' => ['title' => 'Descrição', 'align' => 'left'],
            'dias' => ['title' => 'Dias para pagamento', 'align' => 'center'],
        ];

        $this->_defaultOrderBy = 'id_pagamento';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
        $this->addRowAction('delete');
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => ['title' => 'Gerenciar Prazos de Pagamento'],
            'input' => [
                ['type' => 'text', 'label' => 'Descrição', 'name' => 'descricao', 'required' => true],
                ['type' => 'text', 'label' => 'Dias para pagamento', 'name' => 'dias', 'required' => true],
            ],
            'submit' => ['title' => 'Salvar'],
        ];
        return parent::renderForm();
    }
}
