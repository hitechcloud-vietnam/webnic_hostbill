{if $recipient}
<div class="alert alert-success">Verification email sent to {$recipient|escape}.</div>
{else}
<div class="alert alert-danger">Unable to send verification email.</div>
{/if}