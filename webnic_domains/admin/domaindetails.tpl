<div class="webnic-domain-admin" style="margin:10px 0;">
    <ul class="accor white">
        <li>
            <a href="#">WebNIC Domain Management</a>
            <div class="sor">
                <div class="btn-group" style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:8px;">
                    <a href="#" onclick="return webnicDomainLoadDetails({$domainid});" class="btn btn-primary btn-sm">Refresh Details</a>
                    <a href="#" onclick="return webnicDomainLoadContacts({$domainid});" class="btn btn-default btn-sm">View Contacts</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'SyncContact');" class="btn btn-default btn-sm">Sync Contacts</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'GetEpp');" class="btn btn-warning btn-sm">Send EPP</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'ChangeEpp');" class="btn btn-warning btn-sm">Reset EPP</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'Lock');" class="btn btn-danger btn-sm">Lock</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'Unlock');" class="btn btn-success btn-sm">Unlock</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'SendVerify');" class="btn btn-info btn-sm">Resend Verification</a>
                    <a href="#" onclick="return webnicDomainLoadTransfer({$domainid});" class="btn btn-info btn-sm">Transfer Status</a>
                    <a href="#" onclick="return webnicDomainRunAction({$domainid}, 'Certificate');" class="btn btn-info btn-sm">Download Certificate</a>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div id="webnic-domain-details-panel"></div>
                    </div>
                    <div class="col-md-6">
                        <div id="webnic-domain-status-panel"></div>
                        <div id="webnic-domain-transfer-panel" style="margin-top:15px;"></div>
                    </div>
                </div>

                <div id="webnic-domain-contacts-panel" style="margin-top:15px;"></div>
                <div id="webnic-domain-action-panel" style="margin-top:15px;"></div>
            </div>
        </li>
    </ul>
</div>

{literal}
<script>
function webnicDomainAjax(url, target) {
    ajax_update(url, {load: 1}, target, true);
    return false;
}

function webnicDomainBase(action, id, extra) {
    var url = '?cmd=webnic_domains&action=' + action + '&id=' + id;
    if (extra) {
        url += extra;
    }
    return url;
}

function webnicDomainLoadDetails(id) {
    webnicDomainAjax(webnicDomainBase('domaindetails', id), '#webnic-domain-details-panel');
    webnicDomainAjax(webnicDomainBase('domainstatus', id), '#webnic-domain-status-panel');
    return false;
}

function webnicDomainLoadContacts(id) {
    return webnicDomainAjax(webnicDomainBase('domaincontacts', id), '#webnic-domain-contacts-panel');
}

function webnicDomainLoadTransfer(id) {
    return webnicDomainAjax(webnicDomainBase('domaintransfer', id), '#webnic-domain-transfer-panel');
}

function webnicDomainRunAction(id, action) {
    return webnicDomainAjax(webnicDomainBase('domainaction', id, '&ac=' + encodeURIComponent(action)), '#webnic-domain-action-panel');
}

$(function () {
    webnicDomainLoadDetails({/literal}{$domainid}{literal});
});
</script>
{/literal}