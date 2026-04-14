{if $contacts}
<div class="row">
    {foreach from=$contacts item=contact key=role}
    <div class="col-md-6" style="margin-bottom:15px;">
        <div class="well" style="margin-bottom:0;">
            <h5 style="margin-top:0; text-transform:capitalize;">{$role}</h5>
            <table class="table table-condensed" style="margin-bottom:0;">
                <tbody>
                    {foreach from=$contact item=value key=field}
                        <tr>
                            <th style="width:140px;">{$field|capitalize}</th>
                            <td>{$value|escape}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
    {/foreach}
</div>
{else}
<div class="alert alert-warning">No contact data is available for this domain.</div>
{/if}