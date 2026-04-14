<tr>
    <td id="getvaluesloader">
        {if $test_connection_result}
            <span style="margin-left:10px; font-weight:bold; color:{if $test_connection_result.result == 'Success'}#009900{else}#990000{/if};">
                Connection Test: {$test_connection_result.result}{if $test_connection_result.error}: {$test_connection_result.error|escape}{/if}
            </span>
        {/if}
    </td>
    <td>
        <div style="background:#fff; border:1px solid #ddd; padding:15px;">
            <h4 style="margin-top:0;">WebNIC SSL Product Mapping</h4>
            <p class="fs11">Map this HostBill product to one WebNIC SSL product key.</p>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-weight:bold;">WebNIC Product Key</label>
                <input type="text" name="options[product_key]" value="{$default.product_key|escape}" class="inp" style="width:320px;" />
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-weight:bold;">Reference Catalog</label>
                <select style="min-width:320px;" onchange="if(this.value){ $('input[name=\'options[product_key]\']').val(this.value); }">
                    <option value="">Select catalog product</option>
                    {foreach from=$catalog item=item}
                        <option value="{$item.productKey|default:$item.key|escape}">
                            {$item.productKey|default:$item.key|escape} - {$item.productName|default:$item.name|default:'Unnamed Product'|escape}
                        </option>
                    {/foreach}
                </select>
            </div>
            <div class="alert alert-info" style="margin-bottom:0;">
                Recommended: set the exact `product_key` returned by WebNIC catalog to ensure correct SAN and wildcard behaviour.
            </div>
        </div>
    </td>
</tr>