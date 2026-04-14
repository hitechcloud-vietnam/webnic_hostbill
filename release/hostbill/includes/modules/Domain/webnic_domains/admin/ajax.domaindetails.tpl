{if $info}
<table class="table table-striped table-hover">
    <tbody>
        <tr>
            <th style="width:180px;">Domain</th>
            <td>{$info.domainName|default:$domaininfo.domain}</td>
        </tr>
        <tr>
            <th>Registry Status</th>
            <td>{$info.status|default:'N/A'}</td>
        </tr>
        <tr>
            <th>Expiry Date</th>
            <td>{$status_sync.expires|default:'N/A'}</td>
        </tr>
        <tr>
            <th>WHOIS Privacy</th>
            <td>{if $status_sync.idprotection}Enabled{else}Disabled{/if}</td>
        </tr>
        <tr>
            <th>Nameservers</th>
            <td>
                {if $status_sync.ns}
                    {foreach from=$status_sync.ns item=ns}
                        <div>{$ns}</div>
                    {/foreach}
                {else}
                    <span class="muted">No nameservers returned</span>
                {/if}
            </td>
        </tr>
    </tbody>
</table>
{else}
<div class="alert alert-warning">No domain information available.</div>
{/if}