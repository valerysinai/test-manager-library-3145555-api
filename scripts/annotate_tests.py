"""Publish sanitized PHPUnit diagnostics without exposing test credentials."""
from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET


if __name__ == "__main__":
    report = Path(sys.argv[1])
    if not report.is_file():
        print("::error::PHPUnit did not produce its diagnostic report.")
        raise SystemExit(1)
    for case in ET.parse(report).iter("testcase"):
        for failure in list(case.findall("failure")) + list(case.findall("error")):
            # Allow only code identifiers and source locations. Never publish error messages.
            lines = (failure.text or "").splitlines()
            name = case.get("name", "test")
            name = name if re.fullmatch(r"test_[a-z0-9_]+", name) else "test"
            kind = failure.get("type", "error")
            kind = kind if re.fullmatch(r"[A-Za-z_\\]+", kind) else "error"
            locations = [line for line in lines if re.fullmatch(r"/app/[A-Za-z0-9_/.-]+\.php:\d+", line)]
            print("::error::" + name + ": " + kind + "; " + "; ".join(locations))
