{if $message == 'ok'}
<div class="alert alert-success">Action completed successfully.</div>
{elseif $message == 'error'}
<div class="alert alert-danger">Action failed. Review module logs for details.</div>
{elseif $message}
<div class="alert alert-info">{$message|escape}</div>
{else}
<div class="alert alert-warning">No action result was returned.</div>
{/if}