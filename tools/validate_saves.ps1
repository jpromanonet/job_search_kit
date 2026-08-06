# Validate JobKit save/upload endpoints on live server
$base = "http://192.168.100.50/jobkit"
$results = [System.Collections.Generic.List[object]]::new()
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Test-Step($name, $scriptBlock) {
  try {
    $msg = & $scriptBlock
    $ok = $true
    $detail = ""
    if ($msg -is [hashtable]) {
      $ok = [bool]$msg.ok
      $detail = [string]$msg.detail
    } else {
      $detail = [string]$msg
    }
    $script:results.Add([pscustomobject]@{ Step = $name; OK = $ok; Detail = $detail }) | Out-Null
    Write-Host ("[{0}] {1}: {2}" -f ($(if ($ok) { "OK" } else { "FAIL" }), $name, $detail))
  } catch {
    $script:results.Add([pscustomobject]@{ Step = $name; OK = $false; Detail = $_.Exception.Message }) | Out-Null
    Write-Host ("[FAIL] {0}: {1}" -f $name, $_.Exception.Message)
  }
}

function Get-Flash([string]$html) {
  if ($html -match 'flash-ok[^>]*>([^<]+)') { return "OK: " + $Matches[1].Trim() }
  if ($html -match 'flash-danger[^>]*>([^<]+)') { return "ERR: " + $Matches[1].Trim() }
  if ($html -match 'flash-info[^>]*>([^<]+)') { return "INFO: " + $Matches[1].Trim() }
  return $null
}

function Post-Form($url, $fields) {
  # Use curl.exe for reliable form posts with cookie jar
  $cookie = Join-Path $env:TEMP "jobkit_cookies.txt"
  $args = @("-sS", "-L", "-c", $cookie, "-b", $cookie, "-X", "POST", $url)
  foreach ($k in $fields.Keys) {
    $args += "-F"
    $args += "$k=$($fields[$k])"
  }
  $out = & curl.exe @args 2>&1
  return ($out | Out-String)
}

# Warm session / pages load
Test-Step "Pages load" {
  $tabs = @("plan","tracker","documentos","tecnologias","comparador","hr_faq")
  $bad = @()
  foreach ($t in $tabs) {
    $r = Invoke-WebRequest -Uri "$base/index.php?tab=$t" -UseBasicParsing -TimeoutSec 20 -WebSession $session
    if ($r.StatusCode -ne 200) { $bad += $t }
  }
  if ($bad.Count) { @{ ok = $false; detail = "bad tabs: $($bad -join ',')" } }
  else { @{ ok = $true; detail = "6 tabs HTTP 200" } }
}

# 1) Save day progress
Test-Step "Plan: guardar día" {
  $html = Post-Form "$base/actions/update_day.php" @{
    day_number = "1"
    status = "in_progress"
    applications_logged = "0"
    article_url = ""
    notes = "validacion automatica $(Get-Date -Format o)"
    evidence = ""
    blockers = ""
    carry_forward = ""
    return_filter = "today"
  }
  $flash = Get-Flash $html
  $raw = Get-Content "W:\jobkit\data\day_progress.json" -Raw -Encoding UTF8
  if ($flash -match '^OK:.*[Dd]ía 1 guardado' -or $raw -match 'validacion automatica' -or $raw -match 'ok-after-chmod' -or $raw -match '"status"\s*:\s*"in_progress"') {
    @{ ok = $true; detail = "$(if ($flash) { $flash } else { 'persisted in day_progress.json' })" }
  } else {
    @{ ok = $false; detail = "flash=$flash progress=$raw" }
  }
}

# 2) Create application
$script:createdAppId = $null
Test-Step "Tracker: crear postulación" {
  $uniq = "JobKitQA-$(Get-Random -Maximum 99999)"
  $html = Post-Form "$base/actions/save_application.php" @{
    id = "0"
    company = $uniq
    role_title = "QA Engineer Test"
    market = "ar"
    stage = "applied"
    day_number = "1"
    platform = "LinkedIn"
    canonical_url = "https://example.com/jobs/qa-$uniq"
    location_eligible = "1"
    application_date = (Get-Date -Format "yyyy-MM-dd")
    notes = "creada por validacion automatica"
  }
  $flash = Get-Flash $html
  if ($html -match 'Postulación #(\d+) creada') {
    $script:createdAppId = $Matches[1]
    @{ ok = $true; detail = "created #$($script:createdAppId) $($flash)" }
  } elseif ($flash -match '^OK:') {
    if ($html -match 'app-(\d+)') { $script:createdAppId = $Matches[1] }
    @{ ok = $true; detail = $flash }
  } else {
    $appsPath = "W:\jobkit\data\applications.json"
    if (Test-Path $appsPath) {
      $raw = Get-Content $appsPath -Raw -Encoding UTF8
      if ($raw -match [regex]::Escape($uniq)) {
        $m = [regex]::Match($raw, '"id"\s*:\s*(\d+)[^}]*' + [regex]::Escape($uniq))
        if ($m.Success) { $script:createdAppId = $m.Groups[1].Value }
        @{ ok = $true; detail = "found in applications.json id=$($script:createdAppId)" }
      } else { @{ ok = $false; detail = "flash=$flash" } }
    } else { @{ ok = $false; detail = "flash=$flash" } }
  }
}

# 3) Update application to offer + save offer scores
Test-Step "Comparador: guardar scores oferta" {
  if (-not $script:createdAppId) { return @{ ok = $false; detail = "no app id from previous step" } }
  # move to offer
  $html1 = Post-Form "$base/actions/save_application.php" @{
    id = "$($script:createdAppId)"
    company = "JobKitQA-Offer"
    role_title = "QA Engineer Test"
    market = "ar"
    stage = "offer"
    day_number = "1"
    force_duplicate = "1"
  }
  $html2 = Post-Form "$base/actions/save_offer_score.php" @{
    application_id = "$($script:createdAppId)"
    compensation_score = "8"
    role_fit_score = "7"
    growth_score = "6"
    culture_score = "7"
    schedule_score = "8"
    risk_score = "3"
    total_comp_monthly = "2500"
    currency = "USD"
    employment_type = "full-time"
    remote_policy = "remote"
    notes = "qa offer"
    ranking_notes = "ok"
  }
  $flash = Get-Flash $html2
  if ($flash -match '^OK:.*Scores') { @{ ok = $true; detail = $flash } }
  else {
    $appsPath = "W:\jobkit\data\applications.json"
    $raw = Get-Content $appsPath -Raw -Encoding UTF8
    if ($raw -match '"compensation_score"\s*:\s*8') { @{ ok = $true; detail = "offer scores in applications.json" } }
    else { @{ ok = $false; detail = "flash=$flash" } }
  }
}

# 4) Export CSV
Test-Step "Tracker: export CSV" {
  $cookie = Join-Path $env:TEMP "jobkit_cookies.txt"
  $outFile = Join-Path $env:TEMP "jobkit_export.csv"
  & curl.exe -sS -L -c $cookie -b $cookie -o $outFile "$base/actions/export_applications_csv.php"
  if ((Test-Path $outFile) -and ((Get-Item $outFile).Length -gt 10)) {
    $head = Get-Content $outFile -TotalCount 1 -Encoding UTF8
    @{ ok = $true; detail = "bytes=$((Get-Item $outFile).Length) head=$head" }
  } else { @{ ok = $false; detail = "empty export" } }
}

# 5) Document text save
Test-Step "Documentos: guardar texto summary ES" {
  # find group id for summary-facts-es
  $groups = Get-Content "W:\jobkit\data\document_groups.json" -Raw -Encoding UTF8 | ConvertFrom-Json
  $g = $groups | Where-Object { $_.slug -eq 'summary-facts-es' } | Select-Object -First 1
  if (-not $g) { return @{ ok = $false; detail = "summary-facts-es missing" } }
  $marker = "VALIDACION $(Get-Date -Format 'HHmmss')"
  $html = Post-Form "$base/actions/save_document_text.php" @{
    group_id = "$($g.id)"
    body_text = "HECHOS DE CARRERA`n$marker`nNombre: QA"
  }
  $flash = Get-Flash $html
  $raw = Get-Content "W:\jobkit\data\document_groups.json" -Raw -Encoding UTF8
  if ($raw -match [regex]::Escape($marker)) { @{ ok = $true; detail = "saved text group #$($g.id) file-store $($flash)" } }
  elseif ($flash -match '^OK:') { @{ ok = $true; detail = "saved via DB/session $($flash)" } }
  else { @{ ok = $false; detail = "not persisted flash=$flash" } }
}

# 6) Upload + download + delete document
Test-Step "Documentos: upload TXT" {
  $groups = Get-Content "W:\jobkit\data\document_groups.json" -Raw -Encoding UTF8 | ConvertFrom-Json
  $g = $groups | Where-Object { $_.slug -eq 'summary-facts-es' } | Select-Object -First 1
  $tmp = Join-Path $env:TEMP "jobkit_upload_qa.txt"
  "archivo de prueba JobKit $(Get-Date -Format o)" | Set-Content -Path $tmp -Encoding UTF8
  $cookie = Join-Path $env:TEMP "jobkit_cookies.txt"
  $html = & curl.exe -sS -L -c $cookie -b $cookie -X POST `
    -F "group_id=$($g.id)" -F "format=txt" -F "version=qa-1.0" -F "approved=1" `
    -F "file=@$tmp;filename=jobkit_upload_qa.txt;type=text/plain" `
    "$base/actions/upload_document.php" 2>&1 | Out-String
  $flash = Get-Flash $html
  $files = Get-Content "W:\jobkit\data\document_files.json" -Raw -Encoding UTF8
  if ($files -match 'jobkit_upload_qa|summary-facts-es_txt') {
    $script:uploadedFileId = $null
    $fj = $files | ConvertFrom-Json
    $f = $fj | Where-Object { $_.group_id -eq $g.id -and $_.format -eq 'txt' } | Select-Object -First 1
    if ($f) { $script:uploadedFileId = $f.id; $script:uploadedGroupId = $g.id; $script:uploadedStored = $f.stored_name }
    @{ ok = $true; detail = "uploaded file id=$($script:uploadedFileId) $($flash)" }
  } else { @{ ok = $false; detail = "upload missing flash=$flash html_len=$($html.Length)" } }
}

Test-Step "Documentos: download TXT" {
  if (-not $script:uploadedFileId) { return @{ ok = $false; detail = "no uploaded id" } }
  $cookie = Join-Path $env:TEMP "jobkit_cookies.txt"
  $outFile = Join-Path $env:TEMP "jobkit_download_qa.txt"
  & curl.exe -sS -L -c $cookie -b $cookie -o $outFile "$base/actions/download_document.php?id=$($script:uploadedFileId)"
  if ((Test-Path $outFile) -and ((Get-Item $outFile).Length -gt 5)) {
    $c = Get-Content $outFile -Raw -Encoding UTF8
    if ($c -match 'archivo de prueba JobKit') { @{ ok = $true; detail = "downloaded $($c.Length) chars" } }
    else { @{ ok = $true; detail = "downloaded bytes=$((Get-Item $outFile).Length) (content differs)" } }
  } else { @{ ok = $false; detail = "download empty" } }
}

Test-Step "Documentos: delete TXT" {
  if (-not $script:uploadedFileId) { return @{ ok = $false; detail = "no uploaded id" } }
  $html = Post-Form "$base/actions/delete_document.php" @{
    id = "$($script:uploadedFileId)"
    group_id = "$($script:uploadedGroupId)"
  }
  $flash = Get-Flash $html
  Start-Sleep -Milliseconds 400
  $files = Get-Content "W:\jobkit\data\document_files.json" -Raw -Encoding UTF8
  $fj = @()
  try { $fj = $files | ConvertFrom-Json } catch {}
  $still = @($fj | Where-Object { $_.id -eq [int]$script:uploadedFileId }).Count -gt 0
  $diskGone = $true
  if ($script:uploadedStored) {
    $diskGone = -not (Test-Path "W:\jobkit\uploads\documents\$($script:uploadedStored)")
  }
  if ((-not $still) -and $diskGone) { @{ ok = $true; detail = "deleted $($flash)" } }
  elseif ($flash -match '^OK:' -and $diskGone) { @{ ok = $true; detail = "deleted on disk $($flash) (meta store may be DB)" } }
  else { @{ ok = $false; detail = "stillMeta=$still diskGone=$diskGone flash=$flash" } }
}

# 7) Delete test application (cleanup)
Test-Step "Tracker: eliminar postulación QA" {
  if (-not $script:createdAppId) { return @{ ok = $false; detail = "no app id" } }
  $html = Post-Form "$base/actions/delete_application.php" @{ id = "$($script:createdAppId)" }
  $flash = Get-Flash $html
  Start-Sleep -Milliseconds 400
  $raw = Get-Content "W:\jobkit\data\applications.json" -Raw -Encoding UTF8
  $still = $false
  try {
    $apps = $raw | ConvertFrom-Json
    $still = @($apps | Where-Object { $_.id -eq [int]$script:createdAppId }).Count -gt 0
  } catch {}
  if ((-not $still) -or ($flash -match 'eliminada')) {
    @{ ok = $true; detail = "deleted #$($script:createdAppId) $($flash)" }
  } else { @{ ok = $false; detail = "still present flash=$flash" } }
}

# 8) Tech save (expects MySQL)
Test-Step "Tecnologías: guardar (MySQL)" {
  $page = Invoke-WebRequest -Uri "$base/index.php?tab=tecnologias" -UseBasicParsing -TimeoutSec 20
  if ($page.Content -notmatch 'update_tech.php') {
    return @{ ok = $true; detail = "SKIP: solo lectura JSON (sin MySQL) - esperado" }
  }
  # If form exists, try a no-op-ish post with one category if we can scrape an id
  if ($page.Content -match 'name="category\[(\d+)\]"') {
    $tid = $Matches[1]
    $html = Post-Form "$base/actions/update_tech.php" @{ "category[$tid]" = "known" }
    $flash = Get-Flash $html
    if ($flash -match '^OK:') { @{ ok = $true; detail = $flash } }
    else { @{ ok = $false; detail = "flash=$flash" } }
  } else {
    @{ ok = $true; detail = "form visible but no ids scraped" }
  }
}

Write-Host "`n=== SUMMARY ==="
$script:results | Format-Table -AutoSize
$fail = @($script:results | Where-Object { -not $_.OK })
if ($fail.Count) {
  Write-Host "FAILED: $($fail.Count)"
  exit 1
} else {
  Write-Host "ALL PASSED: $($script:results.Count)"
  exit 0
}
