<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PrazoBoleto extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'prazoboleto';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Seu Nome';
        $this->controllers = ['payment', 'validation'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Pagamento por Boleto com Prazo', [], 'Modules.PrazoBoleto.Admin');
        $this->description = $this->trans('Permite configurar prazos de pagamento para clientes.', [], 'Modules.PrazoBoleto.Admin');
        $this->confirmUninstall = $this->trans('Tem certeza de que deseja desinstalar este m車dulo?', [], 'Modules.PrazoBoleto.Admin');
    }

public function install()
{
    return parent::install()
        && $this->registerHook('paymentOptions')
        && $this->registerHook('displayPaymentReturn')
        && $this->installDb()
        && $this->createAdminTab()
        && $this->registerHook('displayAdminOrderMain')
        && $this->registerHook('displayAdminOrderTabOrder')
         && $this->registerHook('actionAdminOrdersListingFieldsModifier'); // ✅ Novo Hook
}


private function installDb()
{
    return Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'meios_pagamento` (
            `id_pagamento` INT(11) AUTO_INCREMENT,
            `descricao` VARCHAR(255) NOT NULL,
            `dias` INT(11) NOT NULL,
            PRIMARY KEY (`id_pagamento`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;')
    && Db::getInstance()->execute('
        ALTER TABLE `' . _DB_PREFIX_ . 'customer` ADD COLUMN `pagamento_padrao` INT(11) DEFAULT NULL;
    ');
}


public function uninstall()
{
    return parent::uninstall() && $this->uninstallDb();
}

private function uninstallDb()
{
    return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'meios_pagamento`')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'customer` DROP COLUMN `pagamento_padrao`');
}

private function createAdminTab()
{
    $id_parent = (int) Tab::getIdFromClassName('AdminParentModules');

    $tab = new Tab();
    $tab->active = 1;
    $tab->class_name = 'AdminPrazoBoleto';
    $tab->id_parent = $id_parent ?: 0;
    $tab->module = $this->name;

    foreach (Language::getLanguages() as $lang) {
        $tab->name[$lang['id_lang']] = 'Prazos de Pagamento';
    }

    return $tab->add();
}

private function removeAdminTab()
{
    $id_tab = (int) Tab::getIdFromClassName('AdminPrazoBoleto');
    if ($id_tab) {
        $tab = new Tab($id_tab);
        return $tab->delete();
    }
    return true;
}


 public function getContent()
{
    Tools::redirectAdmin($this->context->link->getAdminLink('AdminPrazoBoleto'));
}

public function hookPaymentOptions($params)
{
    if (!$this->active) {
        return [];
    }

    $customer = new Customer($this->context->customer->id);
    $id_pagamento = (int) $customer->pagamento_padrao; // 🔍 Verifica se já tem um prazo salvo

    if ($id_pagamento > 0) {
        // ✅ Cliente já tem um prazo cadastrado → Mostrar apenas esse prazo
        $prazos = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'meios_pagamento`
            WHERE id_pagamento = ' . (int) $id_pagamento
        );
    } else {
        // ✅ Cliente novo → Mostrar todos os prazos disponíveis
        $prazos = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'meios_pagamento`
        ');
    }

    // 🚨 Se não houver prazos, não exibir o método de pagamento
    if (empty($prazos)) {
        return [];
    }

    // 🔹 Passar os prazos para o template do PrestaShop
    $this->context->smarty->assign([
        'prazos' => $prazos,
        'link' => $this->context->link
    ]);

    // 📌 Criar a opção de pagamento
    $paymentOption = new PaymentOption();
    $paymentOption->setModuleName($this->name);
    $paymentOption->setCallToActionText($this->l('Pagar com Boleto e Prazo'));
    $paymentOption->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));
    $paymentOption->setAdditionalInformation($this->context->smarty->fetch('module:prazoboleto/views/templates/hook/displayPayment.tpl'));

    return [$paymentOption];
}


public function hookAdditionalCustomerFormFields($params)
{
    $fields = array();

    $fields['pagamento_padrao'] = (new FormField())
        ->setName('pagamento_padrao')
        ->setType('select') // Ou 'radio', 'text', etc., conforme sua necessidade
        ->setLabel($this->l('Prazo de Pagamento Padrão'))
        ->setRequired(true);

    // Adicione as opções de prazo de pagamento
    $options = Db::getInstance()->executeS('SELECT id_pagamento, descricao FROM ' . _DB_PREFIX_ . 'meios_pagamento');
    foreach ($options as $option) {
        $fields['pagamento_padrao']->addAvailableValue($option['id_pagamento'], $option['descricao']);
    }

    return $fields;
}



public function hookActionOrderStatusPostUpdate($params)
{
    if (!isset($params['id_order'])) {
        return;
    }

    $order = new Order((int) $params['id_order']);

    if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
        return;
    }

    // 🔍 Buscar o prazo de pagamento salvo no cliente
    $id_pagamento = Db::getInstance()->getValue('
        SELECT pagamento_padrao FROM `' . _DB_PREFIX_ . 'customer`
        WHERE id_customer = ' . (int) $order->id_customer
    );

    if (!$id_pagamento) {
        return;
    }

    // 🔍 Buscar a descrição do prazo no banco
    $prazo = Db::getInstance()->getRow('
        SELECT descricao, dias FROM `' . _DB_PREFIX_ . 'meios_pagamento`
        WHERE id_pagamento = ' . (int) $id_pagamento
    );

    $diasTexto = isset($prazo['dias']) ? $prazo['dias'] . ' dias' : 'Sem prazo definido';
    $mensagem = 'Solicitado boleto ' . $diasTexto . '.';

    // 📌 Criar a mensagem associada ao pedido
    $message = new Message();
    $message->message = pSQL($mensagem);
    $message->id_order = (int) $order->id;
    $message->private = true; // Visível apenas no Backoffice
    $message->add();
}

public function hookDisplayAdminOrderMain($params)
{
    $id_order = (int) $params['id_order'];
    $order = new Order($id_order);

    if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
        return;
    }

    // 🔍 Buscar o prazo de pagamento salvo no cliente
    $id_pagamento = Db::getInstance()->getValue('
        SELECT pagamento_padrao FROM `' . _DB_PREFIX_ . 'customer`
        WHERE id_customer = ' . (int) $order->id_customer
    );

    if (!$id_pagamento) {
        return;
    }

    // 🔍 Buscar a descrição do prazo no banco
    $prazo = Db::getInstance()->getRow('
        SELECT descricao, dias FROM `' . _DB_PREFIX_ . 'meios_pagamento`
        WHERE id_pagamento = ' . (int) $id_pagamento
    );

    $diasTexto = isset($prazo['dias']) ? $prazo['dias'] . ' dias' : 'Não definido';

    // 📌 Criar o bloco HTML para exibição dentro do pedido
    return '<div class="card">
                <h4 class="card-header">Prazo de Pagamento</h4>
                <div class="card-body">
                    <p><strong>Prazo Selecionado:</strong> ' . $diasTexto . '</p>
                </div>
            </div>';
}

public function hookDisplayAdminOrderTabOrder($params)
{
    $id_order = (int) $params['id_order'];
    $order = new Order($id_order);

    if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
        return;
    }

    // 🔍 Buscar o prazo de pagamento salvo no cliente
    $id_pagamento = Db::getInstance()->getValue('
        SELECT pagamento_padrao FROM `' . _DB_PREFIX_ . 'customer`
        WHERE id_customer = ' . (int) $order->id_customer
    );

    if (!$id_pagamento) {
        return;
    }

    // 🔍 Buscar a descrição do prazo no banco
    $prazo = Db::getInstance()->getRow('
        SELECT descricao, dias FROM `' . _DB_PREFIX_ . 'meios_pagamento`
        WHERE id_pagamento = ' . (int) $id_pagamento
    );

    $diasTexto = isset($prazo['dias']) ? $prazo['dias'] . ' dias' : 'Não definido';

    // 📌 Criar o bloco HTML para exibição dentro do pedido
    return '<div class="alert alert-info">
                <h4><strong>Prazo de Pagamento:</strong> ' . $diasTexto . '</h4>
            </div>';
}
public function hookActionAdminOrdersListingFieldsModifier($params)
{
    // Adicionar a nova coluna ao grid de pedidos
    $params['fields']['prazo_pagamento'] = [
        'title' => $this->l('Prazo de Pagamento'),
        'align' => 'text-center',
        'orderby' => false,
        'search' => false,
    ];

    // Buscar os pedidos que pertencem a este módulo
    $orders = &$params['sql_select'];

    $orders .= ', (
        SELECT mp.descricao 
        FROM `' . _DB_PREFIX_ . 'customer` c
        LEFT JOIN `' . _DB_PREFIX_ . 'meios_pagamento` mp ON c.pagamento_padrao = mp.id_pagamento
        WHERE c.id_customer = o.id_customer
        LIMIT 1
    ) AS prazo_pagamento';
}


    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['order'];
        $totalToPay = $this->context->getCurrentLocale()->formatPrice(
            $order->getOrdersTotalPaid(),
            (new Currency($order->id_currency))->iso_code
        );

        $this->smarty->assign([
            'total_to_pay' => $totalToPay,
            'shop_name' => $this->context->shop->name,
            'status' => 'ok',
            'id_order' => $order->id,
            'reference' => $order->reference,
        ]);

        return $this->fetch('module:prazoboleto/views/templates/hook/payment_return.tpl');
    }
    
}
