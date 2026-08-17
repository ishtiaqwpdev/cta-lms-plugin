# LPCC NCMHCE asset prep + quiz seed generation
$ErrorActionPreference = 'Continue'
$log = "C:\Users\premier computer\Desktop\New folder\cta-academy-lms\_lpcc_prep_log.txt"
function L($m) { $t = "$(Get-Date -Format o) $m"; Add-Content -Path $log -Value $t; Write-Output $t }

L "=== START ==="
$repo = "C:\Users\premier computer\Desktop\New folder\cta-academy-lms"
$pkgParent = Join-Path $repo "_packages\CTA_LPCC_NCMHCE_v1.0"
L "pkgParent exists: $(Test-Path -LiteralPath $pkgParent)"
if (Test-Path -LiteralPath $pkgParent) {
  Get-ChildItem -LiteralPath $pkgParent | ForEach-Object { L "PKG: $($_.Name)" }
}
# Find package root
$pkg = Get-ChildItem -LiteralPath $pkgParent -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -like "*Complete*" } | Select-Object -First 1
if (-not $pkg) {
  # try recursive find
  $pkg = Get-ChildItem -LiteralPath (Join-Path $repo "_packages") -Directory -Recurse -ErrorAction SilentlyContinue | Where-Object { $_.Name -like "CTA_LPCC_NCMHCE*Complete*" } | Select-Object -First 1
}
if (-not $pkg) {
  L "ERROR: package not found"
  # list all LPCC folders
  Get-ChildItem -LiteralPath (Join-Path $repo "_packages") -ErrorAction SilentlyContinue | ForEach-Object { L "TOP: $($_.Name)" }
  exit 1
}
$PKG = $pkg.FullName
L "PACKAGE: $PKG"

# Use subst for shorter path
subst Z: /d 2>$null
subst Z: $PKG
$SRC = "Z:\"
L "SUBST Z: -> $PKG ; exists: $(Test-Path Z:\)"
Get-ChildItem Z:\ | ForEach-Object { L "ROOT: $($_.Name)" }

$DEST = Join-Path $repo "assets\course-materials\lpcc-ncmhce"
New-Item -ItemType Directory -Force -Path $DEST | Out-Null
@('workbooks','practice-banks','checkpoints','simulations','study-tools','quick-references','student-support','branding') | ForEach-Object {
  New-Item -ItemType Directory -Force -Path (Join-Path $DEST $_) | Out-Null
}

function Copy-Flat($fromDir, $toDir, $filter='*.docx') {
  if (-not (Test-Path -LiteralPath $fromDir)) { L "MISSING: $fromDir"; return @() }
  $copied = @()
  Get-ChildItem -LiteralPath $fromDir -Filter $filter -File -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
    $destFile = Join-Path $toDir $_.Name
    Copy-Item -LiteralPath $_.FullName -Destination $destFile -Force
    $copied += $_.Name
    L "COPIED: $($_.Name) -> $toDir"
  }
  return $copied
}

L "--- workbooks ---"
$wb = Copy-Flat (Join-Path $SRC "01_Student_Workbooks") (Join-Path $DEST "workbooks")

L "--- practice-banks ---"
$pbSrc = Join-Path $SRC "02_Workbook_Practice_Banks"
$pb = @()
if (Test-Path $pbSrc) {
  Get-ChildItem $pbSrc | ForEach-Object { L "PB_SUB: $($_.Name)" }
  $pb = Copy-Flat $pbSrc (Join-Path $DEST "practice-banks")
}

L "--- checkpoints ---"
$ck = @()
foreach ($c in @('Checkpoint_1','Checkpoint_2','Checkpoint_3','03_Checkpoints')) {
  $p = Join-Path $SRC $c
  if (Test-Path $p) {
    L "CK_DIR: $c"
    $ck += Copy-Flat $p (Join-Path $DEST "checkpoints")
  }
}
# Also search for Checkpoint folders under package
Get-ChildItem $SRC -Directory -Recurse -ErrorAction SilentlyContinue | Where-Object { $_.Name -match 'Checkpoint' } | ForEach-Object {
  L "CK_FOUND: $($_.FullName)"
  $ck += Copy-Flat $_.FullName (Join-Path $DEST "checkpoints")
}

L "--- simulations ---"
$simDest = Join-Path $DEST "simulations"
$sim = @()
# Form A/B under 04
$simRoot = Join-Path $SRC "04_Comprehensive_Simulations"
if (-not (Test-Path $simRoot)) {
  $simRoot = Get-ChildItem $SRC -Directory | Where-Object { $_.Name -match 'Simulation|Comprehensive' } | Select-Object -First 1 -ExpandProperty FullName
}
L "SIM_ROOT: $simRoot"
if ($simRoot -and (Test-Path $simRoot)) {
  Get-ChildItem $simRoot -Recurse -File -Filter *.docx | ForEach-Object { L "SIM_FILE: $($_.FullName.Replace($simRoot,''))" }
  # Form A exam + rationales
  foreach ($form in @('Form_A','Form_B','Form_A_Remediation')) {
    $formDir = Get-ChildItem $simRoot -Directory -Recurse -ErrorAction SilentlyContinue | Where-Object { $_.Name -eq $form -or $_.Name -like "*$form*" } | Select-Object -First 1
    if ($formDir) {
      L "FORM_DIR: $($formDir.FullName)"
      Get-ChildItem $formDir.FullName -File -Filter *.docx -Recurse | ForEach-Object {
        # For Form_A: exam + rationales; Form_A_Remediation: workbook; Form_B: exam + rationales
        $n = $_.Name
        $take = $false
        if ($form -eq 'Form_A_Remediation') { $take = $true }
        elseif ($n -match 'Exam|Rationale|Answer_Key|Answer Key') { $take = $true }
        if ($take) {
          Copy-Item $_.FullName (Join-Path $simDest $n) -Force
          $sim += $n
          L "SIM_COPIED: $n"
        }
      }
    }
  }
  # Remediation workbook may be named differently
  Get-ChildItem $simRoot -Recurse -File -Filter *Remediation*.docx | ForEach-Object {
    if ($sim -notcontains $_.Name) {
      Copy-Item $_.FullName (Join-Path $simDest $_.Name) -Force
      $sim += $_.Name
      L "SIM_COPIED_REM: $($_.Name)"
    }
  }
}

L "--- study-tools (05) ---"
$st = Copy-Flat (Join-Path $SRC "05_Flashcards_and_Study_Tools") (Join-Path $DEST "study-tools")
if (-not $st -or $st.Count -eq 0) {
  $d05 = Get-ChildItem $SRC -Directory | Where-Object { $_.Name -like '05*' } | Select-Object -First 1
  if ($d05) { $st = Copy-Flat $d05.FullName (Join-Path $DEST "study-tools") }
}

L "--- quick-references (06) ---"
$qr = Copy-Flat (Join-Path $SRC "06_Quick_References_and_Downloadables") (Join-Path $DEST "quick-references")
if (-not $qr -or $qr.Count -eq 0) {
  $d06 = Get-ChildItem $SRC -Directory | Where-Object { $_.Name -like '06*' } | Select-Object -First 1
  if ($d06) { $qr = Copy-Flat $d06.FullName (Join-Path $DEST "quick-references") }
}

L "--- student-support (07) ---"
$ss = Copy-Flat (Join-Path $SRC "07_Student_Support_and_Orientation") (Join-Path $DEST "student-support")
if (-not $ss -or $ss.Count -eq 0) {
  $d07 = Get-ChildItem $SRC -Directory | Where-Object { $_.Name -like '07*' } | Select-Object -First 1
  if ($d07) { $ss = Copy-Flat $d07.FullName (Join-Path $DEST "student-support") }
}

L "--- branding from 09 if easy ---"
$d09 = Get-ChildItem $SRC -Directory | Where-Object { $_.Name -like '09*' } | Select-Object -First 1
if ($d09) {
  L "09: $($d09.Name)"
  Get-ChildItem $d09.FullName -Recurse -File -ErrorAction SilentlyContinue | Where-Object { $_.Extension -match '\.(png|jpg|jpeg|svg|webp)$' -or $_.Name -match 'logo|brand' } | ForEach-Object {
    Copy-Item $_.FullName (Join-Path $DEST "branding\$($_.Name)") -Force
    L "BRAND: $($_.Name)"
  }
  Get-ChildItem $d09.FullName -Recurse -File -Filter *.docx | ForEach-Object { L "09_DOC: $($_.Name)" }
}

L "=== FILE TREE DEST ==="
Get-ChildItem $DEST -Recurse -File | ForEach-Object {
  $rel = $_.FullName.Substring($DEST.Length+1)
  L "ASSET: $rel"
}

# Save manifest
$manifest = Join-Path $repo "_lpcc_asset_manifest.txt"
Get-ChildItem $DEST -Recurse -File | ForEach-Object { $_.FullName.Substring($DEST.Length+1) } | Set-Content $manifest
L "Manifest written: $manifest"
L "=== COPY DONE ==="
