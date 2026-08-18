param()

# Edit these three values once for a new tool.
$Subdomain = "<subdomain>"
$Domain = "etari.eu"
$ToolName = "<Toolname>"

# Optional infrastructure defaults.
$GithubOwner = "leonk-a24"
$AuthDbName = "Hestia_Admin_users"
$TrustedProxies = "10.0.1.96"

function Assert-Configured {
    param(
        [string]$Name,
        [string]$Value
    )

    if ([string]::IsNullOrWhiteSpace($Value) -or $Value.StartsWith("<")) {
        throw "Please set `$${Name} at the top of scripts/configure-template.ps1."
    }
}

function Convert-ToToolSlug {
    param([string]$Value)

    $slug = $Value.ToLowerInvariant() -replace '[^a-z0-9]+', '_'
    $slug = $slug.Trim('_')
    if ([string]::IsNullOrWhiteSpace($slug)) {
        throw "Could not derive TOOL_SLUG from '$Value'."
    }
    return $slug
}

Assert-Configured -Name "Subdomain" -Value $Subdomain
Assert-Configured -Name "Domain" -Value $Domain
Assert-Configured -Name "ToolName" -Value $ToolName

$ToolSlug = Convert-ToToolSlug $Subdomain
$FullDomain = "$Subdomain.$Domain"
$AppBaseUrl = "https://$FullDomain"
$UserManagementUrl = "https://users.$Domain"
$ToolDbName = $ToolSlug
$ToolUsersTable = "${ToolSlug}_users"
$ToolAdminColumn = "is_${ToolSlug}_admin"
$GitRemoteUrl = "https://github.com/$GithubOwner/$Subdomain.git"

$Replacements = [ordered]@{
    "{{SUBDOMAIN}}" = $Subdomain
    "{{DOMAIN}}" = $Domain
    "{{FULL_DOMAIN}}" = $FullDomain
    "{{TOOL_NAME}}" = $ToolName
    "{{TOOL_SLUG}}" = $ToolSlug
    "{{TOOL_DB_NAME}}" = $ToolDbName
    "{{TOOL_USERS_TABLE}}" = $ToolUsersTable
    "{{TOOL_ADMIN_COLUMN}}" = $ToolAdminColumn
    "{{APP_BASE_URL}}" = $AppBaseUrl
    "{{USER_MANAGEMENT_URL}}" = $UserManagementUrl
    "{{GIT_REMOTE_URL}}" = $GitRemoteUrl
    "{{AUTH_DB_NAME}}" = $AuthDbName
    "{{TRUSTED_PROXIES}}" = $TrustedProxies
}

$Root = Split-Path -Parent $PSScriptRoot
$Files = @(
    "README.md",
    ".env.example",
    "setup.sql",
    "X DKIM_Setup.md",
    "X GIT_Setup.md",
    "X DB_Setup.md",
    "X CapRover_ENV_Setup.md",
    "X overview_user-management.md",
    ".well-known/security.txt",
    "images/site.webmanifest"
)

foreach ($RelativePath in $Files) {
    $Path = Join-Path $Root $RelativePath
    if (-not (Test-Path -LiteralPath $Path)) {
        Write-Warning "Skipped missing file: $RelativePath"
        continue
    }

    $Content = Get-Content -LiteralPath $Path -Raw
    foreach ($Entry in $Replacements.GetEnumerator()) {
        $Content = $Content.Replace($Entry.Key, [string]$Entry.Value)
    }
    Set-Content -LiteralPath $Path -Value $Content -Encoding utf8
    Write-Host "Updated $RelativePath"
}

Write-Host ""
Write-Host "Configured template:"
Write-Host "  Tool:       $ToolName"
Write-Host "  URL:        $AppBaseUrl"
Write-Host "  Tool slug:  $ToolSlug"
Write-Host "  Tool DB:    $ToolDbName"
Write-Host "  Auth table: $ToolUsersTable"
