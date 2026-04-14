param(
    [string]$ReleaseRoot = (Join-Path $PSScriptRoot '..\release\hostbill')
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$releaseRootPath = (Resolve-Path (Split-Path $ReleaseRoot -Parent) -ErrorAction SilentlyContinue)

if (Test-Path $ReleaseRoot) {
    Remove-Item -Path $ReleaseRoot -Recurse -Force
}

$domainTarget = Join-Path $ReleaseRoot 'includes\modules\Domain\webnic_domains'
$hostingSslTarget = Join-Path $ReleaseRoot 'includes\modules\Hosting\webnic_ssl'
$hostingDnsTarget = Join-Path $ReleaseRoot 'includes\modules\Hosting\webnic_dns'
$docsTarget = Join-Path $ReleaseRoot 'docs'

$targets = @(
    $domainTarget,
    $hostingSslTarget,
    $hostingDnsTarget,
    $docsTarget
)

foreach ($target in $targets) {
    New-Item -ItemType Directory -Path $target -Force | Out-Null
}

Copy-Item -Path (Join-Path $repoRoot 'webnic_domains\*') -Destination $domainTarget -Recurse -Force
Copy-Item -Path (Join-Path $repoRoot 'webnic_ssl\*') -Destination $hostingSslTarget -Recurse -Force
Copy-Item -Path (Join-Path $repoRoot 'webnic_dns\*') -Destination $hostingDnsTarget -Recurse -Force

$docFiles = @(
    'README.md',
    'DEPLOYMENT.md',
    'API_REFERENCE.md',
    'TESTING.md',
    'UAT_SCENARIOS.md',
    'OPERATIONS.vi.md'
)

foreach ($docFile in $docFiles) {
    Copy-Item -Path (Join-Path $repoRoot $docFile) -Destination (Join-Path $docsTarget $docFile) -Force
}

$releaseReadme = @'
# HostBill Release Package

Copy the contents of this folder into the HostBill installation root.

Included runtime paths:

- `includes/modules/Domain/webnic_domains`
- `includes/modules/Hosting/webnic_ssl`
- `includes/modules/Hosting/webnic_dns`

Documentation is bundled in `docs/`.
'@

Set-Content -Path (Join-Path $ReleaseRoot 'README.md') -Value $releaseReadme -Encoding UTF8

Write-Host "Release package created at: $ReleaseRoot"