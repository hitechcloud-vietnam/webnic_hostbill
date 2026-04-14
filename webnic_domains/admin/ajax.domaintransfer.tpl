{if $transferinfo}
<table class="table table-bordered table-striped">
    <tbody>
        <tr><th style="width:180px;">Status</th><td>{$transferinfo.status|default:'N/A'}</td></tr>
        <tr><th>Transfer ID</th><td>{$transferinfo.id|default:'N/A'}</td></tr>
        <tr><th>Domain</th><td>{$transferinfo.domainName|default:'N/A'}</td></tr>
        <tr><th>Created</th><td>{$transferinfo.createDate|default:$transferinfo.reDate|default:'N/A'}</td></tr>
        <tr><th>Losing Registrar</th><td>{$transferinfo.acID|default:'N/A'}</td></tr>
        <tr><th>Gaining Registrar</th><td>{$transferinfo.reID|default:'N/A'}</td></tr>
    </tbody>
</table>
{else}
<div class="alert alert-info">No transfer status is recorded for this domain.</div>
{/if}