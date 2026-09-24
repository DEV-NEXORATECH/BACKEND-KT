$ErrorActionPreference = 'Stop'
$backRoot = 'C:\Users\raulm\Downloads\Bakcend-KT'
$frontRoot = 'C:\Users\raulm\Downloads\Frontend-KT'

$seederPath = Join-Path $backRoot 'database\seeders\DatabaseSeeder.php'
$apiPath = Join-Path $backRoot 'routes\api.php'
$appPath = Join-Path $frontRoot 'src\App.jsx'
$outPath = Join-Path $backRoot 'MENU_MATRIX.md'

$NL = [Environment]::NewLine
$lineArr = New-Object System.Collections.Generic.List[string]

function Get-Menus([string]$seed) {
  $text = Get-Content -Path $seed -Raw
  $menus = New-Object System.Collections.Generic.List[object]
  $capturePath = $false
  $slug = ''
  $parent = $null
  $title = ''
  $isNested = $false
  foreach ($ln in ($text -split "`n")) {
    $t = $ln.Trim()
    if ($t -eq '') { continue }
    if ($capturePath) {
      $m = [regex]::Match($t, "'path'\s*=>\s*'([^']*)'")
      if ($m.Success) {
        $menus.Add([pscustomobject]@{ Slug=$slug; Path=$m.Groups[1].Value; Parent=$parent; Title=$title })
      }
      $capturePath = $false
      continue
    }
    if ($t -match "'slug'\s*=>\s*'([^']+)'") {
      $slug = $Matches[1]
      $suggested = [regex]::Match($t, "'suggested_slugs'\s*=>\s*\[([^\]]*)\]")
      $parent = $null
      continue
    }
    if ($t -match "'title'\s*=>\s*'([^']+)'") { $title = $Matches[1]; continue }
    if ($t -match "'parent_id'\s*=>\s*'([^']+)'") { $parent = $Matches[1]; continue }
    if ($t -match "'path'\s*=>\s*'([^']*)'") {
      $menus.Add([pscustomobject]@{ Slug=$slug; Path=$Matches[1]; Parent=$parent; Title=$title })
      continue
    }
  }
  return $menus
}

function Get-RoutePaths([string]$app) {
  $text = Get-Content -Path $app -Raw
  $list = New-Object System.Collections.Generic.List[string]
  foreach ($m in [regex]::Matches($text, 'path="([^"]+)"')) {
    $p = $m.Groups[1].Value
    if ($p -match '^\*$|^/$') { continue }
    $list.Add($p.TrimStart('/'))
  }
  return $list
}

function Get-ApiPerms([string]$api) {
  $text = Get-Content -Path $api -Raw
  $list = New-Object System.Collections.Generic.List[string]
  foreach ($m in [regex]::Matches($text, 'permission:([\w.:\-]+)')) {
    $list.Add($m.Groups[1].Value)
  }
  return ($list | Sort-Object -Unique)
}

$menus = Get-Menus $seederPath
$routes = Get-RoutePaths $appPath
$apiPerms = Get-ApiPerms $apiPath

function StatusFor([string]$menuPath, [System.Collections.Generic.List[string]]$routes) {
  if ([string]::IsNullOrWhiteSpace($menuPath)) { return 'NO_PATH' }
  $mp = $menuPath.TrimStart('/')
  if ($routes -contains $mp) { return 'COMPLETE' }
  $parts = $mp -split '/'
  $cand = ''
  for ($i = 0; $i -lt $parts.Count; $i++) {
    $cand = if ($cand -eq '') { $parts[$i] } else { $cand + '/' + $parts[$i] }
    if ($routes -contains ($cand + '/*')) { return ('NEEDS_MAPPING(wildcard-parent:' + $cand + ')') }
  }
  return 'ROUTE_MISMATCH'
}

$rows = New-Object System.Collections.Generic.List[string]
$rows.Add('# MENU x ROUTE x API x PERMISSION — Cross-Reference Matrix')
$rows.Add('')
$rows.Add('Dihasilkan otomatis dari source aktual kedua repo (bukan tebakan).')
$rows.Add('Source: backend ' + $seederPath)
$rows.Add('Source: frontend ' + $appPath)
$rows.Add('')
$rows.Add('| # | Menu slug | Base Path (seeder) | Status | Route(s) frontend | Permission backend |')
$rows.Add('|---|---|---|---|---|---|')
$i = 0
foreach ($m in $menus) {
  $i++
  $status = StatusFor $m.Path $routes
  $rows.Add(('| {0} | {1} | {2} | {3} | (lihat di bawah) | (lihat di bawah) |' -f $i, $m.Slug, $m.Path, $status))
}
$rows.Add('')
$rows.Add('## Route paths yang terdaftar di App.jsx (' + $routes.Count + ')')
$rows.Add('')
$rows.Add('```')
foreach ($r in $routes) { $rows.Add($r) }
$rows.Add('```')
$rows.Add('')
$rows.Add('## Permission slugs yang dipakai di routes/api.php (' + $apiPerms.Count + ')')
$rows.Add('')
$rows.Add('```')
foreach ($p in $apiPerms) { $rows.Add($p) }
$rows.Add('```')
$rows.Add('')
$rows.Add('## Menu yang ROUTE_MISMATCH (path menu tidak ada di frontend)')
$rows.Add('')
$rows.Add('```')
foreach ($m in $menus) {
  $status = StatusFor $m.Path $routes
  if ($status -eq 'ROUTE_MISMATCH') { $rows.Add(('{0} -> {1}' -f $m.Slug, $m.Path)) }
}
$rows.Add('```')

Set-Content -Path $outPath -Value $rows -Encoding UTF8
Write-Output ("WROTE " + $outPath)
Write-Output ("menus=" + $menus.Count + " routes=" + $routes.Count + " apiPerms=" + $apiPerms.Count)
