<?php

class PrazoboletoValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$this->module->active || !$cart->id) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = new Currency($cart->id_currency);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        // 📌 Captura o prazo de pagamento selecionado pelo cliente
        $id_pagamento = (int) Tools::getValue('prazo_pagamento');
        if (!$id_pagamento) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // 🔍 Buscar a descrição do prazo selecionado
        $prazo = Db::getInstance()->getRow('
            SELECT descricao, dias FROM `' . _DB_PREFIX_ . 'meios_pagamento`
            WHERE id_pagamento = ' . (int) $id_pagamento
        );

        if (!$prazo) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $descricaoPrazo = $prazo['descricao'] . ' (' . $prazo['dias'] . ' dias)';

        // 🔍 Verificar se o cliente já tem um prazo salvo e atualizar caso seja a primeira compra
        if (!$customer->pagamento_padrao) {
            Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'customer`
                SET pagamento_padrao = ' . (int) $id_pagamento . '
                WHERE id_customer = ' . (int) $customer->id
            );
        }

        // 📌 Criar o pedido no PrestaShop com o nome do prazo de pagamento
        $this->module->validateOrder(
            (int) $cart->id,
            Configuration::get('PS_OS_BANKWIRE'), // Status do pedido
            $total,
            '' . $descricaoPrazo, // 📌 Exibir o nome do prazo em vez do nome do módulo
            null, // Observação (podemos adicionar mais informações aqui se necessário)
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        // 📌 Redirecionar para a página de confirmação do pedido
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $this->module->currentOrder . '&key=' . $customer->secure_key);
    }
}
