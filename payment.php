<?php

class PrazoBoletoPaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        if (!$this->module->active || !$cart->id) {
            Tools::redirect('index.php?controller=order');
            return;
        }

        $this->setTemplate('module:prazoboleto/views/templates/front/payment_execution.tpl');
    }
}
