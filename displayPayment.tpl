{if isset($prazos) && $prazos}
    <div class="payment-options">
        <h3>{l s='Escolha o prazo de pagamento' d='Modules.PrazoBoleto.Shop'}</h3>
        <form id="prazo-boleto-form" method="post" action="{$link->getModuleLink('prazoboleto', 'validation', [], true)}">
            {foreach from=$prazos item=prazo}
                <div class="form-group">
                    <input type="radio" name="prazo_pagamento" value="{$prazo.id_pagamento}" {if $prazo@first}checked{/if}>
                    <label>{$prazo.descricao} ({$prazo.dias} dias para pagamento)</label>
                </div>
            {/foreach}
            <button type="submit" class="btn btn-primary">{l s='Confirmar Prazo' d='Modules.PrazoBoleto.Shop'}</button>
        </form>
    </div>
{else}
    <p>{l s='Nenhum prazo de pagamento disponível no momento.' d='Modules.PrazoBoleto.Shop'}</p>
{/if}
