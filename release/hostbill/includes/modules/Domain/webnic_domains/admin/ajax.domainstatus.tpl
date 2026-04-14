{if $info}
<table class="table table-condensed table-hover">
    <tbody>
        <tr>
            <th style="width:180px;">Mapped HostBill Status</th>
            <td>{$status_sync.status|default:'N/A'}</td>
        </tr>
        <tr>
            <th>Registry Status</th>
            <td>{$info.status|default:'N/A'}</td>
        </tr>
        <tr>
            <th>Transfer Lock</th>
            <td>{if $info.status == 'transfer_protected'}Enabled{else}Disabled{/if}</td>
        </tr>
    </tbody>
</table>
{else}
<div class="alert alert-warning">Status information could not be loaded.</div>
{/if}