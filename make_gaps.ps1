$ErrorActionPreference = 'Stop'
$seed  = 'C:\Users\raulm\Downloads\Bakcend-KT\database\seeders\DatabaseSeeder.php'
$app   = 'C:\Users\raulm\Downloads\Frontend-KT\src\App.jsx'
$out   = 'C:\Users\raulm\Downloads\Bakcend-KT\MENU_GAPS.md'

$seedText = Get-Content -Path $seed -Raw

# Collect every menu path (title/slug/path triple) from the seeder
$menuPaths = New-Object System.Collections.Generic.List[string]
foreach ($m in [regex]::Matches($seedText, "'title'\s*=>\s*'([^']+)'\s*,\s*'slug'\s*=>\s*'([^']+)'\s*,\s*'path'\s*=>\s*'([^']*)'")) {
  $menuPaths.Add(("{0}`t{1}`t{2}" -f $m.Groups[1].Value, $m.Groups[2].Value, $m.Groups[3].Value))
}

$appText = Get-Content -Path $app -Raw
$routesRaw = New-Object System.Collections.Generic.List[string]
foreach ($m in [regex]::Matches($appText, 'path="([^"]+)"')) {
  $routesRaw.Add($m.Groups[1].Value)
}
$routes = @($routesRaw | Where-Object { $_ -notmatch '^/|\*|\*$' } | Select-Object -Unique)

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('# MENU GAP ANALYSIS — backend seeder menu vs frontend route')
$lines.Add('')
$lines.Add(('Menus in seeder: ' + $menuPaths.Count))
$lines.Add(('Frontend literal routes: ' + $routes.Count))
$lines.Add('')
$lines.Add('| # | Title | Slug | Menu Path | Frontend Route Match | Result |')
$lines.Add('|---|---|---|---|---|---|')
$i = 0
foreach ($mp in $menuPaths) {
  $i++
  $parts = $mp -split "`t"
  $title = $parts[0]; $slug = $parts[1]; $mPath = $parts[2]
  $match = $routes -contains $mPath.TrimStart('/')
  $status = if ($match) { 'COMPLETE' } elseif ($mPath -eq '') { 'NO_PATH' } else { 'NEEDS_ROUTE' }
  $lines.Add(('| {0} | {1} | {2} | {3} | {4} | {5} |' -f $i, $title, $slug, $mPath, $match, $status))
}
Set-Content -Path $out -Value $lines -Encoding UTF8
Write-Output ('MENUS=' + $menuPaths.Count + ' ROUTES=' + $routes.Count + ' -> ' + $out)
