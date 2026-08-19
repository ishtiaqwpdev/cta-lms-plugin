#!/usr/bin/env python3
"""Build LPCC NCMHCE Form B v2.0 admin-only answer key from parsed sources.

CLI only. Never prints correct letters to stdout in bulk.
Mirrors scripts/build-lpcc-ncmhce-form-a-v2-seeds.php for Form B.

Usage: python scripts/build-lpcc-ncmhce-form-b-v2-seeds.py
"""

from __future__ import annotations

import html
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CAND_JSON = ROOT / "_tmp_lpcc_form_b_v2" / "parsed.json"
KEY_PARAS = ROOT / "_tmp_lpcc_form_b_v2_key" / "paras.txt"
KEY_XML = ROOT / "_tmp_lpcc_form_b_v2_key" / "unzip" / "word" / "document.xml"
REPORT = ROOT / "_tmp_lpcc_form_b_v2_key" / "match_report.txt"
ADMIN_PATH = ROOT / "includes" / "quiz-seeds" / "admin-only" / "lpcc-ncmhce-form-b-v2-answer-key.php"
NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}


def norm(text: str) -> str:
    text = html.unescape(text or "")
    text = text.replace("\u2018", "'").replace("\u2019", "'")
    text = text.replace("\u201c", '"').replace("\u201d", '"')
    text = text.replace("\u00a0", " ").replace("—", "-").replace("–", "-")
    return re.sub(r"\s+", " ", text).strip()


def php_export(value, level: int = 0) -> str:
    pad = "\t" * level
    pad1 = "\t" * (level + 1)
    if isinstance(value, list):
        if not value:
            return "array()"
        lines = ["array(\n"]
        for item in value:
            lines.append(f"{pad1}{php_export(item, level + 1)},\n")
        lines.append(f"{pad})")
        return "".join(lines)
    if isinstance(value, dict):
        if not value:
            return "array()"
        lines = ["array(\n"]
        for k, v in value.items():
            lines.append(f"{pad1}{php_export(str(k), level + 1)} => {php_export(v, level + 1)},\n")
        lines.append(f"{pad})")
        return "".join(lines)
    if isinstance(value, bool):
        return "true" if value else "false"
    if isinstance(value, (int, float)):
        return str(value)
    escaped = str(value).replace("\\", "\\\\").replace("'", "\\'")
    return f"'{escaped}'"


def cell_text(cell) -> str:
    parts = []
    for t in cell.findall(".//w:t", NS):
        if t.text:
            parts.append(t.text)
    return norm("".join(parts))


def extract_paras() -> list[str]:
    tree = ET.parse(KEY_XML)
    root = tree.getroot()
    out = []
    for p in root.findall(".//w:body/w:p", NS):
        parts = []
        for t in p.findall(".//w:t", NS):
            if t.text:
                parts.append(t.text)
        out.append(norm("".join(parts)))
    KEY_PARAS.parent.mkdir(parents=True, exist_ok=True)
    KEY_PARAS.write_text("\n".join(out), encoding="utf-8")
    return out


def flatten_candidate(candidate: dict) -> list[dict]:
    items = []
    for case in candidate.get("cases", []):
        case_no = int(case.get("case_number") or 0)
        for sec in case.get("sections", []):
            sec_no = int(sec.get("section_number") or 0)
            stem = str(sec.get("stem") or "")
            first = True
            for q in sec.get("questions", []):
                num = int(q.get("number") or 0)
                items.append(
                    {
                        "case_number": case_no,
                        "case_title": str(case.get("title") or ""),
                        "section_number": sec_no,
                        "section_title": str(sec.get("title") or ""),
                        "section_stem": stem,
                        "is_section_first": first,
                        "number": num,
                        "question_text": str(q.get("question_text") or ""),
                        "options": dict(q.get("options") or {}),
                    }
                )
                first = False
    return items


def parse_table_meta() -> tuple[list[dict], list[str]]:
    tree = ET.parse(KEY_XML)
    root = tree.getroot()
    tables = root.findall(".//w:tbl", NS)
    table_meta: list[dict] = []
    control_bits: list[str] = []
    for ti, tbl in enumerate(tables, start=1):
        rows = []
        for tr in tbl.findall("./w:tr", NS):
            vals = [cell_text(tc) for tc in tr.findall("./w:tc", NS)]
            rows.append(" | ".join(vals))
        blob = " ".join(rows)
        if ti in (1, 146):
            control_bits.append(blob)
            continue
        if ti == 2:
            continue
        key = status = task = domain = ""
        m = re.search(r"Key:\s*([A-D])", blob, re.I)
        if m:
            key = m.group(1).lower()
        m = re.search(r"Status:\s*(Scored|Field-test)", blob, re.I)
        if m:
            status = "Scored" if m.group(1) == "Scored" else "Field-test"
        m = re.search(r"Task:\s*([A-Z0-9\-]+)", blob, re.I)
        if m:
            task = m.group(1).upper()
        m = re.search(r"Domain:\s*([A-Z]+)", blob, re.I)
        if m:
            domain = m.group(1).upper()
        table_meta.append(
            {
                "correct_option": key,
                "item_status": status,
                "task": task,
                "domain": domain,
            }
        )
    return table_meta, control_bits


def parse_key_items(paras: list[str]) -> tuple[dict[int, dict], dict[int, str]]:
    key_items: dict[int, dict] = {}
    case_keys: dict[int, str] = {}
    current_q = None
    current_case = 0

    def flush() -> None:
        nonlocal current_q
        if current_q:
            key_items[int(current_q["number"])] = current_q
            current_q = None

    for raw in paras:
        line = raw.strip()
        if not line:
            continue
        m = re.match(r"^CASE\s+(\d+)\b", line, re.I)
        if m:
            flush()
            current_case = int(m.group(1))
            continue
        m = re.search(r"Final key:\s*([A-D]{13})", line, re.I)
        if m:
            case_keys[current_case] = m.group(1).upper()
            continue
        m = re.match(r"^Q(\d+)\.\s+(.+)$", line)
        if m:
            flush()
            current_q = {
                "number": int(m.group(1)),
                "case_number": current_case,
                "question_text": m.group(2).strip(),
                "options": {},
                "why_best": "",
                "why_less": {},
                "transfer": "",
            }
            continue
        if current_q:
            m = re.match(r"^([A-D])\.\s+(.+)$", line)
            if m:
                current_q["options"][m.group(1).lower()] = m.group(2).strip()
                continue
            if line.lower().startswith("why the keyed answer is best:"):
                current_q["why_best"] = line[len("Why the keyed answer is best:") :].strip()
                continue
            m = re.match(r"^Why ([A-D]) is less appropriate:\s*(.+)$", line, re.I)
            if m:
                current_q["why_less"][m.group(1).upper()] = m.group(2).strip()
                continue
            if line.lower().startswith("transfer rule:"):
                current_q["transfer"] = line[len("Transfer rule:") :].strip()
                continue
            if re.match(r"^SECTION\s+\d+", line, re.I):
                flush()
                continue
    flush()
    return key_items, case_keys


def main() -> int:
    if not CAND_JSON.is_file() or not KEY_XML.is_file():
        print("Missing candidate JSON or key document.xml", file=sys.stderr)
        return 1

    candidate = json.loads(CAND_JSON.read_text(encoding="utf-8"))
    cand_items = flatten_candidate(candidate)
    paras = extract_paras()
    table_meta, control_bits = parse_table_meta()
    key_items, case_keys = parse_key_items(paras)

    problems: list[str] = []
    if len(cand_items) != 143:
        problems.append(f"Candidate flatten count={len(cand_items)} expected 143")
    if len(table_meta) != 143:
        problems.append(f"Item table count={len(table_meta)} expected 143")
    if len(key_items) != 143:
        problems.append(f"Key parsed questions={len(key_items)} expected 143")

    pass_mentions = [
        re.sub(r"\b[A-D]{11,15}\b", "[KEY-REDACTED]", blob)
        for blob in control_bits
        if re.search(r"pass(ing)?|cut.?score|70\s*%|threshold", blob, re.I)
    ]

    scored_n = field_n = 0
    letter_dist = {k: 0 for k in "abcd"}
    scored_dist = {k: 0 for k in "abcd"}
    domain_dist: dict[str, int] = {}
    admin_rows: dict[str, dict] = {}

    for i in range(143):
        num = i + 1
        cand = cand_items[i]
        key = key_items.get(num)
        meta = table_meta[i]
        code = f"CTA-LPCC-NCMHCE-FB-V2-{num:03d}"

        if not key:
            problems.append(f"Q{num}: missing from answer key parse")
            continue
        if int(cand["number"]) != num:
            problems.append(f"Q{num}: candidate number is {cand['number']}")
        if norm(cand["question_text"]) != norm(key["question_text"]):
            problems.append(f"Q{num}: stem mismatch")
        for opt in "abcd":
            oc = norm(cand["options"].get(opt, ""))
            ok = norm(key["options"].get(opt, ""))
            if not oc or not ok:
                problems.append(f"Q{num}: missing option {opt.upper()}")
            elif oc != ok:
                problems.append(f"Q{num}: option {opt.upper()} mismatch")

        table_letter = str(meta["correct_option"])
        case_no = int(cand["case_number"])
        pos_in_case = (num - 1) % 13
        string_letter = ""
        if case_no in case_keys:
            string_letter = case_keys[case_no][pos_in_case].lower()

        less_letters = list(key["why_less"].keys())
        inferred = [x for x in "ABCD" if x not in less_letters]
        inferred_l = inferred[0].lower() if len(inferred) == 1 else ""

        if table_letter != string_letter and string_letter:
            problems.append(f"Q{num}: table key disagrees with case Final key string")
        if inferred_l and inferred_l != table_letter:
            problems.append(f"Q{num}: inferred letter disagrees with table key")
        if table_letter not in "abcd":
            problems.append(f"Q{num}: invalid table correct letter")

        status = str(meta["item_status"])
        if status == "Scored":
            scored_n += 1
            scored_dist[table_letter] = scored_dist.get(table_letter, 0) + 1
            dom = str(meta["domain"])
            domain_dist[dom] = domain_dist.get(dom, 0) + 1
        elif status == "Field-test":
            field_n += 1
        else:
            problems.append(f"Q{num}: missing Scored/Field-test status")

        letter_dist[table_letter] = letter_dist.get(table_letter, 0) + 1

        if not key["why_best"].strip():
            problems.append(f"Q{num}: missing why_best")
        if len(key["why_less"]) != 3:
            problems.append(f"Q{num}: expected 3 less-appropriate lines, got {len(key['why_less'])}")
        if not key["transfer"].strip():
            problems.append(f"Q{num}: missing transfer rule")

        expl_parts = []
        if key["why_best"].strip():
            expl_parts.append("Why the keyed answer is best: " + key["why_best"].strip())
        for letter in "ABCD":
            if letter in key["why_less"]:
                expl_parts.append(f"Why {letter} is less appropriate: {key['why_less'][letter]}")
        if key["transfer"].strip():
            expl_parts.append("Transfer rule: " + key["transfer"].strip())

        admin_rows[code] = {
            "correct_option": table_letter,
            "item_status": status,
            "domain": str(meta["domain"]),
            "task": str(meta["task"]),
            "source_status": "EXACT/FROZEN SOURCE",
            "explanation": "\n\n".join(expl_parts),
        }

    control_joined = " ".join(control_bits)
    if scored_n != 100 or field_n != 43:
        problems.append(f"Scored/field-test counts {scored_n}/{field_n} (expected 100/43)")
    if scored_dist != {"a": 25, "b": 25, "c": 25, "d": 25}:
        problems.append(f"Scored letter distribution {scored_dist} (expected 25 each)")

    report = [
        "LPCC NCMHCE Form B v2.0 answer-key match report",
        f"candidate_questions={len(cand_items)}",
        f"key_questions={len(key_items)}",
        f"item_tables={len(table_meta)}",
        f"scored={scored_n} field_test={field_n}",
        f"all_letter_dist={json.dumps(letter_dist)}",
        f"scored_letter_dist={json.dumps(scored_dist)}",
        f"scored_domain_dist={json.dumps(domain_dist)}",
        f"passing_percentage_in_source={'KEYWORD_HIT' if pass_mentions else 'NOT_FOUND'}",
        f"control_table_has_100_scored={'yes' if '100 scored-style' in control_joined else 'no'}",
        f"control_table_has_43_field_test={'yes' if '43 field-test-style' in control_joined else 'no'}",
        "rationale_release_in_key_doc=NOT_STATED",
        f"mismatch_count={len(problems)}",
    ]
    for p in problems:
        report.append("MISMATCH: " + p)
    REPORT.write_text("\n".join(report) + "\n", encoding="utf-8")
    print("\n".join(report))

    if problems:
        print("Refusing to write seeds until mismatches are zero.", file=sys.stderr)
        return 1

    header = """<?php
/**
 * ADMIN ONLY — LPCC NCMHCE Form B v2.0 secured answer keys (143 items).
 * Merged into runtime quiz rows by CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync only.
 * Never registered as a learner download or exposed via learner AJAX before full submission.
 *
 * @package CTA_LMS
 */
if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

return """
    ADMIN_PATH.parent.mkdir(parents=True, exist_ok=True)
    ADMIN_PATH.write_text(header + php_export(admin_rows) + ";\n", encoding="utf-8")
    print(f"wrote {ADMIN_PATH}")
    print("OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
