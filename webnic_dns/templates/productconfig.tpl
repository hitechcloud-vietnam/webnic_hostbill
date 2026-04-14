<tr>
    <td></td>
    <td>
        <div style="background:#fff; border:1px solid #ddd; padding:15px;">
            <div class="row">
                <div class="col-md-6">
                    <h4 style="margin-top:0;">WebNIC DNS Product Settings</h4>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-weight:bold;">Default DNS Template</label>
                        <input type="text" name="options[dns_template]" value="{$default.dns_template|escape}" class="inp" style="width:260px;" />
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-weight:bold;">Maximum domains</label>
                        <input type="text" name="options[maxdomain]" value="{$default.maxdomain|escape}" class="inp" style="width:120px;" />
                    </div>
                    <div style="margin-bottom:10px;">
                        <label><input type="checkbox" name="options[hide_billing]" value="1" {if $default.hide_billing == '1'}checked{/if} /> Hide billing block in client area</label>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label><input type="checkbox" name="options[hide_zone_management]" value="1" {if $default.hide_zone_management == '1'}checked{/if} /> Prevent manual zone creation</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4 style="margin-top:0;">Default Nameservers</h4>
                    <input type="text" name="options[ns1]" value="{$default.ns1|escape}" class="inp" style="width:260px; margin-bottom:8px;" placeholder="ns1.example.com" /><br />
                    <input type="text" name="options[ns2]" value="{$default.ns2|escape}" class="inp" style="width:260px; margin-bottom:8px;" placeholder="ns2.example.com" /><br />
                    <input type="text" name="options[ns3]" value="{$default.ns3|escape}" class="inp" style="width:260px; margin-bottom:8px;" placeholder="ns3.example.com" /><br />
                    <input type="text" name="options[ns4]" value="{$default.ns4|escape}" class="inp" style="width:260px;" placeholder="ns4.example.com" />
                </div>
            </div>

            {if $app_summary}
            <hr />
            <div class="row">
                <div class="col-md-6">
                    <strong>Detected WebNIC nameservers</strong>
                    {if $app_summary.nameservers}
                        <ul style="margin-top:8px;">
                            {foreach from=$app_summary.nameservers item=ns}
                                <li>{$ns|escape}</li>
                            {/foreach}
                        </ul>
                    {else}
                        <div class="muted">No nameservers returned from API.</div>
                    {/if}
                </div>
                <div class="col-md-6">
                    <strong>Supported record types</strong>
                    <div style="margin-top:8px;">{foreach from=$app_summary.supported_records item=record}<span class="label label-info" style="margin-right:4px;">{$record|escape}</span>{/foreach}</div>
                    <div style="margin-top:10px;"><strong>Zone limit:</strong> {$app_summary.zone_limit|default:'N/A'}</div>
                </div>
            </div>
            {/if}
        </div>
    </td>
</tr>