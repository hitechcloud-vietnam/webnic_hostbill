<ul class="accor white cert">
    <li>
        <a href="#">WebNIC Certificate Details</a>
        <div class="sor">
            <div class="row"><div class="col-md-2"><strong>Common name</strong></div><div class="col-md-8">{$cert.cn|escape}</div></div>
            <div class="row"><div class="col-md-2"><strong>Order ID</strong></div><div class="col-md-8">{$cert.order_id|escape}</div></div>
            <div class="row"><div class="col-md-2"><strong>Status</strong></div><div class="col-md-8">{$cert.status|escape}</div></div>
            <div class="row"><div class="col-md-2"><strong>DCV status</strong></div><div class="col-md-8">{$cert.dcv_status|default:'N/A'|escape}</div></div>
            <div class="row"><div class="col-md-2"><strong>Actions</strong></div><div class="col-md-8">
                <button id="show-csr" class="btn btn-default btn-sm">Show CSR</button>
                {if $dcv.type == 'email'}<a class="btn btn-default btn-sm" href="?cmd=accounts&action=edit&id={$details.id}&resetdcv">Resend Email</a>{/if}
            </div></div>

            {if $cert.san}
            <div class="row"><div class="col-md-2"><strong>SAN</strong></div><div class="col-md-8">{foreach from=$cert.san item=san}<div>{$san|escape}</div>{/foreach}</div></div>
            {/if}

            <div class="row"><div class="col-md-2"><strong>DCV method</strong></div><div class="col-md-8">{$dcv.type|default:'N/A'|upper}</div></div>

            {if $dcv.type == 'dns'}
                <div class="row"><div class="col-md-2"><strong>DNS validation</strong></div><div class="col-md-8">{foreach from=$dcv.details item=item}<code style="display:block; margin-bottom:5px;">{$item.name|escape} 3600 IN {$item.type|escape} {$item.content|escape}</code>{/foreach}</div></div>
            {elseif $dcv.type == 'http' || $dcv.type == 'https'}
                <div class="row"><div class="col-md-2"><strong>HTTP validation</strong></div><div class="col-md-8">{foreach from=$dcv.details item=item}<div style="margin-bottom:10px;"><strong>URL</strong><pre>{$item.url|escape}</pre><strong>Content</strong><pre>{$item.data|escape}</pre></div>{/foreach}</div></div>
            {elseif $dcv.type == 'email'}
                <div class="row"><div class="col-md-2"><strong>Email validation</strong></div><div class="col-md-8"><table class="table table-condensed">{foreach from=$dcv.details item=item key=name}<tr><td>{$name|escape}</td><td>{$item.name|escape}</td></tr>{/foreach}</table></div></div>
            {/if}
        </div>
    </li>
</ul>

{if $cert.status == 'Awaiting Validation' || $cert.status == 'Processing'}
<ul class="accor white cert">
    <li>
        <a href="#">Change DCV Method</a>
        <div class="sor">
            <form action="" method="post" style="margin:0;">
                <div class="form-group">
                    <label>DCV Method</label>
                    <select id="dcv-method" class="form-control" name="dcv">
                        {if $dcv.type != 'email'}<option value="email">Email</option>{/if}
                        {if $dcv.type != 'http'}<option value="http">HTTP</option>{/if}
                        {if $dcv.type != 'dns'}<option value="dns">DNS</option>{/if}
                    </select>
                </div>
                <div id="dcv-method-email" class="form-group" style="display:none;">
                    <label>Approver Email</label>
                    <select class="form-control" name="dcv_email">
                        {foreach from=$approveremails item=email}
                            <option value="{$email|escape}">{$email|escape}</option>
                        {/foreach}
                    </select>
                </div>
                {securitytoken}
                <button type="submit" name="edo" value="changedcv" class="btn btn-primary">Change</button>
            </form>
        </div>
    </li>
</ul>
{/if}

<div id="csr-dialog" hidden>
    <strong>Certificate signing request</strong>
    <pre style="margin:auto;">{$cert.csr|escape}</pre>
</div>

{literal}
<style>
    .cert .sor .row { margin-bottom: 10px; }
</style>
<script>
$(function () {
    $('#show-csr').on('click', function () {
        bootbox.dialog({ message: $('#csr-dialog').html() });
        return false;
    });
    $('#dcv-method').on('change', function () {
        if ($(this).val() === 'email') {
            $('#dcv-method-email').slideDown();
        } else {
            $('#dcv-method-email').slideUp();
        }
    }).trigger('change');
});
</script>
{/literal}