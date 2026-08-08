# Convert Exam Prep flashcard DOCX collections to JSON decks for the in-browser viewer.
# Usage (from repo root):
#   powershell -File scripts/convert-flashcards-docx.ps1
#
# Re-run after client updates a printable flashcard DOCX.

$ErrorActionPreference = "Stop"

function Get-DocxLines($docxPath) {
  $tmp = Join-Path $env:TEMP ("cta_fc_" + [guid]::NewGuid().ToString("N"))
  New-Item -ItemType Directory -Path $tmp | Out-Null
  try {
    Copy-Item $docxPath (Join-Path $tmp "f.zip")
    Expand-Archive (Join-Path $tmp "f.zip") -DestinationPath (Join-Path $tmp "out") -Force
    # DOCX document.xml is UTF-8. Read explicitly as UTF-8 - never rely on the
    # Windows default (often Windows-1252), which double-encodes bullets/quotes.
    $xmlPath = Join-Path $tmp "out\word\document.xml"
    $xml = [System.IO.File]::ReadAllText($xmlPath, [System.Text.UTF8Encoding]::new($false))
    $text = [regex]::Replace($xml, "</w:p>", "`n")
    $text = [regex]::Replace($text, "<[^>]+>", "")
    $text = [System.Net.WebUtility]::HtmlDecode($text)
    $text = $text -replace [char]0x2014, "-" -replace [char]0x2013, "-" -replace [char]0x2192, "->"
    return @($text -split "`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" })
  } finally {
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
  }
}

# Tag separator class: middle dot (U+00B7), bullet (U+2022), or ASCII period.
# Built from codepoints so Windows PowerShell 5.1 can parse this .ps1 even when
# the file is loaded as the system ANSI code page (no UTF-8 BOM).
function Get-ClinicalTagSepClass {
  return ("[" + [char]0x00B7 + [char]0x2022 + "\.]")
}

function Test-ClinicalTagLine([string]$line) {
  return [bool]($line -match ("^(LPCC|LCSW|LMFT)\s*" + (Get-ClinicalTagSepClass)))
}

function Convert-ClinicalStyle($lines) {
  $cards = New-Object System.Collections.Generic.List[object]
  $i = 0
  $currentTag = ""
  $tagLineRx = "^(LPCC|LCSW|LMFT)\s*" + (Get-ClinicalTagSepClass) + "\s*(.+)$"
  $bullet = [char]0x2022
  while ($i -lt $lines.Count) {
    $line = $lines[$i]
    if ($line -match $tagLineRx) {
      # Normalize to "PROGRAM • SECTION" with a proper UTF-8 bullet.
      $currentTag = ("{0}  {1}  {2}" -f $Matches[1], $bullet, $Matches[2].Trim())
      $i++
      continue
    }
    if ($line -match "^\d{3}$") {
      $id = $line
      $tag = $currentTag
      $j = $i + 1
      while ($j -lt $lines.Count -and $lines[$j] -ne "QUESTION" -and $lines[$j] -notmatch "^\d{3}$") { $j++ }
      if ($j -ge $lines.Count -or $lines[$j] -ne "QUESTION") { $i++; continue }
      $qParts = New-Object System.Collections.Generic.List[string]
      $j++
      while ($j -lt $lines.Count -and $lines[$j] -ne "ANSWER" -and $lines[$j] -notmatch "COVER OR FOLD" -and $lines[$j] -notmatch "^\d{3}$") {
        if (-not (Test-ClinicalTagLine $lines[$j])) { $qParts.Add($lines[$j]) }
        $j++
      }
      while ($j -lt $lines.Count -and $lines[$j] -ne "ANSWER") { $j++ }
      if ($j -ge $lines.Count) { $i++; continue }
      $aParts = New-Object System.Collections.Generic.List[string]
      $j++
      while ($j -lt $lines.Count) {
        $nl = $lines[$j]
        if ($nl -match "^\d{3}$") { break }
        if (Test-ClinicalTagLine $nl) { break }
        if ($nl -eq "QUESTION" -or $nl -eq "ANSWER") { break }
        if ($nl -match "COVER OR FOLD") { $j++; continue }
        $aParts.Add($nl)
        $j++
      }
      $front = ($qParts -join " ").Trim()
      $back = ($aParts -join " ").Trim()
      if ($front -and $back) {
        $cards.Add([pscustomobject]@{ id = $id; tag = $tag; front = $front; back = $back })
      }
      $i = $j
      continue
    }
    $i++
  }
  return $cards
}

function Convert-AmftrbStyle($lines) {
  $cards = New-Object System.Collections.Generic.List[object]
  $workbook = ""
  for ($i = 0; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]
    if ($line -match "^Workbook\s+(\d+):") {
      $workbook = $line
      continue
    }
    if ($line -match "^Card\s+(\d{3})\s*\|\s*(.+)$") {
      $id = $Matches[1]
      $tag = $Matches[2].Trim()
      if ($workbook) { $tag = "$workbook | $tag" }
      $front = ""
      $back = ""
      for ($j = $i + 1; $j -lt [Math]::Min($i + 20, $lines.Count); $j++) {
        $nl = $lines[$j]
        if ($nl -match "^Card\s+\d{3}\s*\|") { break }
        if ($nl -match "^QUESTION\s+(.+)$") { $front = $Matches[1].Trim(); continue }
        if ($nl -match "^ANSWER\s+(.+)$") { $back = $Matches[1].Trim(); continue }
      }
      if ($front -and $back) {
        $cards.Add([pscustomobject]@{ id = $id; tag = $tag; front = $front; back = $back })
      }
    }
  }
  return $cards
}

$root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $root "cta-plugin.php"))) { $root = Get-Location }

$decks = @(
  @{ key = "lpcc-ncmhce"; path = "assets\course-materials\lpcc-ncmhce\study-tools\CTA_LPCC_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx"; out = "assets\course-materials\lpcc-ncmhce\study-tools\flashcards.json"; title = "LPCC NCMHCE Flashcards"; style = "clinical" },
  @{ key = "lcsw-aswb"; path = "assets\course-materials\lcsw-aswb\study-tools\CTA_LCSW_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx"; out = "assets\course-materials\lcsw-aswb\study-tools\flashcards.json"; title = "LCSW ASWB Flashcards"; style = "clinical" },
  @{ key = "lmft-clinical"; path = "assets\course-materials\lmft-clinical\study-tools\CTA_LMFT_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx"; out = "assets\course-materials\lmft-clinical\study-tools\flashcards.json"; title = "LMFT Clinical Flashcards"; style = "clinical" },
  @{ key = "lmft-amftrb"; path = "assets\course-materials\lmft-amftrb\study-tools\CTA_LMFT_AMFTRB_WB1-12_120_Card_Flashcard_Study_Collection_v1.0.docx"; out = "assets\course-materials\lmft-amftrb\study-tools\flashcards.json"; title = "LMFT AMFTRB Flashcards"; style = "amftrb" }
)

Set-Location $root
foreach ($d in $decks) {
  $full = Join-Path $root $d.path
  if (-not (Test-Path $full)) { Write-Warning "Missing: $($d.path)"; continue }
  $lines = Get-DocxLines $full
  $cards = if ($d.style -eq "amftrb") { Convert-AmftrbStyle $lines } else { Convert-ClinicalStyle $lines }
  $payload = [ordered]@{
    program = $d.key
    title   = $d.title
    version = "1.0"
    count   = $cards.Count
    cards   = @($cards | ForEach-Object { [ordered]@{ id = $_.id; tag = $_.tag; front = $_.front; back = $_.back } })
  }
  $outPath = Join-Path $root $d.out
  [System.IO.File]::WriteAllText($outPath, ($payload | ConvertTo-Json -Depth 6), (New-Object System.Text.UTF8Encoding $false))
  Write-Output ("{0}: {1} cards -> {2}" -f $d.key, $cards.Count, $d.out)
}
