<ul class="accor white">
    <li>
        <a href="#">WebNIC DNS Application Summary</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-4"><strong>Zone limit</strong></div>
                <div class="col-md-8">{$app_summary.zone_limit|default:'N/A'}</div>
            </div>
            <div class="row">
                <div class="col-md-4"><strong>Supported records</strong></div>
                <div class="col-md-8">{foreach from=$app_summary.supported_records item=record}<span class="label label-info" style="margin-right:4px;">{$record|escape}</span>{/foreach}</div>
            </div>
            <div class="row">
                <div class="col-md-4"><strong>Default nameservers</strong></div>
                <div class="col-md-8">
                    {if $app_summary.nameservers}
                        {foreach from=$app_summary.nameservers item=ns}<div>{$ns|escape}</div>{/foreach}
                    {else}
                        <span class="muted">No nameservers returned.</span>
                    {/if}
                </div>
            </div>
        </div>
    </li>
</ul>